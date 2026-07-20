<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Submission.php';
require_once __DIR__ . '/../Models/User.php';

class SubmissionController {
    private $db;
    private $submission;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->submission = new Submission($this->db);
        $this->user = new User($this->db);
    }

    public function create($userId, $data) {
        if (empty($data['problem_id']) || empty($data['status']) || !isset($data['passed_count']) || !isset($data['total_count']) || !isset($data['runtime_ms']) || empty($data['code'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete submission data."]);
            return;
        }

        // Save submission record
        $result = $this->submission->create(
            $userId,
            $data['problem_id'],
            $data['status'],
            $data['passed_count'],
            $data['total_count'],
            $data['runtime_ms'],
            $data['code']
        );

        if ($result) {
            // Update streak since user made a submission
            $this->user->updateStreak($userId);

            http_response_code(201);
            echo json_encode(["message" => "Submission logged successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to log submission."]);
        }
    }

    public function getHistory($userId) {
        $history = $this->submission->getByUser($userId);
        http_response_code(200);
        echo json_encode($history);
    }

    public function getProblemHistory($problemId, $userId) {
        $history = $this->submission->getByProblem($problemId, $userId);
        http_response_code(200);
        echo json_encode($history);
    }

    public function getUserAcceptedSubmissions($userId) {
        $submissions = $this->submission->getAcceptedSubmissionsByUser($userId);
        http_response_code(200);
        echo json_encode($submissions);
    }

    public function getUserStats($userId) {
        $stats = $this->submission->getUserStats($userId);
        http_response_code(200);
        echo json_encode($stats);
    }
}
