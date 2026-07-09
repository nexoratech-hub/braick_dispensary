<?php
// backend/api/v1/pharmacy.php
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
        if ($action === 'inventory' || $action === '') {
            $branch_id = $_GET['branch_id'] ?? 'all';
            $search = $_GET['search'] ?? '';
            $low_stock = $_GET['low_stock'] ?? false;
            
            $conditions = ["status = 'active'"];
            $params = [];
            
            if ($branch_id !== 'all') {
                $conditions[] = "branch_id = ?";
                $params[] = intval($branch_id);
            }
            if (!empty($search)) {
                $conditions[] = "(medication_name LIKE ? OR category LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            if ($low_stock === 'true') {
                $conditions[] = "quantity <= reorder_level";
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT * FROM medications_inventory 
                      WHERE $where 
                      ORDER BY medication_name";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($inventory, 'Inventory retrieved');
        }
        
        if ($action === 'sales') {
            $branch_id = $_GET['branch_id'] ?? 'all';
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            
            $conditions = ["sale_date BETWEEN ? AND ?"];
            $params = [$start_date, $end_date];
            
            if ($branch_id !== 'all') {
                $conditions[] = "branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT s.*, p.full_name as patient_name,
                      u.full_name as cashier_name
                      FROM pharmacy_sales s
                      LEFT JOIN patients p ON s.patient_id = p.id
                      LEFT JOIN users u ON s.cashier_id = u.id
                      WHERE $where
                      ORDER BY s.sale_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($sales, 'Sales retrieved');
        }
        break;
        
    case 'POST':
        if ($action === 'add_medication' || $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator();
            $rules = [
                'medication_name' => 'required|min:2|max:100',
                'category' => 'required',
                'quantity' => 'required|numeric',
                'selling_price' => 'required|numeric',
                'branch_id' => 'required|numeric'
            ];
            
            if (!$validator->validate($data, $rules)) {
                Response::error($validator->getFirstError(), 400);
            }
            
            $query = "INSERT INTO medications_inventory (medication_name, category, unit, quantity, 
                      reorder_level, unit_cost, selling_price, supplier, expiry_date, batch_number, 
                      branch_id, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['medication_name'],
                $data['category'],
                $data['unit'] ?? 'Tablets',
                $data['quantity'],
                $data['reorder_level'] ?? 10,
                $data['unit_cost'] ?? 0,
                $data['selling_price'],
                $data['supplier'] ?? null,
                $data['expiry_date'] ?? null,
                $data['batch_number'] ?? null,
                $data['branch_id']
            ]);
            
            if ($result) {
                $new_id = $db->lastInsertId();
                Response::success(['id' => $new_id], 'Medication added successfully');
            } else {
                Response::error('Failed to add medication', 500);
            }
        }
        
        if ($action === 'sale') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['patient_id']) || !isset($data['items']) || empty($data['items'])) {
                Response::error('Patient ID and items are required', 400);
            }
            
            // Generate sale number
            $year = date('Y');
            $query = "SELECT COUNT(*) as count FROM pharmacy_sales WHERE YEAR(sale_date) = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$year]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            $sale_number = "S-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            $db->getConnection()->beginTransaction();
            
            try {
                // Calculate totals
                $subtotal = 0;
                foreach ($data['items'] as $item) {
                    $subtotal += $item['total_price'] ?? ($item['quantity'] * $item['unit_price']);
                }
                
                $discount = $data['discount_percent'] ?? 0;
                $discount_amount = $subtotal * ($discount / 100);
                $total = $subtotal - $discount_amount;
                
                // Insert sale
                $query = "INSERT INTO pharmacy_sales (sale_number, prescription_id, patient_id, cashier_id, 
                          branch_id, sale_type, subtotal, discount_percent, discount_amount, total, 
                          payment_method, payment_status, sale_date, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW(), NOW())";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    $sale_number,
                    $data['prescription_id'] ?? null,
                    $data['patient_id'],
                    $user['id'],
                    $data['branch_id'] ?? $user['branch_id'],
                    $data['sale_type'] ?? 'indoor',
                    $subtotal,
                    $discount,
                    $discount_amount,
                    $total,
                    $data['payment_method'] ?? 'cash'
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to create sale');
                }
                
                $sale_id = $db->lastInsertId();
                
                // Insert sale items and update inventory
                foreach ($data['items'] as $item) {
                    // Insert sale item
                    $query = "INSERT INTO sale_items (sale_id, medication_name, quantity, unit_price, total_price, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $sale_id,
                        $item['medication_name'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total_price'] ?? ($item['quantity'] * $item['unit_price'])
                    ]);
                    
                    // Update inventory
                    $query = "UPDATE medications_inventory 
                              SET quantity = quantity - ? 
                              WHERE medication_name = ? AND branch_id = ? AND status = 'active'";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $item['quantity'],
                        $item['medication_name'],
                        $data['branch_id'] ?? $user['branch_id']
                    ]);
                }
                
                $db->getConnection()->commit();
                
                Response::success([
                    'sale_id' => $sale_id,
                    'sale_number' => $sale_number,
                    'total' => $total
                ], 'Sale completed successfully');
                
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                Response::error($e->getMessage(), 500);
            }
        }
        break;
        
    case 'PUT':
        if ($action === 'update_inventory' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            if (isset($data['quantity'])) {
                $updates[] = "quantity = ?";
                $params[] = $data['quantity'];
            }
            if (isset($data['selling_price'])) {
                $updates[] = "selling_price = ?";
                $params[] = $data['selling_price'];
            }
            if (isset($data['reorder_level'])) {
                $updates[] = "reorder_level = ?";
                $params[] = $data['reorder_level'];
            }
            if (isset($data['supplier'])) {
                $updates[] = "supplier = ?";
                $params[] = $data['supplier'];
            }
            if (isset($data['expiry_date'])) {
                $updates[] = "expiry_date = ?";
                $params[] = $data['expiry_date'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            
            if (empty($updates)) {
                Response::error('No fields to update', 400);
            }
            
            $query = "UPDATE medications_inventory SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                Response::success(null, 'Inventory updated successfully');
            } else {
                Response::error('Failed to update inventory', 500);
            }
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>