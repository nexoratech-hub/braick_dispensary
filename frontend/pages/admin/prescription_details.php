<?php
// ================================================================
// FILE: frontend/pages/admin/prescription_details.php
// PRESCRIPTION DETAILS - VIEW ALL PRESCRIPTIONS FOR A PATIENT
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
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

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
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
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($prescription_id <= 0) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $bd = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($bd) $selected_branch_name = $bd['name'];
}

// ================================================================
// GET CURRENT PRESCRIPTION
// ================================================================
$sql = "
    SELECT 
        p.*,
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        pat.address as patient_address,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        b.name as branch_name,
        COALESCE((SELECT SUM(pi.total_price) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0) as prescription_amount,
        COALESCE((SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0) as item_count
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$prescription_id]);
$current_prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_prescription) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id) . '&error=not_found');
    exit;
}

$patient_id = $current_prescription['patient_id'];

// ================================================================
// GET ALL PRESCRIPTIONS FOR THIS PATIENT
// ================================================================
$all_prescriptions_sql = "
    SELECT 
        p.id, p.prescription_number, p.status, p.created_at, p.diagnosis, p.notes,
        u.full_name as doctor_name,
        COALESCE((SELECT SUM(pi.total_price) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0) as total_amount,
        COALESCE((SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0) as item_count
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($all_prescriptions_sql);
$stmt->execute([$patient_id]);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ITEMS FOR EACH PRESCRIPTION
// ================================================================
foreach ($all_prescriptions as &$prescription) {
    $items_sql = "
        SELECT 
            pi.*,
            mi.medication_name as inventory_medication_name,
            mi.category as inventory_category,
            mi.unit as inventory_unit,
            mi.batch_number as inventory_batch_number,
            mi.expiry_date as inventory_expiry_date,
            mi.quantity as inventory_stock
        FROM prescription_items pi
        LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
        WHERE pi.prescription_id = ?
        ORDER BY pi.created_at DESC
    ";
    $stmt = $db->prepare($items_sql);
    $stmt->execute([$prescription['id']]);
    $prescription['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($prescription);

// ================================================================
// HANDLE DELETE ITEM
// ================================================================
if (isset($_GET['delete_item']) && is_numeric($_GET['delete_item'])) {
    $delete_item_id = (int)$_GET['delete_item'];
    $delete_prescription_id = isset($_GET['pid']) ? (int)$_GET['pid'] : $prescription_id;
    $delete_branch = isset($_GET['branch']) ? $_GET['branch'] : $selected_branch_id;
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT inventory_id, quantity FROM prescription_items WHERE id = ?");
        $stmt->execute([$delete_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            if ($item['inventory_id']) {
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['inventory_id']]);
            }
            
            $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
            $stmt->execute([$delete_item_id]);
        }
        
        $db->commit();
        header('Location: prescription_details.php?id=' . $delete_prescription_id . '&branch=' . urlencode($delete_branch) . '&item_deleted=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=delete_failed');
        exit;
    }
}

// ================================================================
// HANDLE CANCEL ITEM
// ================================================================
if (isset($_GET['cancel_item']) && is_numeric($_GET['cancel_item'])) {
    $cancel_item_id = (int)$_GET['cancel_item'];
    $cancel_prescription_id = isset($_GET['pid']) ? (int)$_GET['pid'] : $prescription_id;
    $cancel_branch = isset($_GET['branch']) ? $_GET['branch'] : $selected_branch_id;
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT inventory_id, quantity FROM prescription_items WHERE id = ?");
        $stmt->execute([$cancel_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            if ($item['inventory_id']) {
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['inventory_id']]);
            }
            
            $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
            $stmt->execute([$cancel_item_id]);
        }
        
        $db->commit();
        header('Location: prescription_details.php?id=' . $cancel_prescription_id . '&branch=' . urlencode($cancel_branch) . '&item_cancelled=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=cancel_failed');
        exit;
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

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* PAGE HEADER */
    .page-header-rx-det {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 20px 28px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 24px rgba(11, 94, 215, 0.3);
    }

    .page-header-rx-det .page-title-rx-det {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin: 0;
    }

    .page-header-rx-det .page-subtitle-rx-det {
        color: rgba(255,255,255,0.9);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .page-header-rx-det .branch-tag-rx-det {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .page-header-rx-det .btn-outline-light-rx-det {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 6px 16px;
        border-radius: 10px;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
    }

    .page-header-rx-det .btn-outline-light-rx-det:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* DETAIL CARD */
    .detail-card-rx-det {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    .detail-card-rx-det:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }

    .detail-card-rx-det .card-title-rx-det {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    .detail-card-rx-det .card-title-rx-det i {
        color: var(--page-primary, #0B5ED7);
    }

    /* SCROLL CONTROLS */
    .scroll-controls-rx-det {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-shrink: 0;
        margin-left: auto;
    }

    .scroll-btn-rx-det {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .scroll-btn-rx-det:hover {
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
        background: #E8F0FE;
        transform: scale(1.08);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
    }

    [data-theme="dark"] .scroll-btn-rx-det {
        background: #1E293B;
        border-color: #334155;
        color: #94A3B8;
    }

    [data-theme="dark"] .scroll-btn-rx-det:hover {
        border-color: #3B82F6;
        color: #3B82F6;
        background: #1A2A4A;
    }

    /* INFO GRID */
    .info-grid-rx-det {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
    }

    .info-grid-rx-det .info-item-rx-det {
        display: flex;
        flex-direction: column;
    }

    .info-grid-rx-det .info-item-rx-det .label-rx-det {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .info-grid-rx-det .info-item-rx-det .value-rx-det {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--page-text-primary, #1E293B);
        margin-top: 2px;
    }

    .info-grid-rx-det .info-item-rx-det .value-rx-det.mono {
        font-family: monospace;
    }

    /* STATUS BADGE */
    .status-badge-rx-det {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge-rx-det.warning { background: #FEF3C7; color: #D97706; }
    .status-badge-rx-det.success { background: #D1FAE5; color: #059669; }
    .status-badge-rx-det.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge-rx-det.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-rx-det.secondary { background: #E2E8F0; color: #64748B; }

    [data-theme="dark"] .status-badge-rx-det.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-rx-det.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-rx-det.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge-rx-det.info { background: #1E3A5F; color: #6EA8FE; }

    /* PRESCRIPTION GROUP */
    .prescription-group-rx-det {
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        margin-bottom: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .prescription-group-rx-det:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }

    .prescription-group-rx-det .pg-header-rx-det {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        padding: 10px 16px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .prescription-group-rx-det .pg-header-rx-det .pg-number-rx-det {
        font-family: monospace;
        font-weight: 700;
        color: #ffffff;
        font-size: 0.85rem;
    }

    .prescription-group-rx-det .pg-header-rx-det .pg-info-rx-det {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        font-size: 0.75rem;
        color: rgba(255,255,255,0.9);
    }

    .prescription-group-rx-det .pg-header-rx-det .pg-info-rx-det .amount-rx-det {
        font-weight: 700;
        color: #ffffff;
        background: rgba(255,255,255,0.15);
        padding: 2px 12px;
        border-radius: 12px;
    }

    .prescription-group-rx-det .pg-body-rx-det {
        padding: 0;
        overflow-x: auto;
        scroll-behavior: smooth;
    }

    .prescription-group-rx-det .pg-body-rx-det::-webkit-scrollbar { height: 6px; }
    .prescription-group-rx-det .pg-body-rx-det::-webkit-scrollbar-track { background: var(--page-hover); border-radius: 10px; }
    .prescription-group-rx-det .pg-body-rx-det::-webkit-scrollbar-thumb { background: var(--page-primary); border-radius: 10px; }

    .prescription-group-rx-det .pg-body-rx-det table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
        min-width: 1000px;
    }

    .prescription-group-rx-det .pg-body-rx-det table th {
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        font-size: 0.6rem;
        text-transform: uppercase;
        padding: 6px 10px;
        text-align: left;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .prescription-group-rx-det .pg-body-rx-det table th {
        background: #0F172A;
    }

    .prescription-group-rx-det .pg-body-rx-det table td {
        padding: 6px 10px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .prescription-group-rx-det .pg-body-rx-det table tr:last-child td {
        border-bottom: none;
    }

    .prescription-group-rx-det .pg-body-rx-det table tr:hover td {
        background: #E8F0FE;
    }

    [data-theme="dark"] .prescription-group-rx-det .pg-body-rx-det table tr:hover td {
        background: #1E3A5F;
    }

    /* ACTION BUTTONS */
    .action-buttons-group-rx-det {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        justify-content: center;
    }

    .btn-action-rx-det {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        cursor: pointer;
        min-width: 60px;
    }

    .btn-action-rx-det i { font-size: 0.65rem; }
    .btn-action-rx-det:hover { transform: translateY(-2px) scale(1.03); }
    .btn-action-rx-det:active { transform: scale(0.95); }

    .btn-edit-rx-det {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
    }
    .btn-edit-rx-det:hover { color: white; box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4); }

    .btn-delete-rx-det {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
    }
    .btn-delete-rx-det:hover { color: white; box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4); }

    .btn-cancel-rx-det {
        background: linear-gradient(135deg, #6B7280, #4B5563);
        color: white;
        box-shadow: 0 2px 8px rgba(107, 114, 128, 0.25);
    }
    .btn-cancel-rx-det:hover { color: white; box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4); }

    /* NO ITEMS */
    .no-items-rx-det {
        padding: 12px 16px;
        text-align: center;
        color: var(--page-text-secondary, #64748B);
        font-size: 0.8rem;
    }

    /* OVERALL TOTAL */
    .overall-total-rx-det {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 12px;
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
        color: white;
    }

    /* FOOTER */
    .footer-rx-det {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-rx-det .footer-brand-rx-det {
        color: var(--page-primary, #0B5ED7);
        font-weight: 600;
    }

    /* FONT HELPERS */
    .font-bold-rx-det { font-weight: 700; }
    .amount-cell-rx-det {
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
    }
    .text-right-rx-det { text-align: right; }
    .text-center-rx-det { text-align: center; }
    .text-gray-400-rx-det { color: var(--page-text-muted, #94A3B8); }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .page-header-rx-det { padding: 14px 18px; }
        .page-header-rx-det .page-title-rx-det { font-size: 1.1rem; }
        .info-grid-rx-det { grid-template-columns: 1fr 1fr; }
        .prescription-group-rx-det .pg-header-rx-det { flex-direction: column; align-items: stretch; }
        .btn-action-rx-det { font-size: 0.55rem; padding: 4px 8px; min-width: 45px; }
        .scroll-btn-rx-det { width: 28px; height: 28px; font-size: 0.65rem; }
    }

    @media (max-width: 480px) {
        .info-grid-rx-det { grid-template-columns: 1fr; }
        .detail-card-rx-det { padding: 12px 14px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-rx-det">
        <div>
            <h1 class="page-title-rx-det">
                <i class="fas fa-user-md"></i>
                Patient Prescriptions
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag-rx-det">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle-rx-det">
                <i class="fas fa-user"></i> Patient: <strong><?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?></strong>
                <span class="branch-tag-rx-det">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($current_prescription['patient_number'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag-rx-det">
                    <i class="fas fa-prescription"></i> <?= count($all_prescriptions) ?> prescriptions
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="prescriptions.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light-rx-det">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="../patients/view_patient.php?id=<?= $patient_id ?>" class="btn-outline-light-rx-det">
                <i class="fas fa-external-link-alt"></i> View Patient
            </a>
        </div>
    </div>

    <!-- PATIENT INFO -->
    <div class="detail-card-rx-det">
        <div class="card-title-rx-det">
            <i class="fas fa-user"></i> Patient Information
        </div>
        
        <div class="info-grid-rx-det">
            <div class="info-item-rx-det">
                <span class="label-rx-det">Patient Name</span>
                <span class="value-rx-det"><?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="info-item-rx-det">
                <span class="label-rx-det">Patient ID</span>
                <span class="value-rx-det mono"><?= htmlspecialchars($current_prescription['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item-rx-det">
                <span class="label-rx-det">Gender</span>
                <span class="value-rx-det"><?= htmlspecialchars($current_prescription['patient_gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item-rx-det">
                <span class="label-rx-det">Phone</span>
                <span class="value-rx-det"><?= htmlspecialchars($current_prescription['patient_phone'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($current_prescription['date_of_birth'])): ?>
            <div class="info-item-rx-det">
                <span class="label-rx-det">Date of Birth</span>
                <span class="value-rx-det"><?= date('M d, Y', strtotime($current_prescription['date_of_birth'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($current_prescription['patient_address'])): ?>
            <div class="info-item-rx-det" style="grid-column: 1 / -1;">
                <span class="label-rx-det">Address</span>
                <span class="value-rx-det"><?= htmlspecialchars($current_prescription['patient_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALL PRESCRIPTIONS -->
    <div class="detail-card-rx-det">
        <div class="card-title-rx-det">
            <i class="fas fa-prescription"></i> All Prescriptions
            <span style="font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);margin-left:8px;">
                (<?= count($all_prescriptions) ?> prescriptions)
            </span>
            <div class="scroll-controls-rx-det">
                <button class="scroll-btn-rx-det" onclick="scrollTableRxDet('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-btn-rx-det" onclick="scrollTableRxDet('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <?php if (count($all_prescriptions) > 0): ?>
            <?php foreach ($all_prescriptions as $prescription): 
                $grand_total = 0;
                foreach ($prescription['items'] as $item) {
                    $grand_total += $item['total_price'];
                }
            ?>
            <div class="prescription-group-rx-det" id="pg-<?= $prescription['id'] ?>">
                <div class="pg-header-rx-det">
                    <div>
                        <span class="pg-number-rx-det"><?= htmlspecialchars($prescription['prescription_number']) ?></span>
                        <span class="status-badge-rx-det" style="background:rgba(255,255,255,0.2) !important; color:#ffffff !important; border:1px solid rgba(255,255,255,0.2); font-size:0.55rem; padding:1px 10px; margin-left:8px;">
                            <?php if ($prescription['status'] === 'pending'): ?>⏳<?php elseif ($prescription['status'] === 'confirmed'): ?>✅<?php elseif ($prescription['status'] === 'dispensed'): ?>💊<?php else: ?>❌<?php endif; ?>
                            <?= ucfirst($prescription['status'] ?? 'Unknown') ?>
                        </span>
                    </div>
                    <div class="pg-info-rx-det">
                        <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($prescription['created_at'])) ?></span>
                        <span><i class="fas fa-pills"></i> <?= $prescription['item_count'] ?? 0 ?> items</span>
                        <span class="amount-rx-det">TSh <?= number_format($prescription['total_amount'] ?? 0, 0) ?></span>
                    </div>
                </div>
                
                <div class="pg-body-rx-det">
                    <?php if (count($prescription['items']) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th style="min-width:130px;">Medication</th>
                                    <th style="min-width:70px;">Dosage</th>
                                    <th style="min-width:80px;">Frequency</th>
                                    <th style="min-width:50px;text-align:center;">Qty</th>
                                    <th style="min-width:60px;">Duration</th>
                                    <th style="min-width:70px;">Route</th>
                                    <th style="min-width:120px;">Instructions</th>
                                    <th style="min-width:90px;text-align:right;">Unit Price</th>
                                    <th style="min-width:90px;text-align:right;">Total</th>
                                    <th style="min-width:200px;text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($prescription['items'] as $item): ?>
                                    <tr>
                                        <td class="font-bold-rx-det" style="color:var(--page-primary);"><?= $i++ ?></td>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?></div>
                                            <?php if (!empty($item['inventory_batch_number'])): ?>
                                                <div style="font-size:0.65rem;color:var(--page-text-secondary);">Batch: <?= htmlspecialchars($item['inventory_batch_number']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                        <td class="text-center-rx-det font-bold-rx-det"><?= $item['quantity'] ?? 0 ?></td>
                                        <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?> days</td>
                                        <td><?= htmlspecialchars($item['route'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if (!empty($item['instructions'])): ?>
                                                <span style="font-size:0.65rem;background:#E8F0FE;color:#0B5ED7;padding:1px 6px;border-radius:4px;">
                                                    <?= htmlspecialchars(substr($item['instructions'], 0, 30)) . (strlen($item['instructions']) > 30 ? '...' : '') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400-rx-det" style="font-size:0.65rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right-rx-det amount-cell-rx-det">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                        <td class="text-right-rx-det amount-cell-rx-det">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                        <td>
                                            <div class="action-buttons-group-rx-det">
                                                <a href="edit_prescription_item.php?id=<?= $item['id'] ?>&prescription_id=<?= $prescription['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                                   class="btn-action-rx-det btn-edit-rx-det" title="Edit Item">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?cancel_item=<?= $item['id'] ?>&id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>&pid=<?= $prescription['id'] ?>" 
                                                   class="btn-action-rx-det btn-cancel-rx-det" title="Cancel Item"
                                                   onclick="return confirm('⚠️ Cancel this item?\n\nMedication: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>\nQuantity: <?= $item['quantity'] ?? 0 ?>\nAmount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <a href="?delete_item=<?= $item['id'] ?>&id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>&pid=<?= $prescription['id'] ?>" 
                                                   class="btn-action-rx-det btn-delete-rx-det" title="Delete Item"
                                                   onclick="return confirm('⚠️ Delete this item permanently?\n\nMedication: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>\nQuantity: <?= $item['quantity'] ?? 0 ?>\nAmount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="9" style="text-align:right;font-weight:600;padding:6px 10px;">
                                        <span style="font-size:0.7rem;color:var(--page-text-secondary);">GRAND TOTAL:</span>
                                    </td>
                                    <td style="font-weight:700;color:var(--page-primary);padding:6px 10px;text-align:right;font-size:0.9rem;">
                                        TSh <?= number_format($grand_total, 0) ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <div class="no-items-rx-det">
                            <i class="fas fa-prescription" style="color:var(--page-primary);margin-right:6px;"></i>
                            No items in this prescription
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- OVERALL TOTAL -->
            <div class="overall-total-rx-det">
                <span style="font-weight:600;font-size:0.85rem;opacity:0.9;">
                    <i class="fas fa-calculator"></i> OVERALL TOTAL (All Prescriptions)
                </span>
                <span style="font-size:1.1rem;font-weight:700;">
                    <?php 
                        $overall_total = 0;
                        foreach ($all_prescriptions as $p) {
                            $overall_total += $p['total_amount'];
                        }
                    ?>
                    TSh <?= number_format($overall_total, 0) ?>
                </span>
            </div>
            
        <?php else: ?>
            <div style="text-align:center;padding:32px 20px;">
                <i class="fas fa-prescription" style="font-size:3rem;color:var(--page-primary);display:block;margin-bottom:12px;"></i>
                <p style="color:var(--page-text-primary);font-weight:500;">No prescriptions found for this patient</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer-rx-det">
        <p>
            <span class="footer-brand-rx-det">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Patient Prescriptions
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
    // TABLE SCROLL FUNCTION
    // ================================================================
    function scrollTableRxDet(direction) {
        var wrappers = document.querySelectorAll('.prescription-group-rx-det .pg-body-rx-det');
        if (!wrappers || wrappers.length === 0) return;
        
        var scrollAmount = 250;
        wrappers.forEach(function(wrapper) {
            if (direction === 'left') {
                wrapper.scrollLeft -= scrollAmount;
            } else {
                wrapper.scrollLeft += scrollAmount;
            }
        });
    }

    // ================================================================
    // TOAST NOTIFICATION (PAGE-SPECIFIC)
    // ================================================================
    function showToastRxDet(title, message, type) {
        var existing = document.getElementById('pageToastRxDet');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.id = 'pageToastRxDet';
        var bgColor = type === 'success' ? '#059669' : 
                      type === 'error' ? '#DC2626' : 
                      type === 'warning' ? '#D97706' : '#0B5ED7';
        var icon = type === 'success' ? 'fa-check-circle' : 
                   type === 'error' ? 'fa-exclamation-circle' : 
                   type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        toast.style.cssText = `
            position: fixed; bottom: 24px; right: 24px; padding: 14px 20px;
            border-radius: 12px; z-index: 99999; max-width: 400px;
            display: flex; align-items: center; gap: 12px; color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            animation: slideInRxDet 0.4s ease; font-size: 0.85rem;
            font-weight: 500; background: ${bgColor};
        `;
        toast.innerHTML = `
            <i class="fas ${icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:2px 0 0 0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.style.animation = 'slideOutRxDet 0.4s ease';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3500);
    }

    if (!document.getElementById('toastAnimationsRxDet')) {
        var style = document.createElement('style');
        style.id = 'toastAnimationsRxDet';
        style.textContent = `
            @keyframes slideInRxDet { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutRxDet { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
        `;
        document.head.appendChild(style);
    }

    // ================================================================
    // SHOW TOAST MESSAGES FROM PHP
    // ================================================================
    <?php if (isset($_GET['item_deleted']) && $_GET['item_deleted'] == 1): ?>
        showToastRxDet('✅ Success', 'Item deleted successfully!', 'success');
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0] + '?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>
    
    <?php if (isset($_GET['item_cancelled']) && $_GET['item_cancelled'] == 1): ?>
        showToastRxDet('✅ Success', 'Item cancelled successfully!', 'success');
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0] + '?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
        showToastRxDet('⚠️ Error', 'Failed to delete item. Please try again.', 'error');
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'cancel_failed'): ?>
        showToastRxDet('⚠️ Error', 'Failed to cancel item. Please try again.', 'error');
    <?php endif; ?>

    console.log('%c🏥 Braick - Patient Prescriptions', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patient: <?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Total Prescriptions: <?= count($all_prescriptions) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>