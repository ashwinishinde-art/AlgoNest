<?php
/**
 * RunnerController
 *
 * Accepts a C++ source string + test cases, compiles with g++, runs each
 * test case, and returns results in the same JSON shape as runner.py.
 *
 * POST /api/run
 * Body: { "code": "...", "tests": [{ "input": "...", "expected": "..." }], "timeout": 2.0 }
 */
class RunnerController {

    // Hard limits to prevent abuse
    const COMPILE_TIMEOUT = 10;   // seconds
    const MAX_RUN_TIMEOUT = 10;   // seconds cap per test regardless of client request
    const MAX_TESTS       = 100;  // max test cases per request

    public function run($body) {
        $code    = $body['code']    ?? '';
        $tests   = $body['tests']   ?? [];
        $timeout = (float)($body['timeout'] ?? 2.0);

        // ── Validate ──────────────────────────────────────────────────────────
        if (empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No code provided.']);
            return;
        }
        if (!is_array($tests)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'tests must be an array.']);
            return;
        }

        $timeout = min(max($timeout, 0.5), self::MAX_RUN_TIMEOUT);
        $tests   = array_slice($tests, 0, self::MAX_TESTS);

        // ── Check g++ ─────────────────────────────────────────────────────────
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            $gpp = trim(shell_exec('where g++ 2>nul') ?? '');
        } else {
            $gpp = trim(shell_exec('which g++ 2>/dev/null') ?? '');
        }
        if (empty($gpp)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'g++ not found on server.']);
            return;
        }
        // Use first line only (where.exe may return multiple paths)
        $gppBin = strtok($gpp, "\n");

        // ── Write source to temp file ─────────────────────────────────────────
        $sep     = DIRECTORY_SEPARATOR;
        $tmpDir  = sys_get_temp_dir() . $sep . 'cpp_run_' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        $srcPath = $tmpDir . $sep . 'solution.cpp';
        $binPath = $tmpDir . $sep . 'solution' . ($isWindows ? '.exe' : '.bin');

        file_put_contents($srcPath, $code);

        // ── Compile ───────────────────────────────────────────────────────────
        $compileResult = $this->execWithTimeout(
            [$gppBin, '-O2', '-o', $binPath, $srcPath],
            '',
            self::COMPILE_TIMEOUT
        );

        if ($compileResult['timed_out']) {
            $this->cleanup($tmpDir);
            echo json_encode([
                'success' => false,
                'error'   => 'Compilation timed out (max ' . self::COMPILE_TIMEOUT . 's).'
            ]);
            return;
        }

        if ($compileResult['exit_code'] !== 0) {
            $this->cleanup($tmpDir);
            echo json_encode([
                'success'         => false,
                'error'           => 'Compilation Error',
                'compile_stderr'  => $compileResult['stderr']
            ]);
            return;
        }

        // ── Run each test case ────────────────────────────────────────────────
        $testResults = [];
        $allPassed   = true;

        foreach ($tests as $idx => $test) {
            $input    = $test['input']    ?? '';
            $expected = rtrim($test['expected'] ?? '');

            $run = $this->execWithTimeout([$binPath], $input, $timeout);

            $actual = rtrim($run['stdout']);
            $stderr = $run['stderr'];

            if ($run['timed_out']) {
                $status = 'Time Limit Exceeded';
                $passed = false;
            } elseif ($run['exit_code'] !== 0) {
                $status = 'Runtime Error';
                $passed = false;
            } elseif ($actual === $expected) {
                $status = 'Accepted';
                $passed = true;
            } else {
                $status = 'Wrong Answer';
                $passed = false;
            }

            if (!$passed) $allPassed = false;

            $testResults[] = [
                'id'         => $idx + 1,
                'status'     => $status,
                'passed'     => $passed,
                'runtime_ms' => $run['runtime_ms'],
                'actual'     => $actual,
                'expected'   => $expected,
                'stderr'     => $stderr
            ];
        }

        $this->cleanup($tmpDir);

        echo json_encode([
            'success'    => true,
            'all_passed' => $allPassed,
            'test_cases' => $testResults
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Execute a command with stdin and a wall-clock timeout.
     * Uses proc_open for non-blocking I/O.
     */
    private function execWithTimeout(array $cmd, string $stdin, float $timeoutSec): array {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $startTime = microtime(true);
        $process   = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            return ['stdout' => '', 'stderr' => 'Failed to start process.', 'exit_code' => -1, 'runtime_ms' => 0, 'timed_out' => false];
        }

        // Write stdin and close
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        // Set stdout/stderr to non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout   = '';
        $stderr   = '';
        $timedOut = false;

        while (true) {
            $elapsed = microtime(true) - $startTime;

            if ($elapsed >= $timeoutSec) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }

            $read = [$pipes[1], $pipes[2]];
            $write = $except = null;
            $changed = stream_select($read, $write, $except, 0, 50000); // 50ms

            if ($changed > 0) {
                foreach ($read as $pipe) {
                    if ($pipe === $pipes[1]) $stdout .= fread($pipe, 8192);
                    if ($pipe === $pipes[2]) $stderr .= fread($pipe, 8192);
                }
            }

            // Check if process finished
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Drain remaining output
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode  = proc_close($process);
        $runtimeMs = (int)(($elapsed ?? (microtime(true) - $startTime)) * 1000);

        return [
            'stdout'     => $stdout,
            'stderr'     => $stderr,
            'exit_code'  => $timedOut ? 124 : $exitCode,
            'runtime_ms' => $runtimeMs,
            'timed_out'  => $timedOut
        ];
    }

    private function cleanup(string $dir): void {
        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*');
            if ($files) array_map('unlink', $files);
            rmdir($dir);
        }
    }
}
