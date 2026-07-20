<?php
class Notification {
    private $conn;
    private $table_name = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($user_id, $problem_id, $type, $title, $message) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, problem_id, type, title, message) 
                  VALUES (:user_id, :problem_id, :type, :title, :message)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);

        return $stmt->execute();
    }

    public function getByUserId($user_id, $unread_only = false) {
        $query = "SELECT n.*, p.title as problem_title 
                  FROM " . $this->table_name . " n
                  JOIN problems p ON n.problem_id = p.id
                  WHERE n.user_id = :user_id";

        if ($unread_only) {
            $query .= " AND n.is_read = FALSE";
        }

        $query .= " ORDER BY n.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead($notification_id) {
        $query = "UPDATE " . $this->table_name . " SET is_read = TRUE WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $notification_id);
        return $stmt->execute();
    }

    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . " SET is_read = TRUE WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        return $stmt->execute();
    }

    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE user_id = :user_id AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
}
?>
