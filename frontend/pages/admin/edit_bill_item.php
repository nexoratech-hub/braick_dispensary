<?php
// ================================================================
// FILE: frontend/pages/admin/edit_bill_item.php
// ADMIN - EDIT BILL ITEM
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Supports Premium
// ✅ Green theme with colored cards
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
// FETCH BILL ITEM
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
// OPTIONS
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
                
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET item_type = ?, item_name = ?, description = ?,
                        quantity = ?, unit_price = ?, total_price = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $item_type, $item_name, $description,
                    $quantity, $unit_price, $total_price,
                    $status, $item_id
                ]);
                
                // Recalculate bill
                $stmt = $db->prepare("
                    SELECT COALESCE(SUM(total_price), 0) as subtotal 
                    FROM bill_items 
                    WHERE bill_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$bill_id]);
                $new_subtotal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['subtotal'];
                
                $discount_amount = (float)($item['bill_discount'] ?? 0);
                $premium_amount = (float)($item['bill_premium'] ?? 0);
                
                $new_total = $new_subtotal - $discount_amount + $premium_amount;
                if ($new_total < 0) $new_total = 0;
                
                $paid_amount = (float)($item['bill_paid'] ?? 0);
                $new_balance = $new_total - $paid_amount;
                if ($new_balance < 0) $new_balance = 0;
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET subtotal = ?, total_amount = ?, balance = ?, updated_at = NOW()
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

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*) -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ✅ PAGE VARIABLES - EXTEND HEADER VARIABLES
       ================================================================ */
    .edit-bill-item-page {
        --green-primary: #059669;
        --green-primary-dark: #047857;
        --green-primary-light: #34D399;
        --green-primary-bg: #D1FAE5;
        --green-gradient: linear-gradient(135deg, #059669, #047857);
        --green-gradient-strong: linear-gradient(135deg, #047857, #065F46);
        
        --edit-success: #059669;
        --edit-danger: #DC2626;
        --edit-warning: #D97706;
        --edit-purple: #7C3AED;
    }

    /* ================================================================
       ✅ PAGE HEADER - GREEN GRADIENT
       ================================================================ */
    .page-header-edit {
        background: linear-gradient(135deg, #047857 0%, #065F46 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-edit::before {
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

    .page-header-edit .page-title {
        color: white;
        font-size: 1.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-edit .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-edit .role-badge-display {
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

    .page-header-edit .header-badge {
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
        transition: all 0.3s ease;
    }

    .page-header-edit .header-badge:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-1px);
    }

    .page-header-edit .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
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

    .page-header-edit .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       ✅ 5 CARDS WITH BACKGROUND COLORS
       ================================================================ */
    .stats-grid-edit {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 24px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .stat-card-edit {
        border-radius: 12px;
        padding: 18px 20px;
        border: 2px solid transparent;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        position: relative;
        overflow: hidden;
        min-height: 100px;
        color: white;
    }

    .stat-card-edit::before {
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

    .stat-card-edit:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    }

    .stat-card-edit.card-total { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card-edit.card-discount { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card-edit.card-premium {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        animation: premiumPulseEdit 2s ease-in-out infinite;
    }
    .stat-card-edit.card-paid { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-edit.card-balance { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .stat-card-edit.card-balance.zero { background: linear-gradient(135deg, #059669, #047857); }

    @keyframes premiumPulseEdit {
        0%, 100% { box-shadow: 0 4px 16px rgba(217, 119, 6, 0.3); }
        50% { box-shadow: 0 4px 24px rgba(217, 119, 6, 0.6); }
    }

    .stat-card-edit .stat-icon-edit {
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

    .stat-card-edit .stat-content-edit { flex: 1; }

    .stat-label-edit {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
        color: rgba(255,255,255,0.9);
    }

    .stat-value-edit {
        font-size: 1.15rem;
        font-weight: 800;
        margin: 3px 0 0 0;
        color: white;
    }

    .stat-sub-edit {
        font-size: 0.55rem;
        margin-top: 3px;
        color: rgba(255,255,255,0.85);
    }

    /* ================================================================
       ✅ FORM CARD - USES PAGE VARIABLES
       ================================================================ */
    .form-card-edit {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 32px 36px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        max-width: 1200px;
        margin: 0 auto 24px;
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .form-card-edit:hover {
        border-color: var(--green-primary, #059669);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

    .form-header-edit {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    .form-header-edit .form-icon-edit {
        width: 52px;
        height: 52px;
        background: var(--green-gradient, linear-gradient(135deg, #059669, #047857));
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        flex-shrink: 0;
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.25);
    }

    .form-header-edit .form-title-edit {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .form-header-edit .form-subtitle-edit {
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
    }

    /* ================================================================
       ✅ FORM CONTROLS
       ================================================================ */
    .form-label-edit {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 5px;
        display: block;
    }

    .form-label-edit .required { color: #DC2626; margin-left: 2px; }
    .form-label-edit .label-icon { margin-right: 4px; color: var(--green-primary, #059669); }
    .form-label-edit .label-badge {
        font-weight: 400;
        font-size: 0.6rem;
        padding: 1px 10px;
        border-radius: 12px;
        background: #F1F5F9;
        color: var(--page-text-secondary, #64748B);
        margin-left: 6px;
    }

    [data-theme="dark"] .form-label-edit .label-badge {
        background: #0F172A;
        color: #94A3B8;
    }

    .form-control-edit {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .form-control-edit:focus {
        border-color: var(--green-primary, #059669);
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
    }

    .form-control-edit::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    .form-control-edit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .form-control-edit:disabled {
        background: #0F172A;
    }

    select.form-control-edit { appearance: auto; cursor: pointer; }
    textarea.form-control-edit { resize: vertical; min-height: 80px; }

    .grid-2-edit {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-row-edit { margin-bottom: 20px; }
    .form-row-edit:last-child { margin-bottom: 0; }

    /* Total Preview */
    .total-preview-edit {
        padding: 10px 16px;
        background: #D1FAE5;
        border-radius: 12px;
        border: 2px solid #34D399;
        font-size: 1.25rem;
        font-weight: 700;
        color: #059669;
    }

    [data-theme="dark"] .total-preview-edit {
        background: #1A3A2A;
        border-color: #059669;
        color: #34D399;
    }

    /* ================================================================
       ✅ BUTTONS
       ================================================================ */
    .btn-edit {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
    }

    .btn-edit:hover { transform: translateY(-2px); }

    .btn-primary-edit {
        background: var(--green-gradient, linear-gradient(135deg, #059669, #047857));
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }

    .btn-primary-edit:hover {
        box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        color: white;
    }

    .btn-outline-edit {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-edit:hover {
        border-color: var(--green-primary, #059669);
        color: var(--green-primary, #059669);
    }

    [data-theme="dark"] .btn-outline-edit {
        color: #94A3B8;
        border-color: #334155;
    }

    [data-theme="dark"] .btn-outline-edit:hover {
        border-color: #34D399;
        color: #34D399;
    }

    .form-actions-edit {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    /* ================================================================
       ✅ ALERTS
       ================================================================ */
    .alert-edit {
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
        animation: slideDownEdit 0.4s ease;
    }

    @keyframes slideDownEdit {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success-edit {
        background: #D1FAE5;
        color: #065F46;
        border-color: #34D399;
    }

    .alert-danger-edit {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #F87171;
    }

    [data-theme="dark"] .alert-success-edit {
        background: #1A3A2A;
        color: #34D399;
        border-color: #059669;
    }

    [data-theme="dark"] .alert-danger-edit {
        background: #3A1A1A;
        color: #F87171;
        border-color: #DC2626;
    }

    /* ================================================================
       ✅ FOOTER
       ================================================================ */
    .footer-edit {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-edit .footer-brand-edit {
        color: var(--green-primary, #059669);
        font-weight: 700;
    }

    /* ================================================================
       ✅ ANIMATIONS
       ================================================================ */
    @keyframes fadeInUpEdit {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-edit {
        animation: fadeInUpEdit 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       ✅ RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid-edit { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 1024px) {
        .grid-2-edit { grid-template-columns: 1fr; }
        .stats-grid-edit { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-edit { padding: 18px 20px; }
        .page-header-edit .page-title { font-size: 1.3rem; }
        .form-card-edit { padding: 18px 20px; }
        .form-actions-edit { flex-direction: column; }
        .form-actions-edit .btn-edit { width: 100%; justify-content: center; }
    }

    @media (max-width: 480px) {
        .stats-grid-edit { grid-template-columns: 1fr; }
        .page-header-edit { flex-direction: column; align-items: flex-start !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content edit-bill-item-page">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-edit">
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
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
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

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="alert-edit alert-<?= $message_type === 'success' ? 'success-edit' : 'danger-edit' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 5 CARDS WITH BACKGROUND COLORS -->
    <!-- ================================================================ -->
    <div class="stats-grid-edit animate-fade-in-up-edit">
        
        <div class="stat-card-edit card-total">
            <div class="stat-icon-edit"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content-edit">
                <p class="stat-label-edit">Bill Total</p>
                <p class="stat-value-edit">TSh <?= number_format($item['bill_total'] ?? 0, 0) ?></p>
                <p class="stat-sub-edit"><i class="fas fa-receipt"></i> Full bill</p>
            </div>
        </div>
        
        <div class="stat-card-edit card-discount">
            <div class="stat-icon-edit"><i class="fas fa-tags"></i></div>
            <div class="stat-content-edit">
                <p class="stat-label-edit">Discount</p>
                <p class="stat-value-edit">TSh <?= number_format($item['bill_discount'] ?? 0, 0) ?></p>
                <p class="stat-sub-edit"><i class="fas fa-percent"></i> Bill discount</p>
            </div>
        </div>
        
        <div class="stat-card-edit card-premium">
            <div class="stat-icon-edit"><i class="fas fa-crown"></i></div>
            <div class="stat-content-edit">
                <p class="stat-label-edit">Premium</p>
                <p class="stat-value-edit">TSh <?= number_format($item['bill_premium'] ?? 0, 0) ?></p>
                <p class="stat-sub-edit">
                    <?php if (!empty($item['bill_premium_note'])): ?>
                        <?= htmlspecialchars($item['bill_premium_note']) ?>
                    <?php else: ?>
                        <i class="fas fa-star"></i> <?= ($item['bill_premium'] ?? 0) > 0 ? 'Charged' : 'No premium' ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="stat-card-edit card-paid">
            <div class="stat-icon-edit"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-edit">
                <p class="stat-label-edit">Paid</p>
                <p class="stat-value-edit">TSh <?= number_format($item['bill_paid'] ?? 0, 0) ?></p>
                <p class="stat-sub-edit"><i class="fas fa-check"></i> Total paid</p>
            </div>
        </div>
        
        <div class="stat-card-edit card-balance <?= ($item['bill_balance'] ?? 0) <= 0 ? 'zero' : '' ?>">
            <div class="stat-icon-edit">
                <i class="fas <?= ($item['bill_balance'] ?? 0) > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
            </div>
            <div class="stat-content-edit">
                <p class="stat-label-edit">Balance</p>
                <p class="stat-value-edit">TSh <?= number_format($item['bill_balance'] ?? 0, 0) ?></p>
                <p class="stat-sub-edit">
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
    <div class="form-card-edit animate-fade-in-up-edit">
        <div class="form-header-edit">
            <div class="form-icon-edit"><i class="fas fa-edit"></i></div>
            <div>
                <h3 class="form-title-edit">Edit Item Details</h3>
                <p class="form-subtitle-edit">Update item information and price</p>
            </div>
        </div>

        <form method="POST" action="" id="itemForm">
            <input type="hidden" name="action" value="update_item">
            
            <!-- Bill & Patient Info (Read-only) -->
            <div class="grid-2-edit">
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-receipt label-icon"></i> Bill Number
                    </label>
                    <input type="text" class="form-control-edit" value="<?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>" disabled>
                </div>
                
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-user label-icon"></i> Patient
                    </label>
                    <input type="text" class="form-control-edit" value="<?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?> (<?= htmlspecialchars($item['patient_code'] ?? 'N/A') ?>)" disabled>
                </div>
                
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-store label-icon"></i> Branch
                    </label>
                    <input type="text" class="form-control-edit" value="<?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>" disabled>
                </div>
                
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-user-plus label-icon"></i> Created By
                    </label>
                    <input type="text" class="form-control-edit" value="<?= htmlspecialchars($item['created_by_name'] ?? 'N/A') ?>" disabled>
                </div>
            </div>
            
            <!-- Item Type & Name -->
            <div class="grid-2-edit">
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-tag label-icon"></i> Item Type <span class="required">*</span>
                    </label>
                    <select name="item_type" class="form-control-edit" required>
                        <?php foreach ($item_types as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($item['item_type'] ?? 'other') === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-cube label-icon"></i> Item Name <span class="required">*</span>
                    </label>
                    <input type="text" name="item_name" class="form-control-edit" 
                           value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" required>
                </div>
            </div>
            
            <!-- Quantity & Unit Price -->
            <div class="grid-2-edit">
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-calculator label-icon"></i> Quantity <span class="required">*</span>
                    </label>
                    <input type="number" name="quantity" class="form-control-edit" 
                           value="<?= (int)($item['quantity'] ?? 1) ?>" min="1" required 
                           oninput="calculateTotal()">
                </div>
                
                <div class="form-row-edit">
                    <label class="form-label-edit">
                        <i class="fas fa-money-bill-wave label-icon"></i> Unit Price <span class="required">*</span>
                        <span class="label-badge">TSh</span>
                    </label>
                    <input type="text" name="unit_price" id="unitPrice" class="form-control-edit" 
                           value="<?= number_format($item['unit_price'] ?? 0, 0) ?>" 
                           oninput="formatAmount(this); calculateTotal()" required>
                </div>
            </div>
            
            <!-- Total Preview -->
            <div class="form-row-edit">
                <label class="form-label-edit">
                    <i class="fas fa-calculator label-icon"></i> Total Price (Auto-calculated)
                </label>
                <div id="totalPreview" class="total-preview-edit">
                    TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                </div>
            </div>
            
            <!-- Description -->
            <div class="form-row-edit">
                <label class="form-label-edit">
                    <i class="fas fa-align-left label-icon"></i> Description
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="description" class="form-control-edit" rows="3" 
                          placeholder="Item description..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
            </div>
            
            <!-- Status -->
            <div class="form-row-edit">
                <label class="form-label-edit">
                    <i class="fas fa-info-circle label-icon"></i> Status <span class="required">*</span>
                </label>
                <select name="status" class="form-control-edit" required>
                    <?php foreach ($status_options as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($item['status'] ?? 'pending') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions-edit">
                <button type="submit" class="btn-edit btn-primary-edit">
                    <i class="fas fa-save"></i> Update Item
                </button>
                <a href="edit_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-edit btn-outline-edit">
                    <i class="fas fa-arrow-left"></i> Back to Bill
                </a>
                <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-edit btn-outline-edit">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-edit">
        <p>
            <span class="footer-brand-edit">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Bill Item
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar toggle) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ✅ FORMAT AMOUNT
    // ================================================================
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

    // ================================================================
    // ✅ CALCULATE TOTAL
    // ================================================================
    function calculateTotal() {
        var quantity = parseInt(document.querySelector('input[name="quantity"]').value) || 0;
        var unitPriceStr = document.getElementById('unitPrice').value.replace(/,/g, '');
        var unitPrice = parseFloat(unitPriceStr) || 0;
        var total = quantity * unitPrice;
        
        document.getElementById('totalPreview').textContent = 'TSh ' + total.toLocaleString();
    }

    // ================================================================
    // ✅ FOOTER TIME ONLY (header ina date/time yake)
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c✏️ Braick Dispensary - Edit Bill Item', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Green theme with 5 colored cards', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>