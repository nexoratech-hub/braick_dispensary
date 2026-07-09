<?php
// backend/api/v1/dashboard.php
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

// Build branch filter
$branch_filter = '';
$selected_branch = $_GET['branch'] ?? 'all';
if ($selected_branch !== 'all') {
    $branch_filter = " AND branch_id = " . intval($selected_branch);
}

switch ($method) {
    case 'GET':
        if ($action === 'stats' || $action === '') {
            $stats = [];
            
            // Total patients
            $query = "SELECT COUNT(*) as total FROM patients WHERE 1=1 $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Patients this month
            $query = "SELECT COUNT(*) as total FROM patients WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['patients_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Total visits
            $query = "SELECT COUNT(*) as total FROM visits WHERE 1=1 $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['total_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Today visits
            $query = "SELECT COUNT(*) as total FROM visits WHERE DATE(created_at) = CURDATE() $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['today_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Total doctors
            $query = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'active' $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['total_doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Online doctors
            $query = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1 AND status = 'active' $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['online_doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Total staff
            $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active' $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['total_staff'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Today revenue
            $query = "SELECT COALESCE(SUM(total), 0) as total FROM pharmacy_sales 
                      WHERE DATE(sale_date) = CURDATE() AND payment_status = 'paid' $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Monthly revenue
            $query = "SELECT COALESCE(SUM(total), 0) as total FROM pharmacy_sales 
                      WHERE MONTH(sale_date) = MONTH(CURRENT_DATE()) 
                      AND YEAR(sale_date) = YEAR(CURRENT_DATE()) 
                      AND payment_status = 'paid' $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Pending prescriptions
            $query = "SELECT COUNT(*) as total FROM prescriptions 
                      WHERE status = 'pending' $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['pending_prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Pending lab tests
            $query = "SELECT COUNT(*) as total FROM lab_tests 
                      WHERE (status = 'pending' OR status = 'in_progress') $branch_filter";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['pending_lab_tests'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Total branches
            $query = "SELECT COUNT(*) as total FROM branches WHERE status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['total_branches'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Weekly revenue data for chart
            $query = "SELECT DATE(sale_date) as date, COALESCE(SUM(total), 0) as total 
                      FROM pharmacy_sales 
                      WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                      AND payment_status = 'paid' $branch_filter
                      GROUP BY DATE(sale_date) 
                      ORDER BY DATE(sale_date)";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $chart_labels = [];
            $chart_values = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $chart_labels[] = date('D', strtotime($date));
                $found = false;
                foreach ($weekly_data as $data) {
                    if ($data['date'] == $date) {
                        $chart_values[] = (float)$data['total'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $chart_values[] = 0;
                }
            }
            
            $stats['chart_labels'] = $chart_labels;
            $stats['chart_values'] = $chart_values;
            
            // Online doctors
            $query = "SELECT u.*, b.name as branch_name FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.role = 'doctor' AND u.is_online = 1 AND u.status = 'active' 
                      $branch_filter
                      ORDER BY u.full_name LIMIT 6";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['online_doctors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent activities
            $query = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($stats, 'Dashboard data retrieved');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}
?>