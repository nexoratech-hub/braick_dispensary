<?php
// backend/api/v1/lab_tests.php
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
            $status = $_GET['status'] ?? '';
            $patient_id = $_GET['patient_id'] ?? null;
            $branch_id = $_GET['branch_id'] ?? 'all';
            
            $conditions = ["1=1"];
            $params = [];
            
            if (!empty($status)) {
                $conditions[] = "l.status = ?";
                $params[] = $status;
            }
            if ($patient_id) {
                $conditions[] = "l.patient_id = ?";
                $params[] = $patient_id;
            }
            if ($branch_id !== 'all') {
                $conditions[] = "l.branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT l.*, 
                      p.full_name as patient_name, p.patient_id,
                      u.full_name as doctor_name,
                      lab.full_name as technician_name
                      FROM lab_tests l
                      LEFT JOIN patients p ON l.patient_id = p.id
                      LEFT JOIN users u ON l.doctor_id = u.id
                      LEFT JOIN users lab ON l.lab_technician_id = lab.id
                      WHERE $where
                      ORDER BY l.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($tests, 'Lab tests retrieved');
        }
        
        if ($action === 'get' && $id) {
            $query = "SELECT l.*, 
                      p.full_name as patient_name, p.patient_id,
                      u.full_name as doctor_name,
                      lab.full_name as technician_name
                      FROM lab_tests l
                      LEFT JOIN patients p ON l.patient_id = p.id
                      LEFT JOIN users u ON l.doctor_id = u.id
                      LEFT JOIN users lab ON l.lab_technician_id = lab.id
                      WHERE l.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($test) {
                Response::success($test, 'Lab test details retrieved');
            } else {
                Response::notFound('Lab test not found');
            }
        }
        break;
        
    case 'POST':
        if ($action === 'create' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator();
            $rules = [
                'patient_id' => 'required|numeric',
                'test_name' => 'required',
                'test_type' => 'required'
            ];
            
            if (!$validator->validate($data, $rules)) {
                Response::error($validator->getFirstError(), 400);
            }
            
            $query = "INSERT INTO lab_tests (patient_id, doctor_id, test_name, test_type, 
                      sample_type, status, notes, branch_id, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['patient_id'],
                $data['doctor_id'] ?? $user['id'],
                $data['test_name'],
                $data['test_type'],
                $data['sample_type'] ?? null,
                $data['notes'] ?? null,
                $data['branch_id'] ?? $user['branch_id']
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                Response::success(['id' => $new_id], 'Lab test created successfully');
            } else {
                Response::error('Failed to create lab test', 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'update' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
                if ($data['status'] === 'completed') {
                    $updates[] = "completed_at = NOW()";
                    $updates[] = "lab_technician_id = ?";
                    $params[] = $user['id'];
                }
            }
            if (isset($data['results'])) {
                $updates[] = "results = ?";
                $params[] = $data['results'];
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
            }
            if (isset($data['test_date'])) {
                $updates[] = "test_date = ?";
                $params[] = $data['test_date'];
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            
            if (empty($updates)) {
                Response::error('No fields to update', 400);
            }
            
            $query = "UPDATE lab_tests SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                Response::success(null, 'Lab test updated successfully');
            } else {
                Response::error('Failed to update lab test', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>