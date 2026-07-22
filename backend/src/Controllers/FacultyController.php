<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Problem.php';
require_once __DIR__ . '/../Models/Notification.php';

/**
 * FacultyController
 * Faculty can approve/reject submitted problems (same as admin for problems).
 * Faculty cannot create/delete problems or manage users.
 */
class FacultyController {
    private $db;
    private $problem;
    private $notification;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->problem = new Problem($this->db);
        $this->notification = new Notification($this->db);
    }

    // GET /api/faculty/problems — list pending problems for review
    public function listPendingProblems() {
        $query = "SELECT p.*, u.username as author_username
                  FROM problems p
                  JOIN users u ON p.author_id = u.id
                  WHERE p.approved = 0 AND p.rejection_reason IS NULL
                  ORDER BY p.created_at ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $problems = $stmt->fetchAll();
        http_response_code(200);
        echo json_encode($problems);
    }

    // GET /api/faculty/problems/all — all problems (for faculty overview)
    public function listAllProblems($filters = []) {
        $problems = $this->problem->getAll($filters);
        http_response_code(200);
        echo json_encode($problems);
    }

    // POST /api/faculty/problems/{id}/approve
    public function approveProblem($id, $data) {
        if (empty($data['difficulty'])) {
            http_response_code(400);
            echo json_encode(["message" => "Difficulty is required to approve."]);
            return;
        }
        $comment = !empty($data['comment']) ? $data['comment'] : null;
        if ($this->problem->approve($id, $data['difficulty'], $comment)) {
            $problemData = $this->problem->getById($id);
            if ($problemData) {
                $this->notification->create(
                    $problemData['author_id'],
                    'problem_approved',
                    'Your Problem Was Approved! 🎉',
                    'Your problem "' . $problemData['title'] . '" has been approved by a faculty member and is now live!',
                    $id
                );
            }
            http_response_code(200);
            echo json_encode(["message" => "Problem approved successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to approve problem."]);
        }
    }

    // POST /api/faculty/problems/{id}/reject
    public function rejectProblem($id, $data) {
        $reason = !empty($data['reason']) ? $data['reason'] : 'No reason provided.';
        if ($this->problem->reject($id, $reason)) {
            $problemData = $this->problem->getById($id);
            if ($problemData) {
                $this->notification->create(
                    $problemData['author_id'],
                    'problem_rejected',
                    'Your Problem Was Rejected',
                    'Your problem "' . $problemData['title'] . '" was reviewed by a faculty member and rejected. Reason: ' . $reason,
                    $id
                );
            }
            http_response_code(200);
            echo json_encode(["message" => "Problem rejected."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to reject problem."]);
        }
    }

    /**
     * Edit an already-approved problem (faculty).
     * Keeps approved = 1; updates metadata and replaces test cases.
     */
    public function updateApprovedProblem($id, $data) {
        $required = ['title', 'difficulty', 'topic_tags', 'description', 'constraints'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["message" => "Missing required field: $field"]);
                return;
            }
        }

        $existing = $this->problem->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(["message" => "Problem not found."]);
            return;
        }
        if ((int)$existing['approved'] !== 1) {
            http_response_code(400);
            echo json_encode(["message" => "Only approved problems can be edited via this endpoint."]);
            return;
        }

        $time_limit   = isset($data['time_limit_sec'])  ? (float)$data['time_limit_sec']  : (float)$existing['time_limit_sec'];
        $memory_limit = isset($data['memory_limit_mb']) ? (int)$data['memory_limit_mb']   : (int)$existing['memory_limit_mb'];

        $result = $this->problem->update(
            $id,
            $data['title'],
            $data['difficulty'],
            $data['topic_tags'],
            $data['description'],
            $data['constraints'],
            $time_limit,
            $memory_limit,
            1   // keep approved
        );

        if (!$result) {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update problem."]);
            return;
        }

        if (!empty($data['test_cases']) && is_array($data['test_cases'])) {
            $this->problem->replaceTestCases($id, $data['test_cases']);
        }

        http_response_code(200);
        echo json_encode(["message" => "Problem updated successfully."]);
    }

    // GET /api/faculty/stats — faculty dashboard stats
    public function getStats() {
        $stats = [];

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM problems");
        $stats['total_problems'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) as pending FROM problems WHERE approved = 0 AND rejection_reason IS NULL");
        $stats['pending_review'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) as approved FROM problems WHERE approved = 1");
        $stats['approved_problems'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) as rejected FROM problems WHERE rejection_reason IS NOT NULL");
        $stats['rejected_problems'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
        $stats['total_students'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM submissions");
        $stats['total_submissions'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) as accepted FROM submissions WHERE status = 'Accepted'");
        $stats['accepted_submissions'] = (int)$stmt->fetchColumn();

        http_response_code(200);
        echo json_encode($stats);
    }
}
