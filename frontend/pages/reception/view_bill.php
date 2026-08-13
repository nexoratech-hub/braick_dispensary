<?php
// ================================================================
// FILE: frontend/pages/reception/view_bill.php
// RECEPTION - VIEW BILL DETAILS
// View complete bill information with items and payment status
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// CHECK IF USER HAS ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// GET BILL ID
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bill_id <= 0) {
    header('Location: bills.php?error=invalid_bill');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$bill = null;
$bill_items = [];
$payment_history = [];

// ================================================================
// GET BILL INFORMATION
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            pb.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.address,
            p.email,
            u.full_name as created_by_name,
            v.visit_number,
            v.visit_type,
            v.created_at as visit_date,
            v.diagnosis,
            v.symptoms
        FROM patient_bills pb
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN users u ON pb.created_by = u.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        WHERE pb.id = ? AND pb.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill) {
        header('Location: bills.php?error=bill_not_found');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: bills.php?error=database_error');
    exit;
}

// ================================================================
// GET BILL ITEMS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            CASE 
                WHEN bi.item_type = 'consultation' THEN '📋 Consultation'
                WHEN bi.item_type = 'lab_test' THEN '🧪 Lab Test'
                WHEN bi.item_type = 'medication' THEN '💊 Medication'
                WHEN bi.item_type = 'procedure' THEN '💉 Procedure'
                WHEN bi.item_type = 'tool' THEN '🔧 Tool'
                WHEN bi.item_type = 'registration' THEN '📝 Registration'
                ELSE bi.item_type
            END as item_type_label,
            CASE 
                WHEN bi.payment_status = 'paid' THEN '✅ Paid'
                WHEN bi.payment_status = 'pending' THEN '⏳ Pending'
                WHEN bi.payment_status = 'partial' THEN '🔄 Partial'
                ELSE bi.payment_status
            END as payment_status_label
        FROM bill_items bi
        WHERE bi.bill_id = ? AND bi.status != 'cancelled'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $bill_items = [];
}

// ================================================================
// GET PAYMENT HISTORY (if any)
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE bill_id = ?
        ORDER BY received_at DESC
    ");
    $stmt->execute([$bill_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payment_history = [];
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_items = count($bill_items);
$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;

foreach ($bill_items as $item) {
    $total_amount += $item['total_price'];
    if ($item['payment_status'] === 'paid') {
        $paid_amount += $item['total_price'];
    } else {
        $pending_amount += $item['total_price'];
    }
}

$balance = $total_amount - $paid_amount;

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadge($status) {
    $map = [
        'paid' => 'badge-success',
        'pending' => 'badge-warning',
        'partial' => 'badge-info',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'paid' => '✅ Paid',
        'pending' => '⏳ Pending',
        'partial' => '🔄 Partial',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getPaymentStatusBadge($status) {
    $map = [
        'paid' => 'badge-success',
        'pending' => 'badge-warning',
        'partial' => 'badge-info'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getPaymentStatusLabel($status) {
    $map = [
        'paid' => '✅ Paid',
        'pending' => '⏳ Pending',
        'partial' => '🔄 Partial'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
    }
    return $colors[abs($hash) % count($colors)];
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
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
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
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
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #B91C1C;
            transform: translateY(-2px);
        }
        
        .patient-avatar-sm {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: white;
            flex-shrink: 0;
        }
        
        .patient-info-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 12px 16px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            margin-bottom: 16px;
        }
        
        [data-theme="dark"] .patient-info-row {
            background: #1E3A5F;
        }
        
        .summary-box {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .summary-box {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .summary-box .total-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .summary-box .total-value {
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        .summary-box .total-value.success { color: var(--success); }
        .summary-box .total-value.danger { color: var(--danger); }
        .summary-box .total-value.warning { color: var(--warning); }
        .summary-box .total-value.primary { color: var(--primary); }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .table-container th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #0B5ED7 !important;
            color: #ffffff !important;
            border-bottom: 3px solid #0A4CA8;
        }
        
        .table-container th i {
            color: #ffffff !important;
            margin-right: 6px;
        }
        
        [data-theme="dark"] .table-container th {
            background: #0B5ED7 !important;
            color: #ffffff !important;
        }
        
        .table-container td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .table-container tr:hover td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-container tr:hover td {
            background: var(--gray-700);
        }
        
        .table-container tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-container tbody tr:nth-child(even) td {
            background: var(--gray-800);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
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
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .patient-info-row { flex-direction: column; text-align: center; }
            .summary-box { text-align: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .card { padding: 14px 16px; }
            .summary-box .total-value { font-size: 1.1rem; }
        }
        
        @media print {
            .main-content { margin: 0; padding: 20px; background: white; }
            .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-container th { background: #0B5ED7 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .btn, .btn-outline, .btn-primary, .btn-success, .btn-danger, .btn-sm { display: none !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
            .footer { display: none; }
        }
    </style>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <span style="font-weight:600;font-size:1rem;color:var(--text-primary);">
            <i class="fas fa-receipt" style="color:var(--primary);"></i> Bill Details
        </span>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Bill Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                <i class="fas fa-calendar"></i>
                Created: <?= date('d/m/Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
            <button onclick="window.print()" class="btn-outline-light" style="background:rgba(255,255,255,0.25);">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-user-circle"></i> Patient Information
        </h3>
        
        <div class="patient-info-row">
            <div class="patient-avatar-sm" style="background:<?= getUserColor($bill['patient_name'] ?? 'Unknown') ?>;">
                <?= strtoupper(substr($bill['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:1rem;color:var(--text-primary);">
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </div>
                <div style="font-size:0.8rem;color:var(--text-secondary);">
                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?></span>
                    <span class="mx-2">•</span>
                    <span><i class="fas fa-calendar"></i> <?= calculateAge($bill['date_of_birth'] ?? '') ?> years</span>
                    <span class="mx-2">•</span>
                    <span><i class="fas <?= ($bill['gender'] ?? '') === 'Male' ? 'fa-mars' : 'fa-venus' ?>"></i> <?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></span>
                    <span class="mx-2">•</span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></span>
                </div>
            </div>
            <div style="margin-left:auto;">
                <span class="badge <?= getStatusBadge($bill['status'] ?? 'pending') ?>" style="font-size:0.8rem;padding:4px 16px;">
                    <?= getStatusLabel($bill['status'] ?? 'pending') ?>
                </span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="detail-row">
                <span class="detail-label">Visit Number</span>
                <span class="detail-value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit Type</span>
                <span class="detail-value"><?= ucfirst($bill['visit_type'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created By</span>
                <span class="detail-value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created At</span>
                <span class="detail-value"><?= date('d/m/Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></span>
            </div>
            <?php if (!empty($bill['diagnosis'])): ?>
                <div class="detail-row" style="grid-column: span 2;">
                    <span class="detail-label">Diagnosis</span>
                    <span class="detail-value"><?= htmlspecialchars($bill['diagnosis']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="summary-box">
            <div class="total-label">Total Amount</div>
            <div class="total-value primary">TSh <?= number_format($total_amount, 0) ?></div>
        </div>
        <div class="summary-box">
            <div class="total-label">Paid Amount</div>
            <div class="total-value success">TSh <?= number_format($paid_amount, 0) ?></div>
        </div>
        <div class="summary-box">
            <div class="total-label">Pending Amount</div>
            <div class="total-value warning">TSh <?= number_format($pending_amount, 0) ?></div>
        </div>
        <div class="summary-box">
            <div class="total-label">Balance</div>
            <div class="total-value <?= $balance > 0 ? 'danger' : 'success' ?>">TSh <?= number_format($balance, 0) ?></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-list"></i> Bill Items
            <span class="badge badge-info" style="margin-left:auto;"><?= $total_items ?> items</span>
        </h3>
        
        <?php if (count($bill_items) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-tag"></i> Item</th>
                            <th><i class="fas fa-cube"></i> Type</th>
                            <th style="text-align:center;"><i class="fas fa-calculator"></i> Qty</th>
                            <th style="text-align:right;"><i class="fas fa-dollar-sign"></i> Unit Price</th>
                            <th style="text-align:right;"><i class="fas fa-dollar-sign"></i> Total</th>
                            <th style="text-align:center;"><i class="fas fa-circle"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                                <td><?= $item['item_type_label'] ?? ucfirst($item['item_type'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $item['quantity'] ?? 1 ?></td>
                                <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="text-align:right;font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="badge <?= getPaymentStatusBadge($item['payment_status'] ?? 'pending') ?>">
                                        <?= getPaymentStatusLabel($item['payment_status'] ?? 'pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:700;border-top:2px solid var(--border-color);">
                            <td colspan="5" style="text-align:right;padding:10px 14px;">TOTAL</td>
                            <td style="text-align:right;padding:10px 14px;font-size:1.1rem;color:var(--primary);">
                                TSh <?= number_format($total_amount, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No items found on this bill.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT HISTORY -->
    <!-- ================================================================ -->
    <?php if (count($payment_history) > 0): ?>
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Payment History
                <span class="badge badge-info" style="margin-left:auto;"><?= count($payment_history) ?> payments</span>
            </h3>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-dollar-sign"></i> Amount</th>
                            <th><i class="fas fa-credit-card"></i> Method</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-user"></i> Received By</th>
                            <th><i class="fas fa-receipt"></i> Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $index => $payment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td style="font-weight:600;color:var(--success);">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></td>
                                <td><?= ucfirst($payment['payment_method'] ?? 'Cash') ?></td>
                                <td><?= date('d/m/Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td><?= htmlspecialchars($payment['received_by'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['reference_number'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTIONS -->
    <!-- ================================================================ -->
    <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-cog"></i> Actions
            </h3>
            
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="process_payment.php?bill_id=<?= $bill_id ?>" class="btn btn-success">
                    <i class="fas fa-credit-card"></i> Process Payment
                </a>
                <a href="edit_bill.php?id=<?= $bill_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Bill
                </a>
                <a href="bills.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bill Details
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Dark Mode
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // Sidebar Toggle
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });

    // Date & Time
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Toast
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c🧾 View Bill - Reception', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total: TSh <?= number_format($total_amount, 0) ?> | Paid: TSh <?= number_format($paid_amount, 0) ?> | Balance: TSh <?= number_format($balance, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Items: <?= $total_items ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>