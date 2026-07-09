<?php
// backend/api/v1/prescriptions.php
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
            $status = $_GET['status'] ?? '';
            $branch_id = $_GET['branch_id'] ?? 'all';
            
            $conditions = ["1=1"];
            $params = [];
            
            if ($patient_id) {
                $conditions[] = "p.patient_id = ?";
                $params[] = $patient_id;
            }
            if (!empty($status)) {
                $conditions[] = "p.status = ?";
                $params[] = $status;
            }
            if ($branch_id !== 'all') {
                $conditions[] = "p.branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT p.*, 
                      pat.full_name as patient_name, pat.patient_id,
                      u.full_name as doctor_name,
                      (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as items_count
                      FROM prescriptions p
                      LEFT JOIN patients pat ON p.patient_id = pat.id
                      LEFT JOIN users u ON p.doctor_id = u.id
                      WHERE $where
                      ORDER BY p.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($prescriptions, 'Prescriptions retrieved');
        }
        
        if ($action === 'get' && $id) {
            $query = "SELECT p.*, 
                      pat.full_name as patient_name, pat.patient_id,
                      u.full_name as doctor_name,
                      b.name as branch_name
                      FROM prescriptions p
                      LEFT JOIN patients pat ON p.patient_id = pat.id
                      LEFT JOIN users u ON p.doctor_id = u.id
                      LEFT JOIN branches b ON p.branch_id = b.id
                      WHERE p.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($prescription) {
                // Get items
                $query = "SELECT * FROM prescription_items WHERE prescription_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $prescription['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                Response::success($prescription, 'Prescription details retrieved');
            } else {
                Response::notFound('Prescription not found');
            }
        }
        break;
        
    case 'POST':
        if ($action === 'create' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator();
            $rules = [
                'patient_id' => 'required|numeric',
                'diagnosis' => 'required',
                'items' => 'required'
            ];
            
            if (!$validator->validate($data, $rules)) {
                Response::error($validator->getFirstError(), 400);
            }
            
            if (empty($data['items']) || !is_array($data['items'])) {
                Response::error('At least one medication item is required', 400);
            }
            
            // Generate prescription number
            $year = date('Y');
            $query = "SELECT COUNT(*) as count FROM prescriptions WHERE YEAR(created_at) = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$year]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            $prescription_number = "RX-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            // Start transaction
            $db->getConnection()->beginTransaction();
            
            try {
                // Insert prescription
                $query = "INSERT INTO prescriptions (prescription_number, visit_id, doctor_id, patient_id, 
                          pharmacy_id, diagnosis, notes, status, is_indoor, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    $prescription_number,
                    $data['visit_id'] ?? null,
                    $user['id'],
                    $data['patient_id'],
                    $data['pharmacy_id'] ?? null,
                    $data['diagnosis'],
                    $data['notes'] ?? null,
                    $data['is_indoor'] ?? 1
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to create prescription');
                }
                
                $prescription_id = $db->lastInsertId();
                
                // Insert prescription items
                foreach ($data['items'] as $item) {
                    $query = "INSERT INTO prescription_items (prescription_id, medication_name, dosage, 
                              frequency, quantity, duration, instructions, unit_price, total_price, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $prescription_id,
                        $item['medication_name'],
                        $item['dosage'] ?? null,
                        $item['frequency'] ?? null,
                        $item['quantity'],
                        $item['duration'] ?? null,
                        $item['instructions'] ?? null,
                        $item['unit_price'] ?? 0,
                        $item['total_price'] ?? 0
                    ]);
                }
                
                $db->getConnection()->commit();
                
                Response::success([
                    'id' => $prescription_id,
                    'prescription_number' => $prescription_number
                ], 'Prescription created successfully');
                
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                Response::error($e->getMessage(), 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'dispense' && $id) {
            // Dispense prescription
            $query = "UPDATE prescriptions SET status = 'dispensed', dispensed_at = NOW(), 
                      pharmacy_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$user['id'], $id]);
            
            if ($result) {
                Response::success(null, 'Prescription dispensed successfully');
            } else {
                Response::error('Failed to dispense prescription', 500);
            }
        }
        
        if ($action === 'update' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            
            if (empty($updates)) {
                Response::error('No fields to update', 400);
            }
            
            $query = "UPDATE prescriptions SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                Response::success(null, 'Prescription updated successfully');
            } else {
                Response::error('Failed to update prescription', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>