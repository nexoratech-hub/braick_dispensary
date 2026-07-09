<?php
// backend/api/v1/auth.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../core/Response.php';
require_once '../../core/Validator.php';
require_once '../../core/Auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['username']) || !isset($data['password'])) {
                Response::error('Username and password required', 400);
            }
            
            $user = $auth->login($data['username'], $data['password']);
            
            if ($user) {
                Response::success([
                    'user' => $user,
                    'token' => session_id()
                ], 'Login successful');
            } else {
                Response::error('Invalid credentials', 401);
            }
        }
        break;
        
    case 'GET':
        if ($action === 'logout') {
            $auth->logout();
            Response::success(null, 'Logout successful');
        }
        
        if ($action === 'me') {
            if ($auth->isAuthenticated()) {
                Response::success($auth->getUser(), 'User data retrieved');
            } else {
                Response::unauthorized('Not authenticated');
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>