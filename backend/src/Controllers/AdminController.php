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

    /**
     * Edit an already-approved problem.
     * Keeps approved = 1; updates metadata fields and replaces all test cases.
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

        $time_limit   = isset($data['time_limit_sec'])   ? (float)$data['time_limit_sec']   : (float)$existing['time_limit_sec'];
        $memory_limit = isset($data['memory_limit_mb'])  ? (int)$data['memory_limit_mb']    : (int)$existing['memory_limit_mb'];

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

        // Replace test cases if provided
        if (!empty($data['test_cases']) && is_array($data['test_cases'])) {
            $this->problem->replaceTestCases($id, $data['test_cases']);
        }

        http_response_code(200);
        echo json_encode(["message" => "Problem updated successfully."]);
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
                    'problem_approved',
                    'Your Problem Was Approved! 🎉',
                    'Your problem "' . $problemData['title'] . '" has been approved and is now live on the platform!',
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

    public function rejectProblem($id, $data) {
        $reason = !empty($data['reason']) ? $data['reason'] : 'No reason provided.';
        if ($this->problem->reject($id, $reason)) {
            // Get problem details to send notification
            $problemData = $this->problem->getById($id);
            if ($problemData) {
                $this->notification->create(
                    $problemData['author_id'],
                    'problem_rejected',
                    'Your Problem Was Rejected',
                    'Your problem "' . $problemData['title'] . '" was rejected. Reason: ' . $reason,
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
            'faculty_approved',
            'Faculty Access Granted! 🎓',
            'Congratulations! Your faculty registration request has been approved. You now have faculty access on AlgoNest.'
            // no problem_id — faculty notification
        );

        http_response_code(200);
        echo json_encode(["message" => "Faculty request approved. User upgraded to faculty."]);
    }

    // ── User Management ───────────────────────────────────────────────────────

    public function listUsers($params = []) {
        $role   = !empty($params['role'])   ? $params['role']   : null;
        $search = !empty($params['search']) ? '%' . $params['search'] . '%' : null;

        $where = [];
        $bindings = [];

        if ($role) {
            $where[] = 'role = :role';
            $bindings[':role'] = $role;
        }
        if ($search) {
            $where[] = '(username LIKE :search OR email LIKE :search2)';
            $bindings[':search']  = $search;
            $bindings[':search2'] = $search;
        }

        $sql = "SELECT id, username, email, role, avatar_url, streak_count, created_at
                FROM users";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        http_response_code(200);
        echo json_encode($stmt->fetchAll());
    }

    public function deleteUser($userId, $adminId) {
        // Prevent admin from deleting themselves
        if ((int)$userId === (int)$adminId) {
            http_response_code(400);
            echo json_encode(["message" => "You cannot delete your own account."]);
            return;
        }

        // Fetch user first so we know their role
        $stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        $target = $stmt->fetch();

        if (!$target) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }

        if ($target['role'] === 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Cannot delete another admin account."]);
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["message" => "User deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete user."]);
        }
    }

    public function changeUserRole($userId, $adminId, $data) {
        $allowed = ['user', 'faculty', 'pending_faculty', 'declined_faculty'];

        if (empty($data['role']) || !in_array($data['role'], $allowed)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid role. Allowed: " . implode(', ', $allowed)]);
            return;
        }

        if ((int)$userId === (int)$adminId) {
            http_response_code(400);
            echo json_encode(["message" => "You cannot change your own role."]);
            return;
        }

        $stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        $target = $stmt->fetch();

        if (!$target) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }

        if ($target['role'] === 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Cannot change the role of another admin."]);
            return;
        }

        $newRole = $data['role'];
        $stmt = $this->db->prepare("UPDATE users SET role = :role WHERE id = :id");
        $stmt->bindParam(':role', $newRole);
        $stmt->bindParam(':id', $userId);

        if ($stmt->execute()) {
            // Notify the user about their role change
            $roleLabels = [
                'user'              => 'User',
                'faculty'           => 'Faculty',
                'pending_faculty'   => 'Pending Faculty',
                'declined_faculty'  => 'Declined Faculty',
            ];
            $roleMessages = [
                'user'             => 'Your account role has been changed to User by an administrator.',
                'faculty'          => 'Congratulations! You have been granted Faculty access on AlgoNest.',
                'pending_faculty'  => 'Your account has been set to Pending Faculty status by an administrator.',
                'declined_faculty' => 'Your account role has been changed to Declined Faculty by an administrator. Please contact support for more information.',
            ];
            $this->notification->create(
                $userId,
                'role_changed',
                'Your Role Has Been Updated',
                $roleMessages[$newRole] ?? "Your role has been updated to {$newRole}."
            );

            http_response_code(200);
            echo json_encode(["message" => "Role updated to '$newRole'."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update role."]);
        }
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

        // Mark user as declined_faculty so they cannot log in
        $db2 = new Database();
        $conn = $db2->getConnection();
        $upd = $conn->prepare("UPDATE users SET role='declined_faculty' WHERE id=:uid AND role='pending_faculty'");
        $upd->bindParam(':uid', $request['user_id']);
        $upd->execute();

        // Notify user
        $this->notification->create(
            $request['user_id'],
            'faculty_declined',
            'Faculty Request Declined',
            'Your faculty registration request was reviewed and declined. Reason: ' . $note
            // no problem_id — faculty notification
        );

        http_response_code(200);
        echo json_encode(["message" => "Faculty request rejected."]);
    }

}
