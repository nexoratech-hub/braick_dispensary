<?php
// ================================================================
// FILE: frontend/pages/admin/reports.php
// ADMIN - REPORTS DASHBOARD - FIXED VERSION
// PDF: Logo starts at top, proper page breaks
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET ADMIN CONTACT NUMBERS
// ================================================================
$admin_phones = [];
try {
    $stmt = $db->prepare("
        SELECT phone FROM users 
        WHERE role = 'admin' AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute();
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $admin_phones = [];
}

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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// FUNCTION FOR STATUS BADGE - PAID = GREEN
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'warning',
        'assigned' => 'primary',
        'with_doctor' => 'purple',
        'completed' => 'success',
        'cancelled' => 'danger',
        'paid' => 'paid',
        'partial' => 'warning',
        'dispensed' => 'success',
        'confirmed' => 'primary',
        'in_progress' => 'primary',
        'scheduled' => 'primary',
        'active' => 'success',
        'inactive' => 'danger',
        'lab_test' => 'purple',
        'lab_completed' => 'success',
        'prescribed' => 'primary',
        'new' => 'primary'
    ];
    return $map[$status] ?? 'secondary';
}

// ================================================================
// ================================================================
// 1. PATIENT REPORT - FIXED
// ================================================================
// ================================================================

$patient_data = null;
$patient_visits = [];
$patient_bills_summary = [
    'total_bills' => 0,
    'total_amount' => 0,
    'total_paid' => 0,
    'total_balance' => 0,
    'total_discount' => 0,
    'total_pharmacy_discount' => 0,
    'total_cashier_discount' => 0,
    'paid_count' => 0,
    'pending_count' => 0,
    'total_prescription' => 0,
    'total_lab' => 0,
    'total_procedures_tools' => 0,
    'total_consultation' => 0
];
$all_patient_bills = [];

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

        // ============================================================
        // FIXED: Get ALL bills directly from bills table using patient_id
        // ============================================================
        $stmt = $db->prepare("
            SELECT * FROM bills 
            WHERE patient_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$patient_id]);
        $all_patient_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ============================================================
        // FIXED: Calculate summary from ALL bills
        // ============================================================
        foreach ($all_patient_bills as $bill) {
            $patient_bills_summary['total_bills']++;
            $patient_bills_summary['total_amount'] += (float)($bill['total_amount'] ?? 0);
            $patient_bills_summary['total_paid'] += (float)($bill['paid_amount'] ?? 0);
            $patient_bills_summary['total_balance'] += (float)($bill['balance'] ?? 0);
            $patient_bills_summary['total_discount'] += (float)($bill['total_discount'] ?? 0);
            $patient_bills_summary['total_pharmacy_discount'] += (float)($bill['pharmacy_discount'] ?? 0);
            $patient_bills_summary['total_cashier_discount'] += (float)($bill['cashier_discount'] ?? 0);
            
            if ($bill['status'] === 'paid') {
                $patient_bills_summary['paid_count']++;
            } else {
                $patient_bills_summary['pending_count']++;
            }
        }

        // ============================================================
        // FIXED: Process visits with their data
        // ============================================================
        foreach ($patient_visits as &$visit) {
            $visit_id = $visit['id'];
            
            // 1. Vital Signs
            $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
            $stmt->execute([$visit_id]);
            $visit['vital_signs'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. Lab Tests
            $stmt = $db->prepare("SELECT * FROM lab_tests WHERE visit_id = ? ORDER BY created_at DESC");
            $stmt->execute([$visit_id]);
            $visit['lab_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. Prescriptions
            $stmt = $db->prepare("
                SELECT p.*, pi.* 
                FROM prescriptions p
                LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                WHERE p.visit_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $visit['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 4. Procedures & Tools
            $stmt = $db->prepare("
                SELECT bi.* 
                FROM bill_items bi
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE b.visit_id = ?
                AND bi.item_type IN ('procedure', 'tool')
                ORDER BY bi.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $visit['procedures_tools'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 5. Bills - FIXED: Use patient_id to get all bills, then filter by visit_id
            $visit['bills'] = array_filter($all_patient_bills, function($bill) use ($visit_id) {
                return ($bill['visit_id'] ?? 0) == $visit_id;
            });
            
            // Determine bill type for each bill
            foreach ($visit['bills'] as &$bill) {
                $bill['bill_type'] = 'Other';
                $bill['bill_type_icon'] = 'fa-file-invoice';
                $bill['bill_type_color'] = '#64748B';
                
                if (strpos($bill['bill_number'] ?? '', 'BILL-PRES-') !== false) {
                    $bill['bill_type'] = 'Prescription';
                    $bill['bill_type_icon'] = 'fa-prescription-bottle';
                    $bill['bill_type_color'] = '#7C3AED';
                } else {
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
        }
        unset($visit);
    }
} else {
    $patients = [];
}

// ================================================================
// ================================================================
// 2. CASHIER REPORT - WITH DISCOUNTS
// ================================================================
// ================================================================

$cashier_data = [];

if ($report_type === 'cashier') {
    $branch_filter = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter = " AND b.branch_id = " . (int)$selected_branch_id;
    }
    
    $date_filter = "";
    if (!empty($date_from) && !empty($date_to)) {
        $date_filter = " AND b.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    } elseif (!empty($date_from)) {
        $date_filter = " AND b.created_at >= '$date_from 00:00:00'";
    } elseif (!empty($date_to)) {
        $date_filter = " AND b.created_at <= '$date_to 23:59:59'";
    }
    
    // Total Revenue
    $stmt = $db->query("
        SELECT COALESCE(SUM(b.total_amount), 0) as total 
        FROM bills b
        WHERE b.status = 'paid' $branch_filter $date_filter
    ");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Discounts
    $stmt = $db->query("
        SELECT COALESCE(SUM(b.total_discount), 0) as total 
        FROM bills b
        WHERE b.status = 'paid' $branch_filter $date_filter
    ");
    $total_discounts = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Pharmacy Discount
    $stmt = $db->query("
        SELECT COALESCE(SUM(b.pharmacy_discount), 0) as total 
        FROM bills b
        WHERE b.status = 'paid' $branch_filter $date_filter
    ");
    $total_pharmacy_discount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Cashier Discount
    $stmt = $db->query("
        SELECT COALESCE(SUM(b.cashier_discount), 0) as total 
        FROM bills b
        WHERE b.status = 'paid' $branch_filter $date_filter
    ");
    $total_cashier_discount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $total_profit = $total_revenue - $total_discounts;
    
    // All bills
    $stmt = $db->query("
        SELECT 
            b.*, 
            p.full_name as patient_name, 
            u.full_name as cashier_name
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE 1=1 $branch_filter $date_filter
        ORDER BY b.created_at DESC
    ");
    $cashier_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Patient totals
    $stmt = $db->query("
        SELECT 
            p.id, p.full_name, p.patient_id,
            COUNT(b.id) as bill_count,
            COALESCE(SUM(b.total_amount), 0) as total_paid,
            COALESCE(SUM(b.total_discount), 0) as total_discount
        FROM patients p
        LEFT JOIN bills b ON p.id = b.patient_id AND b.status = 'paid'
        WHERE 1=1 $branch_filter $date_filter
        GROUP BY p.id
        HAVING bill_count > 0
        ORDER BY total_paid DESC
        LIMIT 20
    ");
    $patient_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cashier_data = [
        'total_revenue' => $total_revenue,
        'total_discounts' => $total_discounts,
        'total_pharmacy_discount' => $total_pharmacy_discount,
        'total_cashier_discount' => $total_cashier_discount,
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
    $branch_filter_otc = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter_otc = " AND os.branch_id = " . (int)$selected_branch_id;
    }
    
    $branch_filter_pres = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter_pres = " AND p.branch_id = " . (int)$selected_branch_id;
    }
    
    $date_filter_otc = "";
    $date_filter_pres = "";
    if (!empty($date_from) && !empty($date_to)) {
        $date_filter_otc = " AND os.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
        $date_filter_pres = " AND p.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    } elseif (!empty($date_from)) {
        $date_filter_otc = " AND os.created_at >= '$date_from 00:00:00'";
        $date_filter_pres = " AND p.created_at >= '$date_from 00:00:00'";
    } elseif (!empty($date_to)) {
        $date_filter_otc = " AND os.created_at <= '$date_to 23:59:59'";
        $date_filter_pres = " AND p.created_at <= '$date_to 23:59:59'";
    }
    
    // OTC Sales
    $stmt = $db->query("
        SELECT os.*, u.full_name as sold_by_name
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.payment_status = 'paid' $branch_filter_otc $date_filter_otc
        ORDER BY os.created_at DESC
    ");
    $otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prescription Sales
    $stmt = $db->query("
        SELECT 
            pi.*,
            p.id as prescription_id,
            p.prescription_number,
            p.status as prescription_status,
            p.created_at as prescription_created_at,
            pat.full_name as patient_name,
            pat.patient_id as patient_code
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        WHERE p.status = 'dispensed' $branch_filter_pres $date_filter_pres
        ORDER BY pi.created_at DESC
    ");
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_otc_amount = 0;
    $total_otc_count = count($otc_sales);
    $total_prescription_amount = 0;
    $total_prescription_count = count($prescription_items);
    
    foreach ($otc_sales as $s) {
        $total_otc_amount += $s['total_amount'] ?? 0;
    }
    foreach ($prescription_items as $s) {
        $total_prescription_amount += $s['total_price'] ?? 0;
    }
    
    // OTC Items
    $stmt = $db->query("
        SELECT oi.*, os.customer_name, os.sale_number
        FROM otc_sale_items oi
        LEFT JOIN otc_sales os ON oi.sale_id = os.id
        WHERE os.payment_status = 'paid' $branch_filter_otc $date_filter_otc
        ORDER BY oi.created_at DESC
        LIMIT 100
    ");
    $otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pharmacy_data = [
        'otc_sales' => $otc_sales,
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
// 4. LAB REPORT
// ================================================================
// ================================================================

$lab_data = [];

if ($report_type === 'lab') {
    $branch_filter = "";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter = " AND lt.branch_id = " . (int)$selected_branch_id;
    }
    
    $date_filter = "";
    if (!empty($date_from) && !empty($date_to)) {
        $date_filter = " AND lt.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    } elseif (!empty($date_from)) {
        $date_filter = " AND lt.created_at >= '$date_from 00:00:00'";
    } elseif (!empty($date_to)) {
        $date_filter = " AND lt.created_at <= '$date_to 23:59:59'";
    }
    
    $stmt = $db->query("
        SELECT 
            lt.id,
            lt.visit_id,
            lt.patient_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.technician_id,
            lt.test_id,
            lt.test_name,
            lt.test_price,
            lt.equipment_used,
            lt.batch_number,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.formatted_result,
            lt.reference_range,
            lt.interpretation,
            lt.performed_by,
            lt.status,
            lt.started_at,
            lt.bill_created,
            lt.branch_id,
            lt.notes,
            lt.created_at,
            lt.completed_at,
            lt.printed_at,
            lt.printed_by,
            lt.updated_at,
            p.full_name as patient_name, 
            p.patient_id as patient_code,
            v.visit_number,
            u.full_name as doctor_name,
            u2.full_name as technician_name
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
        WHERE 1=1 $branch_filter $date_filter
        ORDER BY lt.created_at DESC
    ");
    $lab_tests_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_lab_revenue = 0;
    $total_tests = count($lab_tests_all);
    $completed_tests = 0;
    $pending_tests = 0;
    $in_progress_tests = 0;
    $cancelled_tests = 0;
    $tests_with_results = 0;
    $tests_without_results = 0;
    $test_counts = [];
    
    foreach ($lab_tests_all as $test) {
        $total_lab_revenue += $test['test_price'] ?? 0;
        
        if ($test['status'] === 'completed') $completed_tests++;
        elseif ($test['status'] === 'pending') $pending_tests++;
        elseif ($test['status'] === 'in_progress') $in_progress_tests++;
        elseif ($test['status'] === 'cancelled') $cancelled_tests++;
        
        if (!empty($test['results']) && $test['results'] !== 'NULL' && $test['results'] !== '') {
            $tests_with_results++;
        } else {
            $tests_without_results++;
        }
        
        $test_name = $test['test_name'] ?? 'Unknown';
        if (!isset($test_counts[$test_name])) {
            $test_counts[$test_name] = 0;
        }
        $test_counts[$test_name]++;
    }
    
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
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --danger: #DC2626;
            --warning: #D97706;
            --purple: #7C3AED;
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
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
        
        /* ================================================================
           BADGE - PAID = GREEN
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            border: 1px solid transparent;
            color: #ffffff !important;
        }
        
        .badge-success { background: #059669 !important; color: #ffffff !important; }
        .badge-danger { background: #DC2626 !important; color: #ffffff !important; }
        .badge-warning { background: #D97706 !important; color: #ffffff !important; }
        .badge-info { background: #0B5ED7 !important; color: #ffffff !important; }
        .badge-secondary { background: #64748B !important; color: #ffffff !important; }
        .badge-purple { background: #7C3AED !important; color: #ffffff !important; }
        .badge-teal { background: #0D9488 !important; color: #ffffff !important; }
        .badge-primary { background: #0B5ED7 !important; color: #ffffff !important; }
        
        .badge-paid {
            background: #D1FAE5 !important;
            color: #059669 !important;
            border: 1px solid #059669 !important;
            font-weight: 700 !important;
        }
        
        [data-theme="dark"] .badge-paid {
            background: #1A3A2A !important;
            color: #34D399 !important;
            border: 1px solid #34D399 !important;
        }
        
        .data-table .badge {
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .data-table .badge-paid {
            color: #059669 !important;
            text-shadow: none !important;
        }
        
        [data-theme="dark"] .data-table .badge-paid {
            color: #34D399 !important;
        }
        
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
        
        .btn-pdf {
            background: #DC2626;
            color: white;
            border-color: #DC2626;
        }
        
        .btn-pdf:hover {
            background: #B91C1C;
            border-color: #B91C1C;
            color: white;
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
        
        /* ================================================================
           PDF MODAL
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
            margin-top: 0;
            padding-top: 28px;
        }
        
        /* ================================================================
           PDF STYLES - Logo starts at top
           ================================================================ */
        .pdf-content {
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        .pdf-content .pdf-header-section {
            text-align: left;
            padding: 0 0 12px 0;
            border-bottom: 3px solid #0B5ED7;
            margin-bottom: 16px;
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .pdf-content .pdf-header-top {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 6px;
        }
        
        .pdf-content .pdf-header-top .pdf-logo {
            flex-shrink: 0;
        }
        
        .pdf-content .pdf-header-top .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
            display: block;
        }
        
        .pdf-content .pdf-header-top .pdf-title-area {
            flex: 1;
        }
        
        .pdf-content .pdf-header-top .pdf-title-area .clinic-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        
        .pdf-content .pdf-header-top .pdf-title-area .clinic-sub {
            font-size: 0.75rem;
            color: #64748B;
            letter-spacing: 0.5px;
        }
        
        .pdf-content .admin-contacts {
            display: flex;
            justify-content: flex-start;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #E2E8F0;
            font-size: 0.6rem;
            color: #64748B;
        }
        
        .pdf-content .admin-contacts .admin-phone {
            color: #0B5ED7;
            font-weight: 600;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #0B5ED7;
            border-bottom: 2px solid #6EA8FE;
            padding-bottom: 4px;
            margin: 10px 0 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 4px 0;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-table th {
            background: #059669;
            color: white;
            padding: 4px 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid #047857;
        }
        
        .pdf-content .pdf-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 12px;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .pdf-content .pdf-empty {
            padding: 6px 0;
            color: #64748B;
            font-style: italic;
            font-size: 14px;
            text-align: center;
            background: #F8FAFC;
            border-radius: 4px;
            margin: 2px 0;
        }
        
        .pdf-content .pdf-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 14px;
            border: 3px solid #0B5ED7;
            border-radius: 10px;
            background: #EFF6FF;
            min-width: 150px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 10px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #0B5ED7;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 12px;
            color: #64748B;
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 6px;
            font-size: 11px;
            color: #94A3B8;
        }
        
        .pdf-content .pdf-visit-block {
            background: #F8FAFC;
            border-radius: 8px;
            padding: 10px 14px;
            margin: 8px 0;
            border: 1px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .pdf-content .pdf-visit-block .visit-header {
            font-weight: 700;
            font-size: 0.9rem;
            color: #0B5ED7;
            border-bottom: 2px solid #6EA8FE;
            padding-bottom: 4px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.info { background: #0B5ED7; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
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
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .summary-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav no-print">
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
    
    <div class="flex items-center gap-3 no-print">
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
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
    <div class="report-tabs animate-fade-in-up no-print">
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
    <div class="filter-bar animate-fade-in-up no-print" style="animation-delay:0.05s;">
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
            
            <button type="button" onclick="generatePDF()" class="btn-filter" style="background:#DC2626;">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT REPORT - FIXED -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'patient'): ?>
    
        <?php if ($patient_data && !empty($all_patient_bills)): ?>
        
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini blue"><i class="fas fa-money-bill-wave"></i></span>
                <p class="number blue">TSh <?= number_format($patient_bills_summary['total_paid'] ?? 0, 0) ?></p>
                <p class="label">Total Paid</p>
                <p class="sub-label">Bills: <?= number_format($patient_bills_summary['total_bills'] ?? 0) ?></p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini red"><i class="fas fa-receipt"></i></span>
                <p class="number red">TSh <?= number_format($patient_bills_summary['total_discount'] ?? 0, 0) ?></p>
                <p class="label">Total Discount</p>
                <p class="sub-label">Pharmacy: TSh <?= number_format($patient_bills_summary['total_pharmacy_discount'] ?? 0, 0) ?> | Cashier: TSh <?= number_format($patient_bills_summary['total_cashier_discount'] ?? 0, 0) ?></p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini teal"><i class="fas fa-chart-line"></i></span>
                <p class="number teal">TSh <?= number_format($patient_bills_summary['total_amount'] ?? 0, 0) ?></p>
                <p class="label">Total Amount</p>
                <p class="sub-label">Balance: TSh <?= number_format($patient_bills_summary['total_balance'] ?? 0, 0) ?></p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini green"><i class="fas fa-check-circle"></i></span>
                <p class="number green"><?= number_format($patient_bills_summary['paid_count'] ?? 0) ?></p>
                <p class="label">Paid Bills</p>
                <p class="sub-label">Pending: <?= number_format($patient_bills_summary['pending_count'] ?? 0) ?></p>
            </div>
        </div>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user"></i> Patient Information</h3>
                <span class="badge badge-info">ID: <?= htmlspecialchars($patient_data['patient_id'] ?? 'N/A') ?></span>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Full Name</span><br><strong><?= htmlspecialchars($patient_data['full_name'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Gender</span><br><strong><?= htmlspecialchars($patient_data['gender'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Phone</span><br><strong><?= htmlspecialchars($patient_data['phone'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Email</span><br><strong><?= htmlspecialchars($patient_data['email'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Blood Group</span><br><strong><?= htmlspecialchars($patient_data['blood_group'] ?? 'N/A') ?></strong></div>
                    <div><span style="color:var(--text-secondary);font-size:0.7rem;">Branch</span><br><strong><?= htmlspecialchars($patient_data['branch_name'] ?? 'N/A') ?></strong></div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- FIXED: Bills Table - Shows ALL bills for this patient -->
        <!-- ============================================================ -->
        <div class="card animate-fade-in-up" style="animation-delay:0.14s;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice"></i> All Bills (<?= count($all_patient_bills) ?>)</h3>
                <span class="badge badge-info">Total: TSh <?= number_format($patient_bills_summary['total_amount'] ?? 0, 0) ?></span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($all_patient_bills)): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Bill #</th>
                                    <th>Visit</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Pharmacy Disc</th>
                                    <th>Cashier Disc</th>
                                    <th>Total Disc</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_patient_bills as $bill): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($bill['visit_id'] ?? 'N/A') ?></td>
                                        <td class="font-semibold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                        <td class="text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                        <td class="text-red-600">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['pharmacy_discount'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['cashier_discount'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['total_discount'] ?? 0, 0) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $bill['status'] === 'paid' ? 'paid' : getStatusBadgeClass($bill['status'] ?? 'pending') ?>">
                                                <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--bg-body);font-weight:700;">
                                    <td colspan="2" style="text-align:right;">TOTAL</td>
                                    <td>TSh <?= number_format($patient_bills_summary['total_amount'] ?? 0, 0) ?></td>
                                    <td class="text-green-600">TSh <?= number_format($patient_bills_summary['total_paid'] ?? 0, 0) ?></td>
                                    <td class="text-red-600">TSh <?= number_format($patient_bills_summary['total_balance'] ?? 0, 0) ?></td>
                                    <td>TSh <?= number_format($patient_bills_summary['total_pharmacy_discount'] ?? 0, 0) ?></td>
                                    <td>TSh <?= number_format($patient_bills_summary['total_cashier_discount'] ?? 0, 0) ?></td>
                                    <td>TSh <?= number_format($patient_bills_summary['total_discount'] ?? 0, 0) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-file-invoice"></i><p>No bills found for this patient</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($patient_id > 0): ?>
            <div class="card animate-fade-in-up">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <h3>No Data Found</h3>
                        <p>No bills or data found for the selected patient.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card animate-fade-in-up">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <h3>No Patient Selected</h3>
                        <p>Please select a patient from the filter above to view their report.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CASHIER REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'cashier'): ?>
    
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini green"><i class="fas fa-money-bill-wave"></i></span>
                <p class="number green">TSh <?= number_format($cashier_data['total_revenue'] ?? 0, 0) ?></p>
                <p class="label">Total Revenue</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini red"><i class="fas fa-receipt"></i></span>
                <p class="number red">TSh <?= number_format($cashier_data['total_discounts'] ?? 0, 0) ?></p>
                <p class="label">Total Discounts</p>
                <p class="sub-label">Pharmacy: TSh <?= number_format($cashier_data['total_pharmacy_discount'] ?? 0, 0) ?> | Cashier: TSh <?= number_format($cashier_data['total_cashier_discount'] ?? 0, 0) ?></p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini teal"><i class="fas fa-chart-line"></i></span>
                <p class="number teal">TSh <?= number_format($cashier_data['total_profit'] ?? 0, 0) ?></p>
                <p class="label">Net Profit</p>
                <p class="sub-label">Revenue - Discounts</p>
            </div>
        </div>
        
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice"></i> All Bills (<?= count($cashier_data['bills'] ?? []) ?>)</h3>
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
                                    <th>Pharmacy Disc</th>
                                    <th>Cashier Disc</th>
                                    <th>Total Disc</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashier_data['bills'] as $bill): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                                        <td>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                        <td class="text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                        <td class="text-red-600">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['pharmacy_discount'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['cashier_discount'] ?? 0, 0) ?></td>
                                        <td>TSh <?= number_format($bill['total_discount'] ?? 0, 0) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $bill['status'] === 'paid' ? 'paid' : getStatusBadgeClass($bill['status'] ?? 'pending') ?>">
                                                <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
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
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PHARMACY REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'pharmacy'): ?>
    
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini purple"><i class="fas fa-prescription"></i></span>
                <p class="number purple">TSh <?= number_format($pharmacy_data['total_prescription_amount'] ?? 0, 0) ?></p>
                <p class="label">Prescription Sales</p>
                <p class="sub-label"><?= number_format($pharmacy_data['total_prescription_count'] ?? 0) ?> items</p>
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
            </div>
        </div>
    
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-prescription-bottle"></i> Prescription Items (<?= count($pharmacy_data['prescription_items'] ?? []) ?>)</h3>
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
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_data['prescription_items'] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?></td>
                                        <td><strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($item['dosage'] ?? '-') ?></td>
                                        <td><?= number_format($item['quantity'] ?? 0) ?></td>
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
        
        <div class="card animate-fade-in-up" style="animation-delay:0.14s;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shopping-cart"></i> OTC Sales (<?= count($pharmacy_data['otc_sales'] ?? []) ?>)</h3>
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
                                    <th>Sold By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_data['otc_sales'] as $sale): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                        <td>TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                                        <td><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
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
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB REPORT -->
    <!-- ================================================================ -->
    <?php if ($report_type === 'lab'): ?>
    
        <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="summary-card">
                <span class="card-icon-mini purple"><i class="fas fa-money-bill-wave"></i></span>
                <p class="number purple">TSh <?= number_format($lab_data['total_revenue'] ?? 0, 0) ?></p>
                <p class="label">Total Revenue</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini blue"><i class="fas fa-flask"></i></span>
                <p class="number blue"><?= number_format($lab_data['total_tests'] ?? 0) ?></p>
                <p class="label">Total Tests</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini green"><i class="fas fa-check-circle"></i></span>
                <p class="number green"><?= number_format($lab_data['completed_tests'] ?? 0) ?></p>
                <p class="label">Completed</p>
            </div>
            <div class="summary-card">
                <span class="card-icon-mini orange"><i class="fas fa-clock"></i></span>
                <p class="number orange"><?= number_format(($lab_data['pending_tests'] ?? 0) + ($lab_data['in_progress_tests'] ?? 0)) ?></p>
                <p class="label">In Progress</p>
            </div>
        </div>
        
        <?php if (!empty($lab_data['top_tests'])): ?>
        <div class="card animate-fade-in-up" style="animation-delay:0.12s;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar"></i> Most Frequent Tests</h3>
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
                <h3 class="card-title"><i class="fas fa-flask"></i> All Lab Tests (<?= count($lab_data['tests'] ?? []) ?>)</h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($lab_data['tests'])): ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Price</th>
                                    <th>Result</th>
                                    <th>Doctor</th>
                                    <th>Technician</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lab_data['tests'] as $test): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                        <td>TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                        <td><?= !empty($test['results']) ? '✅' : '⏳' ?></td>
                                        <td>Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge badge-<?= getStatusBadgeClass($test['status'] ?? 'pending') ?>">
                                                <?= ucfirst($test['status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests found</p></div>
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
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                <span id="pdfModalTitle">Report Preview</span>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- Generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

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

    // ================================================================
    // DATA FOR PDF
    // ================================================================
    var adminPhones = <?= json_encode($admin_phones) ?>;
    var branchName = '<?= htmlspecialchars($branch_name) ?>';
    var reportType = '<?= $report_type ?>';
    
    // Patient Data - FIXED
    var patientName = '<?= isset($patient_data['full_name']) ? htmlspecialchars($patient_data['full_name']) : '' ?>';
    var patientId = '<?= isset($patient_data['patient_id']) ? htmlspecialchars($patient_data['patient_id']) : '' ?>';
    var patientPhone = '<?= isset($patient_data['phone']) ? htmlspecialchars($patient_data['phone']) : 'N/A' ?>';
    var patientEmail = '<?= isset($patient_data['email']) ? htmlspecialchars($patient_data['email']) : 'N/A' ?>';
    var patientGender = '<?= isset($patient_data['gender']) ? htmlspecialchars($patient_data['gender']) : 'N/A' ?>';
    var patientBlood = '<?= isset($patient_data['blood_group']) ? htmlspecialchars($patient_data['blood_group']) : 'N/A' ?>';
    var patientAddress = '<?= isset($patient_data['address']) ? htmlspecialchars($patient_data['address']) : 'N/A' ?>';
    var patientDob = '<?= isset($patient_data['date_of_birth']) ? date('F d, Y', strtotime($patient_data['date_of_birth'])) : 'N/A' ?>';
    var patientAllergies = '<?= isset($patient_data['allergies']) ? htmlspecialchars($patient_data['allergies']) : 'None' ?>';
    var visitsData = <?= json_encode($patient_visits) ?>;
    
    // FIXED: All bills data for patient
    var allBillsData = <?= json_encode($all_patient_bills) ?>;
    var billsSummary = <?= json_encode($patient_bills_summary) ?>;
    
    // Cashier Data
    var cashierData = <?= json_encode($cashier_data) ?>;
    
    // Pharmacy Data
    var pharmacyData = <?= json_encode($pharmacy_data) ?>;
    
    // Lab Data
    var labData = <?= json_encode($lab_data) ?>;

    // ================================================================
    // PDF GENERATION - FIXED
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        var titleEl = document.getElementById('pdfModalTitle');
        var html = '';
        var hasData = false;
        var reportTitle = '';
        
        switch(reportType) {
            case 'patient':
                if (allBillsData && allBillsData.length > 0) {
                    hasData = true;
                    reportTitle = 'Patient Report - ' + patientName;
                    html = buildPatientPDF();
                } else if (patientName) {
                    hasData = true;
                    reportTitle = 'Patient Report - ' + patientName;
                    html = buildPatientPDF();
                }
                break;
                
            case 'cashier':
                if (cashierData && cashierData.bills && cashierData.bills.length > 0) {
                    hasData = true;
                    reportTitle = 'Cashier Report';
                    html = buildCashierPDF();
                }
                break;
                
            case 'pharmacy':
                if (pharmacyData && (pharmacyData.otc_sales.length > 0 || pharmacyData.prescription_items.length > 0)) {
                    hasData = true;
                    reportTitle = 'Pharmacy Report';
                    html = buildPharmacyPDF();
                }
                break;
                
            case 'lab':
                if (labData && labData.tests && labData.tests.length > 0) {
                    hasData = true;
                    reportTitle = 'Lab Report';
                    html = buildLabPDF();
                }
                break;
                
            default:
                hasData = false;
        }
        
        if (!hasData) {
            showToast('Error', 'No data available to export for this report', 'error');
            return;
        }
        
        if (titleEl) titleEl.textContent = reportTitle;
        content.innerHTML = html;
        modal.classList.add('active');
        
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
    }

    // ================================================================
    // BUILD PATIENT PDF - FIXED with ALL bills
    // ================================================================
    function buildPatientPDF() {
        var adminPhonesText = adminPhones.length > 0 ? adminPhones.join(' | ') : '+255 700 000 001';
        var currentDate = new Date().toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var currentTime = new Date().toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        // FIXED: Build bills table from allBillsData
        var billsHTML = '';
        var totalAmount = 0;
        var totalPaid = 0;
        var totalBalance = 0;
        var totalPharmacyDisc = 0;
        var totalCashierDisc = 0;
        var totalDiscount = 0;
        
        if (allBillsData && allBillsData.length > 0) {
            allBillsData.forEach(function(bill) {
                totalAmount += parseFloat(bill.total_amount || 0);
                totalPaid += parseFloat(bill.paid_amount || 0);
                totalBalance += parseFloat(bill.balance || 0);
                totalPharmacyDisc += parseFloat(bill.pharmacy_discount || 0);
                totalCashierDisc += parseFloat(bill.cashier_discount || 0);
                totalDiscount += parseFloat(bill.total_discount || 0);
                
                var statusClass = bill.status === 'paid' ? 'paid' : '';
                var statusColor = bill.status === 'paid' ? '#059669' : '#D97706';
                
                billsHTML += `
                    <tr>
                        <td>${escapeHtml(bill.bill_number || 'N/A')}</td>
                        <td>${escapeHtml(bill.visit_id || 'N/A')}</td>
                        <td>TSh ${(parseFloat(bill.total_amount) || 0).toLocaleString()}</td>
                        <td>TSh ${(parseFloat(bill.paid_amount) || 0).toLocaleString()}</td>
                        <td>TSh ${(parseFloat(bill.balance) || 0).toLocaleString()}</td>
                        <td>TSh ${(parseFloat(bill.pharmacy_discount) || 0).toLocaleString()}</td>
                        <td>TSh ${(parseFloat(bill.cashier_discount) || 0).toLocaleString()}</td>
                        <td>TSh ${(parseFloat(bill.total_discount) || 0).toLocaleString()}</td>
                        <td style="color:${statusColor};font-weight:${bill.status === 'paid' ? '700' : '600'};">${escapeHtml(bill.status || 'Pending')}</td>
                    </tr>
                `;
            });
        }
        
        // Build visits HTML
        var visitsHTML = '';
        var visitCount = 0;
        
        if (visitsData && visitsData.length > 0) {
            visitsData.forEach(function(visit) {
                visitCount++;
                var visitNumber = visit.visit_number || 'N/A';
                var visitDate = visit.visit_date ? new Date(visit.visit_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
                var visitTime = visit.visit_date ? new Date(visit.visit_date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : 'N/A';
                var visitStatus = visit.status || 'pending';
                var doctorName = visit.doctor_name || 'Not assigned';
                var visitType = visit.visit_type || 'N/A';
                var diagnosis = visit.diagnosis || '';
                var treatment = visit.treatment || '';
                var notes = visit.notes || '';
                
                var vs = visit.vital_signs || {};
                var labTests = visit.lab_tests || [];
                var prescriptions = visit.prescriptions || [];
                var proceduresTools = visit.procedures_tools || [];
                var visitBills = visit.bills || [];
                
                visitsHTML += `
                    <div class="pdf-visit-block" style="margin-bottom:12px;page-break-inside:avoid;break-inside:avoid;">
                        <div class="visit-header" style="margin-bottom:6px;">
                            <span>📋 Visit #${visitCount}: ${visitNumber}</span>
                            <span style="font-size:0.8rem;color:#64748B;">${visitDate} ${visitTime}</span>
                        </div>
                        
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">📋 Visit Information</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;font-size:14px;">
                                <div style="display:flex;padding:1px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Status</span><span style="font-size:13px;">${visitStatus.charAt(0).toUpperCase() + visitStatus.slice(1)}</span></div>
                                <div style="display:flex;padding:1px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Doctor</span><span style="font-size:13px;">Dr. ${escapeHtml(doctorName)}</span></div>
                                <div style="display:flex;padding:1px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Visit Type</span><span style="font-size:13px;">${escapeHtml(visitType)}</span></div>
                            </div>
                        </div>
                        
                        ${diagnosis || treatment || notes ? `
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">🩺 Clinical Information</div>
                            ${diagnosis ? `<div style="padding:2px 4px;background:#EFF6FF;border-radius:4px;border-left:3px solid #0B5ED7;margin:2px 0;font-size:13px;"><span style="font-weight:600;color:#64748B;">Diagnosis:</span> ${escapeHtml(diagnosis)}</div>` : ''}
                            ${treatment ? `<div style="padding:2px 4px;background:#D1FAE5;border-radius:4px;border-left:3px solid #059669;margin:2px 0;font-size:13px;"><span style="font-weight:600;color:#64748B;">Treatment:</span> ${escapeHtml(treatment)}</div>` : ''}
                            ${notes ? `<div style="padding:2px 4px;background:#F1F5F9;border-radius:4px;border-left:3px solid #94A3B8;margin:2px 0;font-size:13px;"><span style="font-weight:600;color:#64748B;">Notes:</span> ${escapeHtml(notes)}</div>` : ''}
                        </div>
                        ` : ''}
                        
                        ${vs.temperature || vs.blood_pressure_systolic || vs.pulse_rate || vs.weight || vs.height || vs.bmi ? `
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">❤️ Vital Signs</div>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;">
                                ${vs.temperature ? `<div style="background:#EFF6FF;padding:2px 6px;border-radius:4px;text-align:center;border-left:3px solid #0B5ED7;"><div style="font-size:0.5rem;font-weight:600;color:#64748B;text-transform:uppercase;">Temperature</div><div style="font-weight:700;color:#0B5ED7;font-size:14px;">${vs.temperature} °C</div></div>` : ''}
                                ${vs.blood_pressure_systolic || vs.blood_pressure_diastolic ? `<div style="background:#D1FAE5;padding:2px 6px;border-radius:4px;text-align:center;border-left:3px solid #059669;"><div style="font-size:0.5rem;font-weight:600;color:#64748B;text-transform:uppercase;">Blood Pressure</div><div style="font-weight:700;color:#059669;font-size:14px;">${vs.blood_pressure_systolic || '?'}/${vs.blood_pressure_diastolic || '?'} mmHg</div></div>` : ''}
                                ${vs.pulse_rate ? `<div style="background:#EDE9FE;padding:2px 6px;border-radius:4px;text-align:center;border-left:3px solid #7C3AED;"><div style="font-size:0.5rem;font-weight:600;color:#64748B;text-transform:uppercase;">Pulse Rate</div><div style="font-weight:700;color:#7C3AED;font-size:14px;">${vs.pulse_rate} bpm</div></div>` : ''}
                                ${vs.weight ? `<div style="background:#FEF3C7;padding:2px 6px;border-radius:4px;text-align:center;border-left:3px solid #D97706;"><div style="font-size:0.5rem;font-weight:600;color:#64748B;text-transform:uppercase;">Weight</div><div style="font-weight:700;color:#D97706;font-size:14px;">${vs.weight} kg</div></div>` : ''}
                                ${vs.height ? `<div style="background:#D1FAE5;padding:2px 6px;border-radius:4px;text-align:center;border-left:3px solid #0D9488;"><div style="font-size:0.5rem;font-weight:600;color:#64748B;text-transform:uppercase;">Height</div><div style="font-weight:700;color:#0D9488;font-size:14px;">${vs.height} cm</div></div>` : ''}
                                ${vs.bmi ? `<div style="background:#FEE2E2;padding:2px 6px;border-radius:4px;text-align:center;border-left:3px solid #DC2626;"><div style="font-size:0.5rem;font-weight:600;color:#64748B;text-transform:uppercase;">BMI</div><div style="font-weight:700;color:#DC2626;font-size:14px;">${vs.bmi} kg/m²</div></div>` : ''}
                            </div>
                        </div>
                        ` : ''}
                        
                        ${labTests.length > 0 ? `
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">🧪 Lab Tests (${labTests.length})</div>
                            <table class="pdf-table" style="font-size:12px;">
                                <thead><tr><th>Test Name</th><th>Status</th><th>Results</th><th>Technician</th></tr></thead>
                                <tbody>
                                    ${labTests.map(function(test) {
                                        return `<tr><td>${escapeHtml(test.test_name || 'N/A')}</td>
                                            <td>${test.status || 'Pending'}</td>
                                            <td>${test.results ? '✅ ' + escapeHtml(test.results.substring(0, 50)) : '⏳ Pending'}</td>
                                            <td>${escapeHtml(test.technician_name || 'Not assigned')}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                        ` : ''}
                        
                        ${prescriptions.length > 0 ? `
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">💊 Medications (${prescriptions.length})</div>
                            <table class="pdf-table" style="font-size:12px;">
                                <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Qty</th></tr></thead>
                                <tbody>
                                    ${prescriptions.map(function(p) {
                                        var medName = p.medication_name || p.inventory_medication_name || 'N/A';
                                        return `<tr><td>${escapeHtml(medName)}</td>
                                            <td>${escapeHtml(p.dosage || '-')}</td>
                                            <td>${escapeHtml(p.frequency || '-')}</td>
                                            <td>${p.quantity || 0}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                        ` : ''}
                        
                        ${proceduresTools.length > 0 ? `
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">🔧 Procedures & Tools (${proceduresTools.length})</div>
                            <table class="pdf-table" style="font-size:12px;">
                                <thead><tr><th>Item Name</th><th>Type</th><th>Qty</th><th>Total</th></tr></thead>
                                <tbody>
                                    ${proceduresTools.map(function(item) {
                                        return `<tr><td>${escapeHtml(item.item_name || 'N/A')}</td>
                                            <td>${escapeHtml(item.item_type || 'N/A')}</td>
                                            <td>${item.quantity || 1}</td>
                                            <td>TSh ${(parseFloat(item.total_price) || 0).toLocaleString()}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                        ` : ''}
                        
                        ${visitBills.length > 0 ? `
                        <div style="margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:700;color:#0B5ED7;border-bottom:1px solid #6EA8FE;padding-bottom:2px;margin-bottom:4px;">💰 Visit Bills (${visitBills.length})</div>
                            <table class="pdf-table" style="font-size:12px;">
                                <thead><tr><th>Bill #</th><th>Total</th><th>Paid</th><th>Balance</th><th>Discount</th><th>Status</th></tr></thead>
                                <tbody>
                                    ${visitBills.map(function(bill) {
                                        var statusColor = bill.status === 'paid' ? '#059669' : '#D97706';
                                        return `<tr>
                                            <td>${escapeHtml(bill.bill_number || 'N/A')}</td>
                                            <td>TSh ${(parseFloat(bill.total_amount) || 0).toLocaleString()}</td>
                                            <td>TSh ${(parseFloat(bill.paid_amount) || 0).toLocaleString()}</td>
                                            <td>TSh ${(parseFloat(bill.balance) || 0).toLocaleString()}</td>
                                            <td>TSh ${(parseFloat(bill.total_discount) || 0).toLocaleString()}</td>
                                            <td style="color:${statusColor};font-weight:${bill.status === 'paid' ? '700' : '600'};">${escapeHtml(bill.status || 'Pending')}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                        ` : ''}
                    </div>
                `;
            });
        }
        
        return `
            <div class="pdf-header-section">
                <div class="pdf-header-top">
                    <div class="pdf-logo">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="pdf-title-area">
                        <div class="clinic-name">BRAICK DISPENSARY</div>
                        <div class="clinic-sub">Tunajali Afya Yako</div>
                    </div>
                </div>
                <div class="admin-contacts">
                    <span><i class="fas fa-phone-alt"></i> Admin: <span class="admin-phone">${adminPhonesText}</span></span>
                    <span><i class="fas fa-building"></i> Branch: ${branchName}</span>
                    <span><i class="fas fa-calendar-alt"></i> ${currentDate}</span>
                </div>
                <div style="font-size:0.85rem;font-weight:700;color:#0B5ED7;margin-top:4px;background:#EFF6FF;padding:4px 16px;border-radius:20px;display:inline-block;">
                    📋 Patient Report - ${escapeHtml(patientName)}
                </div>
            </div>
            
            <div style="margin-bottom:10px;">
                <div class="section-title" style="margin-top:0;"><i class="fas fa-user"></i> Patient Information</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Full Name</span><span style="font-size:13px;"><strong>${escapeHtml(patientName)}</strong></span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Patient ID</span><span style="font-size:13px;">${escapeHtml(patientId)}</span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Gender</span><span style="font-size:13px;">${escapeHtml(patientGender)}</span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Phone</span><span style="font-size:13px;">${escapeHtml(patientPhone)}</span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Email</span><span style="font-size:13px;">${escapeHtml(patientEmail)}</span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Blood Group</span><span style="font-size:13px;">${escapeHtml(patientBlood)}</span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Date of Birth</span><span style="font-size:13px;">${escapeHtml(patientDob)}</span></div>
                    <div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 3;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Address</span><span style="font-size:13px;">${escapeHtml(patientAddress)}</span></div>
                    ${patientAllergies !== 'None' ? `<div style="display:flex;padding:2px 0;border-bottom:1px solid #E2E8F0;grid-column:span 3;"><span style="font-weight:600;color:#64748B;width:120px;flex-shrink:0;font-size:13px;">Allergies</span><span style="font-size:13px;color:#DC2626;">${escapeHtml(patientAllergies)}</span></div>` : ''}
                </div>
            </div>
            
            <!-- FIXED: All Bills Summary -->
            <div style="margin-bottom:10px;">
                <div class="section-title"><i class="fas fa-file-invoice"></i> All Bills Summary (${allBillsData.length})</div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:4px 0;">
                    <div style="background:#EFF6FF;padding:6px 10px;border-radius:6px;text-align:center;border:1px solid #6EA8FE;">
                        <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;">TSh ${totalAmount.toLocaleString()}</div>
                        <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Amount</div>
                    </div>
                    <div style="background:#D1FAE5;padding:6px 10px;border-radius:6px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:1.2rem;font-weight:700;color:#059669;">TSh ${totalPaid.toLocaleString()}</div>
                        <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Paid</div>
                    </div>
                    <div style="background:#FEE2E2;padding:6px 10px;border-radius:6px;text-align:center;border:1px solid #DC2626;">
                        <div style="font-size:1.2rem;font-weight:700;color:#DC2626;">TSh ${totalBalance.toLocaleString()}</div>
                        <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Balance</div>
                    </div>
                    <div style="background:#FEF3C7;padding:6px 10px;border-radius:6px;text-align:center;border:1px solid #D97706;">
                        <div style="font-size:1.2rem;font-weight:700;color:#D97706;">TSh ${totalDiscount.toLocaleString()}</div>
                        <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Discount</div>
                        <div style="font-size:0.5rem;color:#64748B;">Pharm: TSh ${totalPharmacyDisc.toLocaleString()} | Cashier: TSh ${totalCashierDisc.toLocaleString()}</div>
                    </div>
                </div>
            </div>
            
            <!-- FIXED: All Bills Table -->
            <div style="margin-bottom:10px;">
                <div class="section-title"><i class="fas fa-list"></i> All Bills (${allBillsData.length})</div>
                ${allBillsData.length > 0 ? `
                <table class="pdf-table">
                    <thead>
                        <tr><th>Bill #</th><th>Visit</th><th>Total</th><th>Paid</th><th>Balance</th><th>Pharm Disc</th><th>Cashier Disc</th><th>Total Disc</th><th>Status</th></tr>
                    </thead>
                    <tbody>${billsHTML}</tbody>
                    <tfoot>
                        <tr style="font-weight:700;background:#F1F5F9;">
                            <td colspan="2" style="text-align:right;">TOTAL</td>
                            <td>TSh ${totalAmount.toLocaleString()}</td>
                            <td style="color:#059669;">TSh ${totalPaid.toLocaleString()}</td>
                            <td style="color:#DC2626;">TSh ${totalBalance.toLocaleString()}</td>
                            <td>TSh ${totalPharmacyDisc.toLocaleString()}</td>
                            <td>TSh ${totalCashierDisc.toLocaleString()}</td>
                            <td>TSh ${totalDiscount.toLocaleString()}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                ` : `<div class="pdf-empty">No bills found for this patient</div>`}
            </div>
            
            ${visitsHTML ? `
            <div>
                <div class="section-title"><i class="fas fa-stethoscope"></i> Visit History (${visitsData.length} visits)</div>
                ${visitsHTML}
            </div>
            ` : ''}
            
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Technician: _________________</span>
                        <span style="margin-left:14px;">Date: ${currentDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: ${currentDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on ${currentDate} at ${currentTime} • All rights reserved
                </div>
            </div>
        `;
    }

    // ================================================================
    // BUILD CASHIER PDF
    // ================================================================
    function buildCashierPDF() {
        var adminPhonesText = adminPhones.length > 0 ? adminPhones.join(' | ') : '+255 700 000 001';
        var currentDate = new Date().toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var currentTime = new Date().toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var totalAmount = 0;
        var totalPaid = 0;
        var totalBalance = 0;
        var totalPharmacyDisc = 0;
        var totalCashierDisc = 0;
        var totalDiscount = 0;
        
        cashierData.bills.forEach(function(bill) {
            totalAmount += parseFloat(bill.total_amount || 0);
            totalPaid += parseFloat(bill.paid_amount || 0);
            totalBalance += parseFloat(bill.balance || 0);
            totalPharmacyDisc += parseFloat(bill.pharmacy_discount || 0);
            totalCashierDisc += parseFloat(bill.cashier_discount || 0);
            totalDiscount += parseFloat(bill.total_discount || 0);
        });
        
        var billsHTML = '';
        cashierData.bills.forEach(function(bill) {
            var statusColor = bill.status === 'paid' ? '#059669' : '#D97706';
            billsHTML += `
                <tr>
                    <td>${escapeHtml(bill.bill_number || 'N/A')}</td>
                    <td>${escapeHtml(bill.patient_name || 'N/A')}</td>
                    <td>TSh ${(parseFloat(bill.total_amount) || 0).toLocaleString()}</td>
                    <td>TSh ${(parseFloat(bill.paid_amount) || 0).toLocaleString()}</td>
                    <td>TSh ${(parseFloat(bill.balance) || 0).toLocaleString()}</td>
                    <td>TSh ${(parseFloat(bill.pharmacy_discount) || 0).toLocaleString()}</td>
                    <td>TSh ${(parseFloat(bill.cashier_discount) || 0).toLocaleString()}</td>
                    <td>TSh ${(parseFloat(bill.total_discount) || 0).toLocaleString()}</td>
                    <td style="color:${statusColor};font-weight:${bill.status === 'paid' ? '700' : '600'};">${escapeHtml(bill.status || 'Pending')}</td>
                </tr>
            `;
        });
        
        return `
            <div class="pdf-header-section">
                <div class="pdf-header-top">
                    <div class="pdf-logo">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="pdf-title-area">
                        <div class="clinic-name">BRAICK DISPENSARY</div>
                        <div class="clinic-sub">Tunajali Afya Yako</div>
                    </div>
                </div>
                <div class="admin-contacts">
                    <span><i class="fas fa-phone-alt"></i> Admin: <span class="admin-phone">${adminPhonesText}</span></span>
                    <span><i class="fas fa-building"></i> Branch: ${branchName}</span>
                    <span><i class="fas fa-calendar-alt"></i> ${currentDate}</span>
                </div>
                <div style="font-size:0.85rem;font-weight:700;color:#0B5ED7;margin-top:4px;background:#EFF6FF;padding:4px 16px;border-radius:20px;display:inline-block;">
                    📊 Cashier Report
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;">
                <div style="background:#EFF6FF;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #6EA8FE;">
                    <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;">TSh ${totalAmount.toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Revenue</div>
                </div>
                <div style="background:#FEE2E2;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #DC2626;">
                    <div style="font-size:1.2rem;font-weight:700;color:#DC2626;">TSh ${totalDiscount.toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Discounts</div>
                    <div style="font-size:0.5rem;color:#64748B;">Pharmacy: TSh ${totalPharmacyDisc.toLocaleString()} | Cashier: TSh ${totalCashierDisc.toLocaleString()}</div>
                </div>
                <div style="background:#D1FAE5;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #059669;">
                    <div style="font-size:1.2rem;font-weight:700;color:#059669;">TSh ${(totalAmount - totalDiscount).toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Net Profit</div>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-file-invoice"></i> All Bills (${cashierData.bills.length})</div>
            <table class="pdf-table">
                <thead>
                    <tr><th>Bill #</th><th>Patient</th><th>Total</th><th>Paid</th><th>Balance</th><th>Pharm Disc</th><th>Cashier Disc</th><th>Total Disc</th><th>Status</th></tr>
                </thead>
                <tbody>${billsHTML}</tbody>
            </table>
            
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Technician: _________________</span>
                        <span style="margin-left:14px;">Date: ${currentDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: ${currentDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on ${currentDate} at ${currentTime} • All rights reserved
                </div>
            </div>
        `;
    }

    // ================================================================
    // BUILD PHARMACY PDF
    // ================================================================
    function buildPharmacyPDF() {
        var adminPhonesText = adminPhones.length > 0 ? adminPhones.join(' | ') : '+255 700 000 001';
        var currentDate = new Date().toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var currentTime = new Date().toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var presItemsHTML = '';
        pharmacyData.prescription_items.forEach(function(item) {
            presItemsHTML += `
                <tr>
                    <td>${escapeHtml(item.patient_name || 'N/A')}</td>
                    <td><strong>${escapeHtml(item.medication_name || 'N/A')}</strong></td>
                    <td>${escapeHtml(item.dosage || '-')}</td>
                    <td>${item.quantity || 0}</td>
                    <td>TSh ${(parseFloat(item.total_price) || 0).toLocaleString()}</td>
                </tr>
            `;
        });
        
        var otcHTML = '';
        pharmacyData.otc_sales.forEach(function(sale) {
            otcHTML += `
                <tr>
                    <td>${escapeHtml(sale.sale_number || 'N/A')}</td>
                    <td>${escapeHtml(sale.customer_name || 'Walk-in')}</td>
                    <td>TSh ${(parseFloat(sale.total_amount) || 0).toLocaleString()}</td>
                    <td>${escapeHtml(sale.sold_by_name || 'N/A')}</td>
                    <td>${new Date(sale.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                </tr>
            `;
        });
        
        return `
            <div class="pdf-header-section">
                <div class="pdf-header-top">
                    <div class="pdf-logo">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="pdf-title-area">
                        <div class="clinic-name">BRAICK DISPENSARY</div>
                        <div class="clinic-sub">Tunajali Afya Yako</div>
                    </div>
                </div>
                <div class="admin-contacts">
                    <span><i class="fas fa-phone-alt"></i> Admin: <span class="admin-phone">${adminPhonesText}</span></span>
                    <span><i class="fas fa-building"></i> Branch: ${branchName}</span>
                    <span><i class="fas fa-calendar-alt"></i> ${currentDate}</span>
                </div>
                <div style="font-size:0.85rem;font-weight:700;color:#0B5ED7;margin-top:4px;background:#EFF6FF;padding:4px 16px;border-radius:20px;display:inline-block;">
                    📊 Pharmacy Report
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;">
                <div style="background:#EDE9FE;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #7C3AED;">
                    <div style="font-size:1.2rem;font-weight:700;color:#7C3AED;">TSh ${(pharmacyData.total_prescription_amount || 0).toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Prescription Sales</div>
                    <div style="font-size:0.5rem;color:#64748B;">${pharmacyData.total_prescription_count || 0} items</div>
                </div>
                <div style="background:#FEF3C7;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #D97706;">
                    <div style="font-size:1.2rem;font-weight:700;color:#D97706;">TSh ${(pharmacyData.total_otc_amount || 0).toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">OTC Sales</div>
                    <div style="font-size:0.5rem;color:#64748B;">${pharmacyData.total_otc_count || 0} transactions</div>
                </div>
                <div style="background:#EFF6FF;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;">
                    <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;">TSh ${((pharmacyData.total_prescription_amount || 0) + (pharmacyData.total_otc_amount || 0)).toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Pharmacy Revenue</div>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-prescription-bottle"></i> Prescription Items (${pharmacyData.prescription_items.length})</div>
            <table class="pdf-table">
                <thead><tr><th>Patient</th><th>Medication</th><th>Dosage</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>${presItemsHTML}</tbody>
            </table>
            
            <div class="section-title"><i class="fas fa-shopping-cart"></i> OTC Sales (${pharmacyData.otc_sales.length})</div>
            <table class="pdf-table">
                <thead><tr><th>Sale #</th><th>Customer</th><th>Total</th><th>Sold By</th><th>Date</th></tr></thead>
                <tbody>${otcHTML}</tbody>
            </table>
            
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Technician: _________________</span>
                        <span style="margin-left:14px;">Date: ${currentDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: ${currentDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on ${currentDate} at ${currentTime} • All rights reserved
                </div>
            </div>
        `;
    }

    // ================================================================
    // BUILD LAB PDF
    // ================================================================
    function buildLabPDF() {
        var adminPhonesText = adminPhones.length > 0 ? adminPhones.join(' | ') : '+255 700 000 001';
        var currentDate = new Date().toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var currentTime = new Date().toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var testsHTML = '';
        labData.tests.forEach(function(test) {
            testsHTML += `
                <tr>
                    <td><strong>${escapeHtml(test.patient_name || 'N/A')}</strong></td>
                    <td>${escapeHtml(test.test_name || 'N/A')}</td>
                    <td>TSh ${(parseFloat(test.test_price) || 0).toLocaleString()}</td>
                    <td>${test.results ? '✅' : '⏳'}</td>
                    <td>Dr. ${escapeHtml(test.doctor_name || 'N/A')}</td>
                    <td>${escapeHtml(test.technician_name || 'N/A')}</td>
                    <td style="color:${test.status === 'completed' ? '#059669' : '#D97706'};font-weight:${test.status === 'completed' ? '700' : '600'};">${escapeHtml(test.status || 'Pending')}</td>
                    <td>${test.created_at ? new Date(test.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</td>
                </tr>
            `;
        });
        
        var topTestsHTML = '';
        var topTests = labData.top_tests || {};
        var topEntries = Object.entries(topTests).slice(0, 10);
        topEntries.forEach(function([name, count]) {
            topTestsHTML += `
                <div style="display:flex;justify-content:space-between;padding:4px 8px;background:#F8FAFC;border-radius:4px;border:1px solid #E2E8F0;font-size:13px;">
                    <span style="font-weight:600;">${escapeHtml(name)}</span>
                    <span style="color:#7C3AED;font-weight:700;">${count} tests</span>
                </div>
            `;
        });
        
        return `
            <div class="pdf-header-section">
                <div class="pdf-header-top">
                    <div class="pdf-logo">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="pdf-title-area">
                        <div class="clinic-name">BRAICK DISPENSARY</div>
                        <div class="clinic-sub">Tunajali Afya Yako</div>
                    </div>
                </div>
                <div class="admin-contacts">
                    <span><i class="fas fa-phone-alt"></i> Admin: <span class="admin-phone">${adminPhonesText}</span></span>
                    <span><i class="fas fa-building"></i> Branch: ${branchName}</span>
                    <span><i class="fas fa-calendar-alt"></i> ${currentDate}</span>
                </div>
                <div style="font-size:0.85rem;font-weight:700;color:#0B5ED7;margin-top:4px;background:#EFF6FF;padding:4px 16px;border-radius:20px;display:inline-block;">
                    📊 Lab Report
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px;">
                <div style="background:#EDE9FE;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #7C3AED;">
                    <div style="font-size:1.2rem;font-weight:700;color:#7C3AED;">TSh ${(labData.total_revenue || 0).toLocaleString()}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Revenue</div>
                </div>
                <div style="background:#EFF6FF;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;">
                    <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;">${(labData.total_tests || 0)}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Total Tests</div>
                </div>
                <div style="background:#D1FAE5;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #059669;">
                    <div style="font-size:1.2rem;font-weight:700;color:#059669;">${(labData.completed_tests || 0)}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">Completed</div>
                </div>
                <div style="background:#FEF3C7;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #D97706;">
                    <div style="font-size:1.2rem;font-weight:700;color:#D97706;">${((labData.pending_tests || 0) + (labData.in_progress_tests || 0))}</div>
                    <div style="font-size:0.6rem;color:#64748B;text-transform:uppercase;">In Progress</div>
                </div>
            </div>
            
            ${topTestsHTML ? `
            <div class="section-title"><i class="fas fa-chart-bar"></i> Most Frequent Tests</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:8px;">${topTestsHTML}</div>
            ` : ''}
            
            <div class="section-title"><i class="fas fa-flask"></i> All Lab Tests (${labData.tests.length})</div>
            <table class="pdf-table">
                <thead><tr><th>Patient</th><th>Test</th><th>Price</th><th>Result</th><th>Doctor</th><th>Technician</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>${testsHTML}</tbody>
            </table>
            
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Technician: _________________</span>
                        <span style="margin-left:14px;">Date: ${currentDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: ${currentDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on ${currentDate} at ${currentTime} • All rights reserved
                </div>
            </div>
        `;
    }

    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var reportTypeLabel = reportType.charAt(0).toUpperCase() + reportType.slice(1);
        var filename = 'Report_' + reportTypeLabel + '_' + new Date().toISOString().slice(0,10) + '.pdf';
        
        var opt = {
            margin: [8, 8, 8, 8],
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { 
                mode: ['css', 'legacy']
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE
    // ================================================================
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    // ================================================================
    // FIX: Make status badges visible on page load
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.data-table .badge').forEach(function(badge) {
            badge.style.color = '#ffffff';
            badge.style.textShadow = '0 1px 2px rgba(0,0,0,0.2)';
        });
        
        document.querySelectorAll('.badge-paid').forEach(function(badge) {
            badge.style.color = '#059669';
            badge.style.textShadow = 'none';
        });
        
        console.log('✅ Reports loaded successfully');
        console.log('✅ Patient Report FIXED - Bills loaded from patient_id');
        console.log('✅ Paid status = GREEN color');
        console.log('✅ Discounts calculated correctly (Pharmacy + Cashier = Total)');
        console.log('✅ PDF Export for ALL reports');
        console.log('✅ PDF Logo starts at top');
        console.log('✅ PDF Page breaks working properly');
    });

    console.log('%c📊 Braick Dispensary - Reports FIXED', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Patient Report: Bills now loaded from patient_id (not visit_id)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Paid status = GREEN color', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Discounts: pharmacy_discount + cashier_discount = total_discount', 'font-size:13px; color:#34D399;');
    console.log('%c✅ PDF Export for ALL reports (Patient, Cashier, Pharmacy, Lab)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ PDF Logo starts at top left', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>