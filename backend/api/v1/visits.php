<?php
// backend/api/v1/visits.php
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
            $patient_id = $_GET['patient_id'] ?? null;
            $doctor_id = $_GET['doctor_id'] ?? null;
            $branch_id = $_GET['branch_id'] ?? 'all';
            $status = $_GET['status'] ?? '';
            
            $conditions = ["1=1"];
            $params = [];
            
            if ($patient_id) {
                $conditions[] = "v.patient_id = ?";
                $params[] = $patient_id;
            }
            if ($doctor_id) {
                $conditions[] = "v.doctor_id = ?";
                $params[] = $doctor_id;
            }
            if ($branch_id !== 'all') {
                $conditions[] = "v.branch_id = ?";
                $params[] = intval($branch_id);
            }
            if (!empty($status)) {
                $conditions[] = "v.status = ?";
                $params[] = $status;
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT v.*, p.full_name as patient_name, p.patient_id, 
                      u.full_name as doctor_name, b.name as branch_name
                      FROM visits v
                      LEFT JOIN patients p ON v.patient_id = p.id
                      LEFT JOIN users u ON v.doctor_id = u.id
                      LEFT JOIN branches b ON v.branch_id = b.id
                      WHERE $where
                      ORDER BY v.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($visits, 'Visits retrieved');
        }
        
        if ($action === 'get' && $id) {
            $query = "SELECT v.*, p.full_name as patient_name, p.patient_id, 
                      u.full_name as doctor_name, b.name as branch_name
                      FROM visits v
                      LEFT JOIN patients p ON v.patient_id = p.id
                      LEFT JOIN users u ON v.doctor_id = u.id
                      LEFT JOIN branches b ON v.branch_id = b.id
                      WHERE v.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($visit) {
                Response::success($visit, 'Visit details retrieved');
            } else {
                Response::notFound('Visit not found');
            }
        }
        break;
        
    case 'POST':
        if ($action === 'create' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator();
            $rules = [
                'patient_id' => 'required|numeric',
                'visit_type' => 'required',
                'symptoms' => 'required'
            ];
            
            if (!$validator->validate($data, $rules)) {
                Response::error($validator->getFirstError(), 400);
            }
            
            // Generate visit number
            $year = date('Y');
            $query = "SELECT COUNT(*) as count FROM visits WHERE YEAR(created_at) = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$year]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            $visit_number = "V-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO visits (visit_number, patient_id, doctor_id, receptionist_id, 
                      branch_id, visit_type, status, symptoms, diagnosis, notes, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $visit_number,
                $data['patient_id'],
                $data['doctor_id'] ?? null,
                $user['id'],
                $data['branch_id'] ?? $user['branch_id'],
                $data['visit_type'],
                $data['symptoms'],
                $data['diagnosis'] ?? null,
                $data['notes'] ?? null
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                Response::success(['id' => $new_id, 'visit_number' => $visit_number], 'Visit created successfully');
            } else {
                Response::error('Failed to create visit', 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'update' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            $allowed_fields = ['doctor_id', 'status', 'diagnosis', 'notes', 'symptoms'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            
            if (empty($updates)) {
                Response::error('No fields to update', 400);
            }
            
            $query = "UPDATE visits SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                Response::success(null, 'Visit updated successfully');
            } else {
                Response::error('Failed to update visit', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>