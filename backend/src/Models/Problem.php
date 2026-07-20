<?php
class Problem {
    private $conn;
    private $table_name = "problems";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($filters = []) {
        $query = "SELECT p.id, p.title, p.difficulty, p.topic_tags, p.approved, p.rejection_reason, p.approval_comment, u.username as author 
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.author_id = u.id 
                  WHERE 1=1";

        if (!empty($filters['difficulty'])) {
            $query .= " AND p.difficulty = :difficulty";
        }
        if (!empty($filters['topic'])) {
            $query .= " AND p.topic_tags LIKE :topic";
        }
        if (isset($filters['approved'])) {
            $query .= " AND p.approved = :approved";
        }
        if (!empty($filters['author_id'])) {
            $query .= " AND p.author_id = :author_id";
        }

        $query .= " ORDER BY p.id ASC";
        $stmt = $this->conn->prepare($query);

        if (!empty($filters['difficulty'])) {
            $stmt->bindParam(":difficulty", $filters['difficulty']);
        }
        if (!empty($filters['topic'])) {
            $topic = "%" . $filters['topic'] . "%";
            $stmt->bindParam(":topic", $topic);
        }
        if (isset($filters['approved'])) {
            $approved = (int)$filters['approved'];
            $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);
        }
        if (!empty($filters['author_id'])) {
            $author_id = (int)$filters['author_id'];
            $stmt->bindParam(":author_id", $author_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT p.*, u.username as author 
                  FROM " . $this->table_name . " p 
                  JOIN users u ON p.author_id = u.id 
                  WHERE p.id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function create($title, $difficulty, $tags, $description, $constraints, $time_limit, $memory_limit, $author_id, $approved = 0) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (title, difficulty, topic_tags, description, constraints, time_limit_sec, memory_limit_mb, author_id, approved) 
                  VALUES (:title, :difficulty, :topic_tags, :description, :constraints, :time_limit, :memory_limit, :author_id, :approved)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":difficulty", $difficulty);
        $stmt->bindParam(":topic_tags", $tags);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":constraints", $constraints);
        $stmt->bindParam(":time_limit", $time_limit);
        $stmt->bindParam(":memory_limit", $memory_limit);
        $stmt->bindParam(":author_id", $author_id);
        $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($id, $title, $difficulty, $tags, $description, $constraints, $time_limit, $memory_limit, $approved = 0) {
        $query = "UPDATE " . $this->table_name . " SET 
                  title = :title, 
                  difficulty = :difficulty, 
                  topic_tags = :topic_tags, 
                  description = :description, 
                  constraints = :constraints, 
                  time_limit_sec = :time_limit, 
                  memory_limit_mb = :memory_limit, 
                  approved = :approved 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":difficulty", $difficulty);
        $stmt->bindParam(":topic_tags", $tags);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":constraints", $constraints);
        $stmt->bindParam(":time_limit", $time_limit);
        $stmt->bindParam(":memory_limit", $memory_limit);
        $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    // Get test cases for local compilation evaluation
    public function getTestCases($problem_id, $include_hidden = false) {
        $query = "SELECT id, input_data as input, expected_output as expected, is_sample 
                  FROM test_cases 
                  WHERE problem_id = :problem_id";
        
        if (!$include_hidden) {
            $query .= " AND is_sample = TRUE";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function addTestCase($problem_id, $input, $expected, $is_sample) {
        $query = "INSERT INTO test_cases (problem_id, input_data, expected_output, is_sample) 
                  VALUES (:problem_id, :input, :expected, :is_sample)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->bindParam(":input", $input);
        $stmt->bindParam(":expected", $expected);
        $stmt->bindParam(":is_sample", $is_sample, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteTestCase($test_case_id) {
        $query = "DELETE FROM test_cases WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $test_case_id);
        return $stmt->execute();
    }

    public function approve($id, $difficulty, $comment = null) {
        $query = "UPDATE " . $this->table_name . " SET approved = 1, difficulty = :difficulty, rejection_reason = NULL, approval_comment = :comment WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":difficulty", $difficulty);
        $stmt->bindParam(":comment", $comment);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    public function reject($id, $reason) {
        $query = "UPDATE " . $this->table_name . " SET approved = 2, rejection_reason = :reason WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":reason", $reason);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
?>
