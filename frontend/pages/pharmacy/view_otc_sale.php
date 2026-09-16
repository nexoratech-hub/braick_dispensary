<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_otc_sale.php
// PHARMACY - VIEW OTC SALE DETAILS
// ✅ BLUE THEME
// ✅ Print Receipt
// ✅ View Sale Details
// ✅ Auto-dismiss messages
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
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
    $_SESSION['otc_sale_message'] = 'Invalid sale ID';
    $_SESSION['otc_sale_message_type'] = 'error';
    $_SESSION['otc_sale_message_time'] = time();
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET SALE DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        os.*,
        u.full_name as sold_by_name,
        u.username as sold_by_username,
        b.bill_number,
        b.status as bill_status,
        b.paid_amount,
        b.balance,
        br.name as branch_name,
        br.location as branch_location,
        br.phone as branch_phone,
        br.email as branch_email
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN bills b ON os.bill_id = b.id
    LEFT JOIN branches br ON os.branch_id = br.id
    WHERE os.id = ? AND os.branch_id = ?
");
$stmt->execute([$sale_id, $user_branch_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    $_SESSION['otc_sale_message'] = 'Sale not found';
    $_SESSION['otc_sale_message_type'] = 'error';
    $_SESSION['otc_sale_message_time'] = time();
    header('Location: otc_history.php');
    exit;
}

// ================================================================
// GET SALE ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        oi.*,
        mi.medication_name,
        mi.batch_number,
        mi.expiry_date,
        mi.category
    FROM otc_sale_items oi
    LEFT JOIN medications_inventory mi ON oi.inventory_id = mi.id
    WHERE oi.sale_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$sale_id]);
$sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PAYMENT DETAILS (if any)
// ================================================================
$payment = null;
if (!empty($sale['bill_id'])) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as received_by_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ?
        ORDER BY p.received_at DESC
        LIMIT 1
    ");
    $stmt->execute([$sale['bill_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// STATISTICS
// ================================================================
$total_items = count($sale_items);
$total_quantity = 0;
foreach ($sale_items as $item) {
    $total_quantity += (int)$item['quantity'];
}

$subtotal = (float)($sale['subtotal'] ?? 0);
$discount = (float)($sale['discount_amount'] ?? 0);
$premium = (float)($sale['premium_amount'] ?? 0);
$grand_total = (float)($sale['total_amount'] ?? 0);

// ================================================================
// SIDEBAR STATS
// ================================================================
$low_stock_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) { $low_stock_count = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light';

// Status class
$status_class = 'badge-warning';
$status_label = ucfirst($sale['payment_status'] ?? 'Pending');
if ($sale['payment_status'] === 'paid') {
    $status_class = 'badge-success';
} elseif ($sale['payment_status'] === 'cancelled') {
    $status_class = 'badge-danger';
} elseif ($sale['payment_status'] === 'partial') {
    $status_class = 'badge-info';
}

include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View OTC Sale - <?= htmlspecialchars($sale['sale_number']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A3D8A;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --gold: #F59E0B;
            --gold-light: #FEF3C7;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --primary-light: #1E3A5F;
            --success-light: #1A3A2A;
            --warning-light: #3D2E0A;
            --danger-light: #3A1A1A;
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
        
        /* ✅ PAGE HEADER - BLUE THEME */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A3D8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
            color: white;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%; right: -10%;
            width: 400px; height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
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
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .page-subtitle strong { color: white; font-weight: 600; }
        
        .page-header .stat-chip {
            background: rgba(255,255,255,0.12);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* CARD */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i { font-size: 1.1rem; }
        
        /* INFO GRID */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-item .info-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-item .info-label i {
            color: var(--primary);
            font-size: 0.75rem;
        }
        
        .info-item .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-word;
        }
        
        .info-item .info-value.big {
            font-size: 1.1rem;
            color: var(--primary);
            font-weight: 800;
        }
        
        /* STATUS BADGE */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-badge.paid {
            background: var(--success-light);
            color: var(--success);
            border: 2px solid var(--success);
        }
        
        .status-badge.pending {
            background: var(--warning-light);
            color: var(--warning);
            border: 2px solid var(--warning);
        }
        
        .status-badge.partial {
            background: var(--primary-light);
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .status-badge.cancelled {
            background: var(--danger-light);
            color: var(--danger);
            border: 2px solid var(--danger);
        }
        
        /* ITEMS TABLE */
        .items-table-wrap {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .items-table-wrap::-webkit-scrollbar { height: 6px; width: 6px; }
        .items-table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .items-table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .items-table thead th {
            background: var(--primary);
            color: white;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            text-align: left;
            white-space: nowrap;
        }
        
        .items-table thead th:first-child { border-radius: 10px 0 0 0; }
        .items-table thead th:last-child { border-radius: 0 10px 0 0; }
        
        .items-table thead th.center { text-align: center; }
        .items-table thead th.right { text-align: right; }
        
        .items-table tbody tr:nth-child(even) { background: var(--primary-light); }
        .items-table tbody tr:hover td { background: var(--success-light); }
        
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-table td.center { text-align: center; }
        .items-table td.right { text-align: right; }
        
        .items-table tbody tr:last-child td { border-bottom: none; }
        
        /* BATCH BADGE */
        .batch-badge {
            font-family: 'Courier New', monospace;
            font-size: 0.65rem;
            font-weight: 600;
            background: var(--primary-light);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--primary);
            display: inline-block;
        }
        
        /* QTY BADGE */
        .qty-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-light);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid var(--primary);
        }
        
        /* SUMMARY BOX */
        .summary-box {
            background: var(--primary-light);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 16px;
        }
        
        [data-theme="dark"] .summary-box {
            background: linear-gradient(135deg, #1E3A5F, #16294A);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 0.85rem;
            border-bottom: 1px dashed var(--border-color);
        }
        
        .summary-row:last-child {
            border-bottom: none;
            padding-top: 12px;
            margin-top: 8px;
            border-top: 2px solid var(--primary);
        }
        
        .summary-row .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .summary-row .value {
            color: var(--text-primary);
            font-weight: 700;
        }
        
        .summary-row.total .label {
            color: var(--primary);
            font-weight: 800;
            font-size: 1rem;
        }
        
        .summary-row.total .value {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.2rem;
        }
        
        .summary-row.discount .value { color: var(--warning); }
        .summary-row.premium .value { color: var(--danger); }
        
        /* ACTION BUTTONS */
        .btn {
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .btn-print {
            background: #0A3D8A;
            color: white;
        }
        
        .btn-print:hover {
            background: #083A80;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(10, 61, 138, 0.3);
        }
        
        /* ACTION BAR */
        .action-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .empty-state p { font-size: 0.85rem; }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; justify-content: center; }
            .items-table { font-size: 0.7rem; }
            .items-table th, .items-table td { padding: 8px 8px; }
        }
        
        @media (max-width: 480px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        
        /* PRINT STYLES */
        @media print {
            .main-content { margin: 0; padding: 0; }
            .page-header, .action-bar, .footer, .no-print { display: none !important; }
            .card { border: 1px solid #ccc; box-shadow: none; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ✅ PAGE HEADER - BLUE THEME -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                OTC Sale Details
            </h1>
            <p class="page-subtitle">
                Sale Number: <strong><?= htmlspecialchars($sale['sale_number']) ?></strong>
                <span class="stat-chip">
                    <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?>
                </span>
                <span class="stat-chip">
                    <i class="fas fa-pills"></i> <?= $total_quantity ?> items
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="print_otc_receipt.php?id=<?= $sale_id ?>" class="btn-outline-light" target="_blank">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <a href="otc_history.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to History
            </a>
        </div>
    </div>

    <!-- SALE INFO -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-info-circle"></i>
                Sale Information
            </h2>
            <span class="status-badge <?= $sale['payment_status'] ?? 'pending' ?>">
                <i class="fas <?= $sale['payment_status'] === 'paid' ? 'fa-check-circle' : ($sale['payment_status'] === 'cancelled' ? 'fa-times-circle' : 'fa-clock') ?>"></i>
                <?= $status_label ?>
            </span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label"><i class="fas fa-hashtag"></i> Sale Number</span>
                <span class="info-value"><?= htmlspecialchars($sale['sale_number']) ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-user"></i> Customer Name</span>
                <span class="info-value"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-phone"></i> Customer Phone</span>
                <span class="info-value"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-credit-card"></i> Payment Method</span>
                <span class="info-value"><?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'N/A')) ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-user-tie"></i> Sold By</span>
                <span class="info-value"><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-building"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar-plus"></i> Date & Time</span>
                <span class="info-value"><?= date('d M, Y - H:i A', strtotime($sale['created_at'])) ?></span>
            </div>
            
            <?php if (!empty($sale['bill_number'])): ?>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-file-invoice"></i> Bill Number</span>
                <span class="info-value"><?= htmlspecialchars($sale['bill_number']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-boxes"></i> Total Items</span>
                <span class="info-value big"><?= $total_items ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="fas fa-pills"></i> Total Quantity</span>
                <span class="info-value big"><?= number_format($total_quantity) ?></span>
            </div>
        </div>
        
        <?php if (!empty($sale['notes'])): ?>
        <div style="margin-top:16px;padding:12px 16px;background:var(--bg-body);border-radius:8px;border-left:4px solid var(--primary);">
            <div class="info-label" style="margin-bottom:4px;"><i class="fas fa-sticky-note"></i> Notes</div>
            <div style="font-size:0.85rem;color:var(--text-secondary);"><?= nl2br(htmlspecialchars($sale['notes'])) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ITEMS TABLE -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-pills"></i>
                Dispensed Items (<?= $total_items ?>)
            </h2>
            <span style="font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                Total Quantity: <strong style="color:var(--primary);"><?= number_format($total_quantity) ?></strong>
            </span>
        </div>
        
        <?php if (count($sale_items) > 0): ?>
            <div class="items-table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;" class="center">#</th>
                            <th>Medicine / Item</th>
                            <th>Batch Number</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Route</th>
                            <th class="center">Qty</th>
                            <th class="right">Unit Price</th>
                            <th class="right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($sale_items as $item): ?>
                            <tr>
                                <td class="center"><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name'] ?? $item['medication_name'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($item['category'])): ?>
                                        <div style="font-size:0.65rem;color:var(--text-muted);">
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($item['category']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['instructions'])): ?>
                                        <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">
                                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($item['instructions']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-badge"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:0.7rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.75rem;"><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                <td style="font-size:0.75rem;"><?= htmlspecialchars($item['frequency'] ?? '—') ?></td>
                                <td style="font-size:0.75rem;"><?= htmlspecialchars($item['route'] ?? '—') ?></td>
                                <td class="center">
                                    <span class="qty-badge">
                                        <i class="fas fa-pills"></i> <?= number_format($item['quantity']) ?>
                                    </span>
                                </td>
                                <td class="right" style="font-size:0.75rem;">TSh <?= number_format($item['unit_price'], 0) ?></td>
                                <td class="right">
                                    <strong style="color:var(--primary);">TSh <?= number_format($item['total_price'], 0) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No items found for this sale</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- PAYMENT SUMMARY -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-calculator"></i>
                Payment Summary
            </h2>
        </div>
        
        <div class="summary-box">
            <div class="summary-row">
                <span class="label"><i class="fas fa-receipt"></i> Subtotal</span>
                <span class="value">TSh <?= number_format($subtotal, 0) ?></span>
            </div>
            
            <?php if ($discount > 0): ?>
            <div class="summary-row discount">
                <span class="label"><i class="fas fa-tag"></i> Discount</span>
                <span class="value">- TSh <?= number_format($discount, 0) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($premium > 0): ?>
            <div class="summary-row premium">
                <span class="label"><i class="fas fa-plus-circle"></i> Premium Charge</span>
                <span class="value">+ TSh <?= number_format($premium, 0) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($sale['premium_note'])): ?>
            <div class="summary-row" style="font-size:0.75rem;font-style:italic;color:var(--text-muted);">
                <span class="label" style="font-size:0.75rem;">Note:</span>
                <span class="value" style="font-size:0.75rem;font-weight:500;"><?= htmlspecialchars($sale['premium_note']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="summary-row total">
                <span class="label"><i class="fas fa-money-bill-wave"></i> Grand Total</span>
                <span class="value">TSh <?= number_format($grand_total, 0) ?></span>
            </div>
        </div>
        
        <?php if ($payment): ?>
        <div style="margin-top:16px;padding:12px 16px;background:var(--success-light);border-radius:8px;border-left:4px solid var(--success);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <div class="info-label" style="color:var(--success);"><i class="fas fa-check-circle"></i> Payment Received</div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:2px;">
                        Receipt: <strong><?= htmlspecialchars($payment['receipt_number']) ?></strong> 
                        | Received by: <strong><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></strong>
                        | <?= date('d M, Y - H:i A', strtotime($payment['received_at'])) ?>
                    </div>
                </div>
                <div style="font-size:1.2rem;font-weight:800;color:var(--success);">
                    TSh <?= number_format($payment['amount'], 0) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-bar no-print">
        <a href="otc_history.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to History
        </a>
        <a href="print_otc_receipt.php?id=<?= $sale_id ?>" class="btn btn-print" target="_blank">
            <i class="fas fa-print"></i> Print Receipt
        </a>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            OTC Sale Details
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // AUTO-DISMISS MESSAGE (kama ipo)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) {
            var dismissTimer = setTimeout(function() {
                messageBox.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                messageBox.style.opacity = '0';
                messageBox.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    messageBox.style.display = 'none';
                }, 500);
            }, 5000);
        }
    });
    
    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+P - Print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            // Allow default print
        }
        
        // ESC - Back to history
        if (e.key === 'Escape') {
            window.location.href = 'otc_history.php';
        }
    });
    
    console.log('%c💊 Braick - OTC Sale Details (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Sale details displayed', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Items list with batch numbers', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Payment summary', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Print receipt button', 'font-size:13px; color:#34D399;');
    console.log('%c✅ ESC to go back', 'font-size:13px; color:#FCD34D;');
</script>

</body>
</html>