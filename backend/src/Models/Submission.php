<?php
class Submission {
    private $conn;
    private $table_name = "submissions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($user_id, $problem_id, $status, $passed_count, $total_count, $runtime_ms, $code) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, problem_id, status, passed_count, total_count, runtime_ms, code_content) 
                  VALUES (:user_id, :problem_id, :status, :passed_count, :total_count, :runtime_ms, :code)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":passed_count", $passed_count);
        $stmt->bindParam(":total_count", $total_count);
        $stmt->bindParam(":runtime_ms", $runtime_ms);
        $stmt->bindParam(":code", $code);

        return $stmt->execute();
    }

    public function getByUser($user_id) {
        $query = "SELECT s.id, s.problem_id, p.title as problem_title, s.status, s.passed_count, s.total_count, s.runtime_ms, s.created_at 
                  FROM " . $this->table_name . " s
                  JOIN problems p ON s.problem_id = p.id
                  WHERE s.user_id = :user_id 
                  ORDER BY s.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getByProblem($problem_id, $user_id) {
        $query = "SELECT id, status, passed_count, total_count, runtime_ms, code_content, created_at 
                  FROM " . $this->table_name . " 
                  WHERE problem_id = :problem_id AND user_id = :user_id 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRecentSubmissions($limit = 10) {
        $query = "SELECT s.id, u.username, p.title as problem_title, s.status, s.created_at 
                  FROM " . $this->table_name . " s
                  JOIN users u ON s.user_id = u.id
                  JOIN problems p ON s.problem_id = p.id
                  ORDER BY s.created_at DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAcceptedSubmissionsByUser($user_id) {
        $query = "SELECT DISTINCT s.id, s.problem_id, p.title as problem_title, p.difficulty, s.status, s.passed_count, s.total_count, s.runtime_ms, s.created_at 
                  FROM " . $this->table_name . " s
                  JOIN problems p ON s.problem_id = p.id
                  WHERE s.user_id = :user_id AND s.status = 'Accepted'
                  ORDER BY s.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserStats($user_id) {
        // Get count of accepted problems
        $acceptedQuery = "SELECT COUNT(DISTINCT problem_id) as solved_count 
                          FROM " . $this->table_name . " 
                          WHERE user_id = :user_id AND status = 'Accepted'";
        
        $acceptedStmt = $this->conn->prepare($acceptedQuery);
        $acceptedStmt->bindParam(":user_id", $user_id);
        $acceptedStmt->execute();
        $acceptedResult = $acceptedStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get total submissions
        $totalQuery = "SELECT COUNT(*) as total_submissions 
                       FROM " . $this->table_name . " 
                       WHERE user_id = :user_id";
        
        $totalStmt = $this->conn->prepare($totalQuery);
        $totalStmt->bindParam(":user_id", $user_id);
        $totalStmt->execute();
        $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate accuracy
        $solvedCount = $acceptedResult['solved_count'] ?? 0;
        $totalSubmissions = $totalResult['total_submissions'] ?? 0;
        $accuracy = $totalSubmissions > 0 ? round(($solvedCount / $totalSubmissions) * 100, 2) : 0;
        
        return [
            'solved_count' => $solvedCount,
            'total_submissions' => $totalSubmissions,
            'accuracy' => $accuracy
        ];
    }
}
?>
