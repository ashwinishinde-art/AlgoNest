<?php
class Comment {
    private $conn;
    private $comments_table = "comments";
    private $votes_table = "comment_votes";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all comments for a problem with nested replies
    public function getByProblemId($problem_id) {
        $query = "SELECT c.id, c.problem_id, c.user_id, c.parent_comment_id, c.content, 
                         c.vote_score, c.created_at, c.updated_at, u.username, u.role, u.avatar_url,
                         (SELECT COUNT(*) FROM " . $this->comments_table . " replies WHERE replies.parent_comment_id = c.id) as reply_count
                  FROM " . $this->comments_table . " c
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.problem_id = :problem_id
                  ORDER BY c.parent_comment_id ASC, c.vote_score DESC, c.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Get user's vote on a specific comment
    public function getUserVote($comment_id, $user_id) {
        $query = "SELECT vote_type FROM " . $this->votes_table . " 
                  WHERE comment_id = :comment_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result ? $result['vote_type'] : null;
    }

    // Create a new comment
    public function create($problem_id, $user_id, $content, $parent_comment_id = null) {
        $query = "INSERT INTO " . $this->comments_table . " 
                  (problem_id, user_id, content, parent_comment_id) 
                  VALUES (:problem_id, :user_id, :content, :parent_comment_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":problem_id", $problem_id);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":parent_comment_id", $parent_comment_id);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Update a comment (only by the author)
    public function update($comment_id, $user_id, $content) {
        $query = "UPDATE " . $this->comments_table . " 
                  SET content = :content, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :comment_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":comment_id", $comment_id);
        $stmt->bindParam(":user_id", $user_id);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    // Delete a comment (only by the author or admin)
    public function delete($comment_id, $user_id, $is_admin = false) {
        if ($is_admin) {
            $query = "DELETE FROM " . $this->comments_table . " WHERE id = :comment_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":comment_id", $comment_id);
        } else {
            $query = "DELETE FROM " . $this->comments_table . " 
                      WHERE id = :comment_id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":comment_id", $comment_id);
            $stmt->bindParam(":user_id", $user_id);
        }

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    // Vote on a comment
    public function vote($comment_id, $user_id, $vote_type) {
        try {
            $this->conn->beginTransaction();

            // Remove existing vote if any
            $deleteQuery = "DELETE FROM " . $this->votes_table . " 
                           WHERE comment_id = :comment_id AND user_id = :user_id";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(":comment_id", $comment_id);
            $deleteStmt->bindParam(":user_id", $user_id);
            $deleteStmt->execute();

            // Insert new vote
            $insertQuery = "INSERT INTO " . $this->votes_table . " 
                           (comment_id, user_id, vote_type) 
                           VALUES (:comment_id, :user_id, :vote_type)";
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(":comment_id", $comment_id);
            $insertStmt->bindParam(":user_id", $user_id);
            $insertStmt->bindParam(":vote_type", $vote_type);
            $insertStmt->execute();

            // Update vote score in comments table
            $this->updateVoteScore($comment_id);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Remove a vote
    public function removeVote($comment_id, $user_id) {
        try {
            $this->conn->beginTransaction();

            $deleteQuery = "DELETE FROM " . $this->votes_table . " 
                           WHERE comment_id = :comment_id AND user_id = :user_id";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(":comment_id", $comment_id);
            $deleteStmt->bindParam(":user_id", $user_id);
            $deleteStmt->execute();

            // Update vote score
            $this->updateVoteScore($comment_id);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Update the vote score for a comment
    private function updateVoteScore($comment_id) {
        $query = "UPDATE " . $this->comments_table . " 
                  SET vote_score = (
                      SELECT COALESCE(SUM(CASE 
                          WHEN vote_type = 'upvote' THEN 1 
                          WHEN vote_type = 'downvote' THEN -1 
                          ELSE 0 
                      END), 0)
                      FROM " . $this->votes_table . " 
                      WHERE comment_id = :comment_id
                  )
                  WHERE id = :comment_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id);
        return $stmt->execute();
    }

    // Get comment by ID (for validation)
    public function getById($comment_id) {
        $query = "SELECT c.*, u.username, u.role, u.avatar_url FROM " . $this->comments_table . " c
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.id = :comment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>