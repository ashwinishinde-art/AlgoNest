<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Notification.php';

class NotificationController {
    private $db;
    private $notification;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->notification = new Notification($this->db);
    }

    public function getNotifications($userId, $unread_only = false) {
        try {
            $notifications = $this->notification->getByUserId($userId, $unread_only);
            http_response_code(200);
            echo json_encode($notifications ?: []);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error fetching notifications: " . $e->getMessage()]);
        }
    }

    public function getUnreadCount($userId) {
        try {
            $count = $this->notification->getUnreadCount($userId);
            http_response_code(200);
            echo json_encode(["count" => (int)$count]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error fetching count: " . $e->getMessage()]);
        }
    }

    public function markAsRead($notificationId, $userId) {
        try {
            // Verify notification belongs to user
            $notifications = $this->notification->getByUserId($userId);
            $notificationExists = false;
            foreach ($notifications as $n) {
                if ($n['id'] == $notificationId) {
                    $notificationExists = true;
                    break;
                }
            }

            if (!$notificationExists) {
                http_response_code(403);
                echo json_encode(["message" => "Unauthorized"]);
                return;
            }

            if ($this->notification->markAsRead($notificationId)) {
                http_response_code(200);
                echo json_encode(["message" => "Notification marked as read"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to mark notification as read"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    public function markAllAsRead($userId) {
        try {
            if ($this->notification->markAllAsRead($userId)) {
                http_response_code(200);
                echo json_encode(["message" => "All notifications marked as read"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to mark notifications as read"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }
}
?>
