<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Comment.php';
require_once __DIR__ . '/../Models/Notification.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class CommentController {
    private $db;
    private $comment;
    private $notification;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->comment = new Comment($this->db);
        $this->notification = new Notification($this->db);
    }

    // Get all comments for a problem
    public function getCommentsByProblem($problem_id, $user_id = null) {
        $comments = $this->comment->getByProblemId($problem_id);
        
        // Add user vote information if user is authenticated
        if ($user_id) {
            foreach ($comments as &$comment) {
                $comment['user_vote'] = $this->comment->getUserVote($comment['id'], $user_id);
            }
        }
        
        // Organize comments into threaded structure
        $threaded = $this->organizeComments($comments);
        
        http_response_code(200);
        echo json_encode($threaded);
    }

    // Create a new comment
    public function createComment($user_id, $data) {
        if (empty($data['problem_id']) || empty($data['content'])) {
            http_response_code(400);
            echo json_encode(["message" => "Problem ID and content are required."]);
            return;
        }

        $content = trim($data['content']);
        if (strlen($content) < 3) {
            http_response_code(400);
            echo json_encode(["message" => "Comment must be at least 3 characters long."]);
            return;
        }

        if (strlen($content) > 2000) {
            http_response_code(400);
            echo json_encode(["message" => "Comment must be less than 2000 characters."]);
            return;
        }

        $parent_comment_id = isset($data['parent_comment_id']) ? $data['parent_comment_id'] : null;

        // Validate parent comment exists if provided
        if ($parent_comment_id) {
            $parentComment = $this->comment->getById($parent_comment_id);
            if (!$parentComment || $parentComment['problem_id'] != $data['problem_id']) {
                http_response_code(400);
                echo json_encode(["message" => "Invalid parent comment."]);
                return;
            }
        }

        $comment_id = $this->comment->create($data['problem_id'], $user_id, $content, $parent_comment_id);

        if ($comment_id) {
            if ($parent_comment_id) {
                $parentComment = $this->comment->getById($parent_comment_id);

                // Notify the direct parent comment author (if not the replier)
                if ($parentComment && $parentComment['user_id'] != $user_id) {
                    $this->notification->create(
                        $parentComment['user_id'],
                        'reply',
                        'New Reply to Your Comment',
                        'Someone replied to your comment.',
                        $data['problem_id']
                    );
                }

                // If the parent is itself a reply, also notify the top-level comment author
                // (only if they're different from the direct parent author and the replier)
                if ($parentComment && $parentComment['parent_comment_id'] !== null) {
                    $topComment = $this->comment->getById($parentComment['parent_comment_id']);
                    if ($topComment
                        && $topComment['user_id'] != $user_id
                        && $topComment['user_id'] != $parentComment['user_id']
                    ) {
                        $this->notification->create(
                            $topComment['user_id'],
                            'reply',
                            'New Reply in Your Thread',
                            'Someone replied in a thread you started.',
                            $data['problem_id']
                        );
                    }
                }
            }

            http_response_code(201);
            echo json_encode([
                "message" => "Comment created successfully.",
                "comment_id" => $comment_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create comment."]);
        }
    }

    // Update a comment
    public function updateComment($comment_id, $user_id, $data) {
        if (empty($data['content'])) {
            http_response_code(400);
            echo json_encode(["message" => "Content is required."]);
            return;
        }

        $content = trim($data['content']);
        if (strlen($content) < 3) {
            http_response_code(400);
            echo json_encode(["message" => "Comment must be at least 3 characters long."]);
            return;
        }

        if (strlen($content) > 2000) {
            http_response_code(400);
            echo json_encode(["message" => "Comment must be less than 2000 characters."]);
            return;
        }

        $success = $this->comment->update($comment_id, $user_id, $content);

        if ($success) {
            http_response_code(200);
            echo json_encode(["message" => "Comment updated successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Comment not found or you don't have permission to edit it."]);
        }
    }

    // Delete a comment
    public function deleteComment($comment_id, $user_id, $is_admin = false) {
        $success = $this->comment->delete($comment_id, $user_id, $is_admin);

        if ($success) {
            http_response_code(200);
            echo json_encode(["message" => "Comment deleted successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Comment not found or you don't have permission to delete it."]);
        }
    }

    // Vote on a comment
    public function voteComment($comment_id, $user_id, $data) {
        if (!isset($data['vote_type']) || !in_array($data['vote_type'], ['upvote', 'downvote'])) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid vote type. Must be 'upvote' or 'downvote'."]);
            return;
        }

        // Check if comment exists
        $comment = $this->comment->getById($comment_id);
        if (!$comment) {
            http_response_code(404);
            echo json_encode(["message" => "Comment not found."]);
            return;
        }

        // Check if user is trying to vote on their own comment
        if ($comment['user_id'] == $user_id) {
            http_response_code(400);
            echo json_encode(["message" => "You cannot vote on your own comment."]);
            return;
        }

        $success = $this->comment->vote($comment_id, $user_id, $data['vote_type']);

        if ($success) {
            http_response_code(200);
            echo json_encode(["message" => "Vote recorded successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to record vote."]);
        }
    }

    // Remove vote on a comment
    public function removeVote($comment_id, $user_id) {
        $success = $this->comment->removeVote($comment_id, $user_id);

        if ($success) {
            http_response_code(200);
            echo json_encode(["message" => "Vote removed successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to remove vote."]);
        }
    }

    // Organize flat comments into threaded structure
    // All replies (regardless of depth) are attached flat under the top-level comment.
    // The frontend uses @mention to show who a reply is directed at.
    private function organizeComments($comments) {
        $map = [];
        $threaded = [];

        // Index all comments by id
        foreach ($comments as $comment) {
            $comment['replies'] = [];
            $map[$comment['id']] = $comment;
        }

        // Build tree — replies always nest under the top-level ancestor
        foreach ($map as $id => $comment) {
            if ($comment['parent_comment_id'] === null) {
                $threaded[] = &$map[$id];
            } else {
                // Walk up to find the top-level ancestor
                $parentId = $comment['parent_comment_id'];
                while (isset($map[$parentId]) && $map[$parentId]['parent_comment_id'] !== null) {
                    $parentId = $map[$parentId]['parent_comment_id'];
                }
                if (isset($map[$parentId])) {
                    $map[$parentId]['replies'][] = &$map[$id];
                }
            }
        }

        return array_values($threaded);
    }
}
?>