<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Problem.php';

class ProblemController {
    private $db;
    private $problem;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->problem = new Problem($this->db);
    }

    public function list($filters) {
        error_log("ProblemController::list called with filters: " . json_encode($filters));
        
        $approvedOnly = isset($filters['approved']) ? $filters['approved'] : 1;
        
        // Convert string values to integers
        if (is_string($approvedOnly)) {
            $approvedOnly = (int)$approvedOnly;
        }

        error_log("approvedOnly converted to: " . $approvedOnly);

        // Only require authentication check for unapproved problems
        if ($approvedOnly === 0) {
            try {
                $user = AuthMiddleware::authenticate();
                if ($user['role'] !== 'admin') {
                    if (empty($filters['author_id']) || (int)$filters['author_id'] !== (int)$user['id']) {
                        http_response_code(403);
                        echo json_encode(["message" => "Unauthorized to view unapproved problems."]);
                        return;
                    }
                }
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode(["message" => "Authentication required."]);
                return;
            }
        }
        
        $filters['approved'] = $approvedOnly;
        
        $problems = $this->problem->getAll($filters);
        error_log("Problems found: " . count($problems));
        error_log("Problems: " . json_encode($problems));
        http_response_code(200);
        echo json_encode($problems);
    }

    public function detail($id) {
        $problemData = $this->problem->getById($id);
        if ($problemData) {
            if (!(int)$problemData['approved']) {
                try {
                    $user = AuthMiddleware::authenticate();
                    if ($user['role'] !== 'admin' && (int)$problemData['author_id'] !== (int)$user['id']) {
                        http_response_code(403);
                        echo json_encode(["message" => "Unauthorized to view this pending problem."]);
                        return;
                    }
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(["message" => "Authentication required to view this pending problem."]);
                    return;
                }
            }
            http_response_code(200);
            echo json_encode($problemData);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Problem not found."]);
        }
    }

    public function getSamples($id) {
        $problemData = $this->problem->getById($id);
        if (!$problemData) {
            http_response_code(404);
            echo json_encode(["message" => "Problem not found."]);
            return;
        }

        if (!(int)$problemData['approved']) {
            try {
                $user = AuthMiddleware::authenticate();
                if ($user['role'] !== 'admin' && (int)$problemData['author_id'] !== (int)$user['id']) {
                    http_response_code(403);
                    echo json_encode(["message" => "Unauthorized to view this pending problem's samples."]);
                    return;
                }
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode(["message" => "Authentication required."]);
                return;
            }
        }

        $testCases = $this->problem->getTestCases($id, false);
        http_response_code(200);
        echo json_encode($testCases);
    }

    // This is called when submitting code. It gets all test cases (both sample and hidden).
    // Requires authentication (handled in router / index.php)
    public function getAllTestCases($id) {
        $problemData = $this->problem->getById($id);
        if (!$problemData) {
            http_response_code(404);
            echo json_encode(["message" => "Problem not found."]);
            return;
        }

        try {
            $user = AuthMiddleware::authenticate();
            if (!(int)$problemData['approved']) {
                if ($user['role'] !== 'admin' && (int)$problemData['author_id'] !== (int)$user['id']) {
                    http_response_code(403);
                    echo json_encode(["message" => "Unauthorized to view this pending problem's test cases."]);
                    return;
                }
            }
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["message" => "Authentication required."]);
            return;
        }

        $testCases = $this->problem->getTestCases($id, true);
        http_response_code(200);
        echo json_encode($testCases);
    }

    public function submitProblem($authorId, $data) {
        if (empty($data['title']) || empty($data['topic_tags']) || empty($data['description']) || empty($data['constraints'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete problem parameters."]);
            return;
        }

        $time_limit = isset($data['time_limit_sec']) ? $data['time_limit_sec'] : 2.0;
        $memory_limit = isset($data['memory_limit_mb']) ? $data['memory_limit_mb'] : 256;
        $approved = 0; // Forced to 0 (pending approval) for standard users
        $difficulty = isset($data['difficulty']) ? $data['difficulty'] : 'Medium'; // Default difficulty set by admin later
        $problem_id = isset($data['problem_id']) ? $data['problem_id'] : null;

        // If problem_id is provided, update the existing problem instead of creating a new one
        if ($problem_id) {
            // Verify the user owns this problem
            $problemData = $this->problem->getById($problem_id);
            if (!$problemData || $problemData['author_id'] != $authorId) {
                http_response_code(403);
                echo json_encode(["message" => "Unauthorized to update this problem."]);
                return;
            }

            // Update the problem back to pending status
            $result = $this->problem->update(
                $problem_id,
                $data['title'],
                $difficulty,
                $data['topic_tags'],
                $data['description'],
                $data['constraints'],
                $time_limit,
                $memory_limit,
                0 // Reset to pending approval
            );

            if ($result) {
                // Delete old test cases and add new ones
                $stmt = $this->db->prepare("DELETE FROM test_cases WHERE problem_id = :problem_id");
                $stmt->bindParam(":problem_id", $problem_id);
                $stmt->execute();

                // Add new test cases if provided
                if (!empty($data['test_cases']) && is_array($data['test_cases'])) {
                    foreach ($data['test_cases'] as $tc) {
                        if (isset($tc['input']) && isset($tc['expected'])) {
                            $is_sample = isset($tc['is_sample']) ? $tc['is_sample'] : 0;
                            $this->problem->addTestCase($problem_id, $tc['input'], $tc['expected'], $is_sample);
                        }
                    }
                }

                http_response_code(200);
                echo json_encode(["message" => "Problem resubmitted for admin approval.", "problem_id" => $problem_id]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to resubmit problem."]);
            }
        } else {
            // Create new problem
            $problemId = $this->problem->create(
                $data['title'],
                $difficulty,
                $data['topic_tags'],
                $data['description'],
                $data['constraints'],
                $time_limit,
                $memory_limit,
                $authorId,
                $approved
            );

            if ($problemId) {
                // Add test cases if provided
                if (!empty($data['test_cases']) && is_array($data['test_cases'])) {
                    foreach ($data['test_cases'] as $tc) {
                        if (isset($tc['input']) && isset($tc['expected'])) {
                            $is_sample = isset($tc['is_sample']) ? $tc['is_sample'] : 0;
                            $this->problem->addTestCase($problemId, $tc['input'], $tc['expected'], $is_sample);
                        }
                    }
                }

                http_response_code(201);
                echo json_encode(["message" => "Problem submitted for admin approval.", "problem_id" => $problemId]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to submit problem."]);
            }
        }
    }
}
