<?php
// backend/api/v1/reports.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

switch ($method) {
    case 'GET':
        if ($action === 'patients') {
            $branch_id = $_GET['branch_id'] ?? 'all';
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            
            $conditions = ["DATE(p.created_at) BETWEEN ? AND ?"];
            $params = [$start_date, $end_date];
            
            if ($branch_id !== 'all') {
                $conditions[] = "p.branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT 
                      DATE(p.created_at) as date,
                      COUNT(*) as total_patients,
                      SUM(CASE WHEN p.gender = 'Male' THEN 1 ELSE 0 END) as male,
                      SUM(CASE WHEN p.gender = 'Female' THEN 1 ELSE 0 END) as female,
                      b.name as branch_name
                      FROM patients p
                      LEFT JOIN branches b ON p.branch_id = b.id
                      WHERE $where
                      GROUP BY DATE(p.created_at), b.name
                      ORDER BY DATE(p.created_at) DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Totals
            $totals = [
                'total' => array_sum(array_column($data, 'total_patients')),
                'male' => array_sum(array_column($data, 'male')),
                'female' => array_sum(array_column($data, 'female'))
            ];
            
            Response::success([
                'data' => $data,
                'totals' => $totals
            ], 'Patients report generated');
        }
        
        if ($action === 'revenue') {
            $branch_id = $_GET['branch_id'] ?? 'all';
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            
            $conditions = ["DATE(s.sale_date) BETWEEN ? AND ?", "s.payment_status = 'paid'"];
            $params = [$start_date, $end_date];
            
            if ($branch_id !== 'all') {
                $conditions[] = "s.branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT 
                      DATE(s.sale_date) as date,
                      COUNT(*) as total_sales,
                      COALESCE(SUM(s.total), 0) as total_revenue,
                      COALESCE(SUM(s.discount_amount), 0) as total_discount,
                      s.payment_method,
                      b.name as branch_name
                      FROM pharmacy_sales s
                      LEFT JOIN branches b ON s.branch_id = b.id
                      WHERE $where
                      GROUP BY DATE(s.sale_date), s.payment_method, b.name
                      ORDER BY DATE(s.sale_date) DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Summary
            $summary = [
                'total_revenue' => array_sum(array_column($data, 'total_revenue')),
                'total_sales' => array_sum(array_column($data, 'total_sales')),
                'total_discount' => array_sum(array_column($data, 'total_discount'))
            ];
            
            Response::success([
                'data' => $data,
                'summary' => $summary
            ], 'Revenue report generated');
        }
        
        if ($action === 'doctors') {
            $branch_id = $_GET['branch_id'] ?? 'all';
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            
            $conditions = ["DATE(v.created_at) BETWEEN ? AND ?"];
            $params = [$start_date, $end_date];
            
            if ($branch_id !== 'all') {
                $conditions[] = "v.branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT 
                      u.id as doctor_id,
                      u.full_name as doctor_name,
                      u.specialty,
                      COUNT(DISTINCT v.id) as total_visits,
                      COUNT(DISTINCT v.patient_id) as unique_patients,
                      COUNT(DISTINCT p.id) as total_prescriptions,
                      b.name as branch_name
                      FROM visits v
                      LEFT JOIN users u ON v.doctor_id = u.id
                      LEFT JOIN prescriptions p ON p.visit_id = v.id
                      LEFT JOIN branches b ON v.branch_id = b.id
                      WHERE $where AND u.role = 'doctor'
                      GROUP BY u.id, u.full_name, u.specialty, b.name
                      ORDER BY total_visits DESC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($data, 'Doctors performance report generated');
        }
        
        if ($action === 'pharmacy') {
            $branch_id = $_GET['branch_id'] ?? 'all';
            
            $conditions = ["status = 'active'"];
            if ($branch_id !== 'all') {
                $conditions[] = "branch_id = ?";
                $params[] = intval($branch_id);
            }
            
            $where = implode(" AND ", $conditions);
            
            $query = "SELECT 
                      category,
                      COUNT(*) as total_items,
                      SUM(quantity) as total_quantity,
                      SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock_items,
                      COALESCE(AVG(selling_price), 0) as avg_price,
                      b.name as branch_name
                      FROM medications_inventory m
                      LEFT JOIN branches b ON m.branch_id = b.id
                      WHERE $where
                      GROUP BY category, b.name";
            $stmt = $db->prepare($query);
            $stmt->execute($params ?? []);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($data, 'Pharmacy report generated');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>