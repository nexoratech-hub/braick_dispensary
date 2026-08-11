<?php
// ================================================================
// FILE: frontend/pages/admin/reports.php
// ADMIN - REPORTS DASHBOARD
// PATIENT REPORT - WITH BILL TYPE COLUMN
// BRAICK DISPENSARY - BLUE THEME
// FIXED: Lab Report - No duplicate tests
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$report_type = $_GET['type'] ?? 'patient';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FUNCTION TO GET BRANCH FILTER
// ================================================================
function getBranchFilter($selected_branch_id, $table_alias = '') {
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        if (!empty($table_alias)) {
            return " AND " . $table_alias . ".branch_id = " . (int)$selected_branch_id;
        }
        return " AND branch_id = " . (int)$selected_branch_id;
    }
    return '';
}

// ================================================================
// BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
}

// ================================================================
// ================================================================
// 1. PATIENT REPORT
// ================================================================
// ================================================================

$patient_data = null;
$patient_visits = [];
$patient_bills_summary = [
    'total_paid' => 0,
    'total_prescription' => 0,
    'total_lab' => 0,
    'total_procedures_tools' => 0,
    'total_consultation' => 0,
    'total_bills' => 0
];

// Get patients for dropdown
if ($report_type === 'patient') {
    $branch_filter_patients = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter_patients = " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query("
        SELECT id, full_name, patient_id 
        FROM patients 
        WHERE 1=1 $branch_filter_patients
        ORDER BY full_name
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($patient_id > 0) {
        // Get patient personal info
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as receptionist_name, b.name as branch_name
            FROM patients p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get all visits
        $stmt = $db->prepare("
            SELECT v.*, u.full_name as doctor_name
            FROM visits v
            LEFT JOIN users u ON v.doctor_id = u.id
            WHERE v.patient_id = ?
            ORDER BY v.created_at DESC
        ");
        $stmt->execute([$patient_id]);
        $patient_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each visit, get details
        foreach ($patient_visits as &$visit) {
            $visit_id = $visit['id'];
            
            // Vital Signs
            $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
            $stmt->execute([$visit_id]);
            $visit['vital_signs'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Lab Tests
            $stmt = $db->prepare("SELECT * FROM lab_tests WHERE visit_id = ? ORDER BY created_at DESC");
            $stmt->execute([$visit_id]);
            $visit['lab_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prescriptions
            $stmt = $db->prepare("
                SELECT p.*, pi.* 
                FROM prescriptions p
                LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                WHERE p.visit_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $visit['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Procedures & Tools from bill_items
            $stmt = $db->prepare("
                SELECT bi.* 
                FROM bill_items bi
                INNER JOIN patient_bills pb ON bi.bill_id = pb.id
                WHERE pb.visit_id = ?
                AND bi.item_type IN ('procedure', 'tool')
                ORDER BY bi.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $visit['procedures_tools'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ================================================================
            // BILLS WITH TYPE DETERMINATION
            // ================================================================
            $stmt = $db->prepare("
                SELECT * FROM patient_bills 
                WHERE visit_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $visit['bills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Determine bill type for each bill
            foreach ($visit['bills'] as &$bill) {
                $bill['bill_type'] = 'Other';
                $bill['bill_type_icon'] = 'fa-file-invoice';
                $bill['bill_type_color'] = '#64748B';
                
                // Check if it's a prescription bill
                if (strpos($bill['bill_number'], 'BILL-PRES-') !== false) {
                    $bill['bill_type'] = 'Prescription';
                    $bill['bill_type_icon'] = 'fa-prescription-bottle';
                    $bill['bill_type_color'] = '#7C3AED';
                } else {
                    // Get items from this bill to determine type
                    $stmt_items = $db->prepare("
                        SELECT item_type, COUNT(*) as count, SUM(total_price) as total 
                        FROM bill_items 
                        WHERE bill_id = ? 
                        GROUP BY item_type
                        ORDER BY SUM(total_price) DESC
                    ");
                    $stmt_items->execute([$bill['id']]);
                    $bill_items_types = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($bill_items_types)) {
                        // Get the dominant type (by total amount)
                        $dominant = $bill_items_types[0]['item_type'] ?? 'other';
                        
                        $type_map = [
                            'consultation' => ['label' => 'Consultation', 'icon' => 'fa-user-md', 'color' => '#0D9488'],
                            'lab_test' => ['label' => 'Lab Test', 'icon' => 'fa-flask', 'color' => '#7C3AED'],
                            'procedure' => ['label' => 'Procedure', 'icon' => 'fa-syringe', 'color' => '#D97706'],
                            'tool' => ['label' => 'Tool', 'icon' => 'fa-tools', 'color' => '#F59E0B'],
                            'medication' => ['label' => 'Medication', 'icon' => 'fa-pills', 'color' => '#059669'],
                            'registration' => ['label' => 'Registration', 'icon' => 'fa-file-medical', 'color' => '#0B5ED7'],
                            'other' => ['label' => 'Other', 'icon' => 'fa-file-invoice', 'color' => '#64748B']
                        ];
                        
                        $bill['bill_type'] = $type_map[$dominant]['label'] ?? 'Other';
                        $bill['bill_type_icon'] = $type_map[$dominant]['icon'] ?? 'fa-file-invoice';
                        $bill['bill_type_color'] = $type_map[$dominant]['color'] ?? '#64748B';
                    }
                }
            }
            unset($bill);
            
            // Calculate summary
            foreach ($visit['bills'] as $bill) {
                $patient_bills_summary['total_bills']++;
                if ($bill['status'] === 'paid') {
                    $patient_bills_summary['total_paid'] += $bill['total_amount'];
                    
                    if (strpos($bill['bill_number'], 'BILL-PRES-') !== false) {
                        $patient_bills_summary['total_prescription'] += $bill['total_amount'];
                    } else {
                        $stmt = $db->prepare("SELECT item_type, total_price FROM bill_items WHERE bill_id = ?");
                        $stmt->execute([$bill['id']]);
                        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($items as $item) {
                            if ($item['item_type'] === 'lab_test') {
                                $patient_bills_summary['total_lab'] += $item['total_price'];
                            } elseif ($item['item_type'] === 'procedure' || $item['item_type'] === 'tool') {
                                $patient_bills_summary['total_procedures_tools'] += $item['total_price'];
                            } elseif ($item['item_type'] === 'consultation') {
                                $patient_bills_summary['total_consultation'] += $item['total_price'];
                            }
                        }
                    }
                }
            }
        }
    }
} else {
    $patients = [];
}

// ================================================================
// ================================================================
// 2. CASHIER REPORT
// ================================================================
// ================================================================

$cashier_data = [];

if ($report_type === 'cashier') {
    $branch_filter = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter = " AND pb.branch_id = " . (int)$selected_branch_id;
    }
    
    // Total Revenue
    $stmt = $db->query("
        SELECT COALESCE(SUM(pb.total_amount), 0) as total 
        FROM patient_bills pb
        WHERE pb.status = 'paid' $branch_filter
    ");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Expenses (discounts)
    $stmt = $db->query("
        SELECT COALESCE(SUM(pb.discount_amount), 0) as total 
        FROM patient_bills pb
        WHERE pb.status = 'paid' $branch_filter
    ");
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Profit = Revenue - Expenses
    $total_profit = $total_revenue - $total_expenses;
    
    // All bills with details
    $stmt = $db->query("
        SELECT pb.*, p.full_name as patient_name, u.full_name as cashier_name
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE 1=1 $branch_filter
        ORDER BY pb.created_at DESC
    ");
    $cashier_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Patient totals
    $stmt = $db->query("
        SELECT 
            p.id, p.full_name, p.patient_id,
            COUNT(pb.id) as bill_count,
            COALESCE(SUM(pb.total_amount), 0) as total_paid,
            COALESCE(SUM(pb.discount_amount), 0) as total_discount
        FROM patients p
        LEFT JOIN patient_bills pb ON p.id = pb.patient_id AND pb.status = 'paid'
        WHERE 1=1 $branch_filter
        GROUP BY p.id
        HAVING bill_count > 0
        ORDER BY total_paid DESC
        LIMIT 20
    ");
    $patient_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cashier_data = [
        'total_revenue' => $total_revenue,
        'total_expenses' => $total_expenses,
        'total_profit' => $total_profit,
        'bills' => $cashier_bills,
        'patient_totals' => $patient_totals
    ];
}

// ================================================================
// ================================================================
// 3. PHARMACY REPORT
// ================================================================
// ================================================================

$pharmacy_data = [];

if ($report_type === 'pharmacy') {
    $branch_filter = "";
    $branch_filter_os = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter = " AND os.branch_id = " . (int)$selected_branch_id;
        $branch_filter_os = " AND ps.branch_id = " . (int)$selected_branch_id;
    }
    
    // OTC Sales
    $stmt = $db->query("
        SELECT os.*, u.full_name as sold_by_name
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.payment_status = 'paid' $branch_filter
        ORDER BY os.created_at DESC
    ");
    $otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prescription Sales
    $stmt = $db->query("
        SELECT ps.*, p.full_name as patient_name, u.full_name as dispensed_by_name
        FROM prescription_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        LEFT JOIN users u ON ps.dispensed_by = u.id
        WHERE ps.payment_status = 'paid' $branch_filter_os
        ORDER BY ps.created_at DESC
    ");
    $prescription_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals for cards
    $total_prescription_amount = 0;
    $total_otc_amount = 0;
    $total_prescription_count = count($prescription_sales);
    $total_otc_count = count($otc_sales);
    
    foreach ($prescription_sales as $s) {
        $total_prescription_amount += $s['total_amount'] ?? 0;
    }
    foreach ($otc_sales as $s) {
        $total_otc_amount += $s['net_amount'] ?? 0;
    }
    
    // Prescription Items
    $stmt = $db->query("
        SELECT pi.*, p.patient_id as patient_code, p.full_name as patient_name
        FROM prescription_items pi
        LEFT JOIN prescriptions pr ON pi.prescription_id = pr.id
        LEFT JOIN patients p ON pr.patient_id = p.id
        ORDER BY pi.created_at DESC
        LIMIT 100
    ");
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // OTC Items
    $stmt = $db->query("
        SELECT oi.*, os.customer_name
        FROM otc_sale_items oi
        LEFT JOIN otc_sales os ON oi.sale_id = os.id
        WHERE os.payment_status = 'paid' $branch_filter
        ORDER BY oi.created_at DESC
        LIMIT 100
    ");
    $otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pharmacy_data = [
        'otc_sales' => $otc_sales,
        'prescription_sales' => $prescription_sales,
        'prescription_items' => $prescription_items,
        'otc_items' => $otc_items,
        'total_prescription_amount' => $total_prescription_amount,
        'total_otc_amount' => $total_otc_amount,
        'total_prescription_count' => $total_prescription_count,
        'total_otc_count' => $total_otc_count
    ];
}

// ================================================================
// ================================================================
// 4. LAB REPORT - FIXED: NO DUPLICATES
// ================================================================
// ================================================================

$lab_data = [];

if ($report_type === 'lab') {
    $branch_filter = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter = " AND lt.branch_id = " . (int)$selected_branch_id;
    }
    
    // Date filter
    $date_filter = "";
    if (!empty($date_from) && !empty($date_to)) {
        $date_filter = " AND lt.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    } elseif (!empty($date_from)) {
        $date_filter = " AND lt.created_at >= '$date_from 00:00:00'";
    } elseif (!empty($date_to)) {
        $date_filter = " AND lt.created_at <= '$date_to 23:59:59'";
    }
    
    // All lab tests with patient info - GROUP BY lt.id to avoid duplicates
    $stmt = $db->query("
        SELECT DISTINCT
            lt.id,
            lt.visit_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.test_name,
            lt.test_price,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.reference_range,
            lt.interpretation,
            lt.performed_by,
            lt.status,
            lt.bill_created,
            lt.notes,
            lt.technician_id,
            lt.branch_id,
            lt.created_at,
            lt.completed_at,
            lt.updated_at,
            lt.result_template_id,
            lt.formatted_result,
            lt.printed_at,
            lt.printed_by,
            p.full_name as patient_name, 
            p.patient_id as patient_code,
            v.visit_number,
            u.full_name as doctor_name,
            u2.full_name as technician_name,
            pb.total_amount as bill_amount,
            pb.status as bill_status
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
        LEFT JOIN patient_bills pb ON pb.visit_id = v.id AND pb.status = 'paid'
        WHERE 1=1 $branch_filter $date_filter
        GROUP BY lt.id
        ORDER BY lt.created_at DESC
    ");
    $lab_tests_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary
    $total_lab_revenue = 0;
    $total_tests = count($lab_tests_all);
    $completed_tests = 0;
    $pending_tests = 0;
    $in_progress_tests = 0;
    $cancelled_tests = 0;
    $tests_with_results = 0;
    $tests_without_results = 0;
    
    // Group by test name for top tests
    $test_counts = [];
    
    foreach ($lab_tests_all as $test) {
        $total_lab_revenue += $test['test_price'] ?? 0;
        
        if ($test['status'] === 'completed') $completed_tests++;
        elseif ($test['status'] === 'pending') $pending_tests++;
        elseif ($test['status'] === 'in_progress') $in_progress_tests++;
        elseif ($test['status'] === 'cancelled') $cancelled_tests++;
        
        // Check if test has results
        if (!empty($test['results']) && $test['results'] !== 'NULL' && $test['results'] !== '') {
            $tests_with_results++;
        } else {
            $tests_without_results++;
        }
        
        // Count by test name
        $test_name = $test['test_name'] ?? 'Unknown';
        if (!isset($test_counts[$test_name])) {
            $test_counts[$test_name] = 0;
        }
        $test_counts[$test_name]++;
    }
    
    // Sort test counts by frequency
    arsort($test_counts);
    $top_tests = array_slice($test_counts, 0, 10, true);
    
    $lab_data = [
        'tests' => $lab_tests_all,
        'total_revenue' => $total_lab_revenue,
        'total_tests' => $total_tests,
        'completed_tests' => $completed_tests,
        'pending_tests' => $pending_tests,
        'in_progress_tests' => $in_progress_tests,
        'cancelled_tests' => $cancelled_tests,
        'tests_with_results' => $tests_with_results,
        'tests_without_results' => $tests_without_results,
        'top_tests' => $top_tests
    ];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger',
        'completed' => 'success',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'in_progress' => 'info',
        'scheduled' => 'info',
        'assigned' => 'primary',
        'with_doctor' => 'primary',
        'lab_test' => 'info',
        'lab_completed' => 'success',
        'prescribed' => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-hover: #1E293B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .report-tabs {
            display: flex;
            gap: 4px;
            background: var(--bg-card);
            padding: 4px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .report-tab {
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--text-secondary);
            flex: 1;
            text-align: center;
            min-width: 120px;
            text-decoration: none;
        }
        
        .report-tab:hover {
            background: var(--gray-100);
            color: var(--text-primary);
        }
        
        .report-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .report-tab i { margin-right: 8px; }
        .report-tab.cashier-tab.active {
            background: #059669;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }
        
        .filter-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }
        
        .filter-bar select, .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.75rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
        }
        
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .filter-bar .btn-filter {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-bar .btn-filter:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .filter-bar .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .filter-bar .btn-reset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 14px 20px;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .card-header {
            background: #0F172A;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 16px 20px;
        }
        
        /* ================================================================
           SUMMARY CARDS
           ================================================================ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .summary-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .summary-card:hover::before {
            opacity: 1;
        }
        
        .summary-card .number {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
        }
        
        .summary-card .number.green { color: #059669; }
        .summary-card .number.purple { color: #7C3AED; }
        .summary-card .number.orange { color: #D97706; }
        .summary-card .number.red { color: #DC2626; }
        .summary-card .number.teal { color: #0D9488; }
        .summary-card .number.pink { color: #EC4899; }
        .summary-card .number.blue { color: #0B5ED7; }
        
        .summary-card .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4px;
        }
        
        .summary-card .sub-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
            opacity: 0.7;
        }
        
        .summary-card .card-icon-mini {
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 1.2rem;
            color: var(--border-color);
            opacity: 0.3;
        }
        
        .summary-card .card-icon-mini.blue { color: var(--primary); opacity: 0.2; }
        .summary-card .card-icon-mini.green { color: #059669; opacity: 0.2; }
        .summary-card .card-icon-mini.purple { color: #7C3AED; opacity: 0.2; }
        .summary-card .card-icon-mini.orange { color: #D97706; opacity: 0.2; }
        .summary-card .card-icon-mini.teal { color: #0D9488; opacity: 0.2; }
        
        /* ================================================================
           VISIT HISTORY
           ================================================================ */
        .visit-timeline {
            position: relative;
            padding-left: 28px;
        }
        
        .visit-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-light);
            border-radius: 2px;
        }
        
        .visit-item-modern {
            position: relative;
            margin-bottom: 16px;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .visit-item-modern:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .visit-item-modern .visit-dot {
            position: absolute;
            left: -22px;
            top: 18px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--primary-light);
            z-index: 1;
        }
        
        .visit-item-modern .visit-dot.completed {
            background: #059669;
            box-shadow: 0 0 0 2px #D1FAE5;
        }
        
        .visit-item-modern .visit-dot.pending {
            background: #D97706;
            box-shadow: 0 0 0 2px #FEF3C7;
        }
        
        .visit-item-modern .visit-dot.cancelled {
            background: #DC2626;
            box-shadow: 0 0 0 2px #FEE2E2;
        }
        
        .visit-item-modern .visit-header-modern {
            padding: 14px 18px;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .visit-item-modern .visit-header-modern {
            background: #0F172A;
        }
        
        .visit-item-modern .visit-header-modern .visit-number {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .visit-item-modern .visit-header-modern .visit-number i {
            color: var(--primary);
        }
        
        .visit-item-modern .visit-header-modern .visit-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .visit-item-modern .visit-header-modern .visit-date {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .visit-item-modern .visit-body-modern {
            padding: 14px 18px;
        }
        
        .visit-item-modern .visit-body-modern .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
        }
        
        .visit-item-modern .visit-body-modern .info-item {
            font-size: 0.8rem;
            padding: 4px 0;
        }
        
        .visit-item-modern .visit-body-modern .info-item .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .visit-item-modern .visit-body-modern .info-item .value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           DIAGNOSIS - HIGHLIGHTED STYLING
           ================================================================ */
        .diagnosis-box {
            background: var(--primary-bg);
            border-radius: 8px;
            padding: 10px 14px;
            border-left: 4px solid var(--primary);
            grid-column: 1 / -1;
            margin-top: 4px;
        }
        
        [data-theme="dark"] .diagnosis-box {
            background: #1E3A5F;
            border-left-color: #3B82F6;
        }
        
        .diagnosis-box .label {
            color: var(--primary);
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 2px;
        }
        
        .diagnosis-box .diagnosis-text {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .diagnosis-box .diagnosis-text .highlight {
            color: var(--primary);
            background: rgba(11, 94, 215, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        [data-theme="dark"] .diagnosis-box .diagnosis-text .highlight {
            background: rgba(59, 130, 246, 0.2);
        }
        
        .complaint-box {
            background: var(--bg-body);
            border-radius: 6px;
            padding: 8px 12px;
            border: 1px dashed var(--border-color);
            grid-column: 1 / -1;
        }
        
        [data-theme="dark"] .complaint-box {
            background: #0F172A;
        }
        
        .complaint-box .label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 2px;
        }
        
        .complaint-box .complaint-text {
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .no-diagnosis {
            grid-column: 1 / -1;
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-style: italic;
            padding: 6px 0;
        }
        
        /* ================================================================
           SUB SECTIONS
           ================================================================ */
        .visit-item-modern .sub-section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--border-color);
        }
        
        .visit-item-modern .sub-section .sub-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .visit-item-modern .sub-section .sub-title i {
            color: var(--primary);
        }
        
        .visit-item-modern .sub-table {
            width: 100%;
            font-size: 0.75rem;
            border-collapse: collapse;
        }
        
        .visit-item-modern .sub-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            padding: 6px 10px;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
        }
        
        [data-theme="dark"] .visit-item-modern .sub-table thead th {
            background: #0F172A;
        }
        
        .visit-item-modern .sub-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .visit-item-modern .sub-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .visit-item-modern .sub-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        /* ================================================================
           BILL TYPE BADGE
           ================================================================ */
        .bill-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .bill-type-badge i {
            font-size: 0.55rem;
        }
        
        .bill-type-badge.consultation { background: #D1FAE5; color: #065F46; }
        .bill-type-badge.prescription { background: #EDE9FE; color: #5B21B6; }
        .bill-type-badge.lab_test { background: #EDE9FE; color: #5B21B6; }
        .bill-type-badge.procedure { background: #FEF3C7; color: #92400E; }
        .bill-type-badge.tool { background: #FEF3C7; color: #92400E; }
        .bill-type-badge.medication { background: #D1FAE5; color: #065F46; }
        .bill-type-badge.registration { background: #DBEAFE; color: #1E40AF; }
        .bill-type-badge.other { background: #F1F5F9; color: #475569; }
        
        [data-theme="dark"] .bill-type-badge.consultation { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .bill-type-badge.prescription { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .bill-type-badge.lab_test { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .bill-type-badge.procedure { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .bill-type-badge.tool { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .bill-type-badge.medication { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .bill-type-badge.registration { background: #1E3A5F; color: #60A5FA; }
        [data-theme="dark"] .bill-type-badge.other { background: #1E293B; color: #94A3B8; }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .cashier-table thead th {
            background: #059669;
            border-bottom: 3px solid #047857;
        }
        
        [data-theme="dark"] .cashier-table thead th {
            background: #059669;
            border-bottom: 3px solid #047857;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: var(--bg-card);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .btn-green {
            background: #059669;
            color: white;
            border-color: #059669;
        }
        
        .btn-green:hover {
            background: #047857;
            border-color: #047857;
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-pdf {
            background: #DC2626;
            color: white;
            border-color: #DC2626;
        }
        
        .btn-pdf:hover {
            background: #B91C1C;
            border-color: #B91C1C;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .visit-item-modern .visit-body-modern .info-grid {
                grid-template-columns: 1fr;
            }
            .diagnosis-box { grid-column: 1; }
            .complaint-box { grid-column: 1; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .report-tabs { flex-direction: column; }
            .report-tab { flex: none; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
            .visit-item-modern .visit-header-modern {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .summary-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .filter-bar, .report-tabs { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .data-table thead th {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .cashier-table thead th {
                background: #059669 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .summary-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .visit-item-modern { border: 1px solid #ddd !important; box-shadow: none !important; }
            .visit-item-modern .visit-dot { display: none !important; }
            .visit-timeline::before { display: none !important; }
            .visit-item-modern .visit-header-modern { background: #f5f5f5 !important; }
            .diagnosis-box { background: #f0f7ff !important; border-left-color: #0B5ED7 !important; }
            .complaint-box { background: #f5f5f5 !important; }
            .bill-type-badge { 
                background: #f0f0f0 !important;
                color: #333 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-bar"></i>
                Reports
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-alt"></i>
                Generate and view system reports
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REPORT TABS -->
    <!-- ================================================================ -->
    <div class="report-tabs animate-fade-in-up">
        <a href="?type=patient&branch=<?= urlencode($selected_branch_id) ?>" 
           class="report-tab <?= $report_type === 'patient' ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i> Patient Report
        </a>
        <a href="?type=cashier&branch=<?= urlencode($selected_branch_id) ?>" 
           class="report-tab cashier-tab <?= $report_type === 'cashier' ? 'active' : '' ?>">
            <i class="fas fa-cash-register"></i> Cashier Report
        </a>
        <a href="?type=pharmacy&branch=<?= urlencode($selected_branch_id) ?>" 
           class="report-tab <?= $report_type === 'pharmacy' ? 'active' : '' ?>">
            <i class="fas fa-prescription-bottle"></i> Pharmacy Report
        </a>
        <a href="?type=lab&branch=<?= urlencode($selected_branch_id) ?>" 
           class="report-tab <?= $report_type === 'lab' ? 'active' : '' ?>">
            <i class="fas fa-flask"></i> Lab Report
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" action="" class="flex flex-wrap gap-2 items-center w-full">
            <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <?php if ($report_type === 'patient'): ?>
                <select name="patient_id" class="min-w-[200px]">
                    <option value="">-- Select Patient --</option>
                    <?php if (!empty($patients)): ?>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $patient_id == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No patients found</option>
                    <?php endif; ?>
                </select>
            <?php endif; ?>
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="Date From">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="Date To">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Apply Filter
            </button>
            
            <a href="reports.php?type=<?= urlencode($report_type) ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <!-- 1. PATIENT REPORT - WITH BILL TYPE COLUMN -->
    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'patient'): ?>
    
        <?php if ($patient_data && !empty($patient_visits)): ?>
        
        <!-- Summary Cards -->
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini blue"><i class="fas fa-money-bill-wave"></i></span>
                <p class="number">TSh <?= number_format($patient_bills_summary['total_paid'], 0) ?></p>
                <p class="label">All Paid Bills</p>
                <p class="sub-label">Total payments made</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini green"><i class="fas fa-prescription"></i></span>
                <p class="number green">TSh <?= number_format($patient_bills_summary['total_prescription'], 0) ?></p>
                <p class="label">Prescription Bills</p>
                <p class="sub-label">Medication costs</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini purple"><i class="fas fa-flask"></i></span>
                <p class="number purple">TSh <?= number_format($patient_bills_summary['total_lab'], 0) ?></p>
                <p class="label">Lab Test Bills</p>
                <p class="sub-label">Laboratory services</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini orange"><i class="fas fa-syringe"></i></span>
                <p class="number orange">TSh <?= number_format($patient_bills_summary['total_procedures_tools'], 0) ?></p>
                <p class="label">Procedures & Tools</p>
                <p class="sub-label">Medical procedures</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini teal"><i class="fas fa-user-md"></i></span>
                <p class="number teal">TSh <?= number_format($patient_bills_summary['total_consultation'], 0) ?></p>
                <p class="label">Visit/Consultation</p>
                <p class="sub-label">Doctor consultation fees</p>
            </div>
        </div>
        
        <!-- Patient Personal Info -->
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user"></i> Patient Personal Information
                </h3>
                <span class="badge badge-info">ID: <?= htmlspecialchars($patient_data['patient_id']) ?></span>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Full Name</span><br><strong><?= htmlspecialchars($patient_data['full_name']) ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Gender</span><br><strong><?= htmlspecialchars($patient_data['gender'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Date of Birth</span><br><strong><?= !empty($patient_data['date_of_birth']) ? date('M d, Y', strtotime($patient_data['date_of_birth'])) : 'N/A' ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Phone</span><br><strong><?= htmlspecialchars($patient_data['phone'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Email</span><br><strong><?= htmlspecialchars($patient_data['email'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Address</span><br><strong><?= htmlspecialchars($patient_data['address'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Blood Group</span><br><strong><?= htmlspecialchars($patient_data['blood_group'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Allergies</span><br><strong><?= htmlspecialchars($patient_data['allergies'] ?? 'None') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Branch</span><br><strong><?= htmlspecialchars($patient_data['branch_name'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Registered By</span><br><strong><?= htmlspecialchars($patient_data['receptionist_name'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Registration Date</span><br><strong><?= date('M d, Y h:i A', strtotime($patient_data['created_at'])) ?></strong></div>
                </div>
            </div>
        </div>
        
        <!-- Visits -->
        <div class="card animate-fade-in-up" style="animation-delay:0.14s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-stethoscope"></i> Patient Visit History (<?= count($patient_visits) ?> visits)
                </h3>
                <button onclick="exportPatientPDF()" class="btn btn-pdf btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
            <div class="card-body">
                <?php if (count($patient_visits) > 0): ?>
                    <div class="visit-timeline">
                        <?php foreach ($patient_visits as $visit):
                            $status_class = 'pending';
                            if ($visit['status'] === 'completed') $status_class = 'completed';
                            elseif ($visit['status'] === 'cancelled') $status_class = 'cancelled';
                            
                            $has_diagnosis = !empty($visit['diagnosis']) && $visit['diagnosis'] !== 'NULL' && $visit['diagnosis'] !== '0';
                            $has_complaint = !empty($visit['complaint']) && $visit['complaint'] !== 'NULL';
                            $has_symptoms = !empty($visit['symptoms']) && $visit['symptoms'] !== 'NULL';
                        ?>
                            <div class="visit-item-modern">
                                <div class="visit-dot <?= $status_class ?>"></div>
                                
                                <div class="visit-header-modern">
                                    <span class="visit-number">
                                        <i class="fas fa-file-medical"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                        <span class="badge badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>" style="font-size:0.6rem;padding:2px 10px;margin-left:8px;">
                                            <?= ucfirst($visit['status'] ?? 'Pending') ?>
                                        </span>
                                        <?php if ($has_diagnosis): ?>
                                            <span class="badge badge-purple" style="font-size:0.55rem;padding:1px 10px;">
                                                <i class="fas fa-stethoscope"></i> Diagnosed
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="visit-meta">
                                        <span class="visit-date">
                                            <i class="fas fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? $visit['created_at'])) ?>
                                        </span>
                                        <span class="badge badge-info" style="font-size:0.55rem;padding:1px 10px;background:var(--primary);">
                                            <i class="fas fa-clock"></i> #<?= $visit['id'] ?>
                                        </span>
                                    </span>
                                </div>
                                
                                <div class="visit-body-modern">
                                    <div class="info-grid">
                                        <div class="info-item"><span class="label">Doctor:</span> <span class="value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span></div>
                                        <div class="info-item"><span class="label">Visit Type:</span> <span class="value"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span></div>
                                        
                                        <?php if ($has_symptoms): ?>
                                            <div class="complaint-box">
                                                <span class="label"><i class="fas fa-thermometer-half"></i> Symptoms / Presenting Complaints</span>
                                                <p class="complaint-text"><?= htmlspecialchars($visit['symptoms']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_complaint): ?>
                                            <div class="complaint-box">
                                                <span class="label"><i class="fas fa-question-circle"></i> Reason for Visit / Complaint</span>
                                                <p class="complaint-text"><?= htmlspecialchars($visit['complaint']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_diagnosis): ?>
                                            <div class="diagnosis-box">
                                                <span class="label"><i class="fas fa-stethoscope"></i> Diagnosis / Impression</span>
                                                <p class="diagnosis-text">
                                                    <span class="highlight"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <div class="no-diagnosis">
                                                <i class="fas fa-info-circle" style="color:var(--text-secondary);"></i> No diagnosis recorded for this visit
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($visit['treatment']) && $visit['treatment'] !== 'NULL'): ?>
                                            <div class="info-item" style="grid-column:1/-1;">
                                                <span class="label">Treatment Given:</span>
                                                <span class="value"><?= htmlspecialchars($visit['treatment']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($visit['vital_signs'])): ?>
                                            <div class="info-item" style="grid-column:1/-1;">
                                                <span class="label">Vital Signs:</span>
                                                <span class="value">
                                                    <?php 
                                                        $vs = $visit['vital_signs'];
                                                        echo "🌡️ Temp: " . ($vs['temperature'] ?? 'N/A') . "°C | ";
                                                        echo "❤️ BP: " . ($vs['blood_pressure_systolic'] ?? 'N/A') . "/" . ($vs['blood_pressure_diastolic'] ?? 'N/A') . " | ";
                                                        echo "💓 Pulse: " . ($vs['pulse_rate'] ?? 'N/A') . " | ";
                                                        echo "⚖️ Weight: " . ($vs['weight'] ?? 'N/A') . "kg";
                                                        if (!empty($vs['bmi'])) echo " | BMI: " . $vs['bmi'];
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Lab Tests -->
                                    <?php if (!empty($visit['lab_tests'])): ?>
                                        <div class="sub-section">
                                            <div class="sub-title"><i class="fas fa-flask"></i> Lab Tests (<?= count($visit['lab_tests']) ?>)</div>
                                            <table class="sub-table">
                                                <thead>
                                                    <tr>
                                                        <th>Test Name</th>
                                                        <th>Result</th>
                                                        <th>Reference</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($visit['lab_tests'] as $test): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($test['test_name']) ?></td>
                                                            <td><strong><?= htmlspecialchars($test['results'] ?? '-') ?></strong></td>
                                                            <td><?= htmlspecialchars($test['reference_range'] ?? '-') ?></td>
                                                            <td>
                                                                <span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:1px 8px;">
                                                                    <?= ucfirst($test['status'] ?? 'Pending') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Prescriptions -->
                                    <?php if (!empty($visit['prescriptions'])): ?>
                                        <div class="sub-section">
                                            <div class="sub-title"><i class="fas fa-prescription"></i> Prescriptions (<?= count($visit['prescriptions']) ?>)</div>
                                            <table class="sub-table">
                                                <thead>
                                                    <tr>
                                                        <th>Medication</th>
                                                        <th>Dosage</th>
                                                        <th>Frequency</th>
                                                        <th>Duration</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($visit['prescriptions'] as $presc): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($presc['medication'] ?? $presc['medication_name'] ?? 'N/A') ?></td>
                                                            <td><?= htmlspecialchars($presc['dosage'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($presc['frequency'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($presc['duration'] ?? '-') ?></td>
                                                            <td>
                                                                <span class="badge badge-<?= getStatusBadge($presc['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:1px 8px;">
                                                                    <?= ucfirst($presc['status'] ?? 'Pending') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Procedures & Tools -->
                                    <?php if (!empty($visit['procedures_tools'])): ?>
                                        <div class="sub-section">
                                            <div class="sub-title"><i class="fas fa-syringe"></i> Procedures & Tools (<?= count($visit['procedures_tools']) ?>)</div>
                                            <table class="sub-table">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Type</th>
                                                        <th>Qty</th>
                                                        <th style="text-align:right;">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($visit['procedures_tools'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                            <td><?= ucfirst($item['item_type'] ?? 'N/A') ?></td>
                                                            <td><?= $item['quantity'] ?? 1 ?></td>
                                                            <td style="text-align:right;font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- ================================================================ -->
                                    <!-- BILLS WITH TYPE COLUMN -->
                                    <!-- ================================================================ -->
                                    <?php if (!empty($visit['bills'])): ?>
                                        <div class="sub-section">
                                            <div class="sub-title"><i class="fas fa-file-invoice"></i> Bills (<?= count($visit['bills']) ?>)</div>
                                            <table class="sub-table">
                                                <thead>
                                                    <tr>
                                                        <th>Bill #</th>
                                                        <th>Type</th>
                                                        <th style="text-align:right;">Total</th>
                                                        <th style="text-align:right;">Paid</th>
                                                        <th style="text-align:right;">Balance</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($visit['bills'] as $bill): 
                                                        $bill_type_class = strtolower(str_replace(' ', '_', $bill['bill_type'] ?? 'other'));
                                                    ?>
                                                        <tr>
                                                            <td style="font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($bill['bill_number']) ?></td>
                                                            <td>
                                                                <span class="bill-type-badge <?= $bill_type_class ?>">
                                                                    <i class="fas <?= $bill['bill_type_icon'] ?? 'fa-file-invoice' ?>"></i>
                                                                    <?= htmlspecialchars($bill['bill_type'] ?? 'Other') ?>
                                                                </span>
                                                            </td>
                                                            <td style="text-align:right;font-weight:600;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                                            <td style="text-align:right;color:#059669;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                                            <td style="text-align:right;color:#DC2626;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                                            <td>
                                                                <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:1px 8px;">
                                                                    <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-stethoscope"></i><p>No visits found for this patient</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
            <div class="card animate-fade-in-up">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <h3><?= $patient_id > 0 ? 'No Patient Data Found' : 'No Patient Selected' ?></h3>
                        <p><?= $patient_id > 0 ? 'No data found for the selected patient.' : 'Please select a patient from the filter above to view their report.' ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <!-- 2. CASHIER REPORT -->
    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'cashier'): ?>
    
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini green"><i class="fas fa-money-bill-wave"></i></span>
                <p class="number green">TSh <?= number_format($cashier_data['total_revenue'] ?? 0, 0) ?></p>
                <p class="label">Total Revenue</p>
                <p class="sub-label">All paid bills</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini red"><i class="fas fa-receipt"></i></span>
                <p class="number red">TSh <?= number_format($cashier_data['total_expenses'] ?? 0, 0) ?></p>
                <p class="label">Total Expenses</p>
                <p class="sub-label">Discounts given</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini teal"><i class="fas fa-chart-line"></i></span>
                <p class="number teal">TSh <?= number_format($cashier_data['total_profit'] ?? 0, 0) ?></p>
                <p class="label">Total Profit</p>
                <p class="sub-label">Revenue - Expenses</p>
            </div>
        </div>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice"></i> All Bills (<?= count($cashier_data['bills'] ?? []) ?>)
                </h3>
                <button onclick="exportCashierPDF()" class="btn btn-pdf btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($cashier_data['bills'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table cashier-table">
                            <thead>
                                <tr>
                                    <th>Bill #</th>
                                    <th>Patient</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Discount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashier_data['bills'] as $bill): ?>
                                    <tr>
                                        <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                                        <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                        <td class="text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                        <td class="text-red-600">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['discount_amount'] ?? 0, 0) ?></td>
                                        <td>
                                            <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                                <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
                                        <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-file-invoice"></i><p>No bills found</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.14s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users"></i> Patient Totals
                </h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($cashier_data['patient_totals'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table cashier-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>ID</th>
                                    <th># Bills</th>
                                    <th>Total Paid</th>
                                    <th>Total Discount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashier_data['patient_totals'] as $pt): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($pt['full_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($pt['patient_id']) ?></td>
                                        <td><?= number_format($pt['bill_count']) ?></td>
                                        <td class="text-green-600 font-bold">TSh <?= number_format($pt['total_paid'], 0) ?></td>
                                        <td>TSh <?= number_format($pt['total_discount'], 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-users"></i><p>No patient data found</p></div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <!-- 3. PHARMACY REPORT -->
    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'pharmacy'): ?>
    
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini purple"><i class="fas fa-prescription"></i></span>
                <p class="number purple">TSh <?= number_format($pharmacy_data['total_prescription_amount'] ?? 0, 0) ?></p>
                <p class="label">Prescription Sales</p>
                <p class="sub-label"><?= number_format($pharmacy_data['total_prescription_count'] ?? 0) ?> transactions</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini orange"><i class="fas fa-cash-register"></i></span>
                <p class="number orange">TSh <?= number_format($pharmacy_data['total_otc_amount'] ?? 0, 0) ?></p>
                <p class="label">OTC Sales</p>
                <p class="sub-label"><?= number_format($pharmacy_data['total_otc_count'] ?? 0) ?> transactions</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini blue"><i class="fas fa-prescription-bottle"></i></span>
                <p class="number blue">TSh <?= number_format(($pharmacy_data['total_prescription_amount'] ?? 0) + ($pharmacy_data['total_otc_amount'] ?? 0), 0) ?></p>
                <p class="label">Total Pharmacy Revenue</p>
                <p class="sub-label">Prescription + OTC</p>
            </div>
        </div>
    
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-prescription-bottle"></i> Prescription Sales (<?= count($pharmacy_data['prescription_sales'] ?? []) ?>)
                </h3>
                <button onclick="exportPharmacyPDF()" class="btn btn-pdf btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($pharmacy_data['prescription_sales'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Sale #</th>
                                    <th>Patient</th>
                                    <th>Total</th>
                                    <th>Dispensed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_data['prescription_sales'] as $sale): ?>
                                    <tr>
                                        <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($sale['patient_name'] ?? 'Walk-in') ?></td>
                                        <td class="font-semibold">TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                                        <td><?= htmlspecialchars($sale['dispensed_by_name'] ?? 'N/A') ?></td>
                                        <td class="text-xs"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-prescription-bottle"></i><p>No prescription sales found</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.14s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shopping-cart"></i> OTC Sales (<?= count($pharmacy_data['otc_sales'] ?? []) ?>)
                </h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($pharmacy_data['otc_sales'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Sale #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Net</th>
                                    <th>Sold By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_data['otc_sales'] as $sale): ?>
                                    <tr>
                                        <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                        <td>TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                                        <td class="font-semibold">TSh <?= number_format($sale['net_amount'] ?? 0, 0) ?></td>
                                        <td><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></td>
                                        <td class="text-xs"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>No OTC sales found</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.16s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-pills"></i> Prescription Items
                </h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($pharmacy_data['prescription_items'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Medication</th>
                                    <th>Dosage</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_data['prescription_items'] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?></td>
                                        <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($item['dosage'] ?? '-') ?></td>
                                        <td><?= number_format($item['quantity']) ?></td>
                                        <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                        <td class="font-semibold">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-pills"></i><p>No prescription items found</p></div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <!-- 4. LAB REPORT - FIXED: NO DUPLICATES -->
    <!-- ================================================================ -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'lab'): ?>
    
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini purple"><i class="fas fa-money-bill-wave"></i></span>
                <p class="number purple">TSh <?= number_format($lab_data['total_revenue'] ?? 0, 0) ?></p>
                <p class="label">Total Revenue</p>
                <p class="sub-label">Lab test fees</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini blue"><i class="fas fa-flask"></i></span>
                <p class="number blue"><?= number_format($lab_data['total_tests'] ?? 0) ?></p>
                <p class="label">Total Tests</p>
                <p class="sub-label">All tests performed</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini green"><i class="fas fa-check-circle"></i></span>
                <p class="number green"><?= number_format($lab_data['completed_tests'] ?? 0) ?></p>
                <p class="label">Completed</p>
                <p class="sub-label">Tests finalized</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini orange"><i class="fas fa-clock"></i></span>
                <p class="number orange"><?= number_format(($lab_data['pending_tests'] ?? 0) + ($lab_data['in_progress_tests'] ?? 0)) ?></p>
                <p class="label">In Progress</p>
                <p class="sub-label"><?= number_format($lab_data['pending_tests'] ?? 0) ?> pending · <?= number_format($lab_data['in_progress_tests'] ?? 0) ?> in progress</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini teal"><i class="fas fa-file-medical"></i></span>
                <p class="number teal"><?= number_format($lab_data['tests_with_results'] ?? 0) ?> / <?= number_format($lab_data['tests_without_results'] ?? 0) ?></p>
                <p class="label">Results</p>
                <p class="sub-label"><?= number_format($lab_data['tests_with_results'] ?? 0) ?> with results · <?= number_format($lab_data['tests_without_results'] ?? 0) ?> no results</p>
            </div>
        </div>
        
        <!-- Top Tests -->
        <?php if (!empty($lab_data['top_tests'])): ?>
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i> Most Frequent Tests
                </h3>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach ($lab_data['top_tests'] as $name => $count): ?>
                        <div style="display:flex;justify-content:space-between;padding:6px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);font-size:0.8rem;">
                            <span style="font-weight:600;"><?= htmlspecialchars($name) ?></span>
                            <span style="color:#7C3AED;font-weight:700;"><?= number_format($count) ?> tests</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.14s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-flask"></i> All Lab Tests (<?= count($lab_data['tests'] ?? []) ?>)
                </h3>
                <button onclick="exportLabPDF()" class="btn btn-pdf btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($lab_data['tests'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Visit #</th>
                                    <th>Test Name</th>
                                    <th style="text-align:right;">Price</th>
                                    <th>Result</th>
                                    <th>Doctor</th>
                                    <th>Technician</th>
                                    <th>Status</th>
                                    <th>Bill</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $lt_total = 0;
                                $lt_with_bill = 0;
                                $lt_without_bill = 0;
                                
                                foreach ($lab_data['tests'] as $test):
                                    $lt_total += $test['test_price'] ?? 0;
                                    if (!empty($test['bill_amount']) && $test['bill_amount'] > 0) {
                                        $lt_with_bill++;
                                    } else {
                                        $lt_without_bill++;
                                    }
                                    
                                    // Truncate long results
                                    $result_display = '';
                                    if (!empty($test['results']) && $test['results'] !== 'NULL' && $test['results'] !== '') {
                                        $result_display = htmlspecialchars($test['results']);
                                        if (strlen($result_display) > 50) {
                                            $result_display = substr($result_display, 0, 50) . '...';
                                        }
                                    } else {
                                        $result_display = '<span style="color:var(--text-secondary);">-</span>';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></strong>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_code'] ?? '') ?></div>
                                        </td>
                                        <td style="font-size:0.7rem;"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                                        <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                        <td style="text-align:right;font-weight:600;">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                        <td style="font-size:0.75rem;max-width:120px;word-wrap:break-word;"><?= $result_display ?></td>
                                        <td style="font-size:0.7rem;">Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                        <td style="font-size:0.7rem;"><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>">
                                                <?= ucfirst($test['status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($test['bill_amount']) && $test['bill_amount'] > 0): ?>
                                                <span class="text-green-600">TSh <?= number_format($test['bill_amount'], 0) ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.7rem;"><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--bg-body);font-weight:700;border-top:2px solid var(--primary);">
                                    <td colspan="3" style="text-align:right;">GRAND TOTAL</td>
                                    <td style="text-align:right;">TSh <?= number_format($lt_total, 0) ?></td>
                                    <td colspan="2" style="text-align:center;font-size:0.7rem;">
                                        <?= number_format($lt_with_bill) ?> with bill · <?= number_format($lt_without_bill) ?> no bill
                                    </td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests found for the selected filters</p></div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reports
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            var type = '<?= $report_type ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch + '&type=' + type;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // PDF EXPORT FUNCTIONS
    // ================================================================
    function exportPatientPDF() {
        showToast('PDF Export', 'Generating Patient Report PDF...', 'info');
        var patientId = '<?= $patient_id ?>';
        var branch = '<?= $selected_branch_id ?>';
        if (patientId > 0) {
            window.open('export_patient_pdf.php?patient_id=' + patientId + '&branch=' + branch, '_blank');
        } else {
            showToast('Error', 'Please select a patient first', 'error');
        }
    }
    
    function exportCashierPDF() {
        showToast('PDF Export', 'Generating Cashier Report PDF...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        var dateFrom = '<?= $date_from ?>';
        var dateTo = '<?= $date_to ?>';
        window.open('export_cashier_pdf.php?branch=' + branch + '&date_from=' + dateFrom + '&date_to=' + dateTo, '_blank');
    }
    
    function exportPharmacyPDF() {
        showToast('PDF Export', 'Generating Pharmacy Report PDF...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        var dateFrom = '<?= $date_from ?>';
        var dateTo = '<?= $date_to ?>';
        window.open('export_pharmacy_pdf.php?branch=' + branch + '&date_from=' + dateFrom + '&date_to=' + dateTo, '_blank');
    }
    
    function exportLabPDF() {
        showToast('PDF Export', 'Generating Lab Report PDF...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        var dateFrom = '<?= $date_from ?>';
        var dateTo = '<?= $date_to ?>';
        window.open('export_lab_pdf.php?branch=' + branch + '&date_from=' + dateFrom + '&date_to=' + dateTo, '_blank');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c📊 Braick Dispensary - Reports (IMPROVED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Report Type: <?= ucfirst($report_type) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Lab Report - NO DUPLICATES (GROUP BY lt.id)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Bills show TYPE column (Consultation, Prescription, Lab Test, etc.)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Each bill type has a color-coded badge with icon', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Patient Report shows diagnosis, complaint, symptoms', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Lab Report shows Most Frequent Tests', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Lab Report shows Results count', 'font-size:13px; color:#34D399;');
    console.log('%c📄 PDF Export Available for all reports', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>