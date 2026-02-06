<?php
// api/auth.php

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Authentication.php';

$db = new Database();
$conn = $db->connect();
$auth = new Authentication($conn);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = basename($path);

try {
    if ($endpoint === 'auth.php') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($method === 'POST') {
            $action = $input['action'] ?? '';

            switch ($action) {
                case 'register':
                    $username = trim($input['username'] ?? '');
                    $email = trim($input['email'] ?? '');
                    $password = $input['password'] ?? '';
                    $phone = $input['phone'] ?? '';
                    $role = $input['role'] ?? 'user';
                    $parentId = $input['parent_id'] ?? null;

                    if (!$username || !$email || !$password) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Missing required fields'
                        ]);
                        exit;
                    }

                    $result = $auth->register($username, $email, $password, $phone, $role, $parentId);
                    http_response_code($result['success'] ? 201 : 400);
                    echo json_encode($result);
                    break;

                case 'login':
                    $username = trim($input['username'] ?? '');
                    $password = $input['password'] ?? '';

                    if (!$username || !$password) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Missing credentials'
                        ]);
                        exit;
                    }

                    $result = $auth->login($username, $password);
                    http_response_code($result['success'] ? 200 : 401);
                    echo json_encode($result);
                    break;

                case 'refresh-token':
                    $token = $input['token'] ?? '';
                    $payload = $auth->verifyJWT($token);

                    if (!$payload) {
                        http_response_code(401);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Invalid token'
                        ]);
                        exit;
                    }

                    $newToken = $auth->generateJWT($payload['userId'], $payload['role']);
                    echo json_encode([
                        'success' => true,
                        'token' => $newToken
                    ]);
                    break;

                case 'logout':
                    // Client-side token deletion
                    echo json_encode([
                        'success' => true,
                        'message' => 'Logged out successfully'
                    ]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid action'
                    ]);
            }
        } else {
            // GET current user
            $token = $_GET['token'] ?? '';
            $user = $auth->getCurrentUser($token);

            if ($user) {
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
            }
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$db->close();
