<?php
// backend/api/v1/users.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../core/Response.php';
require_once '../../core/Validator.php';
require_once '../../core/Auth.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    Response::unauthorized('Please login first');
}

$db = Database::getInstance();
$user = $auth->getUser();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            $role_filter = $_GET['role'] ?? '';
            $branch_filter = $_GET['branch_id'] ?? 'all';
            $search = $_GET['search'] ?? '';
            
            $conditions = ["u.status = 'active'"];
            $params = [];
            
            if (!empty($role_filter)) {
                $conditions[] = "u.role = ?";
                $params[] = $role_filter;
            }
            
            if ($branch_filter !== 'all') {
                $conditions[] = "u.branch_id = ?";
                $params[] = intval($branch_filter);
            }
            
            if (!empty($search)) {
                $conditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            $query = "SELECT u.*, b.name as branch_name, 
                      GROUP_CONCAT(DISTINCT u.role SEPARATOR ', ') as roles 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      $where
                      GROUP BY u.id 
                      ORDER BY u.full_name";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($users, 'Users retrieved');
        }
        
        if ($action === 'get' && $id) {
            $query = "SELECT u.*, b.name as branch_name FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                Response::success($user_data, 'User details retrieved');
            } else {
                Response::notFound('User not found');
            }
        }
        break;
        
    case 'POST':
        if ($action === 'create' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator();
            $rules = [
                'full_name' => 'required|min:2|max:100',
                'username' => 'required|min:3|max:50',
                'email' => 'required|email',
                'password' => 'required|min:6',
                'role' => 'required'
            ];
            
            if (!$validator->validate($data, $rules)) {
                Response::error($validator->getFirstError(), 400);
            }
            
            // Check if username exists
            $query = "SELECT id FROM users WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                Response::error('Username already exists', 400);
            }
            
            // Check if email exists
            $query = "SELECT id FROM users WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                Response::error('Email already exists', 400);
            }
            
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, password, full_name, email, phone, role, 
                      branch_id, specialty, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['username'],
                $hashed_password,
                $data['full_name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['role'],
                $data['branch_id'] ?? null,
                $data['specialty'] ?? null
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                Response::success(['id' => $new_id], 'User created successfully');
            } else {
                Response::error('Failed to create user', 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'update' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            if (isset($data['full_name'])) {
                $updates[] = "full_name = ?";
                $params[] = $data['full_name'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['phone'])) {
                $updates[] = "phone = ?";
                $params[] = $data['phone'];
            }
            if (isset($data['role'])) {
                $updates[] = "role = ?";
                $params[] = $data['role'];
            }
            if (isset($data['branch_id'])) {
                $updates[] = "branch_id = ?";
                $params[] = $data['branch_id'];
            }
            if (isset($data['specialty'])) {
                $updates[] = "specialty = ?";
                $params[] = $data['specialty'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            if (!empty($data['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            
            if (empty($updates)) {
                Response::error('No fields to update', 400);
            }
            
            $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                Response::success(null, 'User updated successfully');
            } else {
                Response::error('Failed to update user', 500);
            }
        }
        break;
        
    case 'DELETE':
        if ($action === 'delete' && $id) {
            $query = "UPDATE users SET status = 'inactive' WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                Response::success(null, 'User deactivated successfully');
            } else {
                Response::error('Failed to deactivate user', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>