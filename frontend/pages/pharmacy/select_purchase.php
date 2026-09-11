<?php
// ================================================================
// FILE: frontend/pages/pharmacy/select_purchase.php
// PHARMACY - SELECT PURCHASE (JOIN OR CREATE NEW)
// Shows all IN_PROGRESS purchases for this branch
// ✅ Medicine = Green theme, Equipment = Purple theme
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PURCHASE TYPE FROM URL
// ================================================================
$purchase_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';
if (!in_array($purchase_type, ['medicine', 'equipment'])) {
    $purchase_type = 'medicine';
}

$is_medicine = ($purchase_type === 'medicine');
$type_label = $is_medicine ? 'Medicine' : 'Equipment';
$type_icon = $is_medicine ? 'fa-pills' : 'fa-tools';

// Theme colors based on type
$theme_color = $is_medicine ? '#059669' : '#7C3AED';      // Green vs Purple
$theme_dark = $is_medicine ? '#047857' : '#6D28D9';
$theme_light = $is_medicine ? '#D1FAE5' : '#EDE9FE';      // Light green vs Light purple

// ================================================================
// UNIQUE INVOICE NUMBER GENERATOR
// ================================================================
function generateUniqueInvoiceNumber($db, $prefix, $date) {
    $pattern = $prefix . '-' . $date . '-%';
    $stmt = $db->prepare("SELECT invoice_number FROM purchases WHERE invoice_number LIKE ?");
    $stmt->execute([$pattern]);
    $existing_invoices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $used_numbers = [];
    foreach ($existing_invoices as $inv) {
        if (preg_match('/-(\d+)$/', $inv, $matches)) {
            $used_numbers[(int)$matches[1]] = true;
        }
    }
    
    for ($i = 1; $i <= 9999; $i++) {
        if (!isset($used_numbers[$i])) {
            $candidate = $prefix . '-' . $date . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT COUNT(*) as c FROM purchases WHERE invoice_number = ?");
            $stmt->execute([$candidate]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] == 0) {
                return $candidate;
            }
        }
    }
    
    return $prefix . '-' . $date . '-' . substr((string)microtime(true), -6);
}

// ================================================================
// PROCESS POST - CREATE NEW PURCHASE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_new') {
    $purchase_type_post = $_POST['purchase_type'] ?? 'medicine';
    $date = date('Ymd');
    $prefix = $purchase_type_post === 'medicine' ? 'INV-MED' : 'INV-EQP';
    $invoice_number = generateUniqueInvoiceNumber($db, $prefix, $date);
    
    $max_attempts = 10;
    $inserted = false;
    $new_purchase_id = null;
    
    for ($attempt = 0; $attempt < $max_attempts && !$inserted; $attempt++) {
        try {
            $stmt = $db->prepare("
                INSERT INTO purchases (invoice_number, purchase_type, created_by, created_by_name, branch_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'IN_PROGRESS', NOW())
            ");
            $stmt->execute([$invoice_number, $purchase_type_post, $user_id, $user_full_name, $user_branch_id]);
            $new_purchase_id = $db->lastInsertId();
            $inserted = true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                usleep(100000);
                $invoice_number = generateUniqueInvoiceNumber($db, $prefix, $date);
            } else {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
                break;
            }
        }
    }
    
    if ($inserted && $new_purchase_id) {
        $_SESSION['purchase_message'] = "✅ New " . ucfirst($purchase_type_post) . " purchase created! Invoice: <strong>$invoice_number</strong>";
        $_SESSION['purchase_message_type'] = 'success';
        header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type_post);
        exit;
    }
}

// ================================================================
// GET ALL IN-PROGRESS PURCHASES FOR THIS BRANCH & TYPE
// ================================================================
$in_progress_purchases = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name,
           (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as item_count,
           (SELECT COALESCE(SUM(quantity), 0) FROM purchase_items WHERE purchase_id = p.id) as total_qty
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'IN_PROGRESS' 
      AND p.purchase_type = ?
      AND p.branch_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$purchase_type, $user_branch_id]);
$in_progress_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select <?= $type_label ?> Purchase - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            --purple-dark: #6D28D9;
            --purple-light: #EDE9FE;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            
            /* Dynamic theme colors */
            --theme-color: <?= $theme_color ?>;
            --theme-dark: <?= $theme_dark ?>;
            --theme-light: <?= $theme_light ?>;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--theme-color), var(--theme-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.25);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-left {
            position: relative;
            z-index: 1;
            flex: 1;
            min-width: 250px;
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
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .badge-white {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .page-header-right {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* ================================================================
           CREATE NEW BUTTON - THEME COLOR (Green kwa Medicine, Purple kwa Equipment)
           ================================================================ */
        .btn-create-top {
            background: white;
            color: var(--theme-color);
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
            text-decoration: none;
        }
        
        .btn-create-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            background: var(--theme-light);
            color: var(--theme-dark);
        }
        
        .btn-create-top i {
            font-size: 1rem;
        }
        
        .btn-back-top {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
        }
        
        .btn-back-top:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--theme-color);
        }
        
        .section-title .count-badge {
            background: var(--theme-light);
            color: var(--theme-color);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        
        /* Purchase Card */
        .purchase-card {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .purchase-card:hover {
            border-color: var(--theme-color);
            box-shadow: 0 6px 20px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.12);
            transform: translateY(-2px);
        }
        
        .purchase-card.own-purchase {
            border-color: var(--theme-color);
            background: linear-gradient(90deg, var(--theme-light) 0%, var(--bg-card) 100%);
        }
        
        [data-theme="dark"] .purchase-card.own-purchase {
            background: linear-gradient(90deg, <?= $is_medicine ? '#1A3A2A' : '#2D1B5F' ?> 0%, var(--bg-card) 100%);
        }
        
        .purchase-card .pc-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 250px;
        }
        
        .purchase-card .pc-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--theme-color), var(--theme-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.3);
        }
        
        .purchase-card .pc-info {
            flex: 1;
            min-width: 0;
        }
        
        .purchase-card .pc-invoice {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--theme-color);
            font-family: 'Courier New', monospace;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .purchase-card .pc-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .purchase-card .pc-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .purchase-card .pc-meta i {
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        
        .purchase-card .pc-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        
        .purchase-card .pc-stat {
            background: var(--theme-light);
            color: var(--theme-color);
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
        }
        
        .purchase-card .pc-right {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn-join {
            background: var(--theme-color);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.3);
        }
        
        .btn-join:hover {
            background: var(--theme-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.4);
            color: white;
        }
        
        .btn-continue {
            background: var(--theme-color);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.3);
        }
        
        .btn-continue:hover {
            background: var(--theme-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.4);
            color: white;
        }
        
        .empty-state-card {
            background: var(--bg-card);
            border: 3px dashed var(--theme-color);
            border-radius: 16px;
            padding: 50px 30px;
            text-align: center;
            margin-bottom: 24px;
            background: var(--theme-light);
        }
        
        [data-theme="dark"] .empty-state-card {
            background: <?= $is_medicine ? '#1A3A2A' : '#2D1B5F' ?>;
        }
        
        .empty-state-card .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--theme-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 16px;
            box-shadow: 0 6px 20px rgba(<?= $is_medicine ? '5, 150, 105' : '124, 58, 237' ?>, 0.3);
        }
        
        .empty-state-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--theme-color);
            margin-bottom: 6px;
        }
        
        .empty-state-card p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        /* ================================================================
           INFO BOX - LIGHT GREEN kwa Medicine, LIGHT PURPLE kwa Equipment
           ================================================================ */
        .info-box {
            background: <?= $is_medicine ? '#D1FAE5' : '#EDE9FE' ?>;
            border: 2px solid <?= $is_medicine ? '#059669' : '#7C3AED' ?>;
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.82rem;
            color: <?= $is_medicine ? '#065F46' : '#5B21B6' ?>;
        }
        
        .info-box i {
            color: <?= $is_medicine ? '#059669' : '#7C3AED' ?>;
            font-size: 1.1rem;
            margin-top: 2px;
        }
        
        .info-box strong {
            color: <?= $is_medicine ? '#047857' : '#6D28D9' ?>;
        }
        
        [data-theme="dark"] .info-box {
            background: <?= $is_medicine ? '#1A3A2A' : '#2D1B5F' ?>;
            border-color: <?= $is_medicine ? '#34D399' : '#A78BFA' ?>;
            color: <?= $is_medicine ? '#D1FAE5' : '#EDE9FE' ?>;
        }
        
        [data-theme="dark"] .info-box i {
            color: <?= $is_medicine ? '#34D399' : '#A78BFA' ?>;
        }
        
        [data-theme="dark"] .info-box strong {
            color: <?= $is_medicine ? '#6EE7B7' : '#C4B5FD' ?>;
        }
        
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 30px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--theme-color);
            font-weight: 600;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 20px 18px; flex-direction: column; align-items: stretch; }
            .page-header .page-title { font-size: 1.2rem; }
            .page-header-right { flex-direction: column; align-items: stretch; width: 100%; }
            .page-header-right .btn-create-top,
            .page-header-right .btn-back-top { width: 100%; justify-content: center; }
            .purchase-card { flex-direction: column; align-items: stretch; }
            .purchase-card .pc-right { justify-content: stretch; }
            .purchase-card .pc-right .btn-join,
            .purchase-card .pc-right .btn-continue { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header animate-fade-in-up">
        <div class="page-header-left">
            <div class="page-title">
                <i class="fas <?= $type_icon ?>"></i>
                Select <?= $type_label ?> Purchase
            </div>
            <div class="page-subtitle">
                <span class="badge-white">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="badge-white">
                    <i class="fas fa-spinner"></i> <?= count($in_progress_purchases) ?> In Progress
                </span>
                <span class="badge-white" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </div>
        </div>
        
        <!-- ✅ CREATE NEW & BACK BUTTONS - JUU -->
        <div class="page-header-right">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="create_new">
                <input type="hidden" name="purchase_type" value="<?= $purchase_type ?>">
                <button type="submit" class="btn-create-top">
                    <i class="fas fa-plus-circle"></i> Create New Purchase
                </button>
            </form>
            
            <a href="inventory.php?tab=<?= $purchase_type === 'medicine' ? 'medicines' : 'equipment' ?>" class="btn-back-top">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="info-box" style="background: <?= $message_type === 'error' ? 'var(--danger-light)' : 'var(--success-light)' ?>; border-color: <?= $message_type === 'error' ? 'var(--danger)' : 'var(--success)' ?>;">
            <i class="fas <?= $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         INFO BOX - Light Green (Medicine) au Light Purple (Equipment)
         ================================================================ -->
    <div class="info-box animate-fade-in-up">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>You can join an existing purchase</strong> or create a new one. 
            All purchases shown here are for <strong><?= $type_label ?></strong> only 
            and have status <strong>IN_PROGRESS</strong> for branch <strong><?= htmlspecialchars($user_branch_name) ?></strong>.
        </div>
    </div>

    <!-- IN-PROGRESS PURCHASES SECTION -->
    <div class="animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="section-title">
            <i class="fas fa-list-check"></i>
            In-Progress Purchases
            <span class="count-badge"><?= count($in_progress_purchases) ?></span>
        </div>

        <?php if (count($in_progress_purchases) > 0): ?>
            <?php foreach ($in_progress_purchases as $purchase): 
                $is_owner = ($purchase['created_by'] == $user_id);
            ?>
                <div class="purchase-card <?= $is_owner ? 'own-purchase' : '' ?>">
                    <div class="pc-left">
                        <div class="pc-icon">
                            <i class="fas <?= $is_owner ? 'fa-crown' : 'fa-file-invoice' ?>"></i>
                        </div>
                        <div class="pc-info">
                            <div class="pc-invoice">
                                <?= htmlspecialchars($purchase['invoice_number']) ?>
                                <?php if ($is_owner): ?>
                                    <span style="background:var(--theme-color);color:white;padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:700;">
                                        <i class="fas fa-crown"></i> YOUR PURCHASE
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="pc-meta">
                                <span>
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($purchase['creator_name'] ?? $purchase['created_by_name'] ?? 'Unknown') ?>
                                </span>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                </span>
                            </div>
                            <div class="pc-stats">
                                <span class="pc-stat">
                                    <i class="fas fa-boxes"></i> <?= (int)$purchase['item_count'] ?> items
                                </span>
                                <span class="pc-stat">
                                    <i class="fas fa-cubes"></i> <?= (int)$purchase['total_qty'] ?> units
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pc-right">
                        <?php if ($is_owner): ?>
                            <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase_type ?>" class="btn-continue">
                                <i class="fas fa-arrow-right"></i> Continue
                            </a>
                        <?php else: ?>
                            <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase_type ?>" class="btn-join">
                                <i class="fas fa-sign-in-alt"></i> Join
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- EMPTY STATE - NO IN-PROGRESS PURCHASE -->
            <div class="empty-state-card">
                <div class="empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No <?= $type_label ?> Purchase In Progress</h3>
                <p>There is no ongoing <?= strtolower($type_label) ?> purchase for this branch.</p>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0;">
                    Click "Create New Purchase" button above to start one.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Select <?= $type_label ?> Purchase
            <span class="text-gray-400 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    console.log('%c💊 Braick - Select Purchase', 'font-size:18px; font-weight:bold; color:<?= $theme_color ?>;');
    console.log('%c📋 Purchase Type: <?= $type_label ?>', 'font-size:13px; color:<?= $theme_color ?>;');
    console.log('%c📊 In-Progress: <?= count($in_progress_purchases) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Theme: <?= $is_medicine ? 'GREEN (Medicine)' : 'PURPLE (Equipment)' ?>', 'font-size:13px; color:<?= $theme_color ?>; font-weight:bold;');
</script>

</body>
</html>