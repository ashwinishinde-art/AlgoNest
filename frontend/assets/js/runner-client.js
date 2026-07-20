// Frontend communication handler for code execution and backend submission.
//
// Execution path: local Python runner at localhost:8000
//   Start it with: python3 local-runner/runner.py
//   (./start.sh does this automatically)

const RunnerClient = {

    // ── Main entry point ────────────────────────────────────────────────────

    /**
     * Compile and run C++ code against testCases via the local Python runner.
     *
     * @param {string}   code       - C++ source code
     * @param {Array}    testCases  - [{ input: string, expected: string }, …]
     * @param {number}   timeout    - Per-test timeout in seconds (default 2)
     * @returns {Promise<Object>}   Runner response
     */
    async executeLocally(code, testCases, timeout = 2.0) {
        return RunnerClient._executeViaLocalRunner(code, testCases, timeout);
    },

    // ── Local Python runner ─────────────────────────────────────────────────

    async _executeViaLocalRunner(code, testCases, timeout) {
        try {
            const response = await fetch(RUNNER_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...Auth.getAuthHeader()
                },
                body: JSON.stringify({
                    code:    code,
                    tests:   testCases,
                    timeout: timeout
                })
            });

            if (!response.ok) {
                throw new Error(`Runner responded with HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Runner error:', error);
            return {
                success: false,
                error:   'Could not connect to the C++ runner. Make sure the backend is running (./start.sh).'
            };
        }
    },

    // ── Backend submission ──────────────────────────────────────────────────

    /**
     * Send the aggregated evaluation result to the backend for recording.
     */
    async submitResultToBackend(problemId, status, passedCount, totalCount, runtimeMs, code) {
        const payload = {
            problem_id:   problemId,
            status:       status,
            passed_count: passedCount,
            total_count:  totalCount,
            runtime_ms:   runtimeMs,
            code:         code
        };

        try {
            const response = await fetch(`${API_BASE_URL}/submissions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...Auth.getAuthHeader()
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error(`Backend responded with status ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Submission reporting failed:', error);
            return {
                success: false,
                error:   'Results processed locally but could not be uploaded to backend.'
            };
        }
    }
};
