<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescriptions.php
// DOCTOR - VIEW PRESCRIPTIONS
// SHOWS ALL PRESCRIPTIONS FOR THE LOGGED IN DOCTOR
// WITH FILTERS AND AUTO-UPDATE
// Session-based login (NO BYPASS)
// BRAICK DISPENSARY - USING ACTUAL DATABASE
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, profile_pic, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("view_prescriptions verification error: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY - USING ACTUAL DATABASE COLUMNS
// ================================================================
$conditions = ["p.doctor_id = ?"];
$params = [$doctor_id];

if ($filter_status !== 'all') {
    $conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR pi.medication_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET PRESCRIPTIONS WITH ITEMS - USING ACTUAL TABLES
// ================================================================
$sql = "
    SELECT 
        p.id,
        p.prescription_number,
        p.visit_id,
        p.patient_id,
        p.doctor_id,
        p.pharmacy_id,
        p.diagnosis,
        p.instructions,
        p.notes,
        p.status,
        p.branch_id,
        p.created_at,
        p.dispensed_at,
        p.updated_at,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone,
        pat.gender,
        pat.date_of_birth,
        u.full_name as doctor_name,
        ph.full_name as pharmacy_name,
        v.visit_number,
        v.visit_type,
        GROUP_CONCAT(
            CONCAT(
                pi.medication_name, 
                ' (', pi.dosage, ' ', pi.unit_price, 'x', pi.quantity, ')'
            ) SEPARATOR ', '
        ) as medication_names,
        SUM(pi.total_price) as total_price,
        COUNT(pi.id) as item_count
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    LEFT JOIN visits v ON p.visit_id = v.id
    LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.created_at DESC
";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Prescriptions fetch error: " . $e->getMessage());
    $prescriptions = [];
}

// ================================================================
// GET STATUS COUNTS - USING ACTUAL TABLES
// ================================================================
$status_counts = ['pending' => 0, 'dispensed' => 0, 'cancelled' => 0];
$statuses = ['pending', 'dispensed', 'cancelled'];

foreach ($statuses as $status) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions 
            WHERE doctor_id = ? AND status = ?
        ");
        $stmt->execute([$doctor_id, $status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $status_counts[$status] = 0;
    }
}

// Total prescriptions
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $total_count = 0;
}

// ================================================================
// GET PATIENT LIST FOR FILTER
// ================================================================
$patients_list = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.patient_id, pat.full_name, pat.patient_id as patient_code
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        WHERE p.doctor_id = ?
        ORDER BY pat.full_name ASC
    ");
    $stmt->execute([$doctor_id]);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients_list = [];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'confirmed' => 'badge-info'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'dispensed' => '✅ Dispensed',
        'cancelled' => '❌ Cancelled',
        'confirmed' => '🔄 Confirmed'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescriptions - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            
            --card-total: #0B5ED7;
            --card-total-bg: #E8F0FE;
            --card-pending: #D97706;
            --card-pending-bg: #FEF3C7;
            --card-dispensed: #059669;
            --card-dispensed-bg: #D1FAE5;
            --card-cancelled: #DC2626;
            --card-cancelled-bg: #FEE2E2;
        }
        
        [data-theme="dark"] {
            --card-total: #6EA8FE;
            --card-total-bg: #1E3A5F;
            --card-pending: #FBBF24;
            --card-pending-bg: #3D2E0A;
            --card-dispensed: #34D399;
            --card-dispensed-bg: #1A3A2A;
            --card-cancelled: #F87171;
            --card-cancelled-bg: #3A1A1A;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        [data-theme="dark"] body {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px 24px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border-bottom: 3px solid var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .page-header { background: var(--gray-800); }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        [data-theme="dark"] .page-title { color: var(--gray-100); }
        .page-title i { color: var(--primary); }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
        }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 6px;
        }
        
        /* ================================================================
           STATS CARDS - COLORED
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 6px;
            opacity: 0.8;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.65rem;
            opacity: 0.6;
            margin-top: 4px;
        }
        
        /* Total Card - Blue */
        .stat-card.total {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }
        [data-theme="dark"] .stat-card.total {
            background: linear-gradient(135deg, #1A3A5F, #0A3D7A);
        }
        .stat-card.total .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* Pending Card - Yellow/Orange */
        .stat-card.pending {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
        }
        [data-theme="dark"] .stat-card.pending {
            background: linear-gradient(135deg, #3D2E0A, #5C3D0A);
        }
        .stat-card.pending .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* Dispensed Card - Green */
        .stat-card.dispensed {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        [data-theme="dark"] .stat-card.dispensed {
            background: linear-gradient(135deg, #1A3A2A, #0D3D2A);
        }
        .stat-card.dispensed .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* Cancelled Card - Red */
        .stat-card.cancelled {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
        }
        [data-theme="dark"] .stat-card.cancelled {
            background: linear-gradient(135deg, #3A1A1A, #5C1A1A);
        }
        .stat-card.cancelled .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-section {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        [data-theme="dark"] .filter-section { background: var(--gray-800); border-color: var(--gray-700); }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--gray-200);
            background: transparent;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .filter-btn.active:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .filter-input {
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: #ffffff;
            color: var(--gray-800);
            outline: none;
            transition: var(--transition);
        }
        [data-theme="dark"] .filter-input { background: var(--gray-700); color: var(--gray-100); border-color: var(--gray-600); }
        .filter-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        
        .btn-search {
            padding: 8px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-search:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        /* ================================================================
           TABLE - BLUE HEADER
           ================================================================ */
        .table-container {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        [data-theme="dark"] .table-container { background: var(--gray-800); border-color: var(--gray-700); }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        /* TABLE HEADER - BLUE BACKGROUND */
        .data-table thead th {
            text-align: left;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0A3D7A;
            border-bottom-color: #0B4EA8;
        }
        
        .data-table thead th:first-child {
            border-radius: 0;
        }
        
        .data-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 12px 18px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }
        [data-theme="dark"] .data-table tbody td {
            border-color: var(--gray-700);
            color: var(--gray-300);
        }
        
        .data-table tbody tr {
            transition: var(--transition);
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A5F;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Zebra striping - light */
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        .data-table tbody tr:nth-child(even):hover td {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(even):hover td {
            background: #1A3A5F;
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-warning { 
            background: var(--warning-bg); 
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        .badge-success { 
            background: var(--success-bg); 
            color: var(--success);
            border: 1px solid var(--success);
        }
        .badge-danger { 
            background: var(--danger-bg); 
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .badge-info { 
            background: var(--primary-bg); 
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn-view {
            padding: 4px 12px;
            border-radius: 6px;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
        }
        .btn-view:hover { background: var(--primary-dark); transform: translateY(-1px); }
        
        .btn-print {
            padding: 4px 12px;
            border-radius: 6px;
            background: var(--success);
            color: white;
            border: none;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
        }
        .btn-print:hover { background: var(--success-dark); transform: translateY(-1px); }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--success);
            color: white;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-primary:hover { 
            background: var(--success-dark); 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        
        .btn-outline {
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-600);
            font-size: 0.75rem;
            background: transparent;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray-500);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 16px;
        }
        .empty-state p { font-size: 1rem; }
        .empty-state .sub-text {
            font-size: 0.85rem;
            color: var(--gray-400);
            margin-top: 4px;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--gray-200);
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .table-footer .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .text-gray-300 { color: var(--gray-300); }
        .text-gray-400 { color: var(--gray-400); }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .ml-2 { margin-left: 0.5rem; }
        .text-xs { font-size: 0.75rem; }
        .text-sm { font-size: 0.875rem; }
        .font-mono { font-family: monospace; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .block { display: block; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; }
            .stat-card .stat-number { font-size: 1.5rem; }
            .stat-card { padding: 14px 18px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-title { font-size: 1.1rem; }
            .data-table { font-size: 0.75rem; }
            .data-table thead th, .data-table tbody td { padding: 8px 10px; }
            .data-table thead th { font-size: 0.6rem; }
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: 10px;
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: none;
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i> My Prescriptions
                <span class="page-badge"><?= $total_count ?> Total</span>
            </h1>
            <p class="page-subtitle">
                View all prescriptions you have written
                <span class="text-xs text-gray-400 ml-2"><?= date('F d, Y') ?></span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="prescribe.php" class="btn-primary">
                <i class="fas fa-plus"></i> New Prescription
            </a>
        </div>
    </div>

    <!-- Stats Cards - Colored -->
    <div class="stats-row">
        <!-- Total Card - Blue -->
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number"><?= $total_count ?></div>
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-sub"><i class="fas fa-clock"></i> All time</div>
        </div>
        
        <!-- Pending Card - Yellow/Orange -->
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">Pending</div>
            <div class="stat-sub"><i class="fas fa-hourglass-half"></i> Awaiting pharmacy</div>
        </div>
        
        <!-- Dispensed Card - Green -->
        <div class="stat-card dispensed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">Dispensed</div>
            <div class="stat-sub"><i class="fas fa-check"></i> Completed</div>
        </div>
        
        <!-- Cancelled Card - Red -->
        <div class="stat-card cancelled">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $status_counts['cancelled'] ?? 0 ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-sub"><i class="fas fa-ban"></i> Not dispensed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <div class="filter-row">
            <a href="?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=dispensed" class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">✅ Dispensed</a>
            <a href="?status=cancelled" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" placeholder="Search patient, medication..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="view_prescriptions.php" class="btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Table - Blue Header -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-receipt"></i> Prescription #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th><i class="fas fa-cubes"></i> Qty / Total</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($prescriptions as $pres): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:var(--primary);">
                                        <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (($pres['item_count'] ?? 0) > 0): ?>
                                        <span class="text-xs text-gray-400 block">(<?= $pres['item_count'] ?> items)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($pres['patient_code'] ?? 'N/A') ?></div>
                                    <?php if (!empty($pres['phone'])): ?>
                                        <div class="text-xs text-gray-400"><i class="fas fa-phone"></i> <?= htmlspecialchars($pres['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['date_of_birth']) && $pres['date_of_birth'] !== '0000-00-00'): ?>
                                        <div class="text-xs text-gray-400"><i class="fas fa-calendar-alt"></i> <?= calculateAge($pres['date_of_birth']) ?> yrs</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm">
                                        <?php if (!empty($pres['medication_names'])): ?>
                                            <?= htmlspecialchars(substr($pres['medication_names'], 0, 50)) . (strlen($pres['medication_names']) > 50 ? '...' : '') ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">No items</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm font-semibold">
                                        <?php 
                                            $total_qty = 0;
                                            if (!empty($pres['medication_names'])) {
                                                preg_match_all('/x(\d+)/', $pres['medication_names'], $matches);
                                                if (!empty($matches[1])) {
                                                    $total_qty = array_sum($matches[1]);
                                                }
                                            }
                                            echo $total_qty > 0 ? $total_qty : '-';
                                        ?>
                                    </span>
                                    <?php if (!empty($pres['total_price']) && $pres['total_price'] > 0): ?>
                                        <span class="text-xs text-green-600 block">TSh <?= number_format($pres['total_price'], 0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($pres['status'] ?? 'pending') ?>">
                                        <?= getStatusLabel($pres['status'] ?? 'pending') ?>
                                    </span>
                                    <?php if (!empty($pres['dispensed_at'])): ?>
                                        <span class="text-xs text-gray-400 block">
                                            <?= date('d/m/Y', strtotime($pres['dispensed_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['pharmacy_name'])): ?>
                                        <span class="text-xs text-gray-400 block">
                                            <i class="fas fa-store"></i> <?= htmlspecialchars($pres['pharmacy_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs"><?= formatDate($pres['created_at'] ?? '') ?></span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <span class="text-xs text-gray-400 block">Visit: <?= htmlspecialchars($pres['visit_number']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['visit_type'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= ucfirst($pres['visit_type']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (($pres['status'] ?? '') === 'dispensed'): ?>
                                            <a href="print_prescription.php?id=<?= $pres['id'] ?>" class="btn-print" title="Print Prescription" target="_blank">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-prescription"></i>
                                    <p>No prescriptions found</p>
                                    <p class="sub-text">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            You haven't written any prescriptions yet.
                                            <br><a href="prescribe.php" style="color:var(--primary);text-decoration:underline;">Write your first prescription</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($prescriptions) ?></strong> prescriptions
            </span>
            <span>
                <span class="count-badge"><?= $total_count ?></span> Total prescriptions
            </span>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Prescriptions
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;font-size:0.85rem;margin:0;">Notification</p>
        <p id="toastMessage" style="font-size:0.75rem;opacity:0.9;margin:0;"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom';
        if (type === 'success') { toast.style.background = '#059669'; }
        else if (type === 'error') { toast.style.background = '#DC2626'; }
        else { toast.style.background = '#0B5ED7'; }
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        setTimeout(function() { toast.classList.add('show'); }, 50);
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    console.log('%c💊 View Prescriptions - With Colors', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c📊 Summary Cards: Blue (Total), Yellow (Pending), Green (Dispensed), Red (Cancelled)', 'font-size:12px; color:#6EA8FE;');
    console.log('%c📋 Table Header: Blue Background', 'font-size:12px; color:#0B5ED7;');
    console.log('%c📋 Total Prescriptions: <?= $total_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:12px; color:#059669;');
    console.log('%c❌ Cancelled: <?= $status_counts['cancelled'] ?? 0 ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c📦 Using Actual Database: prescriptions, prescription_items, patients, users', 'font-size:12px; color:#8B5CF6;');
</script>

</body>
</html>