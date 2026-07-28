<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/OtpService.php';

class AuthController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    public function register($data) {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data. Required: username, email, password."]);
            return;
        }

        $userId = $this->user->create($data['username'], $data['email'], $data['password']);

        if ($userId) {
            http_response_code(201);
            echo json_encode(["message" => "User was registered successfully.", "user_id" => $userId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Unable to register user. Username or email may already be in use."]);
        }
    }

    public function login($data) {
        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data. Required: email, password."]);
            return;
        }

        $userData = $this->user->findByEmail($data['email']);

        if ($userData && password_verify($data['password'], $userData['password_hash'])) {
            // Block pending faculty from logging in
            if ($userData['role'] === 'pending_faculty') {
                http_response_code(403);
                echo json_encode(["message" => "Your faculty request is pending admin approval. You'll be notified once reviewed."]);
                return;
            }

            // Block declined faculty from logging in — include the rejection reason
            if ($userData['role'] === 'declined_faculty') {
                $stmt = $this->db->prepare(
                    "SELECT admin_note FROM faculty_requests
                     WHERE user_id = :uid AND status = 'rejected'
                     ORDER BY reviewed_at DESC LIMIT 1"
                );
                $stmt->bindParam(':uid', $userData['id']);
                $stmt->execute();
                $req = $stmt->fetch();
                $reason = (!empty($req['admin_note']) && $req['admin_note'] !== 'No reason provided.')
                    ? $req['admin_note']
                    : 'No reason was provided.';
                http_response_code(403);
                echo json_encode([
                    "message" => "Your faculty registration request was declined.\n\nReason: " . $reason . "\n\nPlease contact the administrator to appeal."
                ]);
                return;
            }

            // Generate token payload
            $token_payload = [
                "iss" => "algonest",
                "iat" => time(),
                "exp" => time() + (3600 * 24), // 24 hours
                "user" => [
                    "id" => $userData['id'],
                    "username" => $userData['username'],
                    "email" => $userData['email'],
                    "role" => $userData['role']
                ]
            ];

            $jwt = JWT::encode($token_payload);

            http_response_code(200);
            echo json_encode([
                "message" => "Login successful.",
                "token" => $jwt,
                "user" => [
                    "id" => $userData['id'],
                    "username" => $userData['username'],
                    "role" => $userData['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Login failed. Invalid email or password."]);
        }
    }

    public function getProfile($userId) {
        $profile = $this->user->findById($userId);
        if ($profile) {
            // Break streak if last_active_date is older than yesterday
            $lastActive = $profile['last_active_date'];
            if ($lastActive) {
                $today     = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($lastActive !== $today && $lastActive !== $yesterday) {
                    // Streak is stale — reset it
                    $this->user->resetStreak($userId);
                    $profile['streak_count'] = 0;
                }
            }
            http_response_code(200);
            echo json_encode($profile);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }

    public function updateProfilePicture($userId) {
        $body = json_decode(file_get_contents('php://input'), true);

        if (empty($body['image_data']) || empty($body['file_type'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing image_data or file_type."]);
            return;
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mime = $body['file_type'];

        if (!in_array($mime, $allowed_types)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed."]);
            return;
        }

        // Strip the data URI prefix if present (data:image/png;base64,...)
        $base64 = $body['image_data'];
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64, 2)[1];
        }

        $imageData = base64_decode($base64);
        if ($imageData === false) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid base64 image data."]);
            return;
        }

        if (strlen($imageData) > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(["message" => "File size exceeds 2MB limit."]);
            return;
        }

        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $ext_map[$mime];

        $upload_dir = __DIR__ . '/../../public/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename  = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $file_path = $upload_dir . $filename;

        if (file_put_contents($file_path, $imageData) === false) {
            http_response_code(500);
            echo json_encode(["message" => "Failed to save image file."]);
            return;
        }

        $avatar_url = '/avatars/' . $filename;

        if ($this->user->updateAvatar($userId, $avatar_url)) {
            http_response_code(200);
            echo json_encode(["message" => "Profile picture updated successfully.", "avatar_url" => $avatar_url]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update profile picture in database."]);
        }
    }

    public function deleteProfilePicture($userId) {
        // Fetch current avatar path so we can delete the file
        $profile = $this->user->findById($userId);
        if (!$profile) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }

        if (empty($profile['avatar_url'])) {
            http_response_code(400);
            echo json_encode(["message" => "No profile picture to delete."]);
            return;
        }

        // Delete the file from disk (avatars live in public/avatars/)
        $filePath = __DIR__ . '/../../public' . $profile['avatar_url'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        if ($this->user->deleteAvatar($userId)) {
            http_response_code(200);
            echo json_encode(["message" => "Profile picture deleted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to remove profile picture."]);
        }
    }

    public function getPublicProfile($userId) {
        error_log("getPublicProfile called with userId: " . $userId);
        $profile = $this->user->getPublicProfile($userId);
        error_log("Profile result: " . json_encode($profile));
        if ($profile) {
            http_response_code(200);
            echo json_encode($profile);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }

    public function registerFaculty($userId, $data) {
        $required = ['full_name', 'institution', 'department', 'designation', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["message" => "Missing required field: $field"]);
                return;
            }
        }

        // Check user exists and is not already faculty/pending
        $user = $this->user->findById($userId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }
        if (in_array($user['role'], ['faculty', 'admin'])) {
            http_response_code(400);
            echo json_encode(["message" => "You already have faculty or admin access."]);
            return;
        }
        if ($user['role'] === 'pending_faculty') {
            http_response_code(400);
            echo json_encode(["message" => "You already have a pending faculty request."]);
            return;
        }
        if ($user['role'] === 'declined_faculty') {
            http_response_code(403);
            echo json_encode(["message" => "Your previous faculty request was declined. Please contact the administrator to appeal."]);
            return;
        }

        // Insert faculty request
        $query = "INSERT INTO faculty_requests (user_id, full_name, institution, department, designation, reason)
                  VALUES (:user_id, :full_name, :institution, :department, :designation, :reason)";
        $db = (new Database())->getConnection();
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindParam(':institution', $data['institution']);
        $stmt->bindParam(':department', $data['department']);
        $stmt->bindParam(':designation', $data['designation']);
        $stmt->bindParam(':reason', $data['reason']);

        try {
            $stmt->execute();
            // Mark user as pending_faculty
            $this->user->setRole($userId, 'pending_faculty');
            http_response_code(201);
            echo json_encode(["message" => "Faculty request submitted. You will be notified once reviewed by an admin."]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Failed to submit faculty request."]);
        }
    }

    public function changePassword($userId, $data) {
        if (empty($data['current_password']) || empty($data['new_password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Current password and new password are required."]);
            return;
        }

        $newPassword = $data['new_password'];
        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(["message" => "New password must be at least 6 characters long."]);
            return;
        }

        // Fetch user to verify current password
        $userData = $this->user->findById($userId);
        if (!$userData) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }

        // Need password_hash field — findById excludes it, so query directly
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->bindParam(":id", $userId);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row || !password_verify($data['current_password'], $row['password_hash'])) {
            http_response_code(401);
            echo json_encode(["message" => "Current password is incorrect."]);
            return;
        }

        if ($this->user->changePassword($userId, $newPassword)) {
            http_response_code(200);
            echo json_encode(["message" => "Password changed successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update password."]);
        }
    }

    public function updateUsername($userId, $data) {
        if (empty($data['username'])) {
            http_response_code(400);
            echo json_encode(["message" => "Username is required."]);
            return;
        }

        $username = trim($data['username']);
        if (strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(["message" => "Username must be at least 3 characters long."]);
            return;
        }

        if (strlen($username) > 50) {
            http_response_code(400);
            echo json_encode(["message" => "Username must be less than 50 characters."]);
            return;
        }

        // Check if username is already taken by another user
        $existingUser = $this->user->findByUsername($username);
        if ($existingUser && $existingUser['id'] != $userId) {
            http_response_code(400);
            echo json_encode(["message" => "Username is already taken."]);
            return;
        }

        if ($this->user->updateUsername($userId, $username)) {
            http_response_code(200);
            echo json_encode(["message" => "Username updated successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update username."]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // OTP — Registration flow
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/auth/send-otp
     * Body: { email, type: 'register'|'reset' }
     * Sends a 6-digit OTP to the given email address.
     * For 'register': rejects if email is already registered.
     * For 'reset':    rejects if email is NOT registered.
     */
    public function sendOtp($data) {
        $email = trim($data['email'] ?? '');
        $type  = $data['type'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "A valid email address is required."]);
            return;
        }

        if (!in_array($type, ['register', 'reset'])) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid OTP type. Must be 'register' or 'reset'."]);
            return;
        }

        $existing = $this->user->findByEmail($email);

        if ($type === 'register' && $existing) {
            http_response_code(409);
            echo json_encode(["message" => "This email is already registered. Please sign in instead."]);
            return;
        }

        if ($type === 'reset' && !$existing) {
            http_response_code(404);
            echo json_encode(["message" => "No account found with that email address."]);
            return;
        }

        $otp    = new OtpService();
        $result = $otp->sendOtp($email, $type);

        http_response_code($result['success'] ? 200 : 429);
        echo json_encode(["message" => $result['message']]);
    }

    /**
     * POST /api/auth/verify-register-otp
     * Body: { username, email, password, otp }
     * Verifies the OTP then creates the user account.
     */
    public function verifyRegisterOtp($data) {
        $email    = trim($data['email']    ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $otp      = trim($data['otp']      ?? '');

        if (empty($email) || empty($username) || empty($password) || empty($otp)) {
            http_response_code(400);
            echo json_encode(["message" => "email, username, password, and otp are all required."]);
            return;
        }

        // Verify OTP first
        $otpSvc = new OtpService();
        $result = $otpSvc->verifyOtp($email, $otp, 'register');

        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(["message" => $result['message']]);
            return;
        }

        // OTP valid — create the account
        $userId = $this->user->create($username, $email, $password);

        if ($userId) {
            http_response_code(201);
            echo json_encode(["message" => "Account created successfully! You can now sign in.", "user_id" => $userId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Unable to create account. Username or email may already be in use."]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // OTP — Forgot / Reset password flow
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/auth/forgot-password
     * Body: { email }
     * Sends a password-reset OTP to the email address.
     */
    public function forgotPassword($data) {
        $data['type'] = 'reset';
        $this->sendOtp($data);
    }

    /**
     * POST /api/auth/check-reset-otp
     * Body: { email, otp }
     * Validates OTP without consuming it — used before showing the new-password screen.
     */
    public function checkResetOtp($data) {
        $email = trim($data['email'] ?? '');
        $otp   = trim($data['otp']   ?? '');

        if (empty($email) || empty($otp)) {
            http_response_code(400);
            echo json_encode(["message" => "email and otp are required."]);
            return;
        }

        $otpSvc = new OtpService();
        $result = $otpSvc->checkOtp($email, $otp, 'reset');
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode(["message" => $result['message']]);
    }

    /**
     * POST /api/auth/reset-password
     * Body: { email, otp, new_password }
     * Verifies OTP then updates the user's password.
     */
    public function resetPassword($data) {
        $email       = trim($data['email']        ?? '');
        $otp         = trim($data['otp']          ?? '');
        $newPassword = $data['new_password'] ?? '';

        if (empty($email) || empty($otp) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(["message" => "email, otp, and new_password are all required."]);
            return;
        }

        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(["message" => "New password must be at least 6 characters."]);
            return;
        }

        // Verify OTP
        $otpSvc = new OtpService();
        $result = $otpSvc->verifyOtp($email, $otp, 'reset');

        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(["message" => $result['message']]);
            return;
        }

        // Find user and update password
        $userData = $this->user->findByEmail($email);
        if (!$userData) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }

        // Check if new password is same as old password
        if (password_verify($newPassword, $userData['password_hash'])) {
            http_response_code(400);
            echo json_encode(["message" => "New password cannot be the same as your current password."]);
            return;
        }

        if ($this->user->changePassword($userData['id'], $newPassword)) {
            http_response_code(200);
            echo json_encode(["message" => "Password reset successfully. You can now sign in with your new password."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to reset password. Please try again."]);
        }
    }
}
