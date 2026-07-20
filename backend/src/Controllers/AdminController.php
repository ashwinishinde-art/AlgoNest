<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Problem.php';
require_once __DIR__ . '/../Models/Notification.php';

class AdminController {
    private $db;
    private $problem;
    private $notification;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->problem = new Problem($this->db);
        $this->notification = new Notification($this->db);
    }

    public function createProblem($authorId, $data) {
        if (empty($data['title']) || empty($data['difficulty']) || empty($data['topic_tags']) || empty($data['description']) || empty($data['constraints'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete problem parameters."]);
            return;
        }

        $time_limit = isset($data['time_limit_sec']) ? $data['time_limit_sec'] : 2.0;
        $memory_limit = isset($data['memory_limit_mb']) ? $data['memory_limit_mb'] : 256;
        $approved = isset($data['approved']) ? $data['approved'] : 1; // Default to approved for admins

        $problemId = $this->problem->create(
            $data['title'],
            $data['difficulty'],
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
            echo json_encode(["message" => "Problem created successfully.", "problem_id" => $problemId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create problem."]);
        }
    }

    public function updateProblem($id, $data) {
        if (empty($data['title']) || empty($data['difficulty']) || empty($data['topic_tags']) || empty($data['description']) || empty($data['constraints'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete problem parameters."]);
            return;
        }

        $time_limit = isset($data['time_limit_sec']) ? $data['time_limit_sec'] : 2.0;
        $memory_limit = isset($data['memory_limit_mb']) ? $data['memory_limit_mb'] : 256;
        $approved = isset($data['approved']) ? $data['approved'] : 1;

        $result = $this->problem->update(
            $id,
            $data['title'],
            $data['difficulty'],
            $data['topic_tags'],
            $data['description'],
            $data['constraints'],
            $time_limit,
            $memory_limit,
            $approved
        );

        if ($result) {
            http_response_code(200);
            echo json_encode(["message" => "Problem updated successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update problem."]);
        }
    }

    public function deleteProblem($id) {
        if ($this->problem->delete($id)) {
            http_response_code(200);
            echo json_encode(["message" => "Problem deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete problem."]);
        }
    }

    public function addTestCase($data) {
        if (empty($data['problem_id']) || !isset($data['input']) || !isset($data['expected'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete test case details."]);
            return;
        }

        $is_sample = isset($data['is_sample']) ? $data['is_sample'] : 0;
        $result = $this->problem->addTestCase($data['problem_id'], $data['input'], $data['expected'], $is_sample);

        if ($result) {
            http_response_code(201);
            echo json_encode(["message" => "Test case added successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to add test case."]);
        }
    }

    public function deleteTestCase($id) {
        if ($this->problem->deleteTestCase($id)) {
            http_response_code(200);
            echo json_encode(["message" => "Test case deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete test case."]);
        }
    }

    public function listProblems($filters) {
        $problems = $this->problem->getAll($filters);
        http_response_code(200);
        echo json_encode($problems);
    }

    public function approveProblem($id, $data) {
        if (empty($data['difficulty'])) {
            http_response_code(400);
            echo json_encode(["message" => "Difficulty is required to approve."]);
            return;
        }
        $comment = !empty($data['comment']) ? $data['comment'] : null;
        if ($this->problem->approve($id, $data['difficulty'], $comment)) {
            // Get problem details to send notification
            $problemData = $this->problem->getById($id);
            if ($problemData) {
                $this->notification->create(
                    $problemData['author_id'],
                    $id,
                    'problem_approved',
                    'Your Problem Was Approved! 🎉',
                    'Your problem "' . $problemData['title'] . '" has been approved and is now live on the platform!'
                );
            }
            http_response_code(200);
            echo json_encode(["message" => "Problem approved successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to approve problem."]);
        }
    }

    public function rejectProblem($id, $data) {
        $reason = !empty($data['reason']) ? $data['reason'] : 'No reason provided.';
        if ($this->problem->reject($id, $reason)) {
            // Get problem details to send notification
            $problemData = $this->problem->getById($id);
            if ($problemData) {
                $this->notification->create(
                    $problemData['author_id'],
                    $id,
                    'problem_rejected',
                    'Your Problem Was Rejected',
                    'Your problem "' . $problemData['title'] . '" was rejected. Reason: ' . $reason
                );
            }
            http_response_code(200);
            echo json_encode(["message" => "Problem rejected."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to reject problem."]);
        }
    }

    // ── Faculty Request Management ────────────────────────────────────────────

    public function listFacultyRequests($filters = []) {
        $status = !empty($filters['status']) ? $filters['status'] : 'pending';
        $query = "SELECT fr.*, u.username, u.email
                  FROM faculty_requests fr
                  JOIN users u ON fr.user_id = u.id
                  WHERE fr.status = :status
                  ORDER BY fr.created_at ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        http_response_code(200);
        echo json_encode($stmt->fetchAll());
    }

    public function approveFacultyRequest($requestId, $adminId, $data) {
        $note = !empty($data['note']) ? $data['note'] : null;
        $now = date('Y-m-d H:i:s');

        // Get request
        $stmt = $this->db->prepare("SELECT * FROM faculty_requests WHERE id = :id");
        $stmt->bindParam(':id', $requestId);
        $stmt->execute();
        $request = $stmt->fetch();

        if (!$request) {
            http_response_code(404);
            echo json_encode(["message" => "Faculty request not found."]);
            return;
        }

        // Update request status
        $stmt = $this->db->prepare(
            "UPDATE faculty_requests SET status='approved', admin_note=:note, reviewed_by=:admin, reviewed_at=:now WHERE id=:id"
        );
        $stmt->bindParam(':note', $note);
        $stmt->bindParam(':admin', $adminId);
        $stmt->bindParam(':now', $now);
        $stmt->bindParam(':id', $requestId);
        $stmt->execute();

        // Upgrade user role to faculty
        $db2 = new Database();
        $conn2 = $db2->getConnection();
        $upd = $conn2->prepare("UPDATE users SET role='faculty' WHERE id=:uid");
        $upd->bindParam(':uid', $request['user_id']);
        $upd->execute();

        // Notify user
        $this->notification->create(
            $request['user_id'],
            1, // placeholder problem_id (notification system requires it)
            'problem_approved', // reuse type
            'Faculty Access Granted! 🎓',
            'Congratulations! Your faculty registration request has been approved. You now have faculty access on AlgoNest.'
        );

        http_response_code(200);
        echo json_encode(["message" => "Faculty request approved. User upgraded to faculty."]);
    }

    public function rejectFacultyRequest($requestId, $adminId, $data) {
        $note = !empty($data['note']) ? $data['note'] : 'No reason provided.';
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("SELECT * FROM faculty_requests WHERE id = :id");
        $stmt->bindParam(':id', $requestId);
        $stmt->execute();
        $request = $stmt->fetch();

        if (!$request) {
            http_response_code(404);
            echo json_encode(["message" => "Faculty request not found."]);
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE faculty_requests SET status='rejected', admin_note=:note, reviewed_by=:admin, reviewed_at=:now WHERE id=:id"
        );
        $stmt->bindParam(':note', $note);
        $stmt->bindParam(':admin', $adminId);
        $stmt->bindParam(':now', $now);
        $stmt->bindParam(':id', $requestId);
        $stmt->execute();

        // Revert user role back to user
        $db2 = new Database();
        $conn = $db2->getConnection();
        $upd = $conn->prepare("UPDATE users SET role='user' WHERE id=:uid AND role='pending_faculty'");
        $upd->bindParam(':uid', $request['user_id']);
        $upd->execute();

        // Notify user
        $this->notification->create(
            $request['user_id'],
            1,
            'problem_rejected',
            'Faculty Request Declined',
            'Your faculty registration request was reviewed and declined. Reason: ' . $note
        );

        http_response_code(200);
        echo json_encode(["message" => "Faculty request rejected."]);
    }

}
