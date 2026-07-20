/**
 * Browser-based C++ Compilation using Clang via Wasmer SDK
 *
 * Requires the page to be served with these HTTP headers for SharedArrayBuffer:
 *   Cross-Origin-Opener-Policy: same-origin
 *   Cross-Origin-Embedder-Policy: require-corp
 *
 * Wasmer SDK CDN: https://unpkg.com/@wasmer/sdk@0.8.0-beta.1/dist/index.mjs
 * Clang package:  clang/clang  (on the Wasmer registry)
 */

class ClangCompiler {
    constructor() {
        this.Wasmer     = null;   // The Wasmer class after init()
        this.Directory  = null;   // The Directory class after init()
        this.clangPkg   = null;   // The loaded clang package from registry
        this.isReady    = false;
        this._initPromise = null;
    }

    // ── Initialisation ─────────────────────────────────────────────────────

    /**
     * Initialise the Wasmer runtime and download the Clang package.
     * Safe to call multiple times — resolves immediately if already done.
     */
    async init(onProgress = null) {
        if (this.isReady) return;
        if (this._initPromise) return this._initPromise;

        this._initPromise = this._doInit(onProgress);
        return this._initPromise;
    }

    async _doInit(onProgress) {
        try {
            if (onProgress) onProgress('Loading Wasmer SDK…');

            // Pin to a stable version to avoid breaking changes from @latest
            const sdk = await import('https://unpkg.com/@wasmer/sdk@0.8.0-beta.1/dist/index.mjs');
            const { init, Wasmer, Directory } = sdk;

            if (onProgress) onProgress('Initialising Wasmer runtime…');
            await init();

            this.Wasmer    = Wasmer;
            this.Directory = Directory;

            if (onProgress) onProgress('Downloading Clang compiler (~100 MB, cached after first run)…');

            // Pull the clang package from the Wasmer registry
            this.clangPkg = await Wasmer.fromRegistry('clang/clang');

            this.isReady = true;
            if (onProgress) onProgress('Clang ready ✔');
        } catch (err) {
            // Reset so the caller can retry
            this._initPromise = null;
            const msg = `Clang.wasm init failed: ${err.message}`;
            console.error(msg, err);
            throw new Error(msg);
        }
    }

    // ── Compilation ─────────────────────────────────────────────────────────

    /**
     * Compile a C++ source string to a WASI-compatible WASM binary.
     *
     * @param {string}   cppCode    - Full C++ source code
     * @param {Function} onProgress - Optional progress callback (string)
     * @returns {Promise<Uint8Array>} Raw WASM bytes of the compiled binary
     */
    async compile(cppCode, onProgress = null) {
        if (!this.isReady) {
            await this.init(onProgress);
        }

        if (onProgress) onProgress('Compiling C++ with Clang…');

        // Virtual filesystem for source + output
        const fs = new this.Directory();
        await fs.writeFile('main.cpp', new TextEncoder().encode(cppCode));

        const instance = await this.clangPkg.entrypoint.run({
            // clang/clang entrypoint is 'clang'; .cpp extension triggers C++ mode automatically
            args: [
                '/proj/main.cpp',
                '-O2',
                '-o', '/proj/main.wasm'
            ],
            mount: { '/proj': fs }
        });

        const result = await instance.wait();

        if (!result.ok) {
            // Collect stderr for a human-readable compiler error
            const stderr = (result.stderr || '').trim() || 'Unknown compilation error';
            throw new CompilationError(stderr);
        }

        if (onProgress) onProgress('Compilation successful, reading binary…');

        const wasmBytes = await fs.readFile('main.wasm');
        return wasmBytes; // Uint8Array
    }

    // ── Execution ────────────────────────────────────────────────────────────

    /**
     * Execute a previously compiled WASM binary with the given stdin.
     *
     * @param {Uint8Array} wasmBytes  - Compiled WASM binary
     * @param {string}     stdinText  - Full stdin string to feed the program
     * @param {number}     timeoutMs  - Abort after this many milliseconds
     * @returns {Promise<{stdout:string, stderr:string, exitCode:number, timedOut:boolean}>}
     */
    async execute(wasmBytes, stdinText = '', timeoutMs = 5000) {
        // Instantiate the WASM module via the Wasmer SDK
        const wasmModule = await this.Wasmer.fromBytes(wasmBytes);

        let timedOut = false;

        const runPromise = (async () => {
            const inst = await wasmModule.entrypoint.run({
                stdin: stdinText.endsWith('\n') ? stdinText : stdinText + '\n'
            });
            return inst.wait();
        })();

        const timeoutPromise = new Promise((_, reject) => {
            setTimeout(() => {
                timedOut = true;
                reject(new Error('Time Limit Exceeded'));
            }, timeoutMs);
        });

        let output;
        try {
            output = await Promise.race([runPromise, timeoutPromise]);
        } catch (err) {
            if (timedOut) {
                return { stdout: '', stderr: 'Time Limit Exceeded', exitCode: 124, timedOut: true };
            }
            return { stdout: '', stderr: err.message, exitCode: 1, timedOut: false };
        }

        return {
            stdout:   output.stdout  || '',
            stderr:   output.stderr  || '',
            exitCode: output.code    ?? (output.ok ? 0 : 1),
            timedOut: false
        };
    }

    // ── Full pipeline ────────────────────────────────────────────────────────

    /**
     * Compile cppCode once, then run it against every test case.
     *
     * testCases shape (same as the Python runner):
     *   [{ input: "...", expected: "..." }, ...]
     *
     * Returns a response in the same shape the Python runner would return:
     * {
     *   success:    bool,
     *   compiled:   bool,
     *   compile_stderr: string,  // only when compiled=false
     *   error:      string,      // only when success=false for non-compile reasons
     *   all_passed: bool,
     *   test_cases: [
     *     { id, passed, status, runtime_ms, expected, actual, stderr }
     *   ]
     * }
     */
    async compileAndTest(cppCode, testCases, timeoutSec = 2, onProgress = null) {
        // ── Step 1: compile ──────────────────────────────────────────────────
        let wasmBytes;
        try {
            wasmBytes = await this.compile(cppCode, onProgress);
        } catch (err) {
            const isCompileErr = err instanceof CompilationError;
            return {
                success:        false,
                compiled:       false,
                compile_stderr: isCompileErr ? err.message : '',
                error:          isCompileErr ? 'Compilation Error' : err.message,
                all_passed:     false,
                test_cases:     []
            };
        }

        // ── Step 2: run every test case ─────────────────────────────────────
        const timeoutMs  = Math.round(timeoutSec * 1000);
        const results    = [];
        let   allPassed  = true;

        for (let i = 0; i < testCases.length; i++) {
            const tc = testCases[i];
            if (onProgress) onProgress(`Running test case ${i + 1} / ${testCases.length}…`);

            const t0  = performance.now();
            const out = await this.execute(wasmBytes, tc.input || '', timeoutMs);
            const ms  = Math.round(performance.now() - t0);

            const actual   = (out.stdout || '').trimEnd();
            const expected = (tc.expected || '').trimEnd();
            const passed   = !out.timedOut && out.exitCode === 0 && actual === expected;

            let status;
            if (out.timedOut)      status = 'Time Limit Exceeded';
            else if (out.exitCode !== 0) status = 'Runtime Error';
            else if (!passed)      status = 'Wrong Answer';
            else                   status = 'Accepted';

            if (!passed) allPassed = false;

            results.push({
                id:         i + 1,
                passed:     passed,
                status:     status,
                runtime_ms: ms,
                expected:   expected,
                actual:     actual,
                stderr:     out.stderr || ''
            });
        }

        return {
            success:    true,
            compiled:   true,
            all_passed: allPassed,
            test_cases: results
        };
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────────

class CompilationError extends Error {
    constructor(message) {
        super(message);
        this.name = 'CompilationError';
    }
}

// ── Singleton ────────────────────────────────────────────────────────────────
// Shared across runner-client.js and problem.html
const clangCompiler = new ClangCompiler();
