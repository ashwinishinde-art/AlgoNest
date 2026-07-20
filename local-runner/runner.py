#!/usr/bin/env python3
import os
import sys
import json
import time
import shutil
import tempfile
import subprocess
from http.server import HTTPServer, BaseHTTPRequestHandler

PORT = 8000

class RunnerHTTPRequestHandler(BaseHTTPRequestHandler):
    def end_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.end_headers()

    def do_POST(self):
        if self.path != '/run':
            self.send_response(404)
            self.end_headers()
            return

        content_length = int(self.headers.get('Content-Length', 0))
        post_data = self.rfile.read(content_length)
        
        try:
            req_json = json.loads(post_data.decode('utf-8'))
        except Exception as e:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(json.dumps({"error": "Invalid JSON"}).encode('utf-8'))
            return

        code = req_json.get('code', '')
        tests = req_json.get('tests', [])
        timeout = float(req_json.get('timeout', 2.0)) # 2 seconds default

        # Run compilation and execution
        result = run_cpp_code(code, tests, timeout)

        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps(result).encode('utf-8'))

def run_cpp_code(code, tests, timeout):
    # Check if g++ is installed
    if not shutil.which("g++"):
        return {
            "success": False,
            "error": "g++ compiler not found on this system. Please install GCC/g++."
        }

    # Create temporary directory for compilation and execution
    with tempfile.TemporaryDirectory() as tmpdir:
        src_path = os.path.join(tmpdir, "solution.cpp")
        bin_path = os.path.join(tmpdir, "solution.bin")

        with open(src_path, "w") as f:
            f.write(code)

        # Compile step
        compile_cmd = ["g++", "-O3", src_path, "-o", bin_path]
        try:
            compproc = subprocess.run(
                compile_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                timeout=10.0
            )
        except subprocess.TimeoutExpired:
            return {
                "success": False,
                "error": "Compilation timed out (max 10 seconds)."
            }

        if compproc.returncode != 0:
            return {
                "success": False,
                "error": "Compilation Error",
                "compile_stderr": compproc.stderr
            }

        # Execution step for each test case
        test_results = []
        all_passed = True

        for idx, test in enumerate(tests):
            input_data = test.get('input', '')
            expected_output = test.get('expected', '').strip()

            start_time = time.perf_counter()
            try:
                runproc = subprocess.run(
                    [bin_path],
                    input=input_data,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    text=True,
                    timeout=timeout
                )
                end_time = time.perf_counter()
                elapsed_ms = int((end_time - start_time) * 1000)

                actual_output = runproc.stdout.strip()
                stderr_output = runproc.stderr

                if runproc.returncode != 0:
                    status = "Runtime Error"
                    passed = False
                    all_passed = False
                elif actual_output == expected_output:
                    status = "Accepted"
                    passed = True
                else:
                    status = "Wrong Answer"
                    passed = False
                    all_passed = False

                test_results.append({
                    "id": idx + 1,
                    "status": status,
                    "passed": passed,
                    "runtime_ms": elapsed_ms,
                    "actual": actual_output,
                    "expected": expected_output,
                    "stderr": stderr_output
                })

            except subprocess.TimeoutExpired:
                all_passed = False
                test_results.append({
                    "id": idx + 1,
                    "status": "Time Limit Exceeded",
                    "passed": False,
                    "runtime_ms": int(timeout * 1000),
                    "actual": "",
                    "expected": expected_output,
                    "stderr": ""
                })
            except Exception as e:
                all_passed = False
                test_results.append({
                    "id": idx + 1,
                    "status": "Runtime Error",
                    "passed": False,
                    "runtime_ms": 0,
                    "actual": "",
                    "expected": expected_output,
                    "stderr": str(e)
                })

        return {
            "success": True,
            "all_passed": all_passed,
            "test_cases": test_results
        }

def run_server():
    server_address = ('', PORT)
    httpd = HTTPServer(server_address, RunnerHTTPRequestHandler)
    print(f"C++ Local Runner daemon running on port {PORT}...")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nShutting down local runner...")
        httpd.server_close()

if __name__ == '__main__':
    run_server()
