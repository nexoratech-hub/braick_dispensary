<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_inventory.php
// PHARMACY - VIEW INVENTORY ITEM WITH ALL BATCHES
// Shows single item with all batches (same name, different batches)
// READ ONLY - No Add or Edit buttons
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
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET INVENTORY ID AND TYPE
// ================================================================
$inventory_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';

if ($inventory_id <= 0) {
    header('Location: inventory.php?error=invalid_id');
    exit;
}

// ================================================================
// GET MAIN ITEM DETAILS (to get the name)
// ================================================================
if ($item_type === 'medicine') {
    $stmt = $db->prepare("
        SELECT id, medication_name as item_name, category, unit, 
               status, created_at, branch_id
        FROM medications_inventory 
        WHERE id = ? AND branch_id = ?
    ");
    $stmt->execute([$inventory_id, $user_branch_id]);
    $main_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$main_item) {
        header('Location: inventory.php?error=not_found');
        exit;
    }
    
    $item_name = $main_item['item_name'];
    
    // GET ALL BATCHES FOR THIS MEDICINE
    $stmt = $db->prepare("
        SELECT *, 
            DATEDIFF(expiry_date, CURDATE()) as days_remaining,
            'medicine' as item_type
        FROM medications_inventory 
        WHERE medication_name = ? AND branch_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$item_name, $user_branch_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET UNIQUE CATEGORIES
    $stmt = $db->prepare("
        SELECT DISTINCT category FROM medications_inventory 
        WHERE medication_name = ? AND branch_id = ?
    ");
    $stmt->execute([$item_name, $user_branch_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} else {
    // Equipment
    $stmt = $db->prepare("
        SELECT id, equipment_name as item_name, category, unit, 
               status, created_at, branch_id
        FROM medical_equipment 
        WHERE id = ? AND branch_id = ?
    ");
    $stmt->execute([$inventory_id, $user_branch_id]);
    $main_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$main_item) {
        header('Location: inventory.php?error=not_found');
        exit;
    }
    
    $item_name = $main_item['item_name'];
    
    // GET ALL BATCHES FOR THIS EQUIPMENT
    $stmt = $db->prepare("
        SELECT *, 
            DATEDIFF(expiry_date, CURDATE()) as days_remaining,
            'equipment' as item_type
        FROM medical_equipment 
        WHERE equipment_name = ? AND branch_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$item_name, $user_branch_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET UNIQUE CATEGORIES
    $stmt = $db->prepare("
        SELECT DISTINCT category FROM medical_equipment 
        WHERE equipment_name = ? AND branch_id = ?
    ");
    $stmt->execute([$item_name, $user_branch_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_quantity = 0;
$total_batches = count($batches);
$active_batches = 0;
$inactive_batches = 0;
$low_stock_batches = 0;
$out_of_stock_batches = 0;
$expired_batches = 0;
$expiring_batches = 0;
$valid_batches = 0;
$min_reorder_level = PHP_INT_MAX;
$max_reorder_level = 0;

foreach ($batches as $batch) {
    $total_quantity += $batch['quantity'];
    
    if ($batch['status'] === 'active') {
        $active_batches++;
    } else {
        $inactive_batches++;
    }
    
    if ($batch['quantity'] <= 0) {
        $out_of_stock_batches++;
    } elseif ($batch['quantity'] <= $batch['reorder_level']) {
        $low_stock_batches++;
    }
    
    if (!empty($batch['expiry_date'])) {
        $days = $batch['days_remaining'];
        if ($days < 0) {
            $expired_batches++;
        } elseif ($days <= 30) {
            $expiring_batches++;
        } else {
            $valid_batches++;
        }
    }
    
    if ($batch['reorder_level'] < $min_reorder_level) {
        $min_reorder_level = $batch['reorder_level'];
    }
    if ($batch['reorder_level'] > $max_reorder_level) {
        $max_reorder_level = $batch['reorder_level'];
    }
}

if ($min_reorder_level === PHP_INT_MAX) {
    $min_reorder_level = 0;
}

// ================================================================
// GET STOCK MOVEMENT HISTORY (ALL BATCHES)
// ================================================================
$all_inventory_ids = array_column($batches, 'id');
$inventory_ids_str = implode(',', $all_inventory_ids);

$stock_movements = [];
if (!empty($all_inventory_ids)) {
    $stmt = $db->prepare("
        SELECT sm.*, 
               CASE 
                   WHEN sm.inventory_id IS NOT NULL THEN 'medicine'
                   ELSE 'equipment'
               END as item_type,
               mi.medication_name as medicine_name,
               me.equipment_name as equipment_name
        FROM stock_movements sm
        LEFT JOIN medications_inventory mi ON sm.inventory_id = mi.id
        LEFT JOIN medical_equipment me ON sm.equipment_id = me.id
        WHERE sm.inventory_id IN (" . implode(',', array_fill(0, count($all_inventory_ids), '?')) . ")
        ORDER BY sm.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($all_inventory_ids);
    $stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// GET OTC SALE HISTORY
// ================================================================
$sale_history = [];
if (!empty($all_inventory_ids)) {
    $stmt = $db->prepare("
        SELECT 
            os.sale_number,
            os.customer_name,
            os.created_at as sale_date,
            os.total_amount,
            os.payment_method,
            os.payment_status,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            oi.inventory_id
        FROM otc_sale_items oi
        JOIN otc_sales os ON oi.sale_id = os.id
        WHERE oi.inventory_id IN (" . implode(',', array_fill(0, count($all_inventory_ids), '?')) . ")
        ORDER BY os.created_at DESC
        LIMIT 30
    ");
    $stmt->execute($all_inventory_ids);
    $sale_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

function getExpiryStatus($days_remaining) {
    if ($days_remaining === null) return 'no-expiry';
    if ($days_remaining < 0) return 'expired';
    if ($days_remaining <= 30) return 'expiring';
    return 'valid';
}

function getExpiryLabel($days_remaining) {
    if ($days_remaining === null) return 'No Expiry';
    if ($days_remaining < 0) return 'EXPIRED';
    if ($days_remaining <= 30) return $days_remaining . ' days left';
    return $days_remaining . ' days';
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y', strtotime($datetime));
}

// ================================================================
// GET SIDEBAR STATS
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
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
        WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
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
            --teal: #0D9488;
            --teal-light: #CCFBF1;
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
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
        
        .page-header .page-title .item-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
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
        
        .page-header .read-only-badge {
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
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            min-height: 75px;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .stat-card .stat-icon {
            font-size: 1.2rem;
            opacity: 0.8;
            float: right;
        }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.gold { background: linear-gradient(135deg, #F59E0B, #D97706); }
        
        /* ================================================================
           CARD
           ================================================================ */
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
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
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
        
        .card-title .badge-count.purple {
            background: var(--purple);
        }
        
        /* ================================================================
           BATCH TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .table-wrap::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        
        .table-wrap::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .data-table {
            width: 100%;
            min-width: 900px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--primary);
            color: white;
            padding: 8px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--primary-light);
        }
        
        .data-table tbody tr:hover td {
            background: var(--success-light);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #1E293B;
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A2A;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            white-space: nowrap;
        }
        
        .data-table .total-row td {
            font-weight: 700;
            border-top: 2px solid var(--primary);
            background: var(--primary-light);
            padding: 8px 12px;
        }
        
        [data-theme="dark"] .data-table .total-row td {
            background: #1E3A5F;
        }
        
        .col-sno { width: 35px; text-align: center; }
        .col-batch { min-width: 130px; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-reorder { min-width: 70px; text-align: center; }
        .col-stock { min-width: 100px; }
        .col-expiry { min-width: 100px; }
        .col-days { min-width: 80px; text-align: center; }
        .col-price { min-width: 90px; }
        .col-supplier { min-width: 100px; }
        .col-status { min-width: 70px; text-align: center; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-light); color: var(--success); border: 1px solid var(--success); }
        .badge-warning { background: var(--warning-light); color: var(--warning); border: 1px solid var(--warning); }
        .badge-danger { background: var(--danger-light); color: var(--danger); border: 1px solid var(--danger); }
        .badge-info { background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); }
        .badge-purple { background: var(--purple-light); color: var(--purple); border: 1px solid var(--purple); }
        
        .stock-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .stock-badge.ok {
            background: var(--success-light);
            color: var(--success);
        }
        
        .stock-badge.low {
            background: var(--warning-light);
            color: var(--warning);
            animation: pulse 1.5s infinite;
        }
        
        .stock-badge.out {
            background: var(--danger-light);
            color: var(--danger);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .expiry-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .expiry-badge.valid {
            background: var(--success-light);
            color: var(--success);
        }
        
        .expiry-badge.expiring {
            background: var(--warning-light);
            color: var(--warning);
            animation: pulse 1.5s infinite;
        }
        
        .expiry-badge.expired {
            background: var(--danger-light);
            color: var(--danger);
            animation: pulse 1s infinite;
        }
        
        .expiry-badge.no-expiry {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        /* ================================================================
           MOVEMENT TABLE
           ================================================================ */
        .movement-in {
            color: var(--success);
            font-weight: 600;
        }
        
        .movement-out {
            color: var(--danger);
            font-weight: 600;
        }
        
        .movement-adjustment {
            color: var(--warning);
            font-weight: 600;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card { padding: 10px 12px; min-height: 60px; }
            .data-table { min-width: 700px; font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card { padding: 6px 8px; min-height: 50px; }
            .stat-card .stat-icon { font-size: 0.8rem; }
            .data-table { min-width: 600px; font-size: 0.6rem; }
            .page-header .page-title { font-size: 1rem; }
            .page-header .btn-outline-light { font-size: 0.7rem; padding: 4px 10px; }
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
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
                <i class="fas <?= $item_type === 'medicine' ? 'fa-pills' : 'fa-tools' ?>"></i>
                <?= htmlspecialchars($item_name) ?>
                <span class="item-badge">
                    <i class="fas <?= $item_type === 'medicine' ? 'fa-pills' : 'fa-tools' ?>"></i>
                    <?= ucfirst($item_type) ?>
                </span>
                <span class="read-only-badge">
                    <i class="fas fa-eye"></i> Read Only
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <?php if (!empty($categories)): ?>
                    <span class="text-xs text-gray-400 ml-2">
                        <i class="fas fa-tags"></i> <?= htmlspecialchars(implode(', ', $categories)) ?>
                    </span>
                <?php endif; ?>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-layer-group"></i> <?= $total_batches ?> batch(es)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="inventory.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-layer-group"></i></span>
            <div class="stat-number"><?= $total_batches ?></div>
            <div class="stat-label">Total Batches</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon"><i class="fas fa-boxes"></i></span>
            <div class="stat-number"><?= $total_quantity ?></div>
            <div class="stat-label">Total Quantity</div>
        </div>
        <div class="stat-card <?= $low_stock_batches > 0 ? 'orange' : 'green' ?>">
            <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
            <div class="stat-number"><?= $active_batches ?></div>
            <div class="stat-label">Active Batches</div>
        </div>
        <div class="stat-card <?= $low_stock_batches > 0 ? 'orange' : 'green' ?>">
            <span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="stat-number"><?= $low_stock_batches ?></div>
            <div class="stat-label">Low Stock Batches</div>
        </div>
        <div class="stat-card <?= $out_of_stock_batches > 0 ? 'red' : 'green' ?>">
            <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
            <div class="stat-number"><?= $out_of_stock_batches ?></div>
            <div class="stat-label">Out of Stock</div>
        </div>
        <div class="stat-card <?= $expired_batches > 0 ? 'red' : 'teal' ?>">
            <span class="stat-icon"><i class="fas fa-skull"></i></span>
            <div class="stat-number"><?= $expired_batches ?></div>
            <div class="stat-label">Expired Batches</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BATCHES TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-list"></i>
            All Batches for <?= htmlspecialchars($item_name) ?>
            <span class="badge-count"><?= $total_batches ?> batches</span>
            <span class="badge-count purple">
                <i class="fas fa-boxes"></i> <?= $total_quantity ?> total
            </span>
        </div>
        
        <?php if ($total_batches > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-sno">#</th>
                            <th class="col-batch">Batch Number</th>
                            <th class="col-qty">Qty</th>
                            <th class="col-reorder">Reorder</th>
                            <th class="col-stock">Stock Status</th>
                            <th class="col-expiry">Expiry Date</th>
                            <th class="col-days">Days Left</th>
                            <th class="col-price">Price</th>
                            <th class="col-supplier">Supplier</th>
                            <th class="col-status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($batches as $batch): ?>
                            <?php
                                $stock_status = getStockStatusClass($batch['quantity'], $batch['reorder_level']);
                                $stock_label = getStockStatusLabel($batch['quantity'], $batch['reorder_level']);
                                $stock_icon = getStockStatusIcon($batch['quantity'], $batch['reorder_level']);
                                
                                $expiry_status = getExpiryStatus($batch['days_remaining'] ?? null);
                                $expiry_label = getExpiryLabel($batch['days_remaining'] ?? null);
                                
                                $is_expired = ($expiry_status === 'expired');
                                $is_expiring = ($expiry_status === 'expiring');
                                $is_low_stock = ($stock_status === 'warning');
                                $is_out_of_stock = ($stock_status === 'danger');
                                
                                // Highlight row if problematic
                                $row_class = '';
                                if ($is_expired || $is_out_of_stock) {
                                    $row_class = 'style="background:var(--danger-light);"';
                                } elseif ($is_expiring || $is_low_stock) {
                                    $row_class = 'style="background:var(--warning-light);"';
                                }
                            ?>
                            <tr <?= $row_class ?>>
                                <td class="col-sno"><?= $counter++ ?></td>
                                <td class="col-batch">
                                    <span class="batch-number"><?= htmlspecialchars($batch['batch_number'] ?? 'N/A') ?></span>
                                </td>
                                <td class="col-qty">
                                    <strong style="color: <?= $stock_status === 'danger' ? 'var(--danger)' : ($stock_status === 'warning' ? 'var(--warning)' : 'var(--success)') ?>;">
                                        <?= $batch['quantity'] ?>
                                    </strong>
                                </td>
                                <td class="col-reorder"><?= $batch['reorder_level'] ?></td>
                                <td class="col-stock">
                                    <span class="stock-badge <?= $stock_status === 'danger' ? 'out' : ($stock_status === 'warning' ? 'low' : 'ok') ?>">
                                        <i class="fas <?= $stock_icon ?>"></i>
                                        <?= $stock_label ?>
                                    </span>
                                </td>
                                <td class="col-expiry">
                                    <?php if (!empty($batch['expiry_date'])): ?>
                                        <span class="expiry-badge <?= $expiry_status ?>">
                                            <?= formatDateShort($batch['expiry_date']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="expiry-badge no-expiry">
                                            <i class="fas fa-infinity"></i> No Expiry
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-days">
                                    <?php if ($expiry_status !== 'no-expiry'): ?>
                                        <span class="expiry-badge <?= $expiry_status ?>">
                                            <?php if ($expiry_status === 'expired'): ?>
                                                <i class="fas fa-skull"></i> EXPIRED
                                            <?php elseif ($expiry_status === 'expiring'): ?>
                                                <i class="fas fa-clock"></i> <?= $batch['days_remaining'] ?>d
                                            <?php else: ?>
                                                <i class="fas fa-check"></i> <?= $batch['days_remaining'] ?>d
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-price">
                                    <?php if ($batch['selling_price'] > 0): ?>
                                        TSh <?= number_format($batch['selling_price'] ?? 0) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Free</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-supplier"><?= htmlspecialchars($batch['supplier'] ?? 'N/A') ?></td>
                                <td class="col-status">
                                    <span class="badge-status <?= getStatusBadgeClass($batch['status'] ?? 'active') ?>">
                                        <?= ucfirst($batch['status'] ?? 'Active') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align:right;font-weight:700;">TOTALS:</td>
                            <td style="text-align:center;font-weight:700;"><?= $total_quantity ?></td>
                            <td colspan="2" style="text-align:center;font-weight:700;">
                                Reorder: <?= $min_reorder_level ?> - <?= $max_reorder_level ?>
                            </td>
                            <td colspan="3" style="text-align:center;font-weight:700;">
                                <?= $active_batches ?> Active | <?= $inactive_batches ?> Inactive
                            </td>
                            <td colspan="2" style="text-align:center;font-weight:700;">
                                <?= $expired_batches ?> Expired | <?= $expiring_batches ?> Expiring
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No batches found for this item</p>
                <p class="sub">This item has no inventory records</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BATCH SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px;">
        <div class="card" style="border-left:4px solid var(--success);">
            <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Active Batches</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--success);"><?= $active_batches ?></div>
        </div>
        <div class="card" style="border-left:4px solid var(--warning);">
            <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Low Stock Batches</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--warning);"><?= $low_stock_batches ?></div>
        </div>
        <div class="card" style="border-left:4px solid var(--danger);">
            <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Out of Stock Batches</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--danger);"><?= $out_of_stock_batches ?></div>
        </div>
        <div class="card" style="border-left:4px solid #7F1D1D;">
            <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Expired Batches</div>
            <div style="font-size:1.8rem;font-weight:700;color:#7F1D1D;"><?= $expired_batches ?></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STOCK MOVEMENT HISTORY -->
    <!-- ================================================================ -->
    <?php if (!empty($stock_movements)): ?>
    <div class="card">
        <div class="card-title">
            <i class="fas fa-exchange-alt"></i>
            Stock Movement History
            <span class="badge-count"><?= count($stock_movements) ?> records</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:center;">New Qty</th>
                        <th>Reason</th>
                        <th>Batch</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_movements as $movement): ?>
                        <?php
                            $movement_type = $movement['movement_type'] ?? 'in';
                            $qty = $movement['quantity'] ?? 0;
                            $is_in = ($movement_type === 'in');
                            $color_class = $is_in ? 'movement-in' : 'movement-out';
                            $sign = $is_in ? '+' : '-';
                            
                            // Find batch number
                            $batch_number = 'N/A';
                            if ($movement['inventory_id']) {
                                foreach ($batches as $batch) {
                                    if ($batch['id'] == $movement['inventory_id']) {
                                        $batch_number = $batch['batch_number'] ?? 'N/A';
                                        break;
                                    }
                                }
                            }
                        ?>
                        <tr>
                            <td><?= formatDate($movement['created_at'] ?? '') ?></td>
                            <td>
                                <span class="badge-status <?= $is_in ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($movement_type) ?>
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:600;color:<?= $is_in ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $sign ?> <?= abs($qty) ?>
                            </td>
                            <td style="text-align:center;font-weight:600;"><?= $movement['new_stock'] ?? $movement['new_quantity'] ?? 'N/A' ?></td>
                            <td><?= htmlspecialchars($movement['notes'] ?? $movement['reason'] ?? 'N/A') ?></td>
                            <td><span class="batch-number"><?= htmlspecialchars($batch_number) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OTC SALE HISTORY -->
    <!-- ================================================================ -->
    <?php if (!empty($sale_history)): ?>
    <div class="card">
        <div class="card-title">
            <i class="fas fa-shopping-cart"></i>
            OTC Sale History
            <span class="badge-count"><?= count($sale_history) ?> sales</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th style="text-align:center;">Qty</th>
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
                            <td><span class="batch-number"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></span></td>
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
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS -->
    <!-- ================================================================ -->
    <div class="card" style="border-color:var(--primary-light);background:var(--primary-light);">
        <div class="card-title" style="border-color:var(--border-color);">
            <i class="fas fa-eye" style="color:var(--primary);"></i>
            View Options
            <span class="badge-status badge-info" style="font-size:0.55rem;">
                <i class="fas fa-lock"></i> Read Only
            </span>
        </div>
        
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <a href="inventory.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border-radius:10px;font-weight:600;font-size:0.85rem;background:var(--primary);color:white;text-decoration:none;border:none;transition:all 0.3s ease;">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="inventory.php?stock=low" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border-radius:10px;font-weight:600;font-size:0.85rem;background:transparent;color:var(--text-secondary);border:2px solid var(--border-color);text-decoration:none;transition:all 0.3s ease;">
                <i class="fas fa-exclamation-triangle"></i> View Low Stock
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
            View Inventory - <?= htmlspecialchars($item_name) ?>
            <span class="text-gray-300 mx-2">|</span>
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
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
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
    // DARK MODE SYNC
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    console.log('%c💊 Braick - View Inventory (ALL BATCHES)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📦 Item: <?= htmlspecialchars($item_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Type: <?= ucfirst($item_type) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Batches: <?= $total_batches ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📦 Total Quantity: <?= $total_quantity ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⚠️ Low Stock: <?= $low_stock_batches ?> | Out of Stock: <?= $out_of_stock_batches ?>', 'font-size:13px; color:#D97706;');
    console.log('%c❌ Expired: <?= $expired_batches ?> | Expiring: <?= $expiring_batches ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Active: <?= $active_batches ?> | Inactive: <?= $inactive_batches ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 READ ONLY - View only, no Add or Edit buttons', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>