<?php
// backend/api/v1/branches.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../core/Response.php';
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
            $query = "SELECT * FROM branches WHERE status = 'active' ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add stats for each branch
            foreach ($branches as &$branch) {
                $query = "SELECT COUNT(*) as total FROM patients WHERE branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch['id']]);
                $branch['patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $query = "SELECT COUNT(*) as total FROM visits WHERE branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch['id']]);
                $branch['visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $query = "SELECT COALESCE(SUM(total), 0) as total FROM pharmacy_sales 
                          WHERE branch_id = ? AND payment_status = 'paid'";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch['id']]);
                $branch['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $query = "SELECT COUNT(*) as total FROM users 
                          WHERE branch_id = ? AND role = 'doctor' AND status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch['id']]);
                $branch['doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            }
            
            Response::success($branches, 'Branches retrieved');
        }
        
        if ($action === 'get' && $id) {
            $query = "SELECT * FROM branches WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($branch) {
                Response::success($branch, 'Branch details retrieved');
            } else {
                Response::notFound('Branch not found');
            }
        }
        break;
        
    case 'POST':
        if ($action === 'create' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || empty($data['name'])) {
                Response::error('Branch name is required', 400);
            }
            
            $query = "INSERT INTO branches (name, location, phone, email, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, 'active', NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['name'],
                $data['location'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                Response::success(['id' => $new_id], 'Branch created successfully');
            } else {
                Response::error('Failed to create branch', 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'update' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $query = "UPDATE branches SET 
                      name = ?, location = ?, phone = ?, email = ?, updated_at = NOW()
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['name'] ?? null,
                $data['location'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $id
            ]);
            
            if ($result) {
                Response::success(null, 'Branch updated successfully');
            } else {
                Response::error('Failed to update branch', 500);
            }
        }
        break;
        
    case 'DELETE':
        if ($action === 'delete' && $id) {
            $query = "UPDATE branches SET status = 'inactive' WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                Response::success(null, 'Branch deactivated successfully');
            } else {
                Response::error('Failed to deactivate branch', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>