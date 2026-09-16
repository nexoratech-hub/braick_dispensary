<?php
// ================================================================
// FILE: frontend/pages/admin/view_bill.php
// ADMIN - VIEW BILL DETAILS
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Green theme + full dark mode support via --page-* variables
// ✅ 5 Cards with Premium + Background colors
// ✅ Buttons show/hide based on BILL status
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
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
// HANDLE CANCEL BILL ITEM
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_bill_item') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $bill_id_return = (int)($_POST['bill_id'] ?? 0);
    $branch_id_return = (int)($_POST['branch_id'] ?? 1);
    
    if ($item_id > 0) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
            $stmt->execute([$bill_id_return]);
            $bill_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill_check) throw new Exception('Bill not found');
            if ($bill_check['status'] === 'paid') throw new Exception('Cannot cancel items from a PAID bill');
            if ($bill_check['status'] === 'cancelled') throw new Exception('Cannot cancel items from a CANCELLED bill');
            
            $stmt = $db->prepare("UPDATE bill_items SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$item_id]);
            
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
            
            $stmt = $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?");
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
// HANDLE RESTORE BILL ITEM
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_bill_item') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $bill_id_return = (int)($_POST['bill_id'] ?? 0);
    $branch_id_return = (int)($_POST['branch_id'] ?? 1);
    
    if ($item_id > 0) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
            $stmt->execute([$bill_id_return]);
            $bill_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill_check || $bill_check['status'] === 'paid') throw new Exception('Cannot restore items on a PAID bill');
            
            $stmt = $db->prepare("UPDATE bill_items SET status = 'pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$item_id]);
            
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
            
            $stmt = $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?");
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
// FETCH BILL
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT b.*, p.id as patient_id, p.patient_id as patient_number, p.full_name as patient_name,
            p.phone as patient_phone, p.email as patient_email, p.address as patient_address,
            p.gender as patient_gender, u.full_name as created_by_name, br.name as branch_name,
            v.visit_number, v.visit_date, v.status as visit_status
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
// FETCH BILL ITEMS
// ================================================================
$bill_items = [];
try {
    $stmt = $db->prepare("
        SELECT bi.id, bi.bill_id, bi.patient_id, bi.branch_id, bi.item_type, bi.item_id,
            bi.item_name, bi.item_code, bi.description, bi.quantity, bi.unit_price,
            bi.total_price, bi.discount_amount, bi.final_price, bi.reference_id,
            bi.reference_type, bi.status, bi.created_at, bi.updated_at
        FROM bill_items bi
        WHERE bi.bill_id = ?
        ORDER BY bi.id ASC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("❌ Error fetching bill items: " . $e->getMessage());
    $bill_items = [];
}

// ================================================================
// FETCH PAYMENTS
// ================================================================
$payments = [];
try {
    $stmt = $db->prepare("SELECT p.*, u.full_name as received_by_name FROM payments p LEFT JOIN users u ON p.received_by = u.id WHERE p.bill_id = ? ORDER BY p.received_at DESC");
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $payments = []; }

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
} catch (Exception $e) { $branches = []; }

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

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* PAGE HEADER */
    .page-header-bill {
        background: linear-gradient(135deg, #059669, #047857);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(5, 150, 105, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-bill::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-bill .page-title-bill {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-bill .page-subtitle-bill {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-bill .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .page-header-bill .header-badge-bill {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-bill .btn-outline-light-bill {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
        transition: all 0.3s;
    }

    .page-header-bill .btn-outline-light-bill:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* STATS GRID */
    .stats-grid-bill {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    .stat-card-bill {
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        color: white;
        min-height: 100px;
        transition: all 0.3s;
    }

    .stat-card-bill:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    }

    .stat-card-bill.card-total { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card-bill.card-discount { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card-bill.card-premium { background: linear-gradient(135deg, #F59E0B, #D97706); }
    .stat-card-bill.card-paid { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-bill.card-balance { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .stat-card-bill.card-balance.zero { background: linear-gradient(135deg, #059669, #047857); }

    .stat-card-bill .stat-icon-bill {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.2);
        color: white;
        flex-shrink: 0;
    }

    .stat-card-bill .stat-content-bill { flex: 1; }

    .stat-label-bill {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
        color: rgba(255,255,255,0.9);
    }

    .stat-value-bill {
        font-size: 1.15rem;
        font-weight: 800;
        margin: 3px 0 0 0;
        line-height: 1.2;
        color: white;
    }

    .stat-sub-bill {
        font-size: 0.55rem;
        margin-top: 3px;
        color: rgba(255,255,255,0.85);
    }

    /* DETAIL CARD */
    .detail-card-bill {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        margin-bottom: 24px;
    }

    [data-theme="dark"] .detail-card-bill { background: #1E293B; border-color: #334155; }

    .detail-label-bill {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        text-transform: uppercase;
    }

    .detail-value-bill {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    /* BADGES */
    .badge-bill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
    }

    .badge-success-bill { background: #059669; }
    .badge-danger-bill { background: #DC2626; }
    .badge-warning-bill { background: #D97706; }
    .badge-info-bill { background: #0B5ED7; }
    .badge-secondary-bill { background: #64748B; }

    /* ITEM STATUS BADGES */
    .item-status-badge-bill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .item-status-pending-bill {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
    }

    .item-status-paid-bill {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }

    .item-status-cancelled-bill {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }

    /* CARD */
    .card-bill {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 24px;
    }

    [data-theme="dark"] .card-bill { background: #1E293B; border-color: #334155; }

    .card-header-bill {
        padding: 16px 24px;
        background: linear-gradient(135deg, #059669, #047857);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .card-header-bill .card-title-bill {
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* DATA TABLE */
    .table-container-bill { overflow-x: auto; }

    .data-table-bill {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table-bill thead th {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-bill thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-bill thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-bill td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .data-table-bill tbody tr:hover td { background: #ECFDF5; }
    [data-theme="dark"] .data-table-bill tbody tr:hover td { background: #1A3A2A; }
    .data-table-bill tbody tr:last-child td { border-bottom: none; }

    .data-table-bill tbody tr.row-cancelled-bill td {
        opacity: 0.6;
        background: rgba(220, 38, 38, 0.05);
    }

    .data-table-bill tbody tr.row-cancelled-bill td .item-name-bill {
        text-decoration: line-through;
        color: var(--page-text-secondary, #64748B);
    }

    /* ITEM ACTION BUTTONS */
    .item-actions-bill {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .item-btn-bill {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .item-btn-bill i { font-size: 0.65rem; }

    .item-btn-view-bill {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.2);
    }
    .item-btn-view-bill:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-2px);
    }

    .item-btn-edit-bill {
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid rgba(217, 119, 6, 0.2);
    }
    .item-btn-edit-bill:hover {
        background: #D97706;
        color: white;
        transform: translateY(-2px);
    }

    .item-btn-cancel-bill {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid rgba(220, 38, 38, 0.2);
    }
    .item-btn-cancel-bill:hover {
        background: #DC2626;
        color: white;
        transform: translateY(-2px);
    }

    .item-btn-restore-bill {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid rgba(5, 150, 105, 0.2);
    }
    .item-btn-restore-bill:hover {
        background: #059669;
        color: white;
        transform: translateY(-2px);
    }

    [data-theme="dark"] .item-btn-view-bill { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .item-btn-edit-bill { background: #3A2E1A; color: #FBBF24; }
    [data-theme="dark"] .item-btn-cancel-bill { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .item-btn-restore-bill { background: #1A3A2A; color: #34D399; }

    /* EMPTY STATE */
    .empty-state-bill {
        text-align: center;
        padding: 30px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-bill i {
        font-size: 2rem;
        color: var(--page-text-muted, #94A3B8);
        margin-bottom: 10px;
        display: block;
    }

    /* BUTTONS */
    .btn-bill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: all 0.3s;
        font-family: inherit;
    }

    .btn-primary-bill {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }
    .btn-primary-bill:hover {
        background: linear-gradient(135deg, #047857, #065F46);
        transform: translateY(-2px);
        color: white;
    }

    .btn-outline-bill {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-bill:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: #059669;
        color: #059669;
    }

    /* FLASH */
    .flash-message-bill {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDownBill 0.4s ease;
    }

    @keyframes slideDownBill {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .flash-message-bill.success {
        background: #D1FAE5;
        color: #065F46;
        border-left: 5px solid #059669;
    }
    .flash-message-bill.error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 5px solid #DC2626;
    }

    [data-theme="dark"] .flash-message-bill.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .flash-message-bill.error { background: #3A1A1A; color: #F87171; }

    /* INFO ALERT */
    .info-alert-bill {
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border: 2px solid #D97706;
        border-radius: 12px;
        padding: 12px 18px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.8rem;
        color: #92400E;
    }

    .info-alert-bill i { color: #D97706; font-size: 1.2rem; }

    [data-theme="dark"] .info-alert-bill { background: linear-gradient(135deg, #3A2A1A, #3D2E0A); color: #FBBF24; }
    [data-theme="dark"] .info-alert-bill i { color: #FBBF24; }

    /* MODAL */
    .modal-overlay-bill {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .modal-overlay-bill.show { display: flex; }

    .modal-content-bill {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        max-width: 500px;
        width: 100%;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        animation: modalSlideInBill 0.3s ease;
    }

    [data-theme="dark"] .modal-content-bill { background: #1E293B; }

    @keyframes modalSlideInBill {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header-bill {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 16px;
    }

    .modal-title-bill {
        font-size: 1.1rem;
        font-weight: 700;
        color: #DC2626;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close-bill {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
    }

    /* FOOTER */
    .footer-bill {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-bill .footer-brand-bill {
        color: #059669;
        font-weight: 500;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) { .stats-grid-bill { grid-template-columns: repeat(3, 1fr); } }

    @media (max-width: 1024px) {
        .stats-grid-bill { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-bill { padding: 16px 18px; }
        .page-header-bill .page-title-bill { font-size: 1.3rem; }
        .stats-grid-bill { grid-template-columns: 1fr 1fr; }
        .item-actions-bill { flex-direction: column; }
        .item-btn-bill { width: 100%; justify-content: center; }
    }

    @media (max-width: 480px) {
        .stats-grid-bill { grid-template-columns: 1fr; }
        .data-table-bill { font-size: 0.65rem; }
        .data-table-bill thead th, .data-table-bill td { padding: 6px 8px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- FLASH MESSAGE -->
    <?php if ($flash_message): ?>
        <div class="flash-message-bill <?= $flash_type ?>">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $flash_message ?></span>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header-bill">
        <div>
            <h1 class="page-title-bill">
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
            <p class="page-subtitle-bill">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></strong>
                <span class="header-badge-bill">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge-bill" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge-bill" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_bill, 0) ?>
                </span>
                <?php if ($premium_amount > 0): ?>
                    <span class="header-badge-bill" style="background:rgba(245,158,11,0.3);border-color:rgba(245,158,11,0.4);color:#FCD34D;">
                        <i class="fas fa-crown"></i> Premium: TSh <?= number_format($premium_amount, 0) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="bills.php?branch=<?= $branch_id ?>" class="btn-outline-light-bill">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($is_bill_paid): ?>
                <a href="print_receipt.php?bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light-bill" style="background:rgba(251,191,36,0.2);" target="_blank">
                    <i class="fas fa-print"></i> Print Receipt
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- INFO ALERT -->
    <?php if ($is_bill_paid): ?>
        <div class="info-alert-bill" style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);border-color:#059669;color:#065F46;">
            <i class="fas fa-check-circle" style="color:#059669;"></i>
            <div>
                <strong>This bill is PAID.</strong> Items can only be <strong>VIEWED</strong>. Edit and Cancel are not available for paid bills.
            </div>
        </div>
    <?php elseif ($is_bill_cancelled): ?>
        <div class="info-alert-bill" style="background:linear-gradient(135deg,#FEE2E2,#FECACA);border-color:#DC2626;color:#991B1B;">
            <i class="fas fa-times-circle" style="color:#DC2626;"></i>
            <div>
                <strong>This bill is CANCELLED.</strong> Items can only be <strong>VIEWED</strong>.
            </div>
        </div>
    <?php else: ?>
        <div class="info-alert-bill">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>This bill is <?= strtoupper($bill_status) ?>.</strong> You can <strong>VIEW</strong>, <strong>EDIT</strong>, and <strong>CANCEL</strong> items.
            </div>
        </div>
    <?php endif; ?>

    <!-- 5 SUMMARY CARDS -->
    <div class="stats-grid-bill">
        <div class="stat-card-bill card-total">
            <div class="stat-icon-bill"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content-bill">
                <p class="stat-label-bill">Total Bill</p>
                <p class="stat-value-bill">TSh <?= number_format($total_bill, 0) ?></p>
                <p class="stat-sub-bill"><i class="fas fa-receipt"></i> Bill amount</p>
            </div>
        </div>
        
        <div class="stat-card-bill card-discount">
            <div class="stat-icon-bill"><i class="fas fa-tags"></i></div>
            <div class="stat-content-bill">
                <p class="stat-label-bill">Discount</p>
                <p class="stat-value-bill">TSh <?= number_format($discount_amount, 0) ?></p>
                <p class="stat-sub-bill">
                    <?php if ($pharmacy_discount > 0 || $cashier_discount > 0): ?>
                        Pharm: <?= number_format($pharmacy_discount, 0) ?>
                        <?php if ($cashier_discount > 0): ?> | Cash: <?= number_format($cashier_discount, 0) ?><?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-percent"></i> No discount
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card-bill card-premium">
            <div class="stat-icon-bill"><i class="fas fa-crown"></i></div>
            <div class="stat-content-bill">
                <p class="stat-label-bill">Premium</p>
                <p class="stat-value-bill">TSh <?= number_format($premium_amount, 0) ?></p>
                <p class="stat-sub-bill">
                    <?php if (!empty($premium_note)): ?>
                        <?= htmlspecialchars($premium_note) ?>
                    <?php else: ?>
                        <i class="fas fa-star"></i> <?= $premium_amount > 0 ? 'Premium charged' : 'No premium' ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card-bill card-paid">
            <div class="stat-icon-bill"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-bill">
                <p class="stat-label-bill">Paid Amount</p>
                <p class="stat-value-bill">TSh <?= number_format($paid_amount, 0) ?></p>
                <p class="stat-sub-bill">
                    <?php if ($paid_amount > 0): ?>
                        <?= $balance <= 0 ? '<i class="fas fa-check-circle"></i> Fully paid' : '<i class="fas fa-hourglass-half"></i> Partial paid' ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i> No payment
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card-bill card-balance <?= $balance <= 0 ? 'zero' : '' ?>">
            <div class="stat-icon-bill"><i class="fas <?= $balance > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
            <div class="stat-content-bill">
                <p class="stat-label-bill">Balance</p>
                <p class="stat-value-bill">TSh <?= number_format($balance, 0) ?></p>
                <p class="stat-sub-bill">
                    <?= $balance > 0 ? '<i class="fas fa-clock"></i> Pending payment' : '<i class="fas fa-check-circle"></i> Fully paid!' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- BILL INFORMATION -->
    <div class="detail-card-bill">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div>
                <p class="detail-label-bill"><i class="fas fa-hashtag"></i> Bill Number</p>
                <p class="detail-value-bill" style="font-family:monospace;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-bill"><i class="fas fa-user"></i> Patient</p>
                <p class="detail-value-bill"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></p>
                <p style="font-size:0.7rem;color:var(--page-text-muted);">ID: <?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-bill"><i class="fas fa-info-circle"></i> Status</p>
                <p class="detail-value-bill">
                    <span class="badge-bill badge-<?= getStatusBadge($bill_status) ?>-bill">
                        <i class="fas <?= getStatusIcon($bill_status) ?>"></i>
                        <?= strtoupper($bill_status) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label-bill"><i class="fas fa-calendar-day"></i> Created</p>
                <p class="detail-value-bill"><?= date('M d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label-bill"><i class="fas fa-user-tie"></i> Created By</p>
                <p class="detail-value-bill"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-bill"><i class="fas fa-hospital"></i> Visit</p>
                <p class="detail-value-bill">
                    <?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>
                    <span style="font-size:0.7rem;color:var(--page-text-muted);display:block;"><?= date('M d, Y', strtotime($bill['visit_date'] ?? 'now')) ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- BILL ITEMS TABLE -->
    <div class="card-bill">
        <div class="card-header-bill">
            <h3 class="card-title-bill">
                <i class="fas fa-list"></i>
                Bill Items
                <span style="opacity:0.7;margin-left:8px;font-size:0.75rem;">(<?= count($bill_items) ?> items)</span>
                <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;">
                    Active: <?= $active_items_count ?>
                </span>
                <?php if ($cancelled_items_count > 0): ?>
                    <span style="background:rgba(220,38,38,0.3);padding:2px 10px;border-radius:12px;font-size:0.65rem;">
                        Cancelled: <?= $cancelled_items_count ?>
                    </span>
                <?php endif; ?>
            </h3>
            <div style="display:flex;gap:8px;">
                <span style="color:rgba(255,255,255,0.7);font-size:0.75rem;">
                    <i class="far fa-clock"></i> Subtotal: TSh <?= number_format($subtotal, 0) ?>
                </span>
            </div>
        </div>
        <div class="table-container-bill">
            <?php if (count($bill_items) > 0): ?>
                <table class="data-table-bill" id="billItemsTable">
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
                            
                            $status_class = 'item-status-pending-bill';
                            $status_icon = 'fa-clock';
                            $status_text = 'PENDING';
                            
                            if ($item_status === 'paid') {
                                $status_class = 'item-status-paid-bill';
                                $status_icon = 'fa-check-circle';
                                $status_text = 'PAID';
                            } elseif ($item_status === 'cancelled') {
                                $status_class = 'item-status-cancelled-bill';
                                $status_icon = 'fa-times-circle';
                                $status_text = 'CANCELLED';
                            }
                        ?>
                            <tr class="<?= $is_cancelled ? 'row-cancelled-bill' : '' ?>" data-item-id="<?= $item['id'] ?>">
                                <td style="text-align:center;color:var(--page-text-muted);"><?= $counter++ ?></td>
                                <td>
                                    <span class="item-name-bill" style="font-weight:500;">
                                        <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-bill badge-info-bill" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getItemTypeIcon($item['item_type'] ?? 'other') ?>"></i>
                                        <?= getItemTypeLabel($item['item_type'] ?? 'Other') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:600;"><?= number_format($item['quantity'] ?? 1) ?></td>
                                <td>TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="item-status-badge-bill <?= $status_class ?>">
                                        <i class="fas <?= $status_icon ?>"></i>
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="item-actions-bill">
                                        <a href="view_bill_item.php?id=<?= $item['id'] ?>&bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" 
                                           class="item-btn-bill item-btn-view-bill" 
                                           title="View Item Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($can_edit_bill && !$is_cancelled): ?>
                                            <a href="edit_bill_item.php?id=<?= $item['id'] ?>&bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" 
                                               class="item-btn-bill item-btn-edit-bill" 
                                               title="Edit Item">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <button type="button" 
                                                    class="item-btn-bill item-btn-cancel-bill" 
                                                    onclick="confirmCancelItemBill(<?= $item['id'] ?>, '<?= addslashes($item['item_name'] ?? 'N/A') ?>')"
                                                    title="Cancel this item">
                                                <i class="fas fa-ban"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_edit_bill && $is_cancelled): ?>
                                            <button type="button" 
                                                    class="item-btn-bill item-btn-restore-bill" 
                                                    onclick="confirmRestoreItemBill(<?= $item['id'] ?>, '<?= addslashes($item['item_name'] ?? 'N/A') ?>')"
                                                    title="Restore this item">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_bill_paid): ?>
                                            <span style="font-size:0.6rem;color:#059669;font-weight:600;padding:4px 8px;background:#D1FAE5;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                                                <i class="fas fa-lock"></i> Paid - View Only
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_bill_cancelled): ?>
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
                <div class="empty-state-bill">
                    <i class="fas fa-receipt"></i>
                    <h4 style="font-size:0.95rem;color:var(--page-text-primary);margin-bottom:4px;">No Bill Items Found</h4>
                    <p>Bill ID: <?= $bill_id ?> - This bill has no items in the database.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAYMENTS -->
    <?php if (count($payments) > 0): ?>
        <div class="card-bill">
            <div class="card-header-bill">
                <h3 class="card-title-bill">
                    <i class="fas fa-hand-holding-usd"></i>
                    Payments (<?= count($payments) ?>)
                </h3>
                <span style="color:rgba(255,255,255,0.7);font-size:0.75rem;">
                    Total Paid: TSh <?= number_format($paid_amount, 0) ?>
                </span>
            </div>
            <div class="table-container-bill">
                <table class="data-table-bill">
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
                                <td style="font-family:monospace;font-size:0.75rem;font-weight:600;">
                                    <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
                                </td>
                                <td style="font-weight:600;color:#059669;">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge-bill badge-info-bill" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= ucfirst($payment['payment_method'] ?? 'Cash') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                                <td style="font-size:0.75rem;"><?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_receipt.php?id=<?= $payment['id'] ?>&branch=<?= $branch_id ?>" style="color:#059669;font-size:0.75rem;text-decoration:underline;">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- QUICK ACTIONS -->
    <div class="detail-card-bill">
        <h3 style="font-size:0.85rem;font-weight:600;color:var(--page-text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px;">
            <i class="fas fa-bolt" style="color:#059669;"></i> Quick Actions
        </h3>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <?php if ($can_edit_bill): ?>
                <a href="edit_bill.php?id=<?= $bill_id ?>&branch=<?= $branch_id ?>" class="btn-bill btn-primary-bill">
                    <i class="fas fa-edit"></i> Edit Bill
                </a>
            <?php endif; ?>
            
            <?php if ($is_bill_paid): ?>
                <a href="print_receipt.php?bill_id=<?= $bill_id ?>&branch=<?= $branch_id ?>" class="btn-bill btn-primary-bill" target="_blank">
                    <i class="fas fa-print"></i> Print Receipt
                </a>
            <?php endif; ?>
            
            <a href="bills.php?branch=<?= $branch_id ?>" class="btn-bill btn-outline-bill">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-bill">
        <p>
            <span class="footer-brand-bill">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Bill Details - <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- CANCEL ITEM MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-bill" id="cancelItemModalBill">
    <div class="modal-content-bill">
        <div class="modal-header-bill">
            <div class="modal-title-bill">
                <i class="fas fa-exclamation-triangle"></i> Cancel Item
            </div>
            <button class="modal-close-bill" onclick="closeModalBill('cancelItemModalBill')">&times;</button>
        </div>
        
        <p style="text-align:center;font-size:3rem;color:#D97706;margin-bottom:10px;">
            <i class="fas fa-exclamation-circle"></i>
        </p>
        
        <p style="text-align:center;color:var(--page-text-primary);font-weight:600;margin-bottom:8px;">
            Are you sure you want to cancel this item?
        </p>
        <p style="text-align:center;color:var(--page-text-secondary);font-size:0.85rem;margin-bottom:16px;">
            Item: <strong id="cancelItemNameBill"></strong>
        </p>
        
        <div style="background:#FEF3C7;border:2px solid #D97706;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#92400E;">
            <i class="fas fa-info-circle"></i>
            The item will be marked as <strong>cancelled</strong> and removed from the bill total. You can restore it later.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="cancel_bill_item">
            <input type="hidden" name="item_id" id="cancelItemIdBill" value="">
            <input type="hidden" name="bill_id" value="<?= $bill_id ?>">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <div style="display:flex;gap:10px;padding-top:14px;border-top:2px solid var(--page-border);">
                <button type="submit" style="flex:1;background:#D97706;color:white;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-ban"></i> Yes, Cancel Item
                </button>
                <button type="button" onclick="closeModalBill('cancelItemModalBill')" style="background:transparent;color:var(--page-text-secondary);border:2px solid var(--page-border);padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-times"></i> No, Go Back
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- RESTORE ITEM MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-bill" id="restoreItemModalBill">
    <div class="modal-content-bill">
        <div class="modal-header-bill">
            <div class="modal-title-bill" style="color:#059669;">
                <i class="fas fa-undo"></i> Restore Item
            </div>
            <button class="modal-close-bill" onclick="closeModalBill('restoreItemModalBill')">&times;</button>
        </div>
        
        <p style="text-align:center;font-size:3rem;color:#059669;margin-bottom:10px;">
            <i class="fas fa-undo"></i>
        </p>
        
        <p style="text-align:center;color:var(--page-text-primary);font-weight:600;margin-bottom:8px;">
            Restore this cancelled item?
        </p>
        <p style="text-align:center;color:var(--page-text-secondary);font-size:0.85rem;margin-bottom:16px;">
            Item: <strong id="restoreItemNameBill"></strong>
        </p>
        
        <div style="background:#D1FAE5;border:2px solid #059669;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#065F46;">
            <i class="fas fa-info-circle"></i>
            The item will be marked as <strong>pending</strong> and added back to the bill total.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="restore_bill_item">
            <input type="hidden" name="item_id" id="restoreItemIdBill" value="">
            <input type="hidden" name="bill_id" value="<?= $bill_id ?>">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <div style="display:flex;gap:10px;padding-top:14px;border-top:2px solid var(--page-border);">
                <button type="submit" style="flex:1;background:#059669;color:white;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-undo"></i> Yes, Restore
                </button>
                <button type="button" onclick="closeModalBill('restoreItemModalBill')" style="background:transparent;color:var(--page-text-secondary);border:2px solid var(--page-border);padding:10px 24px;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME ONLY
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // MODALS
    // ================================================================
    function openModalBill(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModalBill(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    document.querySelectorAll('.modal-overlay-bill').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay-bill.show').forEach(function(modal) {
                modal.classList.remove('show');
            });
            document.body.style.overflow = '';
        }
    });

    function confirmCancelItemBill(itemId, itemName) {
        document.getElementById('cancelItemIdBill').value = itemId;
        document.getElementById('cancelItemNameBill').textContent = itemName;
        openModalBill('cancelItemModalBill');
    }
    
    function confirmRestoreItemBill(itemId, itemName) {
        document.getElementById('restoreItemIdBill').value = itemId;
        document.getElementById('restoreItemNameBill').textContent = itemName;
        openModalBill('restoreItemModalBill');
    }

    console.log('%c💰 Braick - View Bill', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate header JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Bill Status: <?= strtoupper($bill_status) ?>', 'font-size:13px; font-weight:bold;');
    console.log('%c📦 Items loaded: <?= count($bill_items) ?>', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>