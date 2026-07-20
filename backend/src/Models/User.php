<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $email;
    public $password_hash;
    public $role;
    public $streak_count;
    public $last_active_date;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($username, $email, $password) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password_hash) 
                  VALUES (:username, :email, :password_hash)";

        $stmt = $this->conn->prepare($query);

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password_hash", $password_hash);

        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
        } catch (PDOException $e) {
            return false;
        }
        return false;
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function findById($id) {
        $query = "SELECT id, username, email, role, streak_count, last_active_date, avatar_url, created_at FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function updateStreak($id) {
        // Query to check if last active date was yesterday or today
        $query = "SELECT last_active_date, streak_count FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            $last_active = $user['last_active_date'];
            $streak = $user['streak_count'];

            if ($last_active === $today) {
                // Already updated today
                return true;
            } elseif ($last_active === $yesterday) {
                // Streak continues
                $streak += 1;
            } else {
                // Streak resets
                $streak = 1;
            }

            $update_query = "UPDATE " . $this->table_name . " SET last_active_date = :today, streak_count = :streak WHERE id = :id";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":today", $today);
            $update_stmt->bindParam(":streak", $streak);
            $update_stmt->bindParam(":id", $id);
            return $update_stmt->execute();
        }
        return false;
    }

    public function getLeaderboard() {
        // Get leaderboard ranked by problems solved
        $query = "SELECT u.id, u.username, u.role, u.streak_count, COUNT(DISTINCT s.problem_id) as solved_count 
                  FROM " . $this->table_name . " u
                  LEFT JOIN submissions s ON u.id = s.user_id AND s.status = 'Accepted'
                  WHERE u.role NOT IN ('admin', 'faculty')
                  GROUP BY u.id
                  ORDER BY solved_count DESC, u.streak_count DESC
                  LIMIT 50";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateAvatar($id, $avatar_url) {
        $query = "UPDATE " . $this->table_name . " SET avatar_url = :avatar_url WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":avatar_url", $avatar_url);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    public function getPublicProfile($id) {
        // Get public profile information for another user
        $query = "SELECT id, username, avatar_url, streak_count, created_at FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUsername($username) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function updateUsername($id, $username) {
        $query = "UPDATE " . $this->table_name . " SET username = :username WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    public function setRole($id, $role) {
        $query = "UPDATE " . $this->table_name . " SET role = :role WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
