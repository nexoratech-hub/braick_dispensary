<?php
// ================================================================
// FILE: frontend/pages/admin/edit_prescription_item.php
// EDIT PRESCRIPTION ITEM - Edit medication item details
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only
// ✅ Dark mode inatumia header toggle
// ================================================================

// ================================================================
// START SESSION
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

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
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
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$prescription_id = isset($_GET['prescription_id']) ? (int)$_GET['prescription_id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($item_id <= 0 || $prescription_id <= 0) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) {
            $selected_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// HANDLE DELETE ITEM FIRST (BEFORE ANY OTHER PROCESSING)
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
        
        if ($item && $item['inventory_id']) {
            $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['inventory_id']]);
        }
        
        $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
        $stmt->execute([$delete_item_id]);
        
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
        
        if ($item && $item['inventory_id']) {
            $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['inventory_id']]);
        }
        
        $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
        $stmt->execute([$cancel_item_id]);
        
        $db->commit();
        header('Location: prescription_details.php?id=' . $cancel_prescription_id . '&branch=' . urlencode($cancel_branch) . '&item_cancelled=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=cancel_failed');
        exit;
    }
}

// ================================================================
// GET ITEM DETAILS
// ================================================================
$sql = "
    SELECT 
        pi.*,
        p.prescription_number,
        p.patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        mi.medication_name as inventory_medication_name,
        mi.category as inventory_category,
        mi.unit as inventory_unit,
        mi.batch_number as inventory_batch_number,
        mi.quantity as inventory_stock,
        mi.expiry_date as inventory_expiry_date,
        mi.selling_price as inventory_selling_price
    FROM prescription_items pi
    LEFT JOIN prescriptions p ON pi.prescription_id = p.id
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
    WHERE pi.id = ? AND pi.prescription_id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$item_id, $prescription_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=item_not_found');
    exit;
}

// ================================================================
// GET ALL MEDICATIONS FROM INVENTORY FOR DROPDOWN
// ================================================================
$medications_sql = "
    SELECT 
        id,
        medication_name,
        category,
        unit,
        selling_price,
        quantity as stock,
        batch_number,
        expiry_date
    FROM medications_inventory 
    WHERE status = 'active' 
    AND branch_id = ?
    AND quantity > 0
    AND (expiry_date IS NULL OR expiry_date > CURDATE())
    ORDER BY medication_name ASC
";
$stmt = $db->prepare($medications_sql);
$stmt->execute([$user_branch_id]);
$medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medication_name = trim($_POST['medication_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $duration = trim($_POST['duration'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $inventory_id = isset($_POST['inventory_id']) && $_POST['inventory_id'] > 0 ? (int)$_POST['inventory_id'] : null;
    
    $errors = [];
    if (empty($medication_name)) $errors[] = "Medication name is required";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if ($unit_price < 0) $errors[] = "Unit price cannot be negative";
    if (empty($frequency)) $errors[] = "Frequency is required";
    if (empty($route)) $errors[] = "Route is required";
    if (empty($duration)) $errors[] = "Duration is required";
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $old_inventory_id = $item['inventory_id'];
            $old_quantity = $item['quantity'];
            
            if ($old_inventory_id != $inventory_id) {
                if ($old_inventory_id) {
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                    $stmt->execute([$old_quantity, $old_inventory_id]);
                }
                
                if ($inventory_id) {
                    $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                    $stmt->execute([$inventory_id]);
                    $new_stock = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($new_stock && $new_stock['quantity'] < $quantity) {
                        throw new Exception("Not enough stock in selected medication. Available: " . $new_stock['quantity']);
                    }
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity, $inventory_id]);
                }
            } else if ($old_inventory_id && $old_quantity != $quantity) {
                $diff = $quantity - $old_quantity;
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$diff, $old_inventory_id]);
                
                $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                $stmt->execute([$old_inventory_id]);
                $new_stock = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($new_stock && $new_stock['quantity'] < 0) {
                    throw new Exception("Not enough stock. Available: " . ($new_stock['quantity'] + $diff));
                }
            }
            
            $total_price = $unit_price * $quantity;
            $update_sql = "
                UPDATE prescription_items 
                SET 
                    medication_name = ?,
                    dosage = ?,
                    frequency = ?,
                    quantity = ?,
                    duration = ?,
                    route = ?,
                    instructions = ?,
                    unit_price = ?,
                    total_price = ?,
                    inventory_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND prescription_id = ?
            ";
            $stmt = $db->prepare($update_sql);
            $stmt->execute([
                $medication_name,
                $dosage,
                $frequency,
                $quantity,
                $duration,
                $route,
                $instructions,
                $unit_price,
                $total_price,
                $inventory_id,
                $item_id,
                $prescription_id
            ]);
            
            $db->commit();
            header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&item_updated=1');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --pi-primary: #0B5ED7;
        --pi-primary-dark: #0A4CA8;
        --pi-primary-light: #6EA8FE;
        --pi-primary-bg: #E8F0FE;
        --pi-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --pi-success: #059669;
        --pi-success-bg: #D1FAE5;
        --pi-danger: #DC2626;
        --pi-danger-bg: #FEE2E2;
        --pi-warning: #D97706;
        --pi-warning-bg: #FEF3C7;
        --pi-bg-body: #F0F4F8;
        --pi-bg-card: #FFFFFF;
        --pi-text-primary: #1E293B;
        --pi-text-secondary: #64748B;
        --pi-text-muted: #94A3B8;
        --pi-border-color: #E2E8F0;
        --pi-gray-100: #F1F5F9;
        --pi-radius: 10px;
        --pi-radius-lg: 16px;
        --pi-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --pi-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --pi-bg-body: #0F172A;
        --pi-bg-card: #1E293B;
        --pi-text-primary: #F1F5F9;
        --pi-text-secondary: #94A3B8;
        --pi-text-muted: #64748B;
        --pi-border-color: #334155;
        --pi-primary-bg: #1E3A5F;
        --pi-gray-100: #1E293B;
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-pi {
        background: var(--pi-primary-gradient);
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

    .page-header-pi .page-title {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin: 0;
    }

    .page-header-pi .page-title i { color: white; }

    .page-header-pi .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .page-header-pi .page-subtitle strong {
        color: white;
        font-weight: 700;
    }

    .page-header-pi .branch-tag {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-outline-light-pi {
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
        font-weight: 500;
    }

    .btn-outline-light-pi:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* ================================================================
       DETAIL CARD
       ================================================================ */
    .detail-card-pi {
        background: var(--pi-bg-card);
        border-radius: var(--pi-radius-lg);
        padding: 24px 28px;
        border: 1px solid var(--pi-border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .detail-card-pi:hover {
        border-color: var(--pi-primary);
        box-shadow: var(--pi-shadow-md);
    }

    .detail-card-pi .card-title-pi {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--pi-text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--pi-border-color);
        flex-wrap: wrap;
    }

    .detail-card-pi .card-title-pi i { color: var(--pi-primary); }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-group-pi {
        margin-bottom: 16px;
    }

    .form-group-pi:last-child {
        margin-bottom: 0;
    }

    .form-label-pi {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--pi-text-secondary);
        margin-bottom: 4px;
        letter-spacing: 0.02em;
    }

    .form-label-pi .required {
        color: #EF4444;
        margin-left: 2px;
    }

    .form-label-pi .optional {
        font-weight: 400;
        font-size: 0.7rem;
        color: var(--pi-text-muted);
    }

    .form-control-pi {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--pi-border-color);
        border-radius: var(--pi-radius);
        font-size: 0.85rem;
        background: var(--pi-bg-card);
        color: var(--pi-text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-control-pi:focus {
        border-color: var(--pi-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    .form-control-pi:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--pi-gray-100);
    }

    .form-control-pi::placeholder {
        color: var(--pi-text-muted);
        opacity: 0.6;
    }

    [data-theme="dark"] .form-control-pi option {
        background: #1E293B;
        color: #F1F5F9;
    }

    select.form-control-pi {
        appearance: auto;
        cursor: pointer;
    }

    .form-row-pi {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    /* ================================================================
       INFO BOX
       ================================================================ */
    .info-box-pi {
        background: var(--pi-primary-bg);
        border-radius: var(--pi-radius);
        padding: 12px 16px;
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 16px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }

    .info-box-pi .info-item-pi {
        display: flex;
        flex-direction: column;
    }

    .info-box-pi .info-item-pi .label {
        font-size: 0.55rem;
        text-transform: uppercase;
        color: var(--pi-text-secondary);
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .info-box-pi .info-item-pi .value {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--pi-text-primary);
    }

    .info-box-pi .info-item-pi .value.mono { font-family: monospace; }
    .info-box-pi .info-item-pi .value.amount { color: var(--pi-primary); font-weight: 700; }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .status-badge-pi {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge-pi.warning { background: #FEF3C7; color: #D97706; }
    .status-badge-pi.success { background: #D1FAE5; color: #059669; }
    .status-badge-pi.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge-pi.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-pi.secondary { background: #E2E8F0; color: #64748B; }

    [data-theme="dark"] .status-badge-pi.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-pi.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-pi.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge-pi.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge-pi.secondary { background: #2D3748; color: #94A3B8; }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-pi {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: var(--pi-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        font-family: inherit;
    }

    .btn-pi i { font-size: 0.9rem; }
    .btn-pi:hover { transform: translateY(-2px); }
    .btn-pi:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

    .btn-primary-pi {
        background: var(--pi-primary-gradient);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    .btn-primary-pi:hover {
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        color: white;
    }

    .btn-secondary-pi {
        background: var(--pi-bg-body);
        color: var(--pi-text-secondary);
        border: 2px solid var(--pi-border-color);
    }
    .btn-secondary-pi:hover {
        border-color: var(--pi-primary);
        color: var(--pi-primary);
        background: var(--pi-primary-bg);
    }

    .btn-danger-pi {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
    }
    .btn-danger-pi:hover {
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        color: white;
    }

    .form-actions-pi {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 16px;
        border-top: 2px solid var(--pi-border-color);
        margin-top: 16px;
    }

    /* ================================================================
       QUICK INSTRUCTION BUTTONS
       ================================================================ */
    .quick-instruction-btn {
        padding: 4px 12px;
        font-size: 0.65rem;
        background: var(--pi-bg-body);
        border: 1px solid var(--pi-border-color);
        border-radius: 6px;
        color: var(--pi-text-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .quick-instruction-btn:hover {
        background: var(--pi-primary-bg);
        border-color: var(--pi-primary);
        color: var(--pi-primary);
    }

    /* ================================================================
       TOTAL PREVIEW
       ================================================================ */
    .total-preview-pi {
        background: var(--pi-primary-bg);
        border-radius: var(--pi-radius);
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }

    .total-preview-pi .total-label {
        font-weight: 600;
        color: var(--pi-text-secondary);
        font-size: 0.85rem;
    }

    .total-preview-pi .total-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--pi-primary);
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert-pi {
        padding: 12px 16px;
        border-radius: var(--pi-radius);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-pi.alert-error {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #DC2626;
    }

    .alert-pi.alert-success {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #059669;
    }

    .alert-pi.alert-info {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #0B5ED7;
    }

    [data-theme="dark"] .alert-pi.alert-error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #DC2626;
    }

    [data-theme="dark"] .alert-pi.alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #059669;
    }

    [data-theme="dark"] .alert-pi.alert-info {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #0B5ED7;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-pi {
        padding: 14px 0;
        border-top: 2px solid var(--pi-border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--pi-text-secondary);
    }

    .footer-pi .footer-brand { color: var(--pi-primary); font-weight: 600; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-pi { padding: 14px 18px !important; }
        .page-header-pi .page-title { font-size: 1.1rem !important; }
        .form-row-pi { grid-template-columns: 1fr; }
        .info-box-pi { flex-direction: column; gap: 8px; }
        .form-actions-pi { flex-direction: column; }
        .form-actions-pi .btn-pi { width: 100%; }
        .detail-card-pi { padding: 16px 18px; }
    }

    @media (max-width: 480px) {
        .page-header-pi .page-title { font-size: 0.95rem !important; }
        .detail-card-pi { padding: 14px 16px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header - Blue Background -->
    <div class="page-header-pi">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Prescription Item
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-prescription"></i> Prescription: <strong><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($item['patient_name'] ?? 'Unknown') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="prescription_details.php?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light-pi">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ITEM INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card-pi animate-fade-in-up">
        <div class="card-title-pi">
            <i class="fas fa-pills"></i> Edit Medication Item
            <span class="status-badge-pi info" style="font-size:0.6rem;padding:2px 12px;">
                <i class="fas fa-edit"></i> Edit Mode
            </span>
        </div>
        
        <!-- Info Box -->
        <div class="info-box-pi">
            <div class="info-item-pi">
                <span class="label">Prescription</span>
                <span class="value mono"><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item-pi">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($item['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="info-item-pi">
                <span class="label">Patient ID</span>
                <span class="value mono"><?= htmlspecialchars($item['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item-pi">
                <span class="label">Current Medication</span>
                <span class="value" style="color:var(--pi-primary);font-weight:600;"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($item['inventory_batch_number'])): ?>
            <div class="info-item-pi">
                <span class="label">Batch Number</span>
                <span class="value mono"><?= htmlspecialchars($item['inventory_batch_number']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item-pi">
                <span class="label">Current Total</span>
                <span class="value amount">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert-pi alert-<?= $message_type ?>">
                <i class="fas <?= $message_type === 'error' ? 'fa-exclamation-circle' : ($message_type === 'success' ? 'fa-check-circle' : 'fa-info-circle') ?>"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Form -->
        <form method="POST" action="">
            <div class="form-row-pi">
                <div class="form-group-pi">
                    <label class="form-label-pi">Medication Name <span class="required">*</span></label>
                    <input type="text" name="medication_name" class="form-control-pi" 
                           value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" 
                           placeholder="Enter medication name..." required>
                </div>
                <div class="form-group-pi">
                    <label class="form-label-pi">Inventory Medication <span class="optional">(Optional - select to link to inventory)</span></label>
                    <select name="inventory_id" class="form-control-pi" id="inventorySelect" onchange="updateMedicationDetails(this)">
                        <option value="">-- Select from inventory --</option>
                        <?php foreach ($medications_list as $med): ?>
                            <option value="<?= $med['id'] ?>" 
                                    data-name="<?= htmlspecialchars($med['medication_name']) ?>"
                                    data-price="<?= $med['selling_price'] ?>"
                                    data-stock="<?= $med['stock'] ?>"
                                    data-unit="<?= htmlspecialchars($med['unit']) ?>"
                                    data-batch="<?= htmlspecialchars($med['batch_number']) ?>"
                                    <?= ($med['id'] == $item['inventory_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($med['medication_name']) ?> 
                                (Stock: <?= $med['stock'] ?>, TSh <?= number_format($med['selling_price'] ?? 0, 0) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($item['inventory_id']): ?>
                        <div style="font-size:0.6rem;color:var(--pi-text-secondary);margin-top:2px;">
                            <i class="fas fa-info-circle"></i> Currently linked to inventory. Changing will update stock accordingly.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row-pi">
                <div class="form-group-pi">
                    <label class="form-label-pi">Dosage <span class="optional">(e.g. 500mg)</span></label>
                    <input type="text" name="dosage" class="form-control-pi" 
                           value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" 
                           placeholder="e.g. 500mg">
                </div>
                <div class="form-group-pi">
                    <label class="form-label-pi">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control-pi" 
                           value="<?= $item['quantity'] ?? 1 ?>" min="1" max="999" required>
                </div>
            </div>
            
            <div class="form-row-pi">
                <div class="form-group-pi">
                    <label class="form-label-pi">Frequency <span class="required">*</span></label>
                    <select name="frequency" class="form-control-pi" required>
                        <option value="">Select Frequency</option>
                        <option value="Once Daily" <?= ($item['frequency'] === 'Once Daily') ? 'selected' : '' ?>>Once Daily</option>
                        <option value="Twice Daily" <?= ($item['frequency'] === 'Twice Daily') ? 'selected' : '' ?>>Twice Daily</option>
                        <option value="Three Times Daily" <?= ($item['frequency'] === 'Three Times Daily') ? 'selected' : '' ?>>Three Times Daily</option>
                        <option value="Four Times Daily" <?= ($item['frequency'] === 'Four Times Daily') ? 'selected' : '' ?>>Four Times Daily</option>
                        <option value="Every 4 Hours" <?= ($item['frequency'] === 'Every 4 Hours') ? 'selected' : '' ?>>Every 4 Hours</option>
                        <option value="Every 6 Hours" <?= ($item['frequency'] === 'Every 6 Hours') ? 'selected' : '' ?>>Every 6 Hours</option>
                        <option value="Every 8 Hours" <?= ($item['frequency'] === 'Every 8 Hours') ? 'selected' : '' ?>>Every 8 Hours</option>
                        <option value="Every 12 Hours" <?= ($item['frequency'] === 'Every 12 Hours') ? 'selected' : '' ?>>Every 12 Hours</option>
                        <option value="As Needed (PRN)" <?= ($item['frequency'] === 'As Needed (PRN)') ? 'selected' : '' ?>>As Needed (PRN)</option>
                        <option value="Before Meals" <?= ($item['frequency'] === 'Before Meals') ? 'selected' : '' ?>>Before Meals</option>
                        <option value="After Meals" <?= ($item['frequency'] === 'After Meals') ? 'selected' : '' ?>>After Meals</option>
                        <option value="With Meals" <?= ($item['frequency'] === 'With Meals') ? 'selected' : '' ?>>With Meals</option>
                        <option value="At Bedtime" <?= ($item['frequency'] === 'At Bedtime') ? 'selected' : '' ?>>At Bedtime</option>
                        <option value="On Empty Stomach" <?= ($item['frequency'] === 'On Empty Stomach') ? 'selected' : '' ?>>On Empty Stomach</option>
                    </select>
                </div>
                <div class="form-group-pi">
                    <label class="form-label-pi">Duration (Days) <span class="required">*</span></label>
                    <input type="number" name="duration" class="form-control-pi" 
                           value="<?= htmlspecialchars($item['duration'] ?? '7') ?>" 
                           min="1" max="90" required>
                </div>
            </div>
            
            <div class="form-row-pi">
                <div class="form-group-pi">
                    <label class="form-label-pi">Route <span class="required">*</span></label>
                    <select name="route" class="form-control-pi" required>
                        <option value="">Select Route</option>
                        <option value="Oral" <?= ($item['route'] === 'Oral') ? 'selected' : '' ?>>Oral</option>
                        <option value="Topical" <?= ($item['route'] === 'Topical') ? 'selected' : '' ?>>Topical</option>
                        <option value="Injection" <?= ($item['route'] === 'Injection') ? 'selected' : '' ?>>Injection</option>
                        <option value="IV" <?= ($item['route'] === 'IV') ? 'selected' : '' ?>>IV</option>
                        <option value="IM" <?= ($item['route'] === 'IM') ? 'selected' : '' ?>>IM</option>
                        <option value="Sublingual" <?= ($item['route'] === 'Sublingual') ? 'selected' : '' ?>>Sublingual</option>
                        <option value="Inhalation" <?= ($item['route'] === 'Inhalation') ? 'selected' : '' ?>>Inhalation</option>
                        <option value="Nasal" <?= ($item['route'] === 'Nasal') ? 'selected' : '' ?>>Nasal</option>
                        <option value="Ophthalmic" <?= ($item['route'] === 'Ophthalmic') ? 'selected' : '' ?>>Ophthalmic</option>
                        <option value="Otic" <?= ($item['route'] === 'Otic') ? 'selected' : '' ?>>Otic</option>
                        <option value="Rectal" <?= ($item['route'] === 'Rectal') ? 'selected' : '' ?>>Rectal</option>
                    </select>
                </div>
                <div class="form-group-pi">
                    <label class="form-label-pi">Unit Price <span class="required">*</span></label>
                    <input type="number" name="unit_price" class="form-control-pi" 
                           value="<?= $item['unit_price'] ?? 0 ?>" 
                           step="0.01" min="0" required
                           id="unitPriceInput" onchange="updateTotalPrice()">
                    <div style="font-size:0.6rem;color:var(--pi-text-secondary);margin-top:2px;">
                        <i class="fas fa-info-circle"></i> Total will be calculated automatically: Quantity × Unit Price
                    </div>
                </div>
            </div>
            
            <div class="form-group-pi">
                <label class="form-label-pi">Instructions</label>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;">
                    <button type="button" class="quick-instruction-btn" onclick="addInstruction('Take after meals')">After Meals</button>
                    <button type="button" class="quick-instruction-btn" onclick="addInstruction('Take before meals')">Before Meals</button>
                    <button type="button" class="quick-instruction-btn" onclick="addInstruction('Take with plenty of water')">With Water</button>
                    <button type="button" class="quick-instruction-btn" onclick="addInstruction('Take at bedtime')">At Bedtime</button>
                    <button type="button" class="quick-instruction-btn" onclick="addInstruction('Do not crush or chew')">Do Not Crush</button>
                </div>
                <textarea name="instructions" class="form-control-pi" rows="3" 
                          placeholder="e.g. Take after meals, with plenty of water..."
                          id="instructionsTextarea"><?= htmlspecialchars($item['instructions'] ?? '') ?></textarea>
            </div>
            
            <!-- Total Preview -->
            <div class="total-preview-pi">
                <span class="total-label">
                    <i class="fas fa-calculator"></i> Total Price:
                </span>
                <span class="total-value" id="totalPriceDisplay">
                    TSh <?= number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 0) ?>
                </span>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions-pi">
                <button type="submit" class="btn-pi btn-primary-pi">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="prescription_details.php?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-pi btn-secondary-pi">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="?delete_item=<?= $item_id ?>&pid=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                   class="btn-pi btn-danger-pi" style="margin-left:auto;"
                   onclick="return confirm('⚠️ Delete this item permanently?\n\nMedication: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>\nQuantity: <?= $item['quantity'] ?? 0 ?>\nAmount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>\n\nStock will be returned to inventory!')">
                    <i class="fas fa-trash"></i> Delete Item
                </a>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer-pi">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Prescription Item
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?>
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
    // UPDATE TOTAL PRICE
    // ================================================================
    function updateTotalPrice() {
        var qty = parseInt(document.querySelector('input[name="quantity"]').value) || 0;
        var unitPrice = parseFloat(document.getElementById('unitPriceInput').value) || 0;
        var total = qty * unitPrice;
        document.getElementById('totalPriceDisplay').textContent = 'TSh ' + total.toLocaleString();
    }
    
    document.querySelector('input[name="quantity"]')?.addEventListener('input', function() {
        updateTotalPrice();
    });
    
    // ================================================================
    // UPDATE MEDICATION DETAILS FROM INVENTORY SELECT
    // ================================================================
    function updateMedicationDetails(select) {
        var option = select.options[select.selectedIndex];
        if (option && option.value) {
            var name = option.getAttribute('data-name') || '';
            var price = option.getAttribute('data-price') || '';
            var unit = option.getAttribute('data-unit') || '';
            var stock = option.getAttribute('data-stock') || '';
            var batch = option.getAttribute('data-batch') || '';
            
            var nameInput = document.querySelector('input[name="medication_name"]');
            var priceInput = document.getElementById('unitPriceInput');
            
            if (nameInput && name) {
                nameInput.value = name;
            }
            if (priceInput && price) {
                priceInput.value = price;
                updateTotalPrice();
            }
            
            var infoDiv = document.querySelector('.info-box-pi');
            if (infoDiv) {
                var batchItems = infoDiv.querySelectorAll('.info-item-pi');
                var foundBatch = false;
                batchItems.forEach(function(item) {
                    var label = item.querySelector('.label');
                    if (label && label.textContent.trim() === 'Batch Number') {
                        var value = item.querySelector('.value');
                        if (value) {
                            value.textContent = batch || 'N/A';
                        }
                        foundBatch = true;
                    }
                });
                if (!foundBatch && batch) {
                    var newItem = document.createElement('div');
                    newItem.className = 'info-item-pi';
                    newItem.innerHTML = '<span class="label">Batch Number</span><span class="value mono">' + batch + '</span>';
                    infoDiv.appendChild(newItem);
                }
            }
        }
    }
    
    // ================================================================
    // ADD INSTRUCTION
    // ================================================================
    function addInstruction(text) {
        var textarea = document.getElementById('instructionsTextarea');
        if (textarea) {
            var current = textarea.value;
            textarea.value = current ? current + ', ' + text : text;
            textarea.focus();
        }
    }

    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c🏥 Braick Dispensary - Edit Prescription Item', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Item: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Inventory linking - stock updates automatically', 'font-size:13px; color:#34D399;');
    console.log('%c🗑️ Delete button redirects to prescription_details.php immediately', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>