<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/User.php';

class LeaderboardController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    public function get() {
        $leaderboard = $this->user->getLeaderboard();
        http_response_code(200);
        echo json_encode($leaderboard);
    }
}
