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
