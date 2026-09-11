<?php
// ================================================================
// FILE: frontend/pages/admin/edit_bill_item.php
// ADMIN - EDIT BILL ITEM
// BRAICK DISPENSARY - GREEN THEME
// ✅ Supports Premium
// ✅ Cards with background colors
// ✅ Auto-calculates totals
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($item_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH BILL ITEM - WITH BILL & PREMIUM INFO
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            b.bill_number,
            b.patient_id,
            b.visit_id,
            b.branch_id,
            b.subtotal as bill_subtotal,
            b.discount_amount as bill_discount,
            b.premium_amount as bill_premium,
            b.premium_note as bill_premium_note,
            b.total_amount as bill_total,
            b.paid_amount as bill_paid,
            b.balance as bill_balance,
            b.status as bill_status,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            br.name as branch_name,
            u.full_name as created_by_name
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN branches br ON b.branch_id = br.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE bi.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill item: " . $e->getMessage());
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

$bill_id = $item['bill_id'];

// ================================================================
// ITEM TYPES
// ================================================================
$item_types = [
    'registration' => 'Registration Fee',
    'consultation' => 'Consultation Fee',
    'lab_test' => 'Lab Test',
    'medication' => 'Medication',
    'procedure' => 'Procedure',
    'equipment' => 'Equipment',
    'tool' => 'Tool/Supply',
    'other' => 'Other'
];

// ================================================================
// STATUS OPTIONS
// ================================================================
$status_options = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded'
];

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// PROCESS FORM
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ============================================================
    // UPDATE ITEM
    // ============================================================
    if ($action === 'update_item') {
        $item_type = $_POST['item_type'] ?? 'other';
        $item_name = trim($_POST['item_name'] ?? '');
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $unit_price = isset($_POST['unit_price']) ? floatval(str_replace(',', '', $_POST['unit_price'])) : 0;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        
        if (empty($item_name)) {
            $message = "❌ Item name is required";
            $message_type = 'error';
        } elseif ($quantity <= 0) {
            $message = "❌ Quantity must be greater than 0";
            $message_type = 'error';
        } elseif ($unit_price < 0) {
            $message = "❌ Unit price cannot be negative";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                $total_price = $quantity * $unit_price;
                
                // Update item
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET 
                        item_type = ?,
                        item_name = ?,
                        description = ?,
                        quantity = ?,
                        unit_price = ?,
                        total_price = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $item_type,
                    $item_name,
                    $description,
                    $quantity,
                    $unit_price,
                    $total_price,
                    $status,
                    $item_id
                ]);
                
                // ============================================================
                // ✅ RECALCULATE BILL WITH PREMIUM
                // ============================================================
                $stmt = $db->prepare("
                    SELECT COALESCE(SUM(total_price), 0) as subtotal 
                    FROM bill_items 
                    WHERE bill_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$bill_id]);
                $new_subtotal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['subtotal'];
                
                $discount_amount = (float)($item['bill_discount'] ?? 0);
                $premium_amount = (float)($item['bill_premium'] ?? 0);
                
                // ✅ FORMULA: Total = Subtotal - Discount + Premium
                $new_total = $new_subtotal - $discount_amount + $premium_amount;
                if ($new_total < 0) $new_total = 0;
                
                $paid_amount = (float)($item['bill_paid'] ?? 0);
                $new_balance = $new_total - $paid_amount;
                if ($new_balance < 0) $new_balance = 0;
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET 
                        subtotal = ?,
                        total_amount = ?,
                        balance = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_subtotal, $new_total, $new_balance, $bill_id]);
                
                $db->commit();
                
                $message = "✅ Item updated successfully!";
                $message_type = 'success';
                
                // Refresh item
                $stmt = $db->prepare("
                    SELECT bi.*, b.bill_number, b.subtotal as bill_subtotal, 
                           b.discount_amount as bill_discount, b.premium_amount as bill_premium,
                           b.premium_note as bill_premium_note, b.total_amount as bill_total,
                           b.paid_amount as bill_paid, b.balance as bill_balance, b.status as bill_status,
                           p.full_name as patient_name, p.patient_id as patient_code,
                           br.name as branch_name, u.full_name as created_by_name
                    FROM bill_items bi
                    JOIN bills b ON bi.bill_id = b.id
                    LEFT JOIN patients p ON b.patient_id = p.id
                    LEFT JOIN branches br ON b.branch_id = br.id
                    LEFT JOIN users u ON b.created_by = u.id
                    WHERE bi.id = ?
                ");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// STATUS FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'paid' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

function getItemTypeColor($type) {
    $colors = [
        'registration' => 'blue',
        'consultation' => 'purple',
        'lab_test' => 'orange',
        'medication' => 'green',
        'procedure' => 'red',
        'equipment' => 'teal',
        'tool' => 'gray',
        'other' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}

function getItemTypeLabel($type) {
    $labels = [
        'registration' => 'Registration',
        'consultation' => 'Consultation',
        'lab_test' => 'Lab Test',
        'medication' => 'Medication',
        'procedure' => 'Procedure',
        'equipment' => 'Equipment',
        'tool' => 'Tool/Supply',
        'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst($type);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bill Item - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --bg-body: #F0FDF4;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #D1FAE5;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #34D399;
            --primary-bg: #1A3A2A;
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
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            background: var(--danger);
            animation: pulse-dot 2s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
            position: relative;
            overflow: hidden;
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
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
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
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
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.82rem;
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
        
        /* ================================================================
           ✅ 5 CARDS WITH BACKGROUND COLORS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .stat-card {
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
            min-height: 100px;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card.card-total {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }
        .stat-card.card-discount {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
        }
        .stat-card.card-premium {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
            animation: premiumPulse 2s ease-in-out infinite;
        }
        .stat-card.card-paid {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        .stat-card.card-balance {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
        }
        .stat-card.card-balance.zero {
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        @keyframes premiumPulse {
            0%, 100% { box-shadow: 0 4px 16px rgba(217, 119, 6, 0.3); }
            50% { box-shadow: 0 4px 24px rgba(217, 119, 6, 0.6); }
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(8px);
        }
        
        .stat-card .stat-content { flex: 1; }
        
        .stat-label {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0;
            color: rgba(255,255,255,0.9);
        }
        
        .stat-value {
            font-size: 1.15rem;
            font-weight: 800;
            margin: 3px 0 0 0;
            color: white;
        }
        
        .stat-sub {
            font-size: 0.55rem;
            margin-top: 3px;
            color: rgba(255,255,255,0.85);
        }
        
        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1200px;
            margin: 0 auto 24px;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 52px;
            height: 52px;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        .form-label .label-badge {
            font-weight: 400;
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: var(--gray-100);
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
        }
        
        .form-control::placeholder { color: var(--text-secondary); opacity: 0.5; }
        .form-control:disabled { opacity: 0.6; cursor: not-allowed; }
        
        select.form-control { appearance: auto; cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-row { margin-bottom: 20px; }
        .form-row:last-child { margin-bottom: 0; }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        .btn-primary:hover { box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35); }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert-success { background: #D1FAE5; color: #065F46; border-color: #34D399; }
        .alert-danger { background: #FEE2E2; color: #991B1B; border-color: #F87171; }
        
        [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        [data-theme="dark"] .alert-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
        
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
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        .text-xl { font-size: 1.25rem; }
        .font-bold { font-weight: 700; }
        .text-green-600 { color: var(--success); }
        .text-blue-600 { color: #0B5ED7; }
        .text-purple-600 { color: var(--purple); }
        .text-red-600 { color: var(--danger); }
        
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .form-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
    </style>
</head>
<body>

<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Bill Item
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-receipt"></i>
                <strong><?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-invoice"></i> Edit Bill
            </a>
            <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View Bill
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ✅ 5 CARDS WITH BACKGROUND COLORS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <div class="stat-card card-total">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <p class="stat-label">Bill Total</p>
                <p class="stat-value">TSh <?= number_format($item['bill_total'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-receipt"></i> Full bill</p>
            </div>
        </div>
        
        <div class="stat-card card-discount">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-content">
                <p class="stat-label">Discount</p>
                <p class="stat-value">TSh <?= number_format($item['bill_discount'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-percent"></i> Bill discount</p>
            </div>
        </div>
        
        <div class="stat-card card-premium">
            <div class="stat-icon"><i class="fas fa-crown"></i></div>
            <div class="stat-content">
                <p class="stat-label">Premium</p>
                <p class="stat-value">TSh <?= number_format($item['bill_premium'] ?? 0, 0) ?></p>
                <p class="stat-sub">
                    <?php if (!empty($item['bill_premium_note'])): ?>
                        <?= htmlspecialchars($item['bill_premium_note']) ?>
                    <?php else: ?>
                        <i class="fas fa-star"></i> <?= ($item['bill_premium'] ?? 0) > 0 ? 'Charged' : 'No premium' ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card card-paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Paid</p>
                <p class="stat-value">TSh <?= number_format($item['bill_paid'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-check"></i> Total paid</p>
            </div>
        </div>
        
        <div class="stat-card card-balance <?= ($item['bill_balance'] ?? 0) <= 0 ? 'zero' : '' ?>">
            <div class="stat-icon"><i class="fas <?= ($item['bill_balance'] ?? 0) > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
            <div class="stat-content">
                <p class="stat-label">Balance</p>
                <p class="stat-value">TSh <?= number_format($item['bill_balance'] ?? 0, 0) ?></p>
                <p class="stat-sub">
                    <?php if (($item['bill_balance'] ?? 0) > 0): ?>
                        <i class="fas fa-clock"></i> Pending
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i> Fully paid
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- EDIT ITEM FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon"><i class="fas fa-edit"></i></div>
            <div>
                <h3 class="form-title">Edit Item Details</h3>
                <p class="form-subtitle">Update item information and price</p>
            </div>
        </div>

        <form method="POST" action="" id="itemForm">
            <input type="hidden" name="action" value="update_item">
            
            <!-- Bill & Patient Info (Read-only) -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-receipt label-icon"></i> Bill Number
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>" disabled>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Patient
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?> (<?= htmlspecialchars($item['patient_code'] ?? 'N/A') ?>)" disabled>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-store label-icon"></i> Branch
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>" disabled>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-plus label-icon"></i> Created By
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['created_by_name'] ?? 'N/A') ?>" disabled>
                </div>
            </div>
            
            <!-- Item Type & Name -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Item Type <span class="required">*</span>
                    </label>
                    <select name="item_type" class="form-control" required>
                        <?php foreach ($item_types as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($item['item_type'] ?? 'other') === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-cube label-icon"></i> Item Name <span class="required">*</span>
                    </label>
                    <input type="text" name="item_name" class="form-control" 
                           value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" required>
                </div>
            </div>
            
            <!-- Quantity & Unit Price -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calculator label-icon"></i> Quantity <span class="required">*</span>
                    </label>
                    <input type="number" name="quantity" class="form-control" 
                           value="<?= (int)($item['quantity'] ?? 1) ?>" min="1" required 
                           oninput="calculateTotal()">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-money-bill-wave label-icon"></i> Unit Price <span class="required">*</span>
                        <span class="label-badge">TSh</span>
                    </label>
                    <input type="text" name="unit_price" id="unitPrice" class="form-control" 
                           value="<?= number_format($item['unit_price'] ?? 0, 0) ?>" 
                           oninput="formatAmount(this); calculateTotal()" required>
                </div>
            </div>
            
            <!-- Total Preview -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-calculator label-icon"></i> Total Price (Auto-calculated)
                </label>
                <div id="totalPreview" class="text-xl font-bold text-green-600" style="padding:10px 16px;background:var(--primary-bg);border-radius:var(--radius);border:2px solid var(--primary-light);">
                    TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                </div>
            </div>
            
            <!-- Description -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-align-left label-icon"></i> Description
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="description" class="form-control" rows="3" 
                          placeholder="Item description..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
            </div>
            
            <!-- Status -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-info-circle label-icon"></i> Status <span class="required">*</span>
                </label>
                <select name="status" class="form-control" required>
                    <?php foreach ($status_options as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($item['status'] ?? 'pending') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Item
                </button>
                <a href="edit_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Bill
                </a>
                <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Bill Item
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

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
    sidebarToggle?.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // FORMAT AMOUNT
    function formatAmount(input) {
        var val = input.value.replace(/[^0-9.]/g, '');
        var parts = val.split('.');
        var whole = parts[0];
        var decimal = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
        if (whole.length > 0) {
            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        input.value = whole + decimal;
    }

    // CALCULATE TOTAL
    function calculateTotal() {
        var quantity = parseInt(document.querySelector('input[name="quantity"]').value) || 0;
        var unitPriceStr = document.getElementById('unitPrice').value.replace(/,/g, '');
        var unitPrice = parseFloat(unitPriceStr) || 0;
        var total = quantity * unitPrice;
        
        document.getElementById('totalPreview').textContent = 'TSh ' + total.toLocaleString();
    }

    // SEARCH
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=<?= $selected_branch_id ?>';
        }
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) { if (e.key === 'Enter') performSearch(); });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c✏️ Braick Dispensary - Edit Bill Item', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Bill: <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Item: <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ 5 CARDS with BACKGROUND COLORS', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Premium Card ADDED', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Formula: Total = Subtotal - Discount + Premium', 'font-size:13px; color:#FBBF24;');
</script>

</body>
</html>