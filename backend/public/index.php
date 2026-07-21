<?php
// Skip ngrok browser warning for API calls
header("ngrok-skip-browser-warning: true");

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/ProblemController.php';
require_once __DIR__ . '/../src/Controllers/SubmissionController.php';
require_once __DIR__ . '/../src/Controllers/LeaderboardController.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';
require_once __DIR__ . '/../src/Controllers/CommentController.php';
require_once __DIR__ . '/../src/Controllers/NotificationController.php';
require_once __DIR__ . '/../src/Controllers/RunnerController.php';
require_once __DIR__ . '/../src/Controllers/FacultyController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriSegments = explode('/', trim($uri, '/'));

// Expected URL structure: /api/resource or /api/resource/id or /api/resource/id/subresource
if ($uriSegments[0] !== 'api') {
    http_response_code(404);
    echo json_encode(["message" => "Not Found"]);
    exit();
}

$resource = isset($uriSegments[1]) ? $uriSegments[1] : null;
$resourceId = isset($uriSegments[2]) ? $uriSegments[2] : null;
$subResource = isset($uriSegments[3]) ? $uriSegments[3] : null;

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$params = $_GET;

switch ($resource) {
    case 'auth':
        $authController = new AuthController();
        if ($method === 'POST' && $resourceId === 'register') {
            $authController->register($body);
        } elseif ($method === 'POST' && $resourceId === 'login') {
            $authController->login($body);
        } elseif ($method === 'GET' && $resourceId === 'profile') {
            $user = AuthMiddleware::authenticate();
            $authController->getProfile($user['id']);
        } elseif ($method === 'POST' && $resourceId === 'profile' && $subResource === 'avatar') {
            $user = AuthMiddleware::authenticate();
            $authController->updateProfilePicture($user['id']);
        } elseif ($method === 'PUT' && $resourceId === 'profile' && $subResource === 'username') {
            $user = AuthMiddleware::authenticate();
            $authController->updateUsername($user['id'], $body);
        } elseif ($method === 'PUT' && $resourceId === 'profile' && $subResource === 'password') {
            $user = AuthMiddleware::authenticate();
            $authController->changePassword($user['id'], $body);
        } elseif ($method === 'POST' && $resourceId === 'faculty-register') {
            $user = AuthMiddleware::authenticate();
            $authController->registerFaculty($user['id'], $body);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Auth Endpoint Not Found"]);
        }
        break;

    case 'problems':
        $problemController = new ProblemController();
        if ($method === 'GET') {
            if ($resourceId === null) {
                // List problems (can filter via query params)
                $problemController->list($params);
            } else {
                // Detail of a problem
                if ($subResource === 'samples') {
                    // Public sample test cases
                    $problemController->getSamples($resourceId);
                } elseif ($subResource === 'testcases') {
                    // Full test cases (sample + hidden) for running locally. Requires auth.
                    AuthMiddleware::authenticate();
                    $problemController->getAllTestCases($resourceId);
                } else {
                    $problemController->detail($resourceId);
                }
            }
        } elseif ($method === 'POST') {
            $user = AuthMiddleware::authenticate();
            $problemController->submitProblem($user['id'], $body);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'submissions':
        $submissionController = new SubmissionController();
        if ($method === 'POST') {
            $user = AuthMiddleware::authenticate();
            $submissionController->create($user['id'], $body);
        } elseif ($method === 'GET') {
            $user = AuthMiddleware::authenticate();
            if ($resourceId === 'history') {
                $submissionController->getHistory($user['id']);
            } elseif ($resourceId === 'problem' && $subResource !== null) {
                $submissionController->getProblemHistory($subResource, $user['id']);
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Invalid submission endpoint query."]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'leaderboard':
        $leaderboardController = new LeaderboardController();
        if ($method === 'GET') {
            $leaderboardController->get();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'faculty':
        $facultyController = new FacultyController();
        $user = AuthMiddleware::requireFacultyOrAdmin();

        if ($method === 'GET' && $resourceId === 'problems') {
            if ($subResource === 'all') {
                $facultyController->listAllProblems($params);
            } else {
                $facultyController->listPendingProblems();
            }
        } elseif ($method === 'POST' && $resourceId === 'problems' && $subResource !== null) {
            $action = isset($uriSegments[4]) ? $uriSegments[4] : null;
            if ($action === 'approve') {
                $facultyController->approveProblem($subResource, $body);
            } elseif ($action === 'reject') {
                $facultyController->rejectProblem($subResource, $body);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Faculty action not found"]);
            }
        } elseif ($method === 'GET' && $resourceId === 'stats') {
            $facultyController->getStats();
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Faculty endpoint not found"]);
        }
        break;

    case 'admin':
        $adminController = new AdminController();
        $user = AuthMiddleware::requireAdmin();

        if ($method === 'GET' && $resourceId === 'users') {
            $adminController->listUsers($params);
        } elseif ($method === 'DELETE' && $resourceId === 'users' && $subResource !== null) {
            $adminController->deleteUser($subResource, $user['id']);
        } elseif ($method === 'PUT' && $resourceId === 'users' && $subResource !== null) {
            $action = isset($uriSegments[4]) ? $uriSegments[4] : null;
            if ($action === 'role') {
                $adminController->changeUserRole($subResource, $user['id'], $body);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Admin user action not found"]);
            }
        } elseif ($method === 'GET' && $resourceId === 'faculty-requests') {
            $adminController->listFacultyRequests($params);
        } elseif ($method === 'POST' && $resourceId === 'faculty-requests' && $subResource !== null) {
            $action = isset($uriSegments[4]) ? $uriSegments[4] : null;
            if ($action === 'approve') {
                $adminController->approveFacultyRequest($subResource, $user['id'], $body);
            } elseif ($action === 'reject') {
                $adminController->rejectFacultyRequest($subResource, $user['id'], $body);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Admin faculty action not found"]);
            }
        } elseif ($method === 'POST' && $resourceId === 'problems') {
            if ($subResource !== null && isset($uriSegments[4]) && $uriSegments[4] === 'approve') {
                $adminController->approveProblem($subResource, $body);
            } elseif ($subResource !== null && isset($uriSegments[4]) && $uriSegments[4] === 'reject') {
                $adminController->rejectProblem($subResource, $body);
            } else {
                $adminController->createProblem($user['id'], $body);
            }
        } elseif ($method === 'GET' && $resourceId === 'problems') {
            $adminController->listProblems($params);
        } elseif ($method === 'PUT' && $resourceId === 'problems' && $subResource !== null) {
            $adminController->updateProblem($subResource, $body);
        } elseif ($method === 'DELETE' && $resourceId === 'problems' && $subResource !== null) {
            $adminController->deleteProblem($subResource);
        } elseif ($method === 'POST' && $resourceId === 'testcases') {
            $adminController->addTestCase($body);
        } elseif ($method === 'DELETE' && $resourceId === 'testcases' && $subResource !== null) {
            $adminController->deleteTestCase($subResource);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Admin Action Not Found"]);
        }
        break;

    case 'comments':
        $commentController = new CommentController();
        
        if ($method === 'GET' && $resourceId === 'problem' && $subResource !== null) {
            // GET /api/comments/problem/{problem_id}
            try {
                $user = AuthMiddleware::authenticate();
                $commentController->getCommentsByProblem($subResource, $user['id']);
            } catch (Exception $e) {
                // Allow unauthenticated users to view comments
                $commentController->getCommentsByProblem($subResource);
            }
        } elseif ($method === 'POST' && $resourceId !== null && $subResource === 'vote') {
            // POST /api/comments/{comment_id}/vote - Vote on comment
            $user = AuthMiddleware::authenticate();
            $commentController->voteComment($resourceId, $user['id'], $body);
        } elseif ($method === 'DELETE' && $resourceId !== null && $subResource === 'vote') {
            // DELETE /api/comments/{comment_id}/vote - Remove vote
            $user = AuthMiddleware::authenticate();
            $commentController->removeVote($resourceId, $user['id']);
        } elseif ($method === 'POST') {
            // POST /api/comments - Create comment
            $user = AuthMiddleware::authenticate();
            $commentController->createComment($user['id'], $body);
        } elseif ($method === 'PUT' && $resourceId !== null) {
            // PUT /api/comments/{comment_id} - Update comment
            $user = AuthMiddleware::authenticate();
            $commentController->updateComment($resourceId, $user['id'], $body);
        } elseif ($method === 'DELETE' && $resourceId !== null) {
            // DELETE /api/comments/{comment_id} - Delete comment
            $user = AuthMiddleware::authenticate();
            $is_admin = ($user['role'] === 'admin');
            $commentController->deleteComment($resourceId, $user['id'], $is_admin);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Comment endpoint not found"]);
        }
        break;

    case 'notifications':
        $notificationController = new NotificationController();
        $user = AuthMiddleware::authenticate();
        
        if ($method === 'GET') {
            if ($resourceId === null) {
                // Get all notifications
                $unreadOnly = isset($params['unread']) && $params['unread'] === 'true';
                $notificationController->getNotifications($user['id'], $unreadOnly);
            } elseif ($resourceId === 'count') {
                // Get unread count
                $notificationController->getUnreadCount($user['id']);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Notification endpoint not found"]);
            }
        } elseif ($method === 'PUT' && $resourceId !== null) {
            if ($subResource === 'read') {
                // Mark single notification as read
                $notificationController->markAsRead($resourceId, $user['id']);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Notification endpoint not found"]);
            }
        } elseif ($method === 'PUT' && $resourceId === null) {
            // Mark all as read
            $notificationController->markAllAsRead($user['id']);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'users':
        $authController = new AuthController();
        $submissionController = new SubmissionController();
        if ($method === 'GET' && $resourceId !== null && $subResource === 'profile') {
            // GET /api/users/{userId}/profile - Public profile (no auth required)
            $authController->getPublicProfile($resourceId);
        } elseif ($method === 'GET' && $resourceId !== null && $subResource === 'submissions') {
            // GET /api/users/{userId}/submissions - Get user's accepted submissions (no auth required)
            $submissionController->getUserAcceptedSubmissions($resourceId);
        } elseif ($method === 'GET' && $resourceId !== null && $subResource === 'stats') {
            // GET /api/users/{userId}/stats - Get user's stats (no auth required)
            $submissionController->getUserStats($resourceId);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User endpoint not found"]);
        }
        break;

    case 'run':
        if ($method === 'POST') {
            AuthMiddleware::authenticate();
            $runnerController = new RunnerController();
            $runnerController->run($body);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Resource Not Found"]);
        break;
}
?>
