<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_inventory.php
// PHARMACY - VIEW INVENTORY ITEM DETAILS
// Shows single inventory item with all details
// NO ADD OR EDIT BUTTONS - Read Only
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

// ================================================================
// GET INVENTORY ID
// ================================================================
$inventory_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($inventory_id <= 0) {
    header('Location: inventory.php?error=invalid_id');
    exit;
}

// ================================================================
// GET INVENTORY DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT *, 
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE id = ? AND branch_id = ?
");
$stmt->execute([$inventory_id, $user_branch_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: inventory.php?error=not_found');
    exit;
}

// ================================================================
// GET STOCK MOVEMENT HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM stock_movements 
    WHERE inventory_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$inventory_id]);
$stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET SALE HISTORY (OTC sales)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        os.sale_number,
        os.customer_name,
        os.created_at as sale_date,
        os.total_amount,
        os.discount_amount,
        os.net_amount,
        os.payment_method,
        os.payment_status,
        oi.quantity,
        oi.unit_price,
        oi.total_price
    FROM otc_sale_items oi
    JOIN otc_sales os ON oi.sale_id = os.id
    WHERE oi.inventory_id = ?
    ORDER BY os.created_at DESC
    LIMIT 20
");
$stmt->execute([$inventory_id]);
$sale_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTION HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT 
        p.prescription_number,
        p.medication,
        p.quantity,
        p.dosage,
        p.frequency,
        p.duration,
        p.created_at as prescription_date,
        p.status,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        u.full_name as doctor_name
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.medication LIKE ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$search_term = '%' . $item['medication_name'] . '%';
$stmt->execute([$search_term]);
$prescription_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'pending' => 'badge-warning',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStockStatusClass($quantity, $reorder_level) {
    if ($quantity <= 0) {
        return 'danger';
    } elseif ($quantity <= $reorder_level) {
        return 'warning';
    } else {
        return 'success';
    }
}

function getStockStatusLabel($quantity, $reorder_level) {
    if ($quantity <= 0) {
        return 'Out of Stock';
    } elseif ($quantity <= $reorder_level) {
        return 'Low Stock';
    } else {
        return 'In Stock';
    }
}

function getStockStatusIcon($quantity, $reorder_level) {
    if ($quantity <= 0) {
        return 'fa-times-circle';
    } elseif ($quantity <= $reorder_level) {
        return 'fa-exclamation-triangle';
    } else {
        return 'fa-check-circle';
    }
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y', strtotime($datetime));
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
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
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inventory - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
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
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --purple: #7C3AED;
            --purple-light: #EDE9FE;
            --gold: #F59E0B;
            --gold-light: #FEF3C7;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
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
        
        /* Header */
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
            position: relative;
            overflow: hidden;
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
        
        .page-header .btn-outline-light {
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .page-header .badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            padding: 24px 28px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* Detail Rows */
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        /* Badges */
        .badge-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-light); color: var(--success); border: 1px solid var(--success); }
        .badge-warning { background: var(--warning-light); color: var(--warning); border: 1px solid var(--warning); }
        .badge-danger { background: var(--danger-light); color: var(--danger); border: 1px solid var(--danger); }
        .badge-info { background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); }
        
        /* Stock Status */
        .stock-status-large {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .stock-status-large.success {
            background: var(--success-light);
            color: var(--success);
            border: 2px solid var(--success);
        }
        
        .stock-status-large.warning {
            background: var(--warning-light);
            color: var(--warning);
            border: 2px solid var(--warning);
            animation: pulse-low 1.5s infinite;
        }
        
        .stock-status-large.danger {
            background: var(--danger-light);
            color: var(--danger);
            border: 2px solid var(--danger);
            animation: pulse-low 1s infinite;
        }
        
        @keyframes pulse-low {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Expiry */
        .expiry-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .expiry-status.valid {
            background: var(--success-light);
            color: var(--success);
        }
        
        .expiry-status.expiring {
            background: var(--warning-light);
            color: var(--warning);
            animation: pulse-low 1.5s infinite;
        }
        
        .expiry-status.expired {
            background: var(--danger-light);
            color: var(--danger);
            animation: pulse-low 1s infinite;
        }
        
        .expiry-status.no-expiry {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        /* Table */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        .data-table thead th:last-child { text-align: right; }
        .data-table tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody td:last-child { text-align: right; font-weight: 600; font-family: monospace; }
        .data-table tbody tr:hover td { background: var(--primary-light); }
        .data-table .total-row td {
            font-weight: 700;
            border-top: 2px solid var(--primary);
            background: var(--primary-light);
            padding: 8px 12px;
        }
        
        /* Buttons - Only View/Back buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
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
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
        }
        
        /* Empty State */
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
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        /* READ ONLY BADGE */
        .read-only-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Footer */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .card { padding: 16px; }
            .stock-status-large { font-size: 0.85rem; padding: 6px 14px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-title { font-size: 1.1rem; }
            .btn { width: 100%; justify-content: center; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 4px 6px; }
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-pills"></i>
                Inventory Details
                <span class="badge-display"><?= htmlspecialchars($item['medication_name']) ?></span>
                <span class="badge-status <?= getStatusBadgeClass($item['status'] ?? 'active') ?>" style="background:rgba(255,255,255,0.2);color:white;border-color:rgba(255,255,255,0.3);">
                    <?= ucfirst($item['status'] ?? 'Active') ?>
                </span>
                <span class="read-only-badge">
                    <i class="fas fa-eye"></i> Read Only
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-barcode"></i> ID: #<?= $item['id'] ?>
                </span>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="low_stock.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Low Stock
            </a>
            <a href="inventory.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BASIC INFORMATION -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-info-circle"></i>
            Basic Information
        </h3>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;">
            <div class="detail-row"><span class="detail-label">Medicine Name</span><span class="detail-value"><strong><?= htmlspecialchars($item['medication_name']) ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Category</span><span class="detail-value"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Unit</span><span class="detail-value"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></span></div>
            <div class="detail-row"><span class="detail-label">Strength / Dosage</span><span class="detail-value"><?= htmlspecialchars($item['strength'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Supplier</span><span class="detail-value"><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Batch Number</span><span class="detail-value"><?= !empty($item['batch_number']) ? '<span class="batch-number" style="font-family:monospace;font-weight:600;background:var(--primary-light);padding:2px 10px;border-radius:4px;color:var(--primary);">' . htmlspecialchars($item['batch_number']) . '</span>' : 'N/A' ?></span></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STOCK INFORMATION -->
    <!-- ================================================================ -->
    <div class="card" style="border-color: <?= $item['quantity'] <= 0 ? 'var(--danger)' : ($item['quantity'] <= $item['reorder_level'] ? 'var(--warning)' : 'var(--success)') ?>; border-left: 6px solid <?= $item['quantity'] <= 0 ? 'var(--danger)' : ($item['quantity'] <= $item['reorder_level'] ? 'var(--warning)' : 'var(--success)') ?>;">
        <h3 class="card-title">
            <i class="fas fa-warehouse"></i>
            Stock Information
            <span class="badge-status <?= getStatusBadgeClass($item['status'] ?? 'active') ?>">
                <?= ucfirst($item['status'] ?? 'Active') ?>
            </span>
        </h3>
        
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px 20px;">
            <div class="detail-row"><span class="detail-label">Current Quantity</span>
                <span class="detail-value">
                    <strong style="font-size:1.2rem;color: <?= $item['quantity'] <= 0 ? 'var(--danger)' : ($item['quantity'] <= $item['reorder_level'] ? 'var(--warning)' : 'var(--success)') ?>;">
                        <?= $item['quantity'] ?>
                    </strong>
                    <span class="text-xs text-gray-400 ml-1"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></span>
                </span>
            </div>
            <div class="detail-row"><span class="detail-label">Reorder Level</span><span class="detail-value"><strong><?= $item['reorder_level'] ?></strong> <?= htmlspecialchars($item['unit'] ?? 'pcs') ?></span></div>
            <div class="detail-row"><span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="stock-status-large <?= getStockStatusClass($item['quantity'], $item['reorder_level']) ?>">
                        <i class="fas <?= getStockStatusIcon($item['quantity'], $item['reorder_level']) ?>"></i>
                        <?= getStockStatusLabel($item['quantity'], $item['reorder_level']) ?>
                    </span>
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRICING & EXPIRY -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-money-bill-wave"></i>
            Pricing & Expiry
        </h3>
        
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px 20px;">
            <div class="detail-row"><span class="detail-label">Selling Price</span>
                <span class="detail-value">
                    <strong style="color:var(--success);font-size:1.1rem;">TSh <?= number_format($item['selling_price'] ?? 0, 2) ?></strong>
                </span>
            </div>
            <div class="detail-row"><span class="detail-label">Cost Price</span>
                <span class="detail-value">
                    TSh <?= number_format($item['cost_price'] ?? 0, 2) ?>
                </span>
            </div>
            <div class="detail-row"><span class="detail-label">Profit Margin</span>
                <span class="detail-value">
                    <?php 
                        $cost = (float)($item['cost_price'] ?? 0);
                        $sell = (float)($item['selling_price'] ?? 0);
                        if ($cost > 0 && $sell > 0) {
                            $margin = (($sell - $cost) / $cost) * 100;
                            echo '<span style="color:' . ($margin > 20 ? 'var(--success)' : 'var(--warning)') . ';">' . number_format($margin, 1) . '%</span>';
                        } else {
                            echo 'N/A';
                        }
                    ?>
                </span>
            </div>
            <div class="detail-row"><span class="detail-label">Expiry Date</span>
                <span class="detail-value">
                    <?php if (!empty($item['expiry_date'])): ?>
                        <?php 
                            $days = (int)($item['days_remaining'] ?? 0);
                            $expiry_class = 'valid';
                            if ($days < 0) $expiry_class = 'expired';
                            elseif ($days <= 7) $expiry_class = 'expiring';
                            elseif ($days <= 30) $expiry_class = 'expiring';
                        ?>
                        <span class="expiry-status <?= $expiry_class ?>">
                            <i class="fas <?= $days < 0 ? 'fa-skull' : ($days <= 30 ? 'fa-clock' : 'fa-check') ?>"></i>
                            <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                            <?php if ($days >= 0): ?>
                                (<?= $days ?> days left)
                            <?php else: ?>
                                (EXPIRED)
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="expiry-status no-expiry">
                            <i class="fas fa-infinity"></i> No expiry date
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-row"><span class="detail-label">Manufactured Date</span>
                <span class="detail-value"><?= !empty($item['manufactured_date']) ? date('d/m/Y', strtotime($item['manufactured_date'])) : 'N/A' ?></span>
            </div>
            <div class="detail-row"><span class="detail-label">Location / Rack</span>
                <span class="detail-value"><?= htmlspecialchars($item['location'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STOCK MOVEMENT HISTORY -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-exchange-alt"></i>
            Stock Movement History
            <span class="badge-count"><?= count($stock_movements) ?> records</span>
        </h3>
        
        <?php if (count($stock_movements) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:center;">New Qty</th>
                            <th>Reason</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_movements as $movement): ?>
                            <tr>
                                <td><?= formatDate($movement['created_at'] ?? '') ?></td>
                                <td>
                                    <span class="badge-status <?= ($movement['movement_type'] ?? 'in') === 'in' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst($movement['movement_type'] ?? 'In') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:600;color:<?= ($movement['movement_type'] ?? 'in') === 'in' ? 'var(--success)' : 'var(--danger)' ?>;">
                                    <?= ($movement['movement_type'] ?? 'in') === 'in' ? '+' : '-' ?>
                                    <?= abs($movement['quantity'] ?? 0) ?>
                                </td>
                                <td style="text-align:center;font-weight:600;"><?= $movement['new_quantity'] ?? 0 ?></td>
                                <td><?= htmlspecialchars($movement['reason'] ?? $movement['notes'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($movement['performed_by'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-exchange-alt"></i>
                <p>No stock movements recorded</p>
                <p class="sub">Stock movements will appear here when inventory changes</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALE HISTORY -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-shopping-cart"></i>
            OTC Sale History
            <span class="badge-count"><?= count($sale_history) ?> sales</span>
        </h3>
        
        <?php if (count($sale_history) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Qty</th>
                            <th style="text-align:right;">Price</th>
                            <th style="text-align:right;">Total</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_history as $sale): ?>
                            <tr>
                                <td><span class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td style="text-align:center;"><?= $sale['quantity'] ?></td>
                                <td style="text-align:right;font-family:monospace;"><?= number_format($sale['unit_price'] ?? 0, 2) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:600;color:var(--primary);"><?= number_format($sale['total_price'] ?? 0, 2) ?></td>
                                <td><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-status <?= ($sale['payment_status'] ?? '') === 'paid' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= formatDate($sale['sale_date'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No OTC sales recorded for this medicine</p>
                <p class="sub">Sales will appear here when this medicine is sold OTC</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION HISTORY -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-prescription"></i>
            Prescription History
            <span class="badge-count"><?= count($prescription_history) ?> records</span>
        </h3>
        
        <?php if (count($prescription_history) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Qty</th>
                            <th>Dosage</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescription_history as $pres): ?>
                            <tr>
                                <td><span class="font-mono text-xs"><?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($pres['doctor_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $pres['quantity'] ?? 0 ?></td>
                                <td><?= htmlspecialchars($pres['dosage'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($pres['status'] ?? 'pending') ?>">
                                        <?= ucfirst($pres['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= formatDate($pres['prescription_date'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescription history for this medicine</p>
                <p class="sub">Prescriptions will appear here when this medicine is prescribed</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS - NO ADD OR EDIT -->
    <!-- ================================================================ -->
    <div class="card" style="border-color:var(--primary-light);background:var(--primary-light);">
        <h3 class="card-title" style="border-color:var(--border-color);">
            <i class="fas fa-eye" style="color:var(--primary);"></i>
            View Options
            <span class="badge-status badge-info" style="font-size:0.55rem;">
                <i class="fas fa-lock"></i> Read Only
            </span>
        </h3>
        
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <a href="low_stock.php" class="btn btn-primary">
                <i class="fas fa-exclamation-triangle"></i> Back to Low Stock
            </a>
            <a href="inventory.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Inventory
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c💊 Braick - View Inventory (READ ONLY)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active - redirects to login if not authenticated', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Medicine: <?= htmlspecialchars($item['medication_name']) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Stock: <?= $item['quantity'] ?> / <?= $item['reorder_level'] ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Price: TSh <?= number_format($item['selling_price'] ?? 0, 2) ?>', 'font-size:13px; color:#D97706;');
    <?php if (!empty($item['expiry_date'])): ?>
        console.log('%c📅 Expiry: <?= date('d/m/Y', strtotime($item['expiry_date'])) ?> (<?= $item['days_remaining'] ?> days left)', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c📋 Stock Movements: <?= count($stock_movements) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Sales: <?= count($sale_history) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💊 Prescriptions: <?= count($prescription_history) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔒 READ ONLY - No Add or Edit buttons', 'font-size:13px; color:#DC2626;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>