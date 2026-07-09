<?php
// backend/api/v1/patients.php
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
            // Get all patients with filters
            $branch_filter = '';
            $search = $_GET['search'] ?? '';
            $branch_id = $_GET['branch_id'] ?? 'all';
            
            if ($branch_id !== 'all') {
                $branch_filter = " AND p.branch_id = " . intval($branch_id);
            }
            
            if (!empty($search)) {
                $search = "%$search%";
                $search_condition = " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
                $query = "SELECT p.*, b.name as branch_name, 
                          (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) as visit_count
                          FROM patients p 
                          LEFT JOIN branches b ON p.branch_id = b.id 
                          WHERE p.status = 'active' $branch_filter $search_condition
                          ORDER BY p.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$search, $search, $search]);
            } else {
                $query = "SELECT p.*, b.name as branch_name, 
                          (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) as visit_count
                          FROM patients p 
                          LEFT JOIN branches b ON p.branch_id = b.id 
                          WHERE p.status = 'active' $branch_filter
                          ORDER BY p.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
            }
            
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::success($patients, 'Patients retrieved');
        }
        
        if ($action === 'get' && $id) {
            $query = "SELECT p.*, b.name as branch_name FROM patients p 
                      LEFT JOIN branches b ON p.branch_id = b.id 
                      WHERE p.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient) {
                // Get visits
                $query = "SELECT * FROM visits WHERE patient_id = ? ORDER BY created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $patient['visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                Response::success($patient, 'Patient details retrieved');
            } else {
                Response::notFound('Patient not found');
            }
        }
        
        if ($action === 'search') {
            $search = $_GET['q'] ?? '';
            if (strlen($search) < 2) {
                Response::error('Search term must be at least 2 characters', 400);
            }
            
            $search = "%$search%";
            $query = "SELECT p.*, b.name as branch_name FROM patients p 
                      LEFT JOIN branches b ON p.branch_id = b.id 
                      WHERE p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?
                      AND p.status = 'active'
                      ORDER BY p.full_name LIMIT 20";
            $stmt = $db->prepare($query);
            $stmt->execute([$search, $search, $search]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($patients, 'Search results');
        }
        break;
        
    case 'POST':
        if ($action === 'create' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator();
            $rules = [
                'full_name' => 'required|min:2|max:100',
                'phone' => 'required',
                'gender' => 'required',
                'date_of_birth' => 'required',
                'branch_id' => 'required|numeric'
            ];
            
            if (!$validator->validate($data, $rules)) {
                Response::error($validator->getFirstError(), 400);
            }
            
            // Generate patient ID
            $year = date('Y');
            $query = "SELECT COUNT(*) as count FROM patients WHERE YEAR(created_at) = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$year]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            $patient_id = "P-$year-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO patients (patient_id, full_name, date_of_birth, gender, phone, email, 
                      address, emergency_contact, blood_group, allergies, branch_id, created_by, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $patient_id,
                $data['full_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['phone'],
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['emergency_contact'] ?? null,
                $data['blood_group'] ?? null,
                $data['allergies'] ?? null,
                $data['branch_id'],
                $user['id']
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                // Log activity
                $log_query = "INSERT INTO activity_logs (user_id, action, details, created_at) 
                              VALUES (?, 'New Patient Registered', ?, NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$user['id'], "Patient: {$data['full_name']} (ID: $patient_id)"]);
                
                Response::success(['id' => $new_id, 'patient_id' => $patient_id], 'Patient registered successfully');
            } else {
                Response::error('Failed to register patient', 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'update' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $query = "UPDATE patients SET 
                      full_name = ?, date_of_birth = ?, gender = ?, phone = ?, 
                      email = ?, address = ?, emergency_contact = ?, 
                      blood_group = ?, allergies = ?, branch_id = ?, updated_at = NOW()
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['full_name'] ?? null,
                $data['date_of_birth'] ?? null,
                $data['gender'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['emergency_contact'] ?? null,
                $data['blood_group'] ?? null,
                $data['allergies'] ?? null,
                $data['branch_id'] ?? null,
                $id
            ]);
            
            if ($result) {
                Response::success(null, 'Patient updated successfully');
            } else {
                Response::error('Failed to update patient', 500);
            }
        }
        break;
        
    case 'DELETE':
        if ($action === 'delete' && $id) {
            $query = "UPDATE patients SET status = 'deleted' WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                Response::success(null, 'Patient deleted successfully');
            } else {
                Response::error('Failed to delete patient', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>