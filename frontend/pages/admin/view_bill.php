<?php
// ================================================================
// FILE: frontend/pages/admin/view_bill.php
// ADMIN - VIEW BILL DETAILS
// BRAICK DISPENSARY - GREEN THEME
// ✅ ADDED: Premium Card + Background colors on 5 cards
// ✅ FIXED: Bill items display (all statuses)
// ✅ FIXED: Buttons show/hide based on BILL status
//   - PAID bill → VIEW only
//   - PENDING/PARTIAL bill → VIEW, EDIT, CANCEL
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HANDLE CANCEL BILL ITEM ACTION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_bill_item') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $bill_id_return = (int)($_POST['bill_id'] ?? 0);
    $branch_id_return = (int)($_POST['branch_id'] ?? 1);
    
    if ($item_id > 0) {
        try {
            $db->beginTransaction();
            
            // Get the bill to check its status
            $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
            $stmt->execute([$bill_id_return]);
            $bill_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill_check) {
                throw new Exception('Bill not found');
            }
            
            // ✅ Only allow cancel if bill is pending or partial
            if ($bill_check['status'] === 'paid') {
                throw new Exception('Cannot cancel items from a PAID bill');
            }
            
            if ($bill_check['status'] === 'cancelled') {
                throw new Exception('Cannot cancel items from a CANCELLED bill');
            }
            
            // Update item status to cancelled
            $stmt = $db->prepare("
                UPDATE bill_items 
                SET status = 'cancelled', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$item_id]);
            
            // Recalculate bill totals (only count non-cancelled items)
            $stmt = $db->prepare("
                SELECT SUM(total_price) as new_subtotal 
                FROM bill_items 
                WHERE bill_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$bill_id_return]);
            $new_subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['new_subtotal'] ?? 0);
            
            $stmt = $db->prepare("SELECT total_discount FROM bills WHERE id = ?");
            $stmt->execute([$bill_id_return]);
            $total_discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
            
            $new_total = max(0, $new_subtotal - $total_discount);
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM payments WHERE bill_id = ?");
            $stmt->execute([$bill_id_return]);
            $paid = (float)($stmt->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);
            
            $new_balance = $new_total - $paid;
            
            if ($new_total == 0) {
                $new_status = 'pending';
            } elseif ($new_balance <= 0 && $new_total > 0) {
                $new_status = 'paid';
            } elseif ($paid > 0 && $new_balance > 0) {
                $new_status = 'partial';
            } else {
                $new_status = 'pending';
            }
            
            $stmt = $db->prepare("
                UPDATE bills 
                SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_subtotal, $new_total, $paid, $new_balance, $new_status, $bill_id_return]);
            
            $db->commit();
            
            $_SESSION['flash_message'] = "✅ Bill item cancelled successfully!";
            $_SESSION['flash_type'] = 'success';
            header('Location: view_bill.php?id=' . $bill_id_return . '&branch=' . $branch_id_return);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: view_bill.php?id=' . $bill_id_return . '&branch=' . $branch_id_return);
            exit;
        }
    }
}

// ================================================================
// HANDLE RESTORE BILL ITEM ACTION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_bill_item') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $bill_id_return = (int)($_POST['bill_id'] ?? 0);
    $branch_id_return = (int)($_POST['branch_id'] ?? 1);
    
    if ($item_id > 0) {
        try {
            $db->beginTransaction();
            
            // Check bill status
            $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
            $stmt->execute([$bill_id_return]);
            $bill_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill_check || $bill_check['status'] === 'paid') {
                throw new Exception('Cannot restore items on a PAID bill');
            }
            
            $stmt = $db->prepare("UPDATE bill_items SET status = 'pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$item_id]);
            
            // Recalculate
            $stmt = $db->prepare("SELECT SUM(total_price) as new_subtotal FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
            $stmt->execute([$bill_id_return]);
            $new_subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['new_subtotal'] ?? 0);
            
            $stmt = $db->prepare("SELECT total_discount FROM bills WHERE id = ?");
            $stmt->execute([$bill_id_return]);
            $total_discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
            
            $new_total = max(0, $new_subtotal - $total_discount);
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM payments WHERE bill_id = ?");
            $stmt->execute([$bill_id_return]);
            $paid = (float)($stmt->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);
            
            $new_balance = $new_total - $paid;
            
            if ($new_total == 0) $new_status = 'pending';
            elseif ($new_balance <= 0 && $new_total > 0) $new_status = 'paid';
            elseif ($paid > 0 && $new_balance > 0) $new_status = 'partial';
            else $new_status = 'pending';
            
            $stmt = $db->prepare("
                UPDATE bills 
                SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_subtotal, $new_total, $paid, $new_balance, $new_status, $bill_id_return]);
            
            $db->commit();
            
            $_SESSION['flash_message'] = "✅ Bill item restored successfully!";
            $_SESSION['flash_type'] = 'success';
            header('Location: view_bill.php?id=' . $bill_id_return . '&branch=' . $branch_id_return);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: view_bill.php?id=' . $bill_id_return . '&branch=' . $branch_id_return);
            exit;
        }
    }
}

// ================================================================
// GET PARAMETERS
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : ($_SESSION['branch_id'] ?? 1);

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . $branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH BILL DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            p.id as patient_id,
            p.patient_id as patient_number,
            p.full_name as patient_name,
            p.phone as patient_phone,
            p.email as patient_email,
            p.address as patient_address,
            p.gender as patient_gender,
            u.full_name as created_by_name,
            br.name as branch_name,
            v.visit_number,
            v.visit_date,
            v.status as visit_status
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN branches br ON b.branch_id = br.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: bills.php?branch=' . $branch_id . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill: " . $e->getMessage());
    header('Location: bills.php?branch=' . $branch_id . '&error=database_error');
    exit;
}

// ================================================================
// ✅ FETCH BILL ITEMS - SIMPLIFIED & FIXED
// ================================================================
$bill_items = [];
try {
    // ✅ Simple query - no complex JOINs that could fail
    $stmt = $db->prepare("
        SELECT 
            bi.id,
            bi.bill_id,
            bi.patient_id,
            bi.branch_id,
            bi.item_type,
            bi.item_id,
            bi.item_name,
            bi.item_code,
            bi.description,
            bi.quantity,
            bi.unit_price,
            bi.total_price,
            bi.discount_amount,
            bi.final_price,
            bi.reference_id,
            bi.reference_type,
            bi.status,
            bi.created_at,
            bi.updated_at
        FROM bill_items bi
        WHERE bi.bill_id = ?
        ORDER BY bi.id ASC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("✅ Bill #$bill_id has " . count($bill_items) . " items");
    
} catch (Exception $e) {
    error_log("❌ Error fetching bill items: " . $e->getMessage());
    $bill_items = [];
}

// ================================================================
// FETCH PAYMENTS
// ================================================================
$payments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as received_by_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ?
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payments = [];
}

// ================================================================
// CALCULATE SUMMARY
// ================================================================
$bill_status = strtolower($bill['status'] ?? 'pending');

$total_bill = (float)($bill['total_amount'] ?? 0);
$paid_amount = (float)($bill['paid_amount'] ?? 0);
$discount_amount = (float)($bill['total_discount'] ?? $bill['discount_amount'] ?? 0);
$pharmacy_discount = (float)($bill['pharmacy_discount'] ?? 0);
$cashier_discount = (float)($bill['cashier_discount'] ?? 0);
$premium_amount = (float)($bill['premium_amount'] ?? 0);
$premium_note = $bill['premium_note'] ?? '';
$balance = (float)($bill['balance'] ?? 0);

$subtotal = 0;
$active_items_count = 0;
$cancelled_items_count = 0;
$paid_items_count = 0;
$pending_items_count = 0;

foreach ($bill_items as $item) {
    $item_status = $item['status'] ?? 'pending';
    if ($item_status === 'cancelled') {
        $cancelled_items_count++;
    } else {
        $subtotal += (float)($item['total_price'] ?? 0);
        if ($item_status === 'paid') $paid_items_count++;
        elseif ($item_status === 'pending') $pending_items_count++;
        $active_items_count++;
    }
}

// ✅ FLAGS FOR BUTTON VISIBILITY
$can_edit_bill = in_array($bill_status, ['pending', 'partial']);
$can_cancel_items = in_array($bill_status, ['pending', 'partial']);
$is_bill_paid = ($bill_status === 'paid');
$is_bill_cancelled = ($bill_status === 'cancelled');

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
// STATUS HELPERS
// ================================================================
function getStatusBadge($status) {
    $status = $status ?? 'pending';
    $classes = [
        'pending' => 'warning', 'partial' => 'warning', 'paid' => 'success',
        'cancelled' => 'danger', 'active' => 'success', 'inactive' => 'danger',
        'completed' => 'success', 'unknown' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $status = $status ?? 'pending';
    $icons = [
        'pending' => 'fa-clock', 'partial' => 'fa-hourglass-half',
        'paid' => 'fa-check-circle', 'cancelled' => 'fa-times-circle',
        'active' => 'fa-check-circle', 'inactive' => 'fa-times-circle',
        'completed' => 'fa-check-circle', 'unknown' => 'fa-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getItemTypeIcon($type) {
    $icons = [
        'registration' => 'fa-user-plus', 'consultation' => 'fa-stethoscope',
        'lab_test' => 'fa-flask', 'medication' => 'fa-pills',
        'procedure' => 'fa-syringe', 'equipment' => 'fa-tools',
        'tool' => 'fa-tools', 'other' => 'fa-circle'
    ];
    return $icons[$type] ?? 'fa-circle';
}

function getItemTypeLabel($type) {
    return ucfirst(str_replace('_', ' ', $type ?? 'Other'));
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #059669; --primary-dark: #047857; --primary-light: #34D399;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --success: #059669; --danger: #DC2626; --warning: #D97706;
            --purple: #7C3AED;
            --gray-50: #F8FAFC; --gray-100: #F1F5F9; --gray-200: #E2E8F0;
            --gray-300: #CBD5E1; --gray-400: #94A3B8; --gray-500: #64748B;
            --gray-600: #475569; --gray-700: #334155; --gray-800: #1E293B;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
            --bg-body: #F0FDF4; --bg-card: #FFFFFF; --bg-nav: #FFFFFF;
            --text-primary: #1E293B; --text-secondary: #64748B;
            --border-color: #D1FAE5; --radius: 12px; --radius-lg: 18px;
            --table-hover: #ECFDF5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A; --bg-card: #1E293B; --bg-nav: #1E293B;
            --text-primary: #F1F5F9; --text-secondary: #94A3B8;
            --border-color: #334155; --primary: #34D399;
            --primary-dark: #059669; --primary-light: #6EE7B7;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --table-hover: #1A3A2A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* TOP NAV */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0; height: 68px;
            background: var(--bg-nav); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid var(--border-color);
            backdrop-filter: blur(10px); box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex; align-items: center; background: var(--bg-body);
            border-radius: var(--radius); border: 2px solid var(--border-color);
            flex: 1; max-width: 500px;
        }
        
        .top-nav .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 100%; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient); color: white; border: none;
            padding: 8px 16px; border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer; font-size: 0.85rem;
        }
        
        .top-nav .datetime {
            font-size: 0.78rem; color: var(--text-secondary);
            display: flex; align-items: center; gap: 6px;
        }
        
        .top-nav .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); background: transparent;
            border: none; cursor: pointer; position: relative;
        }
        
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; border-radius: 50%;
            border: 2px solid var(--bg-nav); background: var(--danger);
        }
        
        .dark-toggle-btn {
            background: var(--bg-body); border: 2px solid var(--border-color);
            border-radius: var(--radius); padding: 6px 12px;
            cursor: pointer; font-size: 0.82rem; color: var(--text-primary);
            display: flex; align-items: center; gap: 6px;
        }
        
        .branch-selector {
            background: var(--bg-body); border: 2px solid var(--border-color);
            border-radius: var(--radius); padding: 6px 12px;
            font-size: 0.78rem; color: var(--text-primary);
            outline: none; cursor: pointer;
        }
        
        /* MAIN */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 28px 32px; min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg); padding: 28px 36px;
            margin-bottom: 28px;
            display: flex; flex-wrap: wrap; justify-content: space-between;
            align-items: center; gap: 16px;
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.25);
            position: relative; overflow: hidden;
        }
        
        .page-header::before {
            content: ''; position: absolute; top: -60%; right: -10%;
            width: 400px; height: 400px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }
        
        .page-header .page-title {
            color: white; font-size: 1.8rem; font-weight: 700;
            display: flex; align-items: center; gap: 12px;
            flex-wrap: wrap; position: relative; z-index: 1;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85); font-size: 0.95rem;
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap; position: relative; z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2); color: white;
            padding: 4px 14px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12); color: white;
            padding: 4px 14px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 500;
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12); color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px; border-radius: var(--radius);
            font-weight: 500; font-size: 0.82rem;
            text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px;
            position: relative; z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* STATS GRID */
        .stats-grid {
            display: grid; grid-template-columns: repeat(5, 1fr);
            gap: 14px; margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius); padding: 18px 20px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: var(--shadow-md); color: white;
            min-height: 100px; transition: all 0.3s;
        }
        
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); }
        
        .stat-card.card-total { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.card-discount { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.card-premium { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .stat-card.card-paid { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.card-balance { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .stat-card.card-balance.zero { background: linear-gradient(135deg, #059669, #047857); }
        
        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; background: rgba(255,255,255,0.2);
            color: white; flex-shrink: 0;
        }
        
        .stat-card .stat-content { flex: 1; }
        
        .stat-label {
            font-size: 0.6rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            margin: 0; color: rgba(255,255,255,0.9);
        }
        
        .stat-value {
            font-size: 1.15rem; font-weight: 800;
            margin: 3px 0 0 0; line-height: 1.2; color: white;
        }
        
        .stat-sub {
            font-size: 0.55rem; margin-top: 3px;
            color: rgba(255,255,255,0.85);
        }
        
        /* BADGES */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 14px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700;
        }
        
        .badge-success { background: #059669; color: white; }
        .badge-danger { background: #DC2626; color: white; }
        .badge-warning { background: #D97706; color: white; }
        .badge-info { background: #0B5ED7; color: white; }
        .badge-secondary { background: #64748B; color: white; }
        
        /* ✅ ITEM STATUS BADGES */
        .item-status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 14px; border-radius: 20px;
            font-size: 0.62rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        
        .item-status-pending {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
        }
        
        .item-status-paid {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        
        .item-status-cancelled {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        }
        
        /* DETAIL CARD */
        .detail-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            padding: 24px 28px; border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm); margin-bottom: 24px;
        }
        
        .detail-label {
            font-size: 0.7rem; color: var(--text-secondary);
            font-weight: 500; text-transform: uppercase;
        }
        
        .detail-value {
            font-size: 0.95rem; font-weight: 600;
            color: var(--text-primary);
        }
        
        /* DATA TABLE */
        .table-container { overflow-x: auto; }
        
        .data-table {
            width: 100%; border-collapse: separate;
            border-spacing: 0; font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient); color: white;
            font-weight: 600; padding: 10px 12px;
            font-size: 0.65rem; text-transform: uppercase;
            letter-spacing: 0.05em; white-space: nowrap;
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary); vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--table-hover); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        .data-table tbody tr.row-cancelled td {
            opacity: 0.6;
            background: rgba(220, 38, 38, 0.05);
        }
        
        .data-table tbody tr.row-cancelled td .item-name {
            text-decoration: line-through;
            color: var(--text-secondary);
        }
        
        /* ✅ ITEM ACTION BUTTONS */
        .item-actions {
            display: flex; gap: 4px; flex-wrap: wrap;
            justify-content: center;
        }
        
        .item-btn {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 4px 10px; border-radius: 6px;
            font-size: 0.6rem; font-weight: 600;
            border: none; cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .item-btn i { font-size: 0.65rem; }
        
        .item-btn-view {
            background: #E8F0FE; color: #0B5ED7;
            border: 1px solid rgba(11, 94, 215, 0.2);
        }
        .item-btn-view:hover {
            background: #0B5ED7; color: white;
            transform: translateY(-2px);
        }
        
        .item-btn-edit {
            background: #FEF3C7; color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }
        .item-btn-edit:hover {
            background: #D97706; color: white;
            transform: translateY(-2px);
        }
        
        .item-btn-cancel {
            background: #FEE2E2; color: #DC2626;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        .item-btn-cancel:hover {
            background: #DC2626; color: white;
            transform: translateY(-2px);
        }
        
        .item-btn-restore {
            background: #D1FAE5; color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }
        .item-btn-restore:hover {
            background: #059669; color: white;
            transform: translateY(-2px);
        }
        
        [data-theme="dark"] .item-btn-view { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .item-btn-edit { background: #3A2E1A; color: #FBBF24; }
        [data-theme="dark"] .item-btn-cancel { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .item-btn-restore { background: #1A3A2A; color: #34D399; }
        
        /* CARD */
        .card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            border: 2px solid var(--border-color); overflow: hidden;
            box-shadow: var(--shadow-sm); margin-bottom: 24px;
        }
        
        .card-header {
            padding: 16px 24px; background: var(--primary-gradient);
            display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 8px;
        }
        
        .card-header .card-title {
            font-size: 0.9rem; font-weight: 600;
            color: white; margin: 0;
            display: flex; align-items: center; gap: 8px;
            flex-wrap: wrap;
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center; padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i { font-size: 2rem; color: var(--border-color); margin-bottom: 10px; }
        .empty-state h4 { font-size: 0.95rem; color: var(--text-primary); margin-bottom: 4px; }
        
        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px;
            font-weight: 600; font-size: 0.8rem;
            cursor: pointer; border: none; text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-gradient); color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent; color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body); border-color: var(--primary);
            color: var(--primary);
        }
        
        /* FLASH */
        .flash-message {
            padding: 14px 20px; border-radius: var(--radius);
            margin-bottom: 20px; display: flex;
            align-items: center; gap: 12px; font-weight: 500;
        }
        .flash-message.success {
            background: #D1FAE5; color: #065F46;
            border-left: 5px solid #059669;
        }
        .flash-message.error {
            background: #FEE2E2; color: #991B1B;
            border-left: 5px solid #DC2626;
        }
        
        /* MODAL */
        .modal-overlay {
            display: none; position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7); z-index: 9999;
            justify-content: center; align-items: center;
            padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        
        .modal-content {
            background: var(--bg-card); border-radius: var(--radius-lg);
            max-width: 500px; width: 100%;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
        }
        
        .modal-header {
            display: flex; justify-content: space-between;
            align-items: center; padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .modal-title {
            font-size: 1.1rem; font-weight: 700;
            color: #DC2626; display: flex;
            align-items: center; gap: 8px;
        }
        
        .modal-close {
            background: none; border: none; font-size: 1.5rem;
            cursor: pointer; color: var(--text-secondary);
        }
        
        /* FOOTER */
        .footer {
            padding: 14px 0; border-top: 2px solid var(--border-color);
            margin-top: 24px; text-align: center;
            font-size: 0.7rem; color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 500; }
        
        /* ✅ INFO ALERT */
        .info-alert {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #D97706;
            border-radius: var(--radius);
            padding: 12px 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.8rem;
            color: #92400E;
        }
        
        .info-alert i { color: #D97706; font-size: 1.2rem; }
        
        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .item-actions { flex-direction: column; }
            .item-btn { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .item-actions { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<!-- TOP NAV -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search items...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%23059669%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<main class="main-content">

    <!-- FLASH MESSAGE -->
    <?php if ($flash_message): ?>
        <div class="flash-message <?= $flash_type ?>">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $flash_message ?></span>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Bill Details
                <span class="role-badge-display">ADMIN</span>
                <?php if ($is_bill_paid): ?>
                    <span style="background:rgba(5,150,105,0.4);color:#34D399;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:700;border:1px solid rgba(52,211,153,0.4);">
                        <i class="fas fa-check-circle"></i> PAID BILL
                    </span>
                <?php elseif ($is_bill_cancelled): ?>
                    <span style="background:rgba(220,38,38,0.4);color:#F87171;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:700;border:1px solid rgba(248,113,113,0.4);">
                        <i class="fas fa-times-circle"></i> CANCELLED BILL
                    </span>
                <?php else: ?>
                    <span style="background:rgba(217,119,6,0.4);color:#FBBF24;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:700;border:1px solid rgba(251,191,36,0.4);">
                        <i class="fas fa-clock"></i> <?= strtoupper($bill_status) ?> BILL
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_bill, 0) ?>
                </span>
                <?php if ($premium_amount > 0): ?>
                    <span class="header-badge" style="background:rgba(245,158,11,0.3);border-color:rgba(245,158,11,0.4);color:#FCD34D;">
                        <i class="fas fa-crown"></i> Premium: TSh <?= number_format($premium_amount, 0) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="bills.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($is_bill_paid): ?>
                <a href="print_receipt.php?bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light" style="background:rgba(251,191,36,0.2);" target="_blank">
                    <i class="fas fa-print"></i> Print Receipt
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ✅ INFO ALERT - Shows button rules based on bill status -->
    <?php if ($is_bill_paid): ?>
        <div class="info-alert" style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);border-color:#059669;color:#065F46;">
            <i class="fas fa-check-circle" style="color:#059669;"></i>
            <div>
                <strong>This bill is PAID.</strong> Items can only be <strong>VIEWED</strong>. 
                Edit and Cancel are not available for paid bills.
            </div>
        </div>
    <?php elseif ($is_bill_cancelled): ?>
        <div class="info-alert" style="background:linear-gradient(135deg,#FEE2E2,#FECACA);border-color:#DC2626;color:#991B1B;">
            <i class="fas fa-times-circle" style="color:#DC2626;"></i>
            <div>
                <strong>This bill is CANCELLED.</strong> Items can only be <strong>VIEWED</strong>.
            </div>
        </div>
    <?php else: ?>
        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>This bill is <?= strtoupper($bill_status) ?>.</strong> 
                You can <strong>VIEW</strong>, <strong>EDIT</strong>, and <strong>CANCEL</strong> items.
            </div>
        </div>
    <?php endif; ?>

    <!-- 5 SUMMARY CARDS -->
    <div class="stats-grid animate-fade-in-up">
        
        <div class="stat-card card-total">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Bill</p>
                <p class="stat-value">TSh <?= number_format($total_bill, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-receipt"></i> Bill amount</p>
            </div>
        </div>
        
        <div class="stat-card card-discount">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-content">
                <p class="stat-label">Discount</p>
                <p class="stat-value">TSh <?= number_format($discount_amount, 0) ?></p>
                <p class="stat-sub">
                    <?php if ($pharmacy_discount > 0 || $cashier_discount > 0): ?>
                        Pharm: <?= number_format($pharmacy_discount, 0) ?>
                        <?php if ($cashier_discount > 0): ?> | Cash: <?= number_format($cashier_discount, 0) ?><?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-percent"></i> No discount
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card card-premium">
            <div class="stat-icon"><i class="fas fa-crown"></i></div>
            <div class="stat-content">
                <p class="stat-label">Premium</p>
                <p class="stat-value">TSh <?= number_format($premium_amount, 0) ?></p>
                <p class="stat-sub">
                    <?php if (!empty($premium_note)): ?>
                        <?= htmlspecialchars($premium_note) ?>
                    <?php else: ?>
                        <i class="fas fa-star"></i> <?= $premium_amount > 0 ? 'Premium charged' : 'No premium' ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card card-paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Paid Amount</p>
                <p class="stat-value">TSh <?= number_format($paid_amount, 0) ?></p>
                <p class="stat-sub">
                    <?php if ($paid_amount > 0): ?>
                        <?= $balance <= 0 ? '<i class="fas fa-check-circle"></i> Fully paid' : '<i class="fas fa-hourglass-half"></i> Partial paid' ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i> No payment
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card card-balance <?= $balance <= 0 ? 'zero' : '' ?>">
            <div class="stat-icon"><i class="fas <?= $balance > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
            <div class="stat-content">
                <p class="stat-label">Balance</p>
                <p class="stat-value">TSh <?= number_format($balance, 0) ?></p>
                <p class="stat-sub">
                    <?= $balance > 0 ? '<i class="fas fa-clock"></i> Pending payment' : '<i class="fas fa-check-circle"></i> Fully paid!' ?>
                </p>
            </div>
        </div>
        
    </div>

    <!-- BILL INFORMATION -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-hashtag mr-1"></i> Bill Number</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Patient</p>
                <p class="detail-value font-semibold"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></p>
                <p class="text-xs text-gray-400">ID: <?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-info-circle mr-1"></i> Status</p>
                <p class="detail-value">
                    <span class="badge badge-<?= getStatusBadge($bill_status) ?>">
                        <i class="fas <?= getStatusIcon($bill_status) ?>"></i>
                        <?= strtoupper($bill_status) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-day mr-1"></i> Created</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-tie mr-1"></i> Created By</p>
                <p class="detail-value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-hospital mr-1"></i> Visit</p>
                <p class="detail-value">
                    <?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>
                    <span class="text-xs text-gray-400 block"><?= date('M d, Y', strtotime($bill['visit_date'] ?? 'now')) ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ BILL ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Bill Items
                <span class="text-white/70 ml-2 text-xs">(<?= count($bill_items) ?> items)</span>
                <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;">
                    Active: <?= $active_items_count ?>
                </span>
                <?php if ($cancelled_items_count > 0): ?>
                    <span style="background:rgba(220,38,38,0.3);padding:2px 10px;border-radius:12px;font-size:0.65rem;">
                        Cancelled: <?= $cancelled_items_count ?>
                    </span>
                <?php endif; ?>
            </h3>
            <div class="flex gap-2">
                <span class="text-white/70 text-xs">
                    <i class="far fa-clock"></i> Subtotal: TSh <?= number_format($subtotal, 0) ?>
                </span>
            </div>
        </div>
        <div class="table-container">
            <?php if (count($bill_items) > 0): ?>
                <table class="data-table" id="billItemsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th style="text-align:center;min-width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($bill_items as $item): 
                            $item_status = strtolower($item['status'] ?? 'pending');
                            $is_cancelled = ($item_status === 'cancelled');
                            
                            // Status badge
                            $status_class = 'item-status-pending';
                            $status_icon = 'fa-clock';
                            $status_text = 'PENDING';
                            
                            if ($item_status === 'paid') {
                                $status_class = 'item-status-paid';
                                $status_icon = 'fa-check-circle';
                                $status_text = 'PAID';
                            } elseif ($item_status === 'cancelled') {
                                $status_class = 'item-status-cancelled';
                                $status_icon = 'fa-times-circle';
                                $status_text = 'CANCELLED';
                            }
                        ?>
                            <tr class="<?= $is_cancelled ? 'row-cancelled' : '' ?>" data-item-id="<?= $item['id'] ?>">
                                <td class="text-center text-gray-400"><?= $counter++ ?></td>
                                <td>
                                    <span class="item-name font-medium">
                                        <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getItemTypeIcon($item['item_type'] ?? 'other') ?>"></i>
                                        <?= getItemTypeLabel($item['item_type'] ?? 'Other') ?>
                                    </span>
                                </td>
                                <td class="text-center font-semibold"><?= number_format($item['quantity'] ?? 1) ?></td>
                                <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td class="font-semibold">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="item-status-badge <?= $status_class ?>">
                                        <i class="fas <?= $status_icon ?>"></i>
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- ✅ ACTION BUTTONS - Based on BILL status -->
                                    <div class="item-actions">
                                        
                                        <!-- ✅ VIEW BUTTON - ALWAYS VISIBLE for all statuses -->
                                        <a href="view_bill_item.php?id=<?= $item['id'] ?>&bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" 
                                           class="item-btn item-btn-view" 
                                           title="View Item Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($can_edit_bill && !$is_cancelled): ?>
                                            <!-- ✅ EDIT BUTTON - Only if bill is PENDING/PARTIAL and item not cancelled -->
                                            <a href="edit_bill_item.php?id=<?= $item['id'] ?>&bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" 
                                               class="item-btn item-btn-edit" 
                                               title="Edit Item">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <!-- ✅ CANCEL BUTTON - Only if bill is PENDING/PARTIAL -->
                                            <button type="button" 
                                                    class="item-btn item-btn-cancel" 
                                                    onclick="confirmCancelItem(<?= $item['id'] ?>, '<?= addslashes($item['item_name'] ?? 'N/A') ?>')"
                                                    title="Cancel this item">
                                                <i class="fas fa-ban"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_edit_bill && $is_cancelled): ?>
                                            <!-- ✅ RESTORE BUTTON - Only for cancelled items in PENDING/PARTIAL bills -->
                                            <button type="button" 
                                                    class="item-btn item-btn-restore" 
                                                    onclick="confirmRestoreItem(<?= $item['id'] ?>, '<?= addslashes($item['item_name'] ?? 'N/A') ?>')"
                                                    title="Restore this item">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_bill_paid): ?>
                                            <!-- ✅ PAID BILL NOTICE -->
                                            <span style="font-size:0.6rem;color:#059669;font-weight:600;padding:4px 8px;background:#D1FAE5;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                                                <i class="fas fa-lock"></i> Paid - View Only
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_bill_cancelled): ?>
                                            <!-- ✅ CANCELLED BILL NOTICE -->
                                            <span style="font-size:0.6rem;color:#DC2626;font-weight:600;padding:4px 8px;background:#FEE2E2;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                                                <i class="fas fa-times-circle"></i> Bill Cancelled
                                            </span>
                                        <?php endif; ?>
                                        
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h4>No Bill Items Found</h4>
                    <p>Bill ID: <?= $bill_id ?> - This bill has no items in the database.</p>
                    <p style="font-size:0.75rem;color:#DC2626;margin-top:8px;">
                        <i class="fas fa-info-circle"></i> 
                        Debug: Query returned 0 rows for bill_id = <?= $bill_id ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAYMENTS -->
    <?php if (count($payments) > 0): ?>
        <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-hand-holding-usd"></i>
                    Payments (<?= count($payments) ?>)
                </h3>
                <span class="text-white/70 text-xs">
                    Total Paid: TSh <?= number_format($paid_amount, 0) ?>
                </span>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
                                </td>
                                <td class="font-semibold" style="color:#059669;">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= ucfirst($payment['payment_method'] ?? 'Cash') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_receipt.php?id=<?= $payment['id'] ?>&branch=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- QUICK ACTIONS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.2s;">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
            <i class="fas fa-bolt text-primary mr-2"></i> Quick Actions
        </h3>
        <div class="flex flex-wrap gap-3">
            <?php if ($can_edit_bill): ?>
                <a href="edit_bill.php?id=<?= $bill_id ?>&branch=<?= $branch_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Bill
                </a>
            <?php endif; ?>
            
            <?php if ($is_bill_paid): ?>
                <a href="print_receipt.php?bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Print Receipt
                </a>
            <?php endif; ?>
            
            <a href="bills.php?branch=<?= $branch_id ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bill Details - <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- CANCEL ITEM MODAL -->
<div class="modal-overlay" id="cancelItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-exclamation-triangle"></i> Cancel Item
            </div>
            <button class="modal-close" onclick="closeModal('cancelItemModal')">&times;</button>
        </div>
        
        <p style="text-align:center;font-size:3rem;color:#D97706;margin-bottom:10px;">
            <i class="fas fa-exclamation-circle"></i>
        </p>
        
        <p style="text-align:center;color:var(--text-primary);font-weight:600;margin-bottom:8px;">
            Are you sure you want to cancel this item?
        </p>
        <p style="text-align:center;color:var(--text-secondary);font-size:0.85rem;margin-bottom:16px;">
            Item: <strong id="cancelItemName"></strong>
        </p>
        
        <div style="background:#FEF3C7;border:2px solid #D97706;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#92400E;">
            <i class="fas fa-info-circle"></i>
            The item will be marked as <strong>cancelled</strong> and removed from the bill total. You can restore it later.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="cancel_bill_item">
            <input type="hidden" name="item_id" id="cancelItemId" value="">
            <input type="hidden" name="bill_id" value="<?= $bill_id ?>">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <div style="display:flex;gap:10px;padding-top:14px;border-top:2px solid var(--border-color);">
                <button type="submit" style="flex:1;background:#D97706;color:white;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;">
                    <i class="fas fa-ban"></i> Yes, Cancel Item
                </button>
                <button type="button" onclick="closeModal('cancelItemModal')" style="background:transparent;color:var(--text-secondary);border:2px solid var(--border-color);padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;">
                    <i class="fas fa-times"></i> No, Go Back
                </button>
            </div>
        </form>
    </div>
</div>

<!-- RESTORE ITEM MODAL -->
<div class="modal-overlay" id="restoreItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title" style="color:#059669;">
                <i class="fas fa-undo"></i> Restore Item
            </div>
            <button class="modal-close" onclick="closeModal('restoreItemModal')">&times;</button>
        </div>
        
        <p style="text-align:center;font-size:3rem;color:#059669;margin-bottom:10px;">
            <i class="fas fa-undo"></i>
        </p>
        
        <p style="text-align:center;color:var(--text-primary);font-weight:600;margin-bottom:8px;">
            Restore this cancelled item?
        </p>
        <p style="text-align:center;color:var(--text-secondary);font-size:0.85rem;margin-bottom:16px;">
            Item: <strong id="restoreItemName"></strong>
        </p>
        
        <div style="background:#D1FAE5;border:2px solid #059669;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#065F46;">
            <i class="fas fa-info-circle"></i>
            The item will be marked as <strong>pending</strong> and added back to the bill total.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="restore_bill_item">
            <input type="hidden" name="item_id" id="restoreItemId" value="">
            <input type="hidden" name="bill_id" value="<?= $bill_id ?>">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <div style="display:flex;gap:10px;padding-top:14px;border-top:2px solid var(--border-color);">
                <button type="submit" style="flex:1;background:#059669;color:white;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;">
                    <i class="fas fa-undo"></i> Yes, Restore
                </button>
                <button type="button" onclick="closeModal('restoreItemModal')" style="background:transparent;color:var(--text-secondary);border:2px solid var(--border-color);padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // DARK MODE
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // SIDEBAR
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // SEARCH
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim().toLowerCase();
        var table = document.getElementById('billItemsTable');
        if (!table) return;
        
        var rows = table.getElementsByTagName('tbody')[0]?.getElementsByTagName('tr');
        if (!rows) return;
        
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var text = row.textContent.toLowerCase();
            row.style.display = (query === '' || text.includes(query)) ? '' : 'none';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });
    searchInput?.addEventListener('input', performSearch);

    // BRANCH SWITCHER
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // CLOCK
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // MODALS
    function openModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
                modal.classList.remove('show');
            });
            document.body.style.overflow = '';
        }
    });

    function confirmCancelItem(itemId, itemName) {
        document.getElementById('cancelItemId').value = itemId;
        document.getElementById('cancelItemName').textContent = itemName;
        openModal('cancelItemModal');
    }
    
    function confirmRestoreItem(itemId, itemName) {
        document.getElementById('restoreItemId').value = itemId;
        document.getElementById('restoreItemName').textContent = itemName;
        openModal('restoreItemModal');
    }

    console.log('%c💰 Braick Dispensary - View Bill', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Bill Status: <?= strtoupper($bill_status) ?>', 'font-size:13px; font-weight:bold; color:<?= $is_bill_paid ? '#059669' : ($is_bill_cancelled ? '#DC2626' : '#D97706') ?>;');
    console.log('%c📦 Items loaded: <?= count($bill_items) ?>', 'font-size:13px; color:#F59E0B; font-weight:bold;');
    console.log('%c✅ Button Rules:', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
    console.log('%c   PAID bill → View only', 'font-size:12px; color:#059669;');
    console.log('%c   PENDING/PARTIAL bill → View, Edit, Cancel', 'font-size:12px; color:#D97706;');
    console.log('%c   CANCELLED bill → View only', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>