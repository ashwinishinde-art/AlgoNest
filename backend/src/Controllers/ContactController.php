<?php
require_once __DIR__ . '/../../config/database.php';

class ContactController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // ─── POST /api/contact ───────────────────────────────────────────────────
    // Authenticated users submit a message. Prefills name/email from profile if
    // provided, but accepts overrides in the body so guests could too in future.
    public function submit($userId, $body) {
        $sender_name  = trim($body['sender_name']  ?? '');
        $sender_email = trim($body['sender_email'] ?? '');
        $subject      = trim($body['subject']      ?? '');
        $category     = trim($body['category']     ?? 'other');
        $message      = trim($body['message']      ?? '');

        $allowed_categories = ['bug', 'suggestion', 'question', 'content', 'other'];

        if (!$sender_name || !$sender_email || !$subject || !$message) {
            http_response_code(400);
            echo json_encode(["message" => "sender_name, sender_email, subject, and message are required."]);
            return;
        }
        if (!filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid email address."]);
            return;
        }
        if (!in_array($category, $allowed_categories)) {
            $category = 'other';
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO contact_messages
                    (user_id, sender_name, sender_email, subject, category, message)
                VALUES
                    (:user_id, :sender_name, :sender_email, :subject, :category, :message)
            ");
            $stmt->execute([
                ':user_id'      => $userId,
                ':sender_name'  => $sender_name,
                ':sender_email' => $sender_email,
                ':subject'      => $subject,
                ':category'     => $category,
                ':message'      => $message,
            ]);

            http_response_code(201);
            echo json_encode(["message" => "Your message has been sent. The admin will get back to you soon."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Failed to submit message: " . $e->getMessage()]);
        }
    }

    // ─── GET /api/contact/my ─────────────────────────────────────────────────
    // Logged-in user can see their own submitted messages + admin replies.
    public function myMessages($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    cm.id, cm.subject, cm.category, cm.message,
                    cm.status, cm.admin_reply, cm.replied_at, cm.created_at,
                    u.username AS replied_by_username
                FROM contact_messages cm
                LEFT JOIN users u ON u.id = cm.replied_by
                WHERE cm.user_id = :user_id
                ORDER BY cm.created_at DESC
            ");
            $stmt->execute([':user_id' => $userId]);
            http_response_code(200);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    // ─── GET /api/admin/contact ──────────────────────────────────────────────
    // Admin: list all messages. Optional ?status=open|replied|closed filter.
    public function adminList($params) {
        $status = isset($params['status']) ? trim($params['status']) : null;
        $allowed = ['open', 'replied', 'closed'];

        try {
            if ($status && in_array($status, $allowed)) {
                $stmt = $this->db->prepare("
                    SELECT
                        cm.id, cm.user_id, cm.sender_name, cm.sender_email,
                        cm.subject, cm.category, cm.message,
                        cm.status, cm.admin_reply, cm.replied_at, cm.created_at,
                        u.username AS replied_by_username
                    FROM contact_messages cm
                    LEFT JOIN users u ON u.id = cm.replied_by
                    WHERE cm.status = :status
                    ORDER BY cm.created_at DESC
                ");
                $stmt->execute([':status' => $status]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT
                        cm.id, cm.user_id, cm.sender_name, cm.sender_email,
                        cm.subject, cm.category, cm.message,
                        cm.status, cm.admin_reply, cm.replied_at, cm.created_at,
                        u.username AS replied_by_username
                    FROM contact_messages cm
                    LEFT JOIN users u ON u.id = cm.replied_by
                    ORDER BY cm.created_at DESC
                ");
                $stmt->execute();
            }
            http_response_code(200);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    // ─── GET /api/admin/contact/count ────────────────────────────────────────
    // Admin: count of open (unread) messages — used for the badge.
    public function adminOpenCount() {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM contact_messages WHERE status = 'open'");
            $stmt->execute();
            $row = $stmt->fetch();
            http_response_code(200);
            echo json_encode(["count" => (int)$row['cnt']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    // ─── POST /api/admin/contact/:id/reply ───────────────────────────────────
    // Admin: write a reply. Sets status → 'replied'.
    public function adminReply($messageId, $adminUserId, $body) {
        $reply = trim($body['reply'] ?? '');
        if (!$reply) {
            http_response_code(400);
            echo json_encode(["message" => "Reply text is required."]);
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE contact_messages
                SET admin_reply = :reply,
                    replied_by  = :admin_id,
                    replied_at  = NOW(),
                    status      = 'replied'
                WHERE id = :id
            ");
            $stmt->execute([
                ':reply'    => $reply,
                ':admin_id' => $adminUserId,
                ':id'       => $messageId,
            ]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Message not found."]);
                return;
            }

            http_response_code(200);
            echo json_encode(["message" => "Reply sent."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    // ─── PUT /api/admin/contact/:id/status ───────────────────────────────────
    // Admin: manually change status (open / replied / closed).
    public function adminUpdateStatus($messageId, $body) {
        $status   = trim($body['status'] ?? '');
        $allowed  = ['open', 'replied', 'closed'];
        if (!in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid status. Use: open, replied, or closed."]);
            return;
        }

        try {
            $stmt = $this->db->prepare("UPDATE contact_messages SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $messageId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Message not found."]);
                return;
            }

            http_response_code(200);
            echo json_encode(["message" => "Status updated."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }
}
