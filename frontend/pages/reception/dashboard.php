<?php
// ================================================================
// FILE: frontend/pages/reception/dashboard.php
// BRAICK DISPENSARY - RECEPTION & CASHIER DASHBOARD
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force session for direct access (Reception role)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 102;
    $_SESSION['full_name'] = 'Reception Sarah';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
}

// ================================================================
// GET BRANCH FROM URL PARAMETER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? $_SESSION['branch_id'] ?? 1;

// If branch is passed via URL, update session
if (isset($_GET['branch']) && is_numeric($_GET['branch'])) {
    $_SESSION['branch_id'] = (int)$_GET['branch'];
    $selected_branch_id = (int)$_GET['branch'];
}

// ================================================================
// BRANCH CHECK
// ================================================================
$user_branch_id = $selected_branch_id;

// Include database and helpers
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
require_once $root_path . 'backend/config/database.php';
require_once $root_path . 'backend/helpers/functions.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48'%3E%3Crect width='48' height='48' fill='%230B5ED7' rx='12'/%3E%3Ctext x='24' y='32' text-anchor='middle' fill='white' font-size='20' font-weight='bold'%3EB%3C/text%3E%3C/svg%3E";
$avatar_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='38' height='38'%3E%3Crect width='38' height='38' fill='%230B5ED7' rx='50%25'/%3E%3Ctext x='19' y='25' text-anchor='middle' fill='white' font-size='18' font-weight='bold'%3ER%3C/text%3E%3C/svg%3E";

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Default Branch';
$stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($branch_data) {
    $branch_name = $branch_data['name'];
    $branch_location = $branch_data['location'] ?? '';
} else {
    // If branch not found, get first active branch
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' LIMIT 1");
    $default = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($default) {
        $branch_name = $default['name'];
        $_SESSION['branch_id'] = $default['id'];
        $user_branch_id = $default['id'];
    }
}

// ================================================================
// FETCH STATISTICS - WITH BRANCH FILTER
// ================================================================

$today = date('Y-m-d');

// Total Patients (All time)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Visits (All time)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Income (All time from payments)
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_branch_id]);
$total_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Doctors
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = ? AND branch_id = ?");
$stmt->execute([$today, $user_branch_id]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(created_at) = ? AND branch_id = ?");
$stmt->execute([$today, $user_branch_id]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Income
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = ? AND branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$today, $user_branch_id]);
$today_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending Appointments Today
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ? AND branch_id = ? AND status IN ('scheduled', 'confirmed')");
$stmt->execute([$today, $user_branch_id]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Waiting Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits v WHERE v.status IN ('pending', 'assigned') AND v.branch_id = ?");
$stmt->execute([$user_branch_id]);
$waiting_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed Visits Today
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(created_at) = ? AND status = 'completed' AND branch_id = ?");
$stmt->execute([$today, $user_branch_id]);
$completed_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// With Doctor
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits v WHERE v.status = 'with_doctor' AND v.branch_id = ?");
$stmt->execute([$user_branch_id]);
$with_doctor = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Laboratory Queue
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits v WHERE v.status = 'lab_test' AND v.branch_id = ?");
$stmt->execute([$user_branch_id]);
$lab_queue = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pharmacy Queue
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits v WHERE v.status = 'prescribed' AND v.branch_id = ?");
$stmt->execute([$user_branch_id]);
$pharmacy_queue = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// QUEUE LIST
// ================================================================
$queue_list = [];
$stmt = $db->prepare("
    SELECT v.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name,
           TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_minutes
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.branch_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
    ORDER BY FIELD(v.status, 'pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed'), v.created_at ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$queue_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// TODAY RECEIPTS
// ================================================================
$today_receipts = [];
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name
    FROM payments p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    WHERE DATE(p.payment_date) = ? 
    AND p.branch_id = ? 
    AND p.payment_status = 'paid'
    ORDER BY p.payment_date DESC
    LIMIT 5
");
$stmt->execute([$today, $user_branch_id]);
$today_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PAYMENT BREAKDOWN
// ================================================================
$stmt = $db->prepare("
    SELECT payment_type, COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE DATE(payment_date) = ? 
    AND branch_id = ? 
    AND payment_status = 'paid'
    GROUP BY payment_type
");
$stmt->execute([$today, $user_branch_id]);
$payment_breakdown = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $payment_breakdown[$row['payment_type']] = $row['total'];
}

// ================================================================
// RECENT ACTIVITIES
// ================================================================
$recent_activities = [
    ['action' => 'Patient Registered', 'details' => 'John Doe registered at 10:30 AM', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['action' => 'Payment Received', 'details' => 'Consultation fee TSh 15,000 from Mary Jane', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ['action' => 'Patient Sent to Doctor', 'details' => 'Peter Smith sent to Dr. Sarah Mwamba', 'created_at' => date('Y-m-d H:i:s', strtotime('-25 minutes'))],
    ['action' => 'Receipt Printed', 'details' => 'Receipt #RCP-2026-0042 printed', 'created_at' => date('Y-m-d H:i:s', strtotime('-45 minutes'))],
];

// ================================================================
// ALL PATIENTS (for table)
// ================================================================
$all_patients = [];
$stmt = $db->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) as visit_count,
           (SELECT COUNT(*) FROM payments pm WHERE pm.patient_id = p.id AND pm.payment_status = 'paid') as payment_count
    FROM patients p
    WHERE p.branch_id = ?
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute([$user_branch_id]);
$all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// APPOINTMENTS LIST
// ================================================================
$appointments_list = [];
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE DATE(a.appointment_date) = ? 
    AND a.branch_id = ?
    AND a.status IN ('scheduled', 'confirmed')
    ORDER BY a.appointment_date ASC
    LIMIT 5
");
$stmt->execute([$today, $user_branch_id]);
$appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DOCTORS LIST
// ================================================================
$doctors_list = [];
$stmt = $db->prepare("SELECT id, full_name, specialty FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CASHIER STATISTICS
// ================================================================
// Today's Cash Transactions
$stmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE DATE(payment_date) = ? 
    AND branch_id = ? 
    AND payment_status = 'paid'
");
$stmt->execute([$today, $user_branch_id]);
$cashier_today = $stmt->fetch(PDO::FETCH_ASSOC);

// Pending Cash Transactions
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM payments 
    WHERE payment_status = 'pending' 
    AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$pending_cash = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: rgba(11, 94, 215, 0.15);
            --secondary: #0AA84F;
            --secondary-light: rgba(10, 168, 79, 0.15);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --radius: 18px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ===== SIDEBAR - BLUE WITH GREEN HOVER ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; background: #0B5ED7; 
            color: white;
            z-index: 50; overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-brand .branch-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 4px;
        }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { 
            font-size: 0.55rem; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            color: rgba(255,255,255,0.4); 
            padding: 0 12px; 
            margin: 12px 0 6px; 
            font-weight: 700; 
        }
        .sidebar-link {
            display: flex; 
            align-items: center; 
            gap: 12px;
            padding: 9px 14px; 
            border-radius: 10px;
            color: rgba(255,255,255,0.75); 
            text-decoration: none;
            transition: var(--transition); 
            font-size: 0.85rem; 
            font-weight: 500;
            margin: 2px 0;
        }
        /* Hover effect - GREEN */
        .sidebar-link:hover { 
            background: rgba(10, 168, 79, 0.25); 
            color: #FFFFFF; 
        }
        /* Active link - GREEN */
        .sidebar-link.active { 
            background: #0AA84F; 
            color: white; 
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4); 
        }
        .sidebar-link.active i { color: white; }
        .sidebar-link i { 
            width: 20px; 
            text-align: center; 
            font-size: 1rem; 
        }
        .sidebar-link .badge { 
            margin-left: auto; 
            background: rgba(255,255,255,0.15); 
            padding: 1px 9px; 
            border-radius: 20px; 
            font-size: 0.65rem; 
            font-weight: 600; 
        }
        .sidebar-link.active .badge { 
            background: rgba(255,255,255,0.25); 
            color: white; 
        }
        
        /* ===== TOP NAV ===== */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-card); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--bg-body); border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        .top-nav .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 280px; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        .top-nav .search-wrapper input::placeholder { color: var(--text-secondary); }
        .top-nav .search-wrapper .search-btn {
            background: var(--primary); color: white;
            border: none; padding: 8px 16px; border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: var(--transition);
        }
        .top-nav .search-wrapper .search-btn:hover { background: var(--primary-dark); }
        .top-nav .branch-badge {
            background: rgba(11, 94, 215, 0.08);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(11, 94, 215, 0.12);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: var(--transition);
        }
        .top-nav .avatar:hover { border-color: var(--primary); }
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: var(--transition);
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: #EF4444;
            border-radius: 50%; border: 2px solid var(--bg-card);
        }
        .dark-toggle {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 6px 12px;
            cursor: pointer; font-size: 0.85rem;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex; align-items: center; gap: 6px;
        }
        .dark-toggle:hover { border-color: var(--primary); }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ===== SUMMARY CARDS - BLUE & GREEN ONLY ===== */
        .summary-card {
            border-radius: var(--radius);
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .summary-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .summary-card.blue { 
            background: rgba(11, 94, 215, 0.10); 
            border-left: 4px solid #0B5ED7;
        }
        .summary-card.blue::after { background: linear-gradient(90deg, #0B5ED7, #1E88E5); }
        .summary-card.blue .sc-icon { background: rgba(11, 94, 215, 0.15); color: #0B5ED7; }
        .summary-card.blue .sc-number { color: #0B5ED7; }
        
        .summary-card.green { 
            background: rgba(10, 168, 79, 0.10); 
            border-left: 4px solid #0AA84F;
        }
        .summary-card.green::after { background: linear-gradient(90deg, #0AA84F, #34D399); }
        .summary-card.green .sc-icon { background: rgba(10, 168, 79, 0.15); color: #0AA84F; }
        .summary-card.green .sc-number { color: #0AA84F; }
        
        .summary-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }
        .summary-card .sc-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .summary-card .sc-number {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .summary-card .sc-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .summary-card .sc-trend {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 20px;
        }
        .summary-card .sc-trend.up { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .summary-card .sc-trend.neutral { background: rgba(100, 116, 139, 0.12); color: #64748B; }
        
        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .card-title { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
        .card-title i { color: var(--primary); }
        
        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 10px;
            font-weight: 600; font-size: 0.8rem;
            transition: var(--transition); cursor: pointer;
            border: none; text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-secondary:hover { background: #08944A; box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ===== TABLES ===== */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .data-table th {
            text-align: left; padding: 10px 14px;
            font-weight: 600; color: var(--text-secondary);
            font-size: 0.65rem; text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .data-table tr:hover td { background: var(--bg-body); }
        .data-table .table-actions {
            display: flex; gap: 8px;
        }
        
        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.pending { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
        .status-badge.completed { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.paid { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.waiting { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
        .status-badge.scheduled { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
        .status-badge.with_doctor { background: rgba(139, 92, 246, 0.12); color: #8B5CF6; }
        .status-badge.lab_test { background: rgba(245, 158, 11, 0.12); color: #D97706; }
        .status-badge.prescribed { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        
        /* ===== QUEUE ITEM ===== */
        .queue-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            transition: var(--transition);
        }
        .queue-item:hover { background: var(--bg-body); }
        .queue-item .q-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .queue-item .q-icon.blue { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
        .queue-item .q-icon.green { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .queue-item .q-icon.purple { background: rgba(139, 92, 246, 0.12); color: #8B5CF6; }
        .queue-item .q-icon.teal { background: rgba(13, 148, 136, 0.12); color: #0D9488; }
        
        /* ===== QUICK ACTION ===== */
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 12px;
            border-radius: var(--radius);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-primary);
            gap: 6px;
            text-align: center;
        }
        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        .quick-action .qa-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .quick-action .qa-label { font-size: 0.7rem; font-weight: 500; }
        
        /* ===== PAYMENT BREAKDOWN ===== */
        .pb-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: 12px;
            transition: var(--transition);
        }
        .pb-item:hover { background: var(--bg-body); }
        .pb-item .pb-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }
        .pb-item .pb-icon.blue { background: rgba(11, 94, 215, 0.08); color: #0B5ED7; }
        .pb-item .pb-icon.green { background: rgba(10, 168, 79, 0.08); color: #0AA84F; }
        .pb-item .pb-icon.purple { background: rgba(139, 92, 246, 0.08); color: #8B5CF6; }
        .pb-item .pb-icon.teal { background: rgba(13, 148, 136, 0.08); color: #0D9488; }
        
        /* ===== QUICK REPORTS - STYLED ===== */
        .quick-report-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.78rem;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-report-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .quick-report-btn.blue {
            background: rgba(11, 94, 215, 0.08);
            color: #0B5ED7;
            border: 1px solid rgba(11, 94, 215, 0.12);
        }
        .quick-report-btn.blue:hover {
            background: #0B5ED7;
            color: white;
        }
        .quick-report-btn.green {
            background: rgba(10, 168, 79, 0.08);
            color: #0AA84F;
            border: 1px solid rgba(10, 168, 79, 0.12);
        }
        .quick-report-btn.green:hover {
            background: #0AA84F;
            color: white;
        }
        .quick-report-btn.purple {
            background: rgba(139, 92, 246, 0.08);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, 0.12);
        }
        .quick-report-btn.purple:hover {
            background: #8B5CF6;
            color: white;
        }
        .quick-report-btn.orange {
            background: rgba(245, 158, 11, 0.08);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.12);
        }
        .quick-report-btn.orange:hover {
            background: #F59E0B;
            color: white;
        }
        .quick-report-btn.red {
            background: rgba(239, 68, 68, 0.08);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.12);
        }
        .quick-report-btn.red:hover {
            background: #EF4444;
            color: white;
        }
        .quick-report-btn.teal {
            background: rgba(13, 148, 136, 0.08);
            color: #0D9488;
            border: 1px solid rgba(13, 148, 136, 0.12);
        }
        .quick-report-btn.teal:hover {
            background: #0D9488;
            color: white;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: 16px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper input { width: 160px; }
        }
        @media (max-width: 768px) {
            .summary-card .sc-number { font-size: 1.6rem; }
        }
        @media (max-width: 640px) {
            .top-nav .search-wrapper input { width: 100px; }
            .top-nav .branch-badge { font-size: 0.6rem; padding: 2px 8px; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 10px; }
            .summary-card .sc-number { font-size: 1.4rem; }
            .top-nav .dark-toggle span { display: none; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
        
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR - BLUE WITH GREEN HOVER -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='<?= $logo_fallback ?>'">
            <div>
                <p class="font-bold text-base leading-tight">Braick Dispensary</p>
                <p class="text-xs opacity-80">Reception & Cashier</p>
                <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?></span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <!-- DASHBOARD -->
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php?branch=<?= $user_branch_id ?>" class="sidebar-link active"><i class="fas fa-home"></i> Dashboard</a>
        
        <!-- PATIENT MODULE -->
        <div class="nav-label">Patient Module</div>
        <a href="patient_queue.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-hourglass-half"></i> Patient Queue <span class="badge"><?= $waiting_patients ?></span></a>
        <a href="register_patient.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-user-plus"></i> Register Patient</a>
        <a href="patients.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-users"></i> All Patients <span class="badge"><?= $total_patients ?></span></a>
        <a href="export_patients_excel.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-file-excel"></i> Export Excel</a>
        <a href="export_patients_pdf.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-file-pdf"></i> Export PDF</a>
        
        <!-- CASHIER MODULE -->
        <div class="nav-label">Cashier Module</div>
        <a href="cashier.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-cash-register"></i> Cashier</a>
        <a href="receipts.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-receipt"></i> Receipts</a>
        <a href="daily_cash_report.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-file-invoice-dollar"></i> Daily Cash Report</a>
        
        <!-- PATIENT RECORDS -->
        <div class="nav-label">Patient Records</div>
        <a href="medical_history.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-notes-medical"></i> Medical History</a>
        <a href="patient_documents.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-folder-open"></i> Documents</a>
        
        <!-- REPORTS -->
        <div class="nav-label">Reports</div>
        <a href="reports.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        
        <!-- LOGOUT -->
        <a href="<?= $root_path ?>logout.php" class="sidebar-link" style="margin-top:12px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Search patient, receipt, phone..." class="search-input">
            <button id="searchBtn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle">
            <i class="fas fa-moon" id="darkIcon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn" id="notifToggle">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-envelope text-lg"></i>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $avatar_fallback ?>'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-primary">Reception & Cashier Dashboard</h1>
            <p class="text-sm text-secondary">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Receptionist') ?>! 
                <span class="inline-flex ml-2" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7; padding: 3px 14px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(11, 94, 215, 0.15);">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="register_patient.php?branch=<?= $user_branch_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Register</a>
            <a href="cashier.php?branch=<?= $user_branch_id ?>" class="btn btn-secondary btn-sm"><i class="fas fa-cash-register"></i> Cashier</a>
            <button onclick="refreshData()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARDS - BLUE & GREEN ONLY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Patients</p>
                    <p class="sc-number"><?= number_format($total_patients) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> +<?= $today_patients ?> today</span>
                </div>
                <div class="sc-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Visits</p>
                    <p class="sc-number"><?= number_format($total_visits) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> <?= $today_visits ?> today</span>
                </div>
                <div class="sc-icon"><i class="fas fa-clinic-medical"></i></div>
            </div>
        </div>
        
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Income</p>
                    <p class="sc-number">TSh <?= number_format($total_income) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> TSh <?= number_format($today_income) ?> today</span>
                </div>
                <div class="sc-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Appointments</p>
                    <p class="sc-number"><?= $today_appointments ?></p>
                    <span class="sc-trend neutral"><i class="fas fa-calendar-check"></i> Scheduled</span>
                </div>
                <div class="sc-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CASHIER STATS CARDS - BLUE & GREEN ONLY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="summary-card blue animate-fade-in-up" style="border-left: 4px solid #0B5ED7;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Transactions</p>
                    <p class="sc-number"><?= $cashier_today['count'] ?? 0 ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> Today</span>
                </div>
                <div class="sc-icon" style="background: rgba(11, 94, 215, 0.15); color: #0B5ED7;">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>
        
        <div class="summary-card green animate-fade-in-up" style="border-left: 4px solid #0AA84F;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Revenue</p>
                    <p class="sc-number">TSh <?= number_format($cashier_today['total'] ?? 0) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> Collected</span>
                </div>
                <div class="sc-icon" style="background: rgba(10, 168, 79, 0.15); color: #0AA84F;">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
        
        <div class="summary-card blue animate-fade-in-up" style="border-left: 4px solid #0B5ED7;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Pending Payments</p>
                    <p class="sc-number"><?= $pending_cash ?></p>
                    <span class="sc-trend neutral"><i class="fas fa-clock"></i> Awaiting</span>
                </div>
                <div class="sc-icon" style="background: rgba(11, 94, 215, 0.15); color: #0B5ED7;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>
        
        <div class="summary-card green animate-fade-in-up" style="border-left: 4px solid #0AA84F;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Payments</p>
                    <p class="sc-number"><?= $total_patients ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> All time</span>
                </div>
                <div class="sc-icon" style="background: rgba(10, 168, 79, 0.15); color: #0AA84F;">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT QUEUE & APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-hourglass-half mr-2"></i> Patient Queue</h3>
                <a href="patient_queue.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
            </div>
            <div class="space-y-1 max-h-72 overflow-y-auto">
                <?php if (count($queue_list) > 0): ?>
                    <?php foreach ($queue_list as $patient): ?>
                        <?php 
                            $icon = 'user';
                            $icon_class = 'blue';
                            $status_label = str_replace('_', ' ', $patient['status'] ?? 'pending');
                            if ($patient['status'] == 'with_doctor') { $icon = 'stethoscope'; $icon_class = 'purple'; }
                            else if ($patient['status'] == 'lab_test') { $icon = 'flask'; $icon_class = 'teal'; }
                            else if ($patient['status'] == 'prescribed') { $icon = 'pills'; $icon_class = 'green'; }
                        ?>
                        <div class="queue-item">
                            <span class="q-icon <?= $icon_class ?>">
                                <i class="fas fa-<?= $icon ?>"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm truncate"><?= htmlspecialchars($patient['patient_name'] ?? 'Unknown') ?></p>
                                <p class="text-xs text-secondary">
                                    <?= htmlspecialchars($patient['doctor_name'] ?? 'Waiting') ?> • 
                                    <span class="status-badge <?= $patient['status'] ?>"><?= $status_label ?></span>
                                </p>
                            </div>
                            <span class="text-xs text-secondary"><?= $patient['waiting_minutes'] ?? 0 ?>m</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-secondary text-sm py-4">
                        <i class="fas fa-check-circle text-green-500 text-2xl block mb-2"></i>
                        No patients in queue
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-day mr-2"></i> Today's Appointments</h3>
                <a href="appointments.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
            </div>
            <div class="space-y-1 max-h-72 overflow-y-auto">
                <?php if (count($appointments_list) > 0): ?>
                    <?php foreach ($appointments_list as $appt): ?>
                        <div class="queue-item">
                            <span class="q-icon blue"><i class="fas fa-calendar-check"></i></span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm truncate"><?= htmlspecialchars($appt['patient_name'] ?? 'Unknown') ?></p>
                                <p class="text-xs text-secondary">
                                    <?= htmlspecialchars($appt['doctor_name'] ?? 'Not assigned') ?> • 
                                    <span class="status-badge scheduled"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                                </p>
                            </div>
                            <span class="text-xs text-secondary"><?= $appt['status'] ?? 'scheduled' ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-secondary text-sm py-4">
                        <i class="fas fa-calendar-check text-primary text-2xl block mb-2"></i>
                        No appointments today
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS & PAYMENT BREAKDOWN -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt mr-2"></i> Quick Actions</h3>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                <a href="register_patient.php?branch=<?= $user_branch_id ?>" class="quick-action">
                    <div class="qa-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;"><i class="fas fa-user-plus"></i></div>
                    <span class="qa-label">Register</span>
                </a>
                <a href="cashier.php?branch=<?= $user_branch_id ?>" class="quick-action">
                    <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-cash-register"></i></div>
                    <span class="qa-label">Cashier</span>
                </a>
                <a href="receipts.php?branch=<?= $user_branch_id ?>" class="quick-action">
                    <div class="qa-icon" style="background: rgba(139, 92, 246, 0.08); color: #7C3AED;"><i class="fas fa-receipt"></i></div>
                    <span class="qa-label">Receipt</span>
                </a>
                <a href="patients.php?branch=<?= $user_branch_id ?>" class="quick-action">
                    <div class="qa-icon" style="background: rgba(13, 148, 136, 0.08); color: #0D9488;"><i class="fas fa-search"></i></div>
                    <span class="qa-label">Search</span>
                </a>
                <a href="export_patients_excel.php?branch=<?= $user_branch_id ?>" class="quick-action">
                    <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-file-excel"></i></div>
                    <span class="qa-label">Excel</span>
                </a>
                <a href="export_patients_pdf.php?branch=<?= $user_branch_id ?>" class="quick-action">
                    <div class="qa-icon" style="background: rgba(239, 68, 68, 0.08); color: #EF4444;"><i class="fas fa-file-pdf"></i></div>
                    <span class="qa-label">PDF</span>
                </a>
            </div>
        </div>
        
        <div class="card lg:col-span-1">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i> Today's Payments</h3>
            </div>
            <div class="space-y-1">
                <div class="pb-item">
                    <div class="flex items-center gap-3">
                        <div class="pb-icon blue"><i class="fas fa-user-md"></i></div>
                        <span class="text-sm font-medium">Consultation</span>
                    </div>
                    <span class="font-semibold text-sm">TSh <?= number_format($payment_breakdown['consultation'] ?? 0) ?></span>
                </div>
                <div class="pb-item">
                    <div class="flex items-center gap-3">
                        <div class="pb-icon green"><i class="fas fa-flask"></i></div>
                        <span class="text-sm font-medium">Laboratory</span>
                    </div>
                    <span class="font-semibold text-sm">TSh <?= number_format($payment_breakdown['laboratory'] ?? 0) ?></span>
                </div>
                <div class="pb-item">
                    <div class="flex items-center gap-3">
                        <div class="pb-icon blue"><i class="fas fa-pills"></i></div>
                        <span class="text-sm font-medium">Pharmacy</span>
                    </div>
                    <span class="font-semibold text-sm">TSh <?= number_format($payment_breakdown['pharmacy'] ?? 0) ?></span>
                </div>
                <div class="pb-item">
                    <div class="flex items-center gap-3">
                        <div class="pb-icon green"><i class="fas fa-plus-circle"></i></div>
                        <span class="text-sm font-medium">Other</span>
                    </div>
                    <span class="font-semibold text-sm">TSh <?= number_format($payment_breakdown['other'] ?? 0) ?></span>
                </div>
                <div class="pt-2 border-t flex justify-between font-bold">
                    <span>Total Today</span>
                    <span class="text-primary">TSh <?= number_format($today_income) ?></span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- ALL PATIENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users mr-2"></i> All Patients</h3>
            <div class="flex gap-2">
                <a href="export_patients_excel.php?branch=<?= $user_branch_id ?>" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="export_patients_pdf.php?branch=<?= $user_branch_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                <a href="patients.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
            </div>
        </div>
        <div class="overflow-x-auto max-h-72 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Visits</th>
                        <th>Payments</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_patients) > 0): ?>
                        <?php foreach ($all_patients as $patient): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td><span class="status-badge paid"><?= $patient['visit_count'] ?? 0 ?></span></td>
                                <td><span class="status-badge paid"><?= $patient['payment_count'] ?? 0 ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $user_branch_id ?>" class="text-primary text-xs hover:underline">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= $user_branch_id ?>" class="text-secondary text-xs hover:underline">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-secondary text-sm py-3">No patients found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT RECEIPTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-receipt mr-2"></i> Recent Receipts</h3>
            <a href="receipts.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="overflow-x-auto max-h-52 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Receipt #</th><th>Patient</th><th>Service</th><th>Amount</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (count($today_receipts) > 0): ?>
                        <?php foreach ($today_receipts as $receipt): ?>
                            <tr>
                                <td class="font-mono text-xs">#<?= htmlspecialchars($receipt['receipt_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($receipt['patient_name'] ?? 'Unknown') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($receipt['payment_type'] ?? 'N/A') ?></td>
                                <td class="font-semibold text-green-600">TSh <?= number_format($receipt['amount'] ?? 0) ?></td>
                                <td>
                                    <button onclick="printReceipt(<?= $receipt['id'] ?>)" class="text-primary text-xs hover:underline">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-secondary text-sm py-3">No receipts today</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock mr-2"></i> Recent Activity</h3>
            <a href="activity_logs.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
            <?php foreach ($recent_activities as $activity): ?>
                <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-body transition">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                        <i class="fas fa-circle text-[8px]"></i>
                    </div>
                    <div>
                        <p class="font-medium text-sm text-primary"><?= htmlspecialchars($activity['action']) ?></p>
                        <p class="text-xs text-secondary"><?= htmlspecialchars($activity['details']) ?></p>
                        <p class="text-[10px] text-secondary mt-0.5"><?= time_ago($activity['created_at']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS - STYLED -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i> Quick Reports</h3>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="reports.php?type=daily_revenue&branch=<?= $user_branch_id ?>" class="quick-report-btn blue">
                <i class="fas fa-calendar-day"></i> Daily Revenue
            </a>
            <a href="reports.php?type=daily_patients&branch=<?= $user_branch_id ?>" class="quick-report-btn green">
                <i class="fas fa-users"></i> Daily Patients
            </a>
            <a href="reports.php?type=cash_report&branch=<?= $user_branch_id ?>" class="quick-report-btn blue">
                <i class="fas fa-file-invoice-dollar"></i> Cash Report
            </a>
            <a href="reports.php?type=receipts&branch=<?= $user_branch_id ?>" class="quick-report-btn green">
                <i class="fas fa-receipt"></i> Receipts
            </a>
            <a href="reports.php?type=appointments&branch=<?= $user_branch_id ?>" class="quick-report-btn blue">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="reports.php?type=payments&branch=<?= $user_branch_id ?>" class="quick-report-btn green">
                <i class="fas fa-money-bill-wave"></i> Payments
            </a>
            <div class="flex-1"></div>
            <button onclick="downloadPDF()" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
            <button onclick="exportExcel()" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</button>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p class="font-medium text-sm">Braick Dispensary Management System</p>
        <p class="text-xs">Reception & Cashier Dashboard v2.0 &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const darkToggle = document.getElementById('darkModeToggle');
    const darkIcon = document.getElementById('darkIcon');
    const darkText = document.getElementById('darkText');
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');
    const refreshBtn = document.getElementById('refreshBtn');

    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    let isDark = false;
    darkToggle?.addEventListener('click', () => {
        isDark = !isDark;
        const html = document.getElementById('htmlRoot');
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
        } else {
            html.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
        }
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        isDark = true;
        document.getElementById('htmlRoot').setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }

    function performSearch() {
        const query = searchInput.value.trim();
        if (query.length > 0) {
            showToast('Search', 'Searching for: "' + query + '"', 'info');
            const branch = '<?= $user_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    function refreshData() {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<span class="spinner"></span> Refreshing...';
            refreshBtn.disabled = true;
        }
        setTimeout(() => { location.reload(); }, 800);
    }

    function showToast(title, message, type = 'info') {
        const existing = document.querySelector('.toast-custom');
        if (existing) existing.remove();
        const colors = {
            info: { bg: '#0B5ED7', icon: 'fa-info-circle' },
            success: { bg: '#0AA84F', icon: 'fa-check-circle' },
            error: { bg: '#EF4444', icon: 'fa-exclamation-circle' },
            warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' }
        };
        const style = colors[type] || colors.info;
        const toast = document.createElement('div');
        toast.className = 'toast-custom';
        toast.style.cssText = `
            background: ${style.bg}; color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        `;
        toast.innerHTML = `
            <i class="fas ${style.icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    function printReceipt(id) {
        showToast('Print Receipt', 'Printing receipt #' + id + '...', 'info');
        window.open('print_receipt.php?id=' + id + '&branch=<?= $user_branch_id ?>', '_blank');
    }

    function downloadPDF() {
        showToast('Downloading PDF', 'Generating PDF report...', 'info');
        window.location.href = 'reports.php?export=pdf&branch=<?= $user_branch_id ?>';
    }
    function exportExcel() {
        showToast('Exporting Excel', 'Preparing Excel export...', 'info');
        window.location.href = 'reports.php?export=excel&branch=<?= $user_branch_id ?>';
    }

    function updateDateTime() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            showToast('Welcome', 'Reception Dashboard loaded successfully', 'success');
        }, 300);
    });

    console.log('%c🏥 Braick Dispensary - Reception Dashboard v2.0', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0AA84F;');
</script>

</body>
</html>