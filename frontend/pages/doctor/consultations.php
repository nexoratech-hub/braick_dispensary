<?php
// ================================================================
// FILE: frontend/pages/doctor/consultations.php
// DOCTOR - CONSULTATIONS LIST WITH AUTO-UPDATE
// - Pending: Active consultations (with_doctor, assigned, pending)
// - Lab Test: Waiting for lab results
// - Prescribed: Doctor saved, waiting for payment
// - Completed: All paid and completed
// - Auto-update every 3 seconds without refresh (AJAX)
// - Uses SHARED HEADER (dark mode, date/time, status toggle inherited)
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET FILTER PARAMETER
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Allowed filters
$allowed_filters = ['pending', 'lab_test', 'prescribed', 'completed', 'cancelled'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// AUTO-COMPLETE LOGIC - Check all prescribed visits
// ================================================================
try {
    if ($is_admin) {
        // Admin can auto-complete all visits
        $stmt = $db->prepare("
            SELECT v.id, v.visit_number, v.patient_id
            FROM visits v
            WHERE v.status = 'prescribed'
            AND v.is_completed = 0
        ");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT v.id, v.visit_number, v.patient_id
            FROM visits v
            WHERE v.doctor_id = ? 
            AND v.status = 'prescribed'
            AND v.is_completed = 0
        ");
        $stmt->execute([$doctor_id]);
    }
    $prescribed_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($prescribed_visits as $visit) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bills,
                SUM(CASE WHEN status IN ('pending', 'partial') THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(total_amount) as total_amount,
                SUM(paid_amount) as total_paid
            FROM patient_bills 
            WHERE visit_id = ?
        ");
        $stmt->execute([$visit['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_bills = (int)($result['total_bills'] ?? 0);
        $pending_count = (int)($result['pending_count'] ?? 0);
        $paid_count = (int)($result['paid_count'] ?? 0);
        $total_amount = (float)($result['total_amount'] ?? 0);
        $total_paid = (float)($result['total_paid'] ?? 0);
        
        if ($total_bills > 0 && $pending_count == 0 && $paid_count > 0) {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'completed', 
                    is_completed = 1, 
                    completed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$visit['id']]);
            
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET status = 'paid', updated_at = NOW()
                WHERE visit_id = ? AND status IN ('pending', 'partial')
            ");
            $stmt->execute([$visit['id']]);
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'consultation_auto_completed', ?, NOW())
                ");
                $stmt->execute([
                    $doctor_id,
                    $doctor_branch_id,
                    "Consultation #" . $visit['visit_number'] . " auto-completed" . ($is_admin ? " (Admin)" : "")
                ]);
            } catch (Exception $e) {}
            
            $db->commit();
        }
    }
} catch (Exception $e) {
    error_log("Auto-complete error: " . $e->getMessage());
}

// ================================================================
// GET COUNTS FOR BADGES
// ================================================================
$pending_count = 0;
$lab_test_count = 0;
$prescribed_count = 0;
$completed_count = 0;
$cancelled_count = 0;

if ($is_admin) {
    // Admin can see all counts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status IN ('pending', 'assigned', 'with_doctor') AND is_completed = 0");
    $stmt->execute();
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'lab_test' AND is_completed = 0");
    $stmt->execute();
    $lab_test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'prescribed' AND is_completed = 0");
    $stmt->execute();
    $prescribed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'completed' AND is_completed = 1");
    $stmt->execute();
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'cancelled'");
    $stmt->execute();
    $cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    // Doctor can only see their counts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor') AND is_completed = 0");
    $stmt->execute([$doctor_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'lab_test' AND is_completed = 0");
    $stmt->execute([$doctor_id]);
    $lab_test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'prescribed' AND is_completed = 0");
    $stmt->execute([$doctor_id]);
    $prescribed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'completed' AND is_completed = 1");
    $stmt->execute([$doctor_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'cancelled'");
    $stmt->execute([$doctor_id]);
    $cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// ================================================================
// GET INITIAL CONSULTATIONS
// ================================================================
$params = [];
$search_condition = "";
$status_condition = "";
$doctor_condition = "";

if ($is_admin) {
    $doctor_condition = "";
} else {
    $doctor_condition = "v.doctor_id = ?";
    $params[] = $doctor_id;
}

if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($filter) {
    case 'pending':
        $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor') AND v.is_completed = 0";
        break;
    case 'lab_test':
        $status_condition = "AND v.status = 'lab_test' AND v.is_completed = 0";
        break;
    case 'prescribed':
        $status_condition = "AND v.status = 'prescribed' AND v.is_completed = 0";
        break;
    case 'completed':
        $status_condition = "AND v.status = 'completed' AND v.is_completed = 1";
        break;
    case 'cancelled':
        $status_condition = "AND v.status = 'cancelled'";
        break;
    default:
        $status_condition = "AND v.status IN ('pending', 'assigned', 'with_doctor') AND v.is_completed = 0";
        break;
}

$sql = "
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.address,
        p.blood_group,
        p.allergies,
        u.full_name as doctor_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id AND status IN ('pending', 'in_progress')) as pending_lab_count,
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id AND status = 'completed') as completed_lab_count,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status IN ('pending', 'dispensed')) as total_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id AND status IN ('pending', 'partial')) as pending_bills_count,
        (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id AND status = 'paid') as paid_bills_count,
        (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id) as total_bills_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE visit_id = v.id) as total_bill_amount,
        (SELECT COALESCE(SUM(paid_amount), 0) FROM patient_bills WHERE visit_id = v.id) as total_paid_amount
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE 1=1
    " . ($is_admin ? "" : "AND " . $doctor_condition) . "
    $status_condition
    $search_condition
    ORDER BY v.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_consultations = count($consultations);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR - CORRECT PATHS
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($filter) ?> Consultations - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header-custom::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-custom .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header-custom .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header-custom .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header-custom .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.15);
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
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
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
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .filter-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: var(--bg-card);
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .filter-tab {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 2px solid transparent;
        }
        
        .filter-tab:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .filter-tab .tab-badge {
            font-size: 0.6rem;
            padding: 1px 8px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .filter-tab:not(.active) .tab-badge {
            background: var(--gray-200);
            color: var(--gray-500);
        }
        
        .filter-tab .tab-badge.prescribed-badge { background: #7C3AED; color: white; }
        .filter-tab .tab-badge.danger { background: #EF4444; color: white; }
        .filter-tab .tab-badge.green { background: #059669; color: white; }
        .filter-tab .tab-badge.gray { background: #64748B; color: white; }
        .filter-tab .tab-badge.lab-badge { background: #8B5CF6; color: white; }
        
        .consultation-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 16px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
        }
        
        .consultation-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .consultation-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .consultation-card .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .consultation-card .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .consultation-card .patient-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .consultation-card .patient-id {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .consultation-card .patient-details {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .consultation-card .visit-number {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .consultation-card .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.prescribed { background: #EDE9FE; color: #7C3AED; }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.assigned { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.with_doctor { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.lab_test { background: #EDE9FE; color: #7C3AED; }
        .status-badge.completed { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        .consultation-card .lab-indicator { font-size: 0.7rem; color: var(--purple); }
        .consultation-card .lab-indicator .pending { color: var(--warning); }
        .consultation-card .lab-indicator .completed { color: var(--success); }
        .consultation-card .bill-indicator { font-size: 0.7rem; }
        .consultation-card .bill-indicator .pending { color: var(--warning); }
        .consultation-card .bill-indicator .paid { color: var(--success); }
        .consultation-card .bill-amount { font-size: 0.7rem; color: var(--text-secondary); }
        .consultation-card .bill-amount .amount { font-weight: 600; }
        
        .consultation-card .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }
        
        .consultation-card .card-footer .meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 4px 10px; font-size: 0.65rem; border-radius: 6px; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        .empty-state i { font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 12px; }
        .empty-state .empty-title { font-size: 1.2rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .empty-sub { font-size: 0.85rem; color: var(--text-secondary); }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
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
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(52, 211, 153, 0.2);
            color: #34D399;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }
        .live-badge i { font-size: 0.4rem; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        .update-flash {
            animation: flash-green 0.6s ease;
        }
        
        @keyframes flash-green {
            0%, 100% { background: transparent; }
            50% { background: rgba(5, 150, 105, 0.08); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar-toggle-btn { display: block; }
        }
        
        @media (max-width: 768px) {
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.3rem; }
            .consultation-card { padding: 14px 16px; }
            .filter-tabs { padding: 6px 8px; }
            .filter-tab { padding: 6px 12px; font-size: 0.7rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .consultation-card { padding: 10px 12px; }
            .consultation-card .card-header { flex-direction: column; }
            .filter-tabs { flex-wrap: wrap; }
            .filter-tab { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                <?= ucfirst($filter) ?> Consultations
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">DOCTOR</span>
                <?php if ($is_admin): ?>
                    <span class="role-badge-display" style="background:rgba(220,38,38,0.3);color:white;border:1px solid rgba(220,38,38,0.3);">👑 Admin</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-medical"></i>
                View <?= $filter ?> consultations
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <span id="totalCount"><?= $total_consultations ?></span> Total
                </span>
                
                <span class="live-badge" id="liveBadge">
                    <i class="fas fa-circle" style="color:#34D399;"></i>
                    Live
                    <span id="liveTime" style="font-weight:400;font-size:0.55rem;"><?= date('H:i:s') ?></span>
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-sync-alt fa-fw"></i>
                    <span id="updateCount">0</span> updates
                </span>
                
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#F87171;">
                        <i class="fas fa-user-shield"></i> Admin View - All Doctors
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER TABS -->
    <!-- ================================================================ -->
    <div class="filter-tabs" id="filterTabs">
        <a href="consultations.php?filter=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>" data-filter="pending">
            <i class="fas fa-clock"></i> Pending
            <span class="tab-badge danger" id="badgePending"><?= $pending_count ?></span>
        </a>
        
        <a href="consultations.php?filter=lab_test<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'lab_test' ? 'active' : '' ?>" data-filter="lab_test">
            <i class="fas fa-flask"></i> Lab Test
            <span class="tab-badge lab-badge" id="badgeLabTest"><?= $lab_test_count ?></span>
        </a>
        
        <a href="consultations.php?filter=prescribed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'prescribed' ? 'active' : '' ?>" data-filter="prescribed">
            <i class="fas fa-hourglass-half"></i> Waiting
            <span class="tab-badge prescribed-badge" id="badgePrescribed"><?= $prescribed_count ?></span>
        </a>
        
        <a href="consultations.php?filter=completed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>" data-filter="completed">
            <i class="fas fa-check-circle"></i> Completed
            <span class="tab-badge green" id="badgeCompleted"><?= $completed_count ?></span>
        </a>
        
        <a href="consultations.php?filter=cancelled<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'cancelled' ? 'active' : '' ?>" data-filter="cancelled">
            <i class="fas fa-times-circle"></i> Cancelled
            <span class="tab-badge gray" id="badgeCancelled"><?= $cancelled_count ?></span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- CONSULTATIONS LIST -->
    <!-- ================================================================ -->
    <div id="consultationsContainer">
        <?php if (count($consultations) > 0): ?>
            <?php foreach ($consultations as $consultation): 
                $initial = strtoupper(substr($consultation['patient_name'] ?? 'U', 0, 1));
                $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
                $color = $colors[abs(crc32($consultation['patient_name'] ?? 'U')) % count($colors)];
                
                $pending_lab = (int)($consultation['pending_lab_count'] ?? 0);
                $completed_lab = (int)($consultation['completed_lab_count'] ?? 0);
                $pending_rx = (int)($consultation['pending_prescriptions'] ?? 0);
                $dispensed_rx = (int)($consultation['dispensed_prescriptions'] ?? 0);
                $pending_bills = (int)($consultation['pending_bills_count'] ?? 0);
                $paid_bills = (int)($consultation['paid_bills_count'] ?? 0);
                $total_bills = (int)($consultation['total_bills_count'] ?? 0);
                $total_amount = (float)($consultation['total_bill_amount'] ?? 0);
                $total_paid = (float)($consultation['total_paid_amount'] ?? 0);
                
                $can_complete = ($pending_bills == 0 && $paid_bills > 0 && $consultation['status'] === 'prescribed');
            ?>
                <div class="consultation-card animate-fade-in-up" data-visit-id="<?= $consultation['id'] ?>" data-status="<?= $consultation['status'] ?>">
                    <div class="card-header">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= $color ?>;">
                                <?= $initial ?>
                            </div>
                            <div>
                                <div class="patient-name"><?= htmlspecialchars($consultation['patient_name'] ?? 'N/A') ?></div>
                                <div class="patient-id">ID: <?= htmlspecialchars($consultation['patient_code'] ?? 'N/A') ?></div>
                                <div class="patient-details">
                                    <?= htmlspecialchars($consultation['gender'] ?? 'N/A') ?> • 
                                    <?= htmlspecialchars($consultation['phone'] ?? 'N/A') ?>
                                    <?php if (!empty($consultation['blood_group'])): ?>
                                        • Blood: <?= htmlspecialchars($consultation['blood_group']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="visit-number"><?= htmlspecialchars($consultation['visit_number'] ?? 'N/A') ?></span>
                            <span class="status-badge <?= $consultation['status'] ?? 'pending' ?>">
                                <?= ucfirst(str_replace('_', ' ', $consultation['status'] ?? 'Pending')) ?>
                            </span>
                            <?php if ($is_admin): ?>
                                <span class="status-badge" style="background:#FEE2E2;color:#DC2626;font-size:0.5rem;border:1px solid #DC2626;">
                                    <i class="fas fa-user-shield"></i> Admin
                                </span>
                            <?php endif; ?>
                            <?php if ($can_complete): ?>
                                <span class="status-badge completed" style="background:#D1FAE5;color:#059669;">
                                    <i class="fas fa-check"></i> Auto-complete
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Indicators -->
                    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px;">
                        <?php if ($pending_lab > 0): ?>
                            <span class="lab-indicator"><i class="fas fa-flask pending"></i> <?= $pending_lab ?> lab(s) pending</span>
                        <?php endif; ?>
                        <?php if ($completed_lab > 0): ?>
                            <span class="lab-indicator"><i class="fas fa-check-circle completed"></i> <?= $completed_lab ?> lab(s) completed</span>
                        <?php endif; ?>
                        <?php if ($pending_rx > 0): ?>
                            <span class="lab-indicator"><i class="fas fa-prescription pending"></i> <?= $pending_rx ?> prescription(s) pending</span>
                        <?php endif; ?>
                        <?php if ($dispensed_rx > 0): ?>
                            <span class="lab-indicator"><i class="fas fa-check-circle completed"></i> <?= $dispensed_rx ?> prescription(s) dispensed</span>
                        <?php endif; ?>
                        <?php if ($pending_bills > 0): ?>
                            <span class="bill-indicator"><i class="fas fa-receipt pending"></i> <?= $pending_bills ?> bill(s) pending <span class="bill-amount">(TSh <?= number_format($total_amount) ?>)</span></span>
                        <?php endif; ?>
                        <?php if ($paid_bills > 0): ?>
                            <span class="bill-indicator"><i class="fas fa-check-circle paid"></i> <?= $paid_bills ?> bill(s) paid <span class="bill-amount">(TSh <?= number_format($total_paid) ?>)</span></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Footer -->
                    <div class="card-footer">
                        <div class="meta">
                            <i class="far fa-calendar-alt"></i> <?= date('M d, Y', strtotime($consultation['created_at'])) ?>
                            <span class="mx-1">•</span>
                            <i class="far fa-clock"></i> <?= date('h:i A', strtotime($consultation['created_at'])) ?>
                            <?php if (!empty($consultation['doctor_name'])): ?>
                                <span class="mx-1">•</span>
                                <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($consultation['doctor_name']) ?>
                            <?php endif; ?>
                            <?php if ($total_bills > 0): ?>
                                <span class="mx-1">•</span>
                                <i class="fas fa-receipt"></i> Bills: <?= $paid_bills ?>/<?= $total_bills ?>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if (in_array($filter, ['pending', 'lab_test', 'prescribed'])): ?>
                                <a href="consultation.php?visit_id=<?= $consultation['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-stethoscope"></i> Continue
                                </a>
                            <?php endif; ?>
                            <?php if (in_array($filter, ['completed', 'cancelled'])): ?>
                                <a href="consultation.php?visit_id=<?= $consultation['id'] ?>&view=1" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            <?php endif; ?>
                            <?php if ($filter === 'prescribed' && $pending_bills > 0): ?>
                                <span class="text-xs text-gray-400 self-center">
                                    <i class="fas fa-clock"></i> Waiting for payment...
                                </span>
                            <?php endif; ?>
                            <?php if ($filter === 'prescribed' && $pending_bills == 0 && $total_bills > 0): ?>
                                <span class="text-xs text-green-600 self-center animate-fade-in-up">
                                    <i class="fas fa-check-circle"></i> Auto-completing...
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="max-width:1200px;margin:0 auto;">
                <i class="fas fa-<?= $filter === 'pending' ? 'clock' : ($filter === 'lab_test' ? 'flask' : ($filter === 'prescribed' ? 'hourglass-half' : ($filter === 'completed' ? 'check-circle' : 'times-circle'))) ?>"></i>
                <div class="empty-title">No <?= $filter ?> consultations</div>
                <div class="empty-sub">
                    <?php if ($filter === 'pending'): ?>
                        All consultations have been processed or no pending consultations
                    <?php elseif ($filter === 'lab_test'): ?>
                        No consultations waiting for lab results
                    <?php elseif ($filter === 'prescribed'): ?>
                        All consultations have been completed or no prescribed consultations waiting for payment
                    <?php elseif ($filter === 'completed'): ?>
                        No completed consultations yet
                    <?php else: ?>
                        No cancelled consultations
                    <?php endif; ?>
                    <?php if (!empty($search)): ?>
                        <br>Try adjusting your search criteria
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            <?= ucfirst($filter) ?> Consultations
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
<!-- JAVASCRIPT - AUTO-UPDATE EVERY 3 SECONDS (AJAX) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE (for responsive)
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME (for footer timestamp)
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
    }
    updateFooterTime();

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
    // AUTO-UPDATE - EVERY 3 SECONDS (AJAX)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    var updateCount = 0;
    var filter = '<?= $filter ?>';
    var search = '<?= addslashes($search) ?>';

    function fetchAndUpdateConsultations() {
        if (isUpdating) return;
        isUpdating = true;
        
        var url = 'get_consultations.php?filter=' + encodeURIComponent(filter) + 
                  '&search=' + encodeURIComponent(search) + 
                  '&t=' + Date.now();
        
        fetch(url)
            .then(function(response) { 
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json(); 
            })
            .then(function(data) {
                if (data.success) {
                    // Check if data has changed
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateCount++;
                        updateConsultations(data);
                        updateFooterTime();
                        
                        // Update update count badge
                        var updateCountEl = document.getElementById('updateCount');
                        if (updateCountEl) {
                            updateCountEl.textContent = updateCount;
                        }
                        
                        // Show notification on change (after first update)
                        if (updateCount > 1) {
                            showToast('🔄 Updated', 'Consultations auto-updated at ' + data.timestamp, 'info');
                        }
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Update error:', error);
                isUpdating = false;
            });
    }

    function updateConsultations(data) {
        var container = document.getElementById('consultationsContainer');
        if (!container) return;
        
        // Update counts in filter tabs
        var badgeMap = {
            'pending': 'badgePending',
            'lab_test': 'badgeLabTest',
            'prescribed': 'badgePrescribed',
            'completed': 'badgeCompleted',
            'cancelled': 'badgeCancelled'
        };
        
        for (var key in badgeMap) {
            var el = document.getElementById(badgeMap[key]);
            if (el && data.counts[key] !== undefined) {
                el.textContent = data.counts[key];
            }
        }
        
        // Update page header total
        var totalBadge = document.getElementById('totalCount');
        if (totalBadge) {
            totalBadge.textContent = data.total;
        }
        
        // Update consultations list with animation
        var oldContainer = container.cloneNode(true);
        container.innerHTML = data.html;
        
        // Add flash animation to new cards
        var cards = container.querySelectorAll('.consultation-card');
        cards.forEach(function(card, index) {
            card.style.animationDelay = (index * 0.05) + 's';
            card.classList.add('animate-fade-in-up');
        });
        
        // Update live time
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = data.timestamp;
    }

    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAndUpdateConsultations();
        updateInterval = setInterval(fetchAndUpdateConsultations, 3000);
        console.log('%c🔄 Auto-update started (every 3s) via AJAX', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
        
        lastHash = null;
        fetchAndUpdateConsultations();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Consultations updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'F5') {
            e.preventDefault();
            manualRefresh();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    console.log('%c👨‍⚕️ Braick - Consultations (AJAX Auto-Update)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User ID: <?= $doctor_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:13px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Viewing All Consultations', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c📊 Pending: <?= $pending_count ?> | Lab Test: <?= $lab_test_count ?> | Waiting: <?= $prescribed_count ?> | Completed: <?= $completed_count ?> | Cancelled: <?= $cancelled_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update every 3 seconds without refresh (AJAX)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Filter: <?= ucfirst($filter) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Uses shared header for dark mode, date/time, status toggle', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>