<?php
require_once __DIR__ . '/../../config/jwt.php';

class AuthMiddleware {
    public static function authenticate() {
        $headers = apache_request_headers();
        
        // Handle case where apache_request_headers is not available (e.g. built-in PHP server)
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (function_exists('getallheaders')) {
                $h = getallheaders();
                if (isset($h['Authorization'])) {
                    $headers['Authorization'] = $h['Authorization'];
                }
            }
        }

        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(["message" => "Access denied. Token missing."]);
            exit;
        }

        $authHeader = $headers['Authorization'];
        $token = null;
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            http_response_code(401);
            echo json_encode(["message" => "Access denied. Invalid header format."]);
            exit;
        }

        $decoded = JWT::decode($token);
        if (!$decoded) {
            http_response_code(401);
            echo json_encode(["message" => "Access denied. Invalid or expired token."]);
            exit;
        }

        return $decoded['user']; // Returns array with id, username, email, role
    }

    public static function requireAdmin() {
        $user = self::authenticate();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Access denied. Admin role required."]);
            exit;
        }
        return $user;
    }
}
