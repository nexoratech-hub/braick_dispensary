<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_otc_sale.php
// PHARMACY - VIEW OTC SALE DETAILS (NO FINANCIAL DATA)
// UPDATED FOR NEW DATABASE: dispensary_db
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
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
// CHECK IF USER HAS PHARMACY ACCESS
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($user_username) && !empty($user_username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$user_username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $profile_pic = $user['profile_pic'];
                
                // Get branch name
                $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
                $stmt->execute([$user_branch_id]);
                $branch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($branch) {
                    $_SESSION['branch_name'] = $branch['name'];
                    $user_branch_name = $branch['name'];
                }
            }
        } catch (Exception $e) {
            // Fallback to session values
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
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
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET OTC SALE DETAILS - NEW DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT 
        os.*,
        u.full_name as cashier_name,
        b.name as branch_name
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches b ON os.branch_id = b.id
    WHERE os.id = ? AND os.branch_id = ?
");
$stmt->execute([$sale_id, $user_branch_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET SALE ITEMS - NEW DATABASE
// ================================================================
$stmt = $db->prepare("
    SELECT 
        osi.*,
        mi.medication_name as inventory_medication_name
    FROM otc_sale_items osi
    LEFT JOIN medications_inventory mi ON osi.inventory_id = mi.id
    WHERE osi.sale_id = ?
    ORDER BY osi.id ASC
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC Sale Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
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
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
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
        
        .btn-action {
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: #047857;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #B91C1C;
        }
        
        /* ================================================================
           DETAIL CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .detail-card .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .detail-card .detail-header .sale-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: monospace;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-card .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .detail-card .detail-grid .info-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .detail-card .detail-grid .info-item .value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .detail-card .detail-grid .info-item .sub-value {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .type-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        [data-theme="dark"] .type-badge {
            background: #2A1A3A;
            color: #9B4DCA;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.paid {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .status-badge.partial {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .status-badge.paid {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .status-badge.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge.cancelled {
            background: #3A1A1A;
            color: #F87171;
        }
        
        [data-theme="dark"] .status-badge.partial {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .medicines-list {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
        }
        
        .medicines-list .medicine-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        
        .medicines-list .medicine-item:last-child {
            border-bottom: none;
        }
        
        .medicines-list .medicine-item .med-name {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .medicines-list .medicine-item .med-details {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .summary-box {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
            border: 1px solid var(--border-color);
        }
        
        .summary-box .total-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .summary-box .total-items {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .summary-box .total-items strong {
            color: var(--primary);
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
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
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-card .detail-grid {
                grid-template-columns: 1fr 1fr;
            }
            .detail-card {
                padding: 16px 18px;
            }
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .action-buttons .btn-action {
                justify-content: center;
            }
            .summary-box {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .detail-card .detail-grid {
                grid-template-columns: 1fr;
            }
            .medicines-list .medicine-item {
                flex-direction: column;
                gap: 2px;
            }
        }
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
                <i class="fas fa-shopping-cart"></i>
                OTC Sale Details
                <span class="role-badge-display">PHARMACY</span>
            </h1>
            <p class="page-subtitle">
                View OTC sale information
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="type-badge" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-shopping-cart"></i> OTC
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="otc_history.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALE DETAILS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="detail-header">
            <div class="sale-number">
                <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>
                <span class="status-badge <?= $sale['payment_status'] ?? 'pending' ?>">
                    <?php 
                        $status = $sale['payment_status'] ?? 'pending';
                        if ($status === 'paid') {
                            echo '<i class="fas fa-check-circle"></i> Paid';
                        } elseif ($status === 'pending') {
                            echo '<i class="fas fa-clock"></i> Pending';
                        } elseif ($status === 'cancelled') {
                            echo '<i class="fas fa-times-circle"></i> Cancelled';
                        } elseif ($status === 'partial') {
                            echo '<i class="fas fa-money-bill-wave"></i> Partial';
                        } else {
                            echo ucfirst($status);
                        }
                    ?>
                </span>
            </div>
            <div class="text-sm text-gray-400">
                <i class="fas fa-calendar-alt mr-1"></i>
                <?= date('M d, Y h:i A', strtotime($sale['created_at'] ?? 'now')) ?>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="detail-grid">
            <div class="info-item">
                <div class="label">Customer</div>
                <div class="value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                <div class="sub-value">Phone: <?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="label">Cashier</div>
                <div class="value"><?= htmlspecialchars($sale['cashier_name'] ?? 'Unknown') ?></div>
                <div class="sub-value">ID: <?= $sale['sold_by'] ?? 'N/A' ?></div>
            </div>
            <div class="info-item">
                <div class="label">Branch</div>
                <div class="value"><?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Payment Method</div>
                <div class="value">
                    <span class="status-badge paid">
                        <?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'cash')) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- ITEMS LIST - NO FINANCIAL DATA -->
        <!-- ================================================================ -->
        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-3">
            <i class="fas fa-pills mr-2" style="color: var(--primary);"></i>
            Medicines Dispensed
        </h4>
        
        <?php if (count($items) > 0): ?>
            <div class="medicines-list">
                <?php foreach ($items as $item): ?>
                    <div class="medicine-item">
                        <span class="med-name">
                            <?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? 'N/A') ?>
                        </span>
                        <span class="med-details">
                            Quantity: <strong><?= $item['quantity'] ?? 0 ?></strong>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No medicines found for this sale</p>
            </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- SUMMARY - NO FINANCIAL DATA -->
        <!-- ================================================================ -->
        <div class="summary-box">
            <span class="total-label">
                <i class="fas fa-pills mr-1"></i> Total Items
            </span>
            <span class="total-items">
                <strong><?= count($items) ?></strong> medicine(s)
            </span>
        </div>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS -->
        <!-- ================================================================ -->
        <div class="action-buttons">
            <a href="print_receipt.php?type=otc&id=<?= $sale['id'] ?>" class="btn-action btn-success" target="_blank">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <a href="otc_history.php" class="btn-action btn-outline">
                <i class="fas fa-arrow-left"></i> Back to History
            </a>
            <?php if (($sale['payment_status'] ?? '') === 'pending'): ?>
                <button onclick="confirmCancel(<?= $sale['id'] ?>)" class="btn-action btn-danger">
                    <i class="fas fa-times"></i> Cancel Sale
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            OTC Sale Details
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTime"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggleBtn');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
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
        var ftEl = document.getElementById('footerTime');
        if (ftEl) {
            ftEl.textContent = timeStr;
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

    // ================================================================
    // CANCEL SALE
    // ================================================================
    function confirmCancel(saleId) {
        if (confirm('Are you sure you want to cancel this OTC sale?')) {
            window.location.href = 'cancel_otc_sale.php?id=' + saleId;
        }
    }

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💊 Braick - View OTC Sale (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Sale #: <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Items: <?= count($items) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🚫 No financial data shown', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Uses shared header with date & time', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>