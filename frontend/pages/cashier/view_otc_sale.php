<?php
// ================================================================
// FILE: frontend/pages/cashier/view_otc_sale.php
// CASHIER - VIEW OTC SALE DETAILS
// SHOWS ALL ITEMS FOR A SPECIFIC OTC SALE
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET SALE ID
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    header('Location: pending_bills.php?error=Invalid OTC sale ID');
    exit;
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // GET OTC SALE DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            o.*,
            u.full_name as sold_by_name,
            b.name as branch_name
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        LEFT JOIN branches b ON o.branch_id = b.id
        WHERE o.id = ? AND o.branch_id = ?
    ");
    $stmt->execute([$sale_id, $user_branch_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        $message = "OTC sale not found!";
        $message_type = 'error';
    }

    // ================================================================
    // GET OTC ITEMS
    // ================================================================
    $items = [];
    if ($sale) {
        $stmt = $db->prepare("
            SELECT * FROM otc_sale_items 
            WHERE sale_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // GET PATIENT INFO IF EXISTS
    // ================================================================
    $patient = null;
    if ($sale && !empty($sale['patient_id'])) {
        $stmt = $db->prepare("
            SELECT * FROM patients 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$sale['patient_id'], $user_branch_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // GET BILL INFO IF EXISTS
    // ================================================================
    $bill = null;
    if ($sale && !empty($sale['bill_id'])) {
        $stmt = $db->prepare("
            SELECT * FROM bills 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$sale['bill_id'], $user_branch_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // GET PAYMENT INFO
    // ================================================================
    $payment = null;
    if ($sale && $sale['payment_status'] === 'paid') {
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE bill_id = ? AND patient_id = ?
            ORDER BY received_at DESC LIMIT 1
        ");
        $stmt->execute([$sale['bill_id'] ?? 0, $sale['patient_id'] ?? 0]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $sale = null;
    $items = [];
    $patient = null;
    $bill = null;
    $payment = null;
    error_log("View OTC sale error: " . $e->getMessage());
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC Sale #<?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --otc-color: #8B5CF6;
            --otc-bg: #EDE9FE;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1A1A2E;
            --gray-100: #1E293B;
            --gray-200: #2D3748;
            --gray-300: #4A5568;
            --gray-400: #718096;
            --gray-500: #A0AEC0;
            --gray-600: #CBD5E1;
            --gray-700: #E2E8F0;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3A2A1A;
            --purple-bg: #2A1A3A;
            --otc-bg: #2A1A3A;
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
        ::-webkit-scrollbar-thumb { background: var(--otc-color); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .page-header {
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .page-header .page-title {
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
        
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
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
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .sale-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: var(--shadow);
        }
        
        .sale-card:hover {
            border-color: var(--otc-color);
            box-shadow: var(--shadow-md);
        }
        
        .sale-header {
            background: var(--otc-bg);
            padding: 18px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .sale-header .sale-number {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--otc-color);
            font-family: monospace;
        }
        
        .sale-header .sale-status {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .sale-header .sale-status.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .sale-header .sale-status.paid {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .sale-header .sale-status.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .sale-body {
            padding: 24px 28px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px 24px;
            padding-bottom: 18px;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 18px;
        }
        
        .info-grid .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-grid .info-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .info-grid .info-item .value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .info-grid .info-item .value.otc-color {
            color: var(--otc-color);
        }
        
        .items-table-wrap {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 18px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--otc-color);
            border-bottom: 3px solid #6D28D9;
            white-space: nowrap;
        }
        
        .items-table thead th:first-child { border-radius: 8px 0 0 0; }
        .items-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .items-table td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .items-table .text-right { text-align: right; }
        .items-table .font-mono { font-family: 'Courier New', monospace; }
        .items-table .item-name { font-weight: 500; font-size: 0.9rem; }
        .items-table .item-instruction {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-style: italic;
            display: block;
            margin-top: 3px;
            background: var(--bg-body);
            padding: 2px 10px;
            border-radius: 4px;
            border-left: 3px solid var(--otc-color);
        }
        
        .items-table .item-type-badge {
            font-size: 0.55rem;
            background: var(--gray-100);
            padding: 1px 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            display: inline-block;
        }
        
        [data-theme="dark"] .items-table .item-type-badge {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .totals-section {
            display: flex;
            justify-content: flex-end;
            padding-top: 14px;
            border-top: 2px solid var(--border-color);
        }
        
        .totals-box {
            width: 320px;
        }
        
        .totals-box .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.85rem;
            align-items: center;
        }
        
        .totals-box .total-row .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .totals-box .total-row .value {
            font-weight: 600;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
        }
        
        .totals-box .total-row.discount .value {
            color: var(--warning);
        }
        
        .totals-box .total-row.grand-total {
            border-top: 2px solid var(--border-color);
            padding-top: 10px;
            margin-top: 4px;
            font-size: 1.05rem;
        }
        
        .totals-box .total-row.grand-total .label {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .totals-box .total-row.grand-total .value {
            color: var(--otc-color);
            font-weight: 700;
            font-size: 1.15rem;
        }
        
        .totals-box .total-row.paid .value {
            color: var(--success);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 2px solid var(--border-color);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-otc {
            background: var(--otc-color);
            color: white;
        }
        .btn-otc:hover {
            background: #6D28D9;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--otc-color);
            color: var(--otc-color);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        
        .message-box {
            padding: 12px 20px;
            border-radius: var(--radius);
            border: 2px solid transparent;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .message-box.success {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        .message-box.error {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        .message-box.warning {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }
        .message-box i { font-size: 1.2rem; }
        
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--otc-color); font-weight: 600; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
            max-width: 800px;
            margin: 0 auto;
        }
        .empty-state i { font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 12px; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-primary); margin-bottom: 8px; }
        .empty-state p { color: var(--text-secondary); font-size: 0.9rem; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .sale-body { padding: 14px 16px; }
            .items-table { font-size: 0.75rem; }
            .items-table thead th, .items-table tbody td { padding: 6px 10px; }
            .totals-box { width: 100%; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .info-grid { grid-template-columns: 1fr; }
            .sale-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                OTC Sale Details
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> RECEPTION
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                View OTC sale #<strong><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="header-badge" style="background:rgba(139,92,246,0.2);border-color:rgba(139,92,246,0.2);">
                    <i class="fas fa-shopping-cart"></i> OTC Sale
                </span>
                <?php if ($sale && $sale['payment_status'] === 'paid'): ?>
                    <span class="header-badge" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.2);color:#34D399;">
                        <i class="fas fa-check-circle"></i> Paid
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($sale && $sale['payment_status'] === 'pending'): ?>
                <a href="process_otc_payment.php?sale_id=<?= $sale_id ?>" class="btn-outline-light" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.2);">
                    <i class="fas fa-money-bill-wave"></i> Pay Now
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.2);">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($sale): ?>
    
    <!-- SALE CARD -->
    <div class="sale-card animate-fade-in-up">
        <!-- Header -->
        <div class="sale-header">
            <div>
                <span class="sale-number"><?= htmlspecialchars($sale['sale_number']) ?></span>
                <span style="font-size:0.7rem;color:var(--text-secondary);margin-left:10px;">
                    <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?>
                </span>
                <?php if (!empty($sale['updated_at']) && $sale['updated_at'] !== $sale['created_at']): ?>
                    <span style="font-size:0.6rem;color:var(--text-secondary);margin-left:6px;">
                        <i class="fas fa-edit"></i> Updated: <?= date('d/m/Y h:i A', strtotime($sale['updated_at'])) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($sale['payment_status'] === 'paid'): ?>
                    <span class="sale-status paid"><i class="fas fa-check-circle"></i> PAID</span>
                <?php elseif ($sale['payment_status'] === 'cancelled'): ?>
                    <span class="sale-status cancelled"><i class="fas fa-times-circle"></i> CANCELLED</span>
                <?php else: ?>
                    <span class="sale-status pending"><i class="fas fa-clock"></i> PENDING</span>
                <?php endif; ?>
                <span style="font-size:0.6rem;color:var(--text-secondary);margin-left:8px;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?>
                </span>
                <span style="font-size:0.6rem;color:var(--text-secondary);margin-left:8px;">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?>
                </span>
            </div>
        </div>
        
        <!-- Body -->
        <div class="sale-body">
            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="label"><i class="fas fa-user"></i> Customer</span>
                    <span class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($sale['patient_id'])): ?>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-id-card"></i> Patient ID</span>
                        <span class="value"><?= htmlspecialchars($sale['patient_id']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($patient): ?>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-user-md"></i> Patient Name</span>
                        <span class="value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                    <span class="value"><?= ucfirst($sale['payment_method'] ?? 'Not set') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-info-circle"></i> Status</span>
                    <span class="value otc-color">
                        <?php if ($sale['payment_status'] === 'paid'): ?>
                            ✅ Paid
                        <?php elseif ($sale['payment_status'] === 'cancelled'): ?>
                            ❌ Cancelled
                        <?php else: ?>
                            ⏳ Pending
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($sale['notes'])): ?>
                    <div class="info-item" style="grid-column: span 2;">
                        <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                        <span class="value"><?= nl2br(htmlspecialchars($sale['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Items Table -->
            <h4 style="font-size:0.9rem;font-weight:600;color:var(--text-primary);margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-list-ul" style="color:var(--otc-color);"></i>
                Sale Items
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                    (<?= count($items) ?> items)
                </span>
            </h4>
            
            <?php if (count($items) > 0): ?>
            <div class="items-table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="min-width:200px;">Item Name</th>
                            <th style="min-width:80px;">Type</th>
                            <th style="text-align:right;min-width:60px;">Qty</th>
                            <th style="text-align:right;min-width:100px;">Unit Price</th>
                            <th style="text-align:right;min-width:100px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="item-name"><?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? 'N/A') ?></span>
                                    <?php if (!empty($item['instructions'])): ?>
                                        <span class="item-instruction">
                                            <i class="fas fa-edit"></i> <?= htmlspecialchars($item['instructions']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['medicine_name']) || !empty($item['inventory_id'])): ?>
                                        <span class="item-type-badge">💊 Medication</span>
                                    <?php else: ?>
                                        <span class="item-type-badge">📦 Product</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right"><?= $item['quantity'] ?? 1 ?></td>
                                <td class="text-right font-mono"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td class="text-right font-mono" style="font-weight:600;color:var(--otc-color);">
                                    <?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-4" style="color:var(--text-secondary);border:1px solid var(--border-color);border-radius:var(--radius);">
                    <i class="fas fa-box-open mr-1"></i> No items found in this sale
                </div>
            <?php endif; ?>
            
            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-box">
                    <?php if (($sale['subtotal'] ?? 0) > 0 && ($sale['discount_amount'] ?? 0) > 0): ?>
                        <div class="total-row">
                            <span class="label">Subtotal</span>
                            <span class="value"><?= $currency ?> <?= number_format($sale['subtotal'] ?? 0, 0) ?></span>
                        </div>
                        <div class="total-row discount">
                            <span class="label">Discount</span>
                            <span class="value">-<?= $currency ?> <?= number_format($sale['discount_amount'] ?? 0, 0) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="total-row grand-total">
                        <span class="label">Total Amount</span>
                        <span class="value"><?= $currency ?> <?= number_format($sale['total_amount'] ?? 0, 0) ?></span>
                    </div>
                    <?php if ($sale['payment_status'] === 'paid'): ?>
                        <div class="total-row paid" style="border-top:1px solid var(--border-color);padding-top:6px;margin-top:4px;">
                            <span class="label">Payment Status</span>
                            <span class="value" style="color:var(--success);">✅ Paid</span>
                        </div>
                    <?php else: ?>
                        <div class="total-row" style="border-top:1px solid var(--border-color);padding-top:6px;margin-top:4px;">
                            <span class="label">Payment Status</span>
                            <span class="value" style="color:var(--warning);">⏳ Pending</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($sale['payment_status'] === 'pending'): ?>
                    <a href="process_otc_payment.php?sale_id=<?= $sale_id ?>" class="btn btn-success">
                        <i class="fas fa-money-bill-wave"></i> Process Payment
                    </a>
                <?php endif; ?>
                <a href="pending_bills.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Pending
                </a>
                <button onclick="window.print()" class="btn btn-otc">
                    <i class="fas fa-print"></i> Print
                </button>
                <?php if ($is_admin): ?>
                    <a href="delete_otc_sale.php?id=<?= $sale_id ?>" class="btn btn-outline" style="border-color:var(--danger);color:var(--danger);" onclick="return confirm('Are you sure you want to delete this OTC sale?')">
                        <i class="fas fa-trash-alt"></i> Delete
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-shopping-cart"></i>
            <h3>OTC Sale Not Found</h3>
            <p>The OTC sale you are looking for does not exist or you don't have permission to view it.</p>
            <a href="pending_bills.php" class="btn btn-otc mt-4">
                <i class="fas fa-arrow-left"></i> Back to Pending Bills
            </a>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            OTC Sale Details
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
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
    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        syncDarkMode();
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDarkMode();
        });
        document.addEventListener('darkModeChanged', function(e) {
            if (e.detail && e.detail.isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        });
    })();

    // ================================================================
    // SIDEBAR TOGGLE
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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
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

    console.log('%c🛒 Braick - View OTC Sale (All Items)', 'font-size:18px; font-weight:bold; color:#8B5CF6;');
    console.log('%c✅ Shows all items for the OTC sale', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Shows customer information', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Shows totals and payment status', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🛒 Sale: <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📦 Items: <?= count($items) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>