<?php
// ================================================================
// FILE: frontend/pages/admin/edit_prescription_item.php
// EDIT PRESCRIPTION ITEM - Edit medication item details
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
        
        // Get item details to return stock
        $stmt = $db->prepare("SELECT inventory_id, quantity FROM prescription_items WHERE id = ?");
        $stmt->execute([$delete_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item && $item['inventory_id']) {
            // Return stock to inventory
            $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['inventory_id']]);
        }
        
        // Delete the item
        $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
        $stmt->execute([$delete_item_id]);
        
        $db->commit();
        
        // ✅ REDIRECT TO PRESCRIPTION DETAILS PAGE IMMEDIATELY
        header('Location: prescription_details.php?id=' . $delete_prescription_id . '&branch=' . urlencode($delete_branch) . '&item_deleted=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=delete_failed');
        exit;
    }
}

// ================================================================
// HANDLE CANCEL ITEM (FIRST)
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
    
    // Validate
    $errors = [];
    if (empty($medication_name)) {
        $errors[] = "Medication name is required";
    }
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0";
    }
    if ($unit_price < 0) {
        $errors[] = "Unit price cannot be negative";
    }
    if (empty($frequency)) {
        $errors[] = "Frequency is required";
    }
    if (empty($route)) {
        $errors[] = "Route is required";
    }
    if (empty($duration)) {
        $errors[] = "Duration is required";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Check if we need to update stock (if inventory_id changed or quantity changed)
            $old_inventory_id = $item['inventory_id'];
            $old_quantity = $item['quantity'];
            
            // If inventory_id changed, return stock to old inventory and deduct from new
            if ($old_inventory_id != $inventory_id) {
                // Return stock to old inventory
                if ($old_inventory_id) {
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                    $stmt->execute([$old_quantity, $old_inventory_id]);
                }
                
                // Deduct from new inventory
                if ($inventory_id) {
                    // Check if new inventory has enough stock
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
                // Same inventory, adjust stock difference
                $diff = $quantity - $old_quantity;
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$diff, $old_inventory_id]);
                
                // Check if stock went negative
                $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                $stmt->execute([$old_inventory_id]);
                $new_stock = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($new_stock && $new_stock['quantity'] < 0) {
                    // Rollback the stock update
                    throw new Exception("Not enough stock. Available: " . ($new_stock['quantity'] + $diff));
                }
            }
            
            // Update prescription item
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

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }
    
    .page-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        border-radius: 16px !important;
        padding: 20px 28px !important;
        margin-bottom: 20px !important;
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 12px !important;
        box-shadow: 0 4px 24px rgba(11, 94, 215, 0.3) !important;
    }
    .page-header .page-title { color: white !important; font-size: 1.4rem !important; font-weight: 700 !important; }
    .page-header .page-title i { color: white !important; }
    .page-header .page-subtitle { color: rgba(255,255,255,0.9) !important; font-size: 0.85rem !important; }
    .page-header .branch-tag { background: rgba(255,255,255,0.2) !important; color: white !important; padding: 3px 14px !important; border-radius: 20px !important; font-size: 0.7rem !important; font-weight: 600 !important; }
    
    .btn-outline-light {
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
    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
    }
    
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .detail-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .detail-card .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
        flex-wrap: wrap;
    }
    .detail-card .card-title i { color: #0B5ED7; }
    
    .form-group {
        margin-bottom: 16px;
    }
    .form-group:last-child {
        margin-bottom: 0;
    }
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
        letter-spacing: 0.02em;
    }
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    .form-control:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--gray-100);
    }
    .form-control option {
        background: var(--bg-card);
        color: var(--text-primary);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .info-box {
        background: var(--primary-bg);
        border-radius: 10px;
        padding: 12px 16px;
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 16px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    .info-box .info-item {
        display: flex;
        flex-direction: column;
    }
    .info-box .info-item .label {
        font-size: 0.55rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        font-weight: 600;
        letter-spacing: 0.05em;
    }
    .info-box .info-item .value {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    .info-box .info-item .value.mono { font-family: monospace; }
    .info-box .info-item .value.amount { color: #0B5ED7; font-weight: 700; }
    
    .status-badge {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
    }
    .btn i { font-size: 0.9rem; }
    .btn:hover { transform: translateY(-2px); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
    
    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    .btn-primary:hover {
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
    }
    
    .btn-secondary {
        background: var(--bg-body);
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-secondary:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        background: var(--primary-bg);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
    }
    .btn-danger:hover {
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        background: linear-gradient(135deg, #B91C1C, #991B1B);
    }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
        margin-top: 16px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
    }
    .alert-error {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #DC2626;
    }
    .alert-success {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #059669;
    }
    .alert-info {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #0B5ED7;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    .text-gray-300 { color: var(--text-muted); }
    .mx-2 { margin-left: 8px; margin-right: 8px; }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 16px; }
        .page-header { padding: 14px 18px !important; }
        .page-header .page-title { font-size: 1.1rem !important; }
        .form-row { grid-template-columns: 1fr; }
        .info-box { flex-direction: column; gap: 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; }
    }
    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .page-header .page-title { font-size: 0.95rem !important; }
        .detail-card { padding: 14px 16px; }
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
</style>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper" style="flex:1;display:flex;align-items:center;background:var(--bg-body);border-radius:10px;border:1px solid var(--border-color);padding:0 12px;">
            <i class="fas fa-search text-gray-400"></i>
            <span style="padding:8px 12px;font-size:0.85rem;color:var(--text-secondary);">
                <i class="fas fa-edit"></i> Editing Prescription Item
            </span>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
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

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header - Blue Background -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit mr-2"></i>
                Edit Prescription Item
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag ml-2">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-prescription"></i> Prescription: <strong><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag ml-2">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($item['patient_name'] ?? 'Unknown') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="prescription_details.php?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ITEM INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title">
            <i class="fas fa-pills"></i> Edit Medication Item
            <span class="status-badge info" style="font-size:0.6rem;padding:2px 12px;">
                <i class="fas fa-edit"></i> Edit Mode
            </span>
        </div>
        
        <!-- Info Box -->
        <div class="info-box">
            <div class="info-item">
                <span class="label">Prescription</span>
                <span class="value mono"><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($item['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Patient ID</span>
                <span class="value mono"><?= htmlspecialchars($item['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Current Medication</span>
                <span class="value" style="color:#0B5ED7;font-weight:600;"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($item['inventory_batch_number'])): ?>
            <div class="info-item">
                <span class="label">Batch Number</span>
                <span class="value mono"><?= htmlspecialchars($item['inventory_batch_number']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="label">Current Total</span>
                <span class="value amount">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas <?= $message_type === 'error' ? 'fa-exclamation-circle' : ($message_type === 'success' ? 'fa-check-circle' : 'fa-info-circle') ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <!-- Form -->
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Medication Name <span class="required">*</span></label>
                    <input type="text" name="medication_name" class="form-control" 
                           value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" 
                           placeholder="Enter medication name..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Inventory Medication <span style="font-size:0.7rem;color:var(--text-secondary);">(Optional - select to link to inventory)</span></label>
                    <select name="inventory_id" class="form-control" id="inventorySelect" onchange="updateMedicationDetails(this)">
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
                        <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:2px;">
                            <i class="fas fa-info-circle"></i> Currently linked to inventory. Changing will update stock accordingly.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Dosage <span style="font-size:0.7rem;color:var(--text-secondary);">(e.g. 500mg)</span></label>
                    <input type="text" name="dosage" class="form-control" 
                           value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" 
                           placeholder="e.g. 500mg">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" 
                           value="<?= $item['quantity'] ?? 1 ?>" min="1" max="999" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Frequency <span class="required">*</span></label>
                    <select name="frequency" class="form-control" required>
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
                <div class="form-group">
                    <label class="form-label">Duration (Days) <span class="required">*</span></label>
                    <input type="number" name="duration" class="form-control" 
                           value="<?= htmlspecialchars($item['duration'] ?? '7') ?>" 
                           min="1" max="90" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Route <span class="required">*</span></label>
                    <select name="route" class="form-control" required>
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
                <div class="form-group">
                    <label class="form-label">Unit Price <span class="required">*</span></label>
                    <input type="number" name="unit_price" class="form-control" 
                           value="<?= $item['unit_price'] ?? 0 ?>" 
                           step="0.01" min="0" required
                           id="unitPriceInput" onchange="updateTotalPrice()">
                    <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:2px;">
                        <i class="fas fa-info-circle"></i> Total will be calculated automatically: Quantity × Unit Price
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Instructions</label>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;">
                    <button type="button" class="btn" style="padding:4px 12px;font-size:0.65rem;background:var(--bg-body);border:1px solid var(--border-color);border-radius:6px;color:var(--text-secondary);" onclick="addInstruction('Take after meals')">After Meals</button>
                    <button type="button" class="btn" style="padding:4px 12px;font-size:0.65rem;background:var(--bg-body);border:1px solid var(--border-color);border-radius:6px;color:var(--text-secondary);" onclick="addInstruction('Take before meals')">Before Meals</button>
                    <button type="button" class="btn" style="padding:4px 12px;font-size:0.65rem;background:var(--bg-body);border:1px solid var(--border-color);border-radius:6px;color:var(--text-secondary);" onclick="addInstruction('Take with plenty of water')">With Water</button>
                    <button type="button" class="btn" style="padding:4px 12px;font-size:0.65rem;background:var(--bg-body);border:1px solid var(--border-color);border-radius:6px;color:var(--text-secondary);" onclick="addInstruction('Take at bedtime')">At Bedtime</button>
                    <button type="button" class="btn" style="padding:4px 12px;font-size:0.65rem;background:var(--bg-body);border:1px solid var(--border-color);border-radius:6px;color:var(--text-secondary);" onclick="addInstruction('Do not crush or chew')">Do Not Crush</button>
                </div>
                <textarea name="instructions" class="form-control" rows="3" 
                          placeholder="e.g. Take after meals, with plenty of water..."
                          id="instructionsTextarea"><?= htmlspecialchars($item['instructions'] ?? '') ?></textarea>
            </div>
            
            <!-- Total Preview -->
            <div style="background:var(--primary-bg);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-top:8px;border:1px solid rgba(11,94,215,0.15);">
                <span style="font-weight:600;color:var(--text-secondary);font-size:0.85rem;">
                    <i class="fas fa-calculator"></i> Total Price:
                </span>
                <span style="font-size:1.1rem;font-weight:700;color:#0B5ED7;" id="totalPriceDisplay">
                    TSh <?= number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 0) ?>
                </span>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="prescription_details.php?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="?delete_item=<?= $item_id ?>&pid=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                   class="btn btn-danger" style="margin-left:auto;"
                   onclick="return confirm('⚠️ Delete this item permanently?\n\nMedication: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>\nQuantity: <?= $item['quantity'] ?? 0 ?>\nAmount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>\n\nStock will be returned to inventory!')">
                    <i class="fas fa-trash"></i> Delete Item
                </a>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Prescription Item
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- JAVASCRIPT -->
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
    
    // Update total on quantity change
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
            
            // Show selected medication info
            var infoDiv = document.querySelector('.info-box');
            if (infoDiv) {
                // Update batch info
                var batchItems = infoDiv.querySelectorAll('.info-item');
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
                    // Add batch info if not exists
                    var newItem = document.createElement('div');
                    newItem.className = 'info-item';
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
    // DARK MODE
    // ================================================================
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // DATE TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Edit Prescription Item', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Item: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Edit medication name, dosage, frequency, quantity, duration, route, instructions, unit price', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Inventory linking - stock updates automatically', 'font-size:13px; color:#34D399;');
    console.log('%c🗑️ Delete button redirects to prescription_details.php immediately', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>