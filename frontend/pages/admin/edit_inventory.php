<?php
// ================================================================
// FILE: frontend/pages/admin/edit_inventory.php
// SUPER ADMIN - EDIT INVENTORY ITEM
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
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
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
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
        default: header('Location: ../login.php'); break;
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
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// VERIFY USER EXISTS
// ================================================================
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// GET PARAMETERS
// ================================================================
$inventory_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

if ($inventory_id <= 0) {
    header('Location: pharmacy_inventory.php?branch=' . $branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH INVENTORY ITEM
// ================================================================
$stmt = $db->prepare("
    SELECT 
        mi.*,
        b.name as branch_name
    FROM medications_inventory mi
    LEFT JOIN branches b ON mi.branch_id = b.id
    WHERE mi.id = ?
");
$stmt->execute([$inventory_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: pharmacy_inventory.php?branch=' . $branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET BRANCHES FOR DROPDOWN
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $medication_name = trim($_POST['medication_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 0);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $expiry_date = $_POST['expiry_date'] ?? null;
    $batch_number = trim($_POST['batch_number'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $branch_id_update = (int)($_POST['branch_id'] ?? 0);
    
    // Validate
    $errors = [];
    if (empty($medication_name)) {
        $errors[] = "Medication name is required.";
    }
    if ($quantity < 0) {
        $errors[] = "Quantity cannot be negative.";
    }
    if ($reorder_level < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }
    if ($unit_cost < 0) {
        $errors[] = "Unit cost cannot be negative.";
    }
    if ($selling_price < 0) {
        $errors[] = "Selling price cannot be negative.";
    }
    if ($branch_id_update <= 0) {
        $errors[] = "Please select a branch.";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE medications_inventory 
                SET 
                    medication_name = ?,
                    category = ?,
                    unit = ?,
                    quantity = ?,
                    reorder_level = ?,
                    unit_cost = ?,
                    selling_price = ?,
                    supplier = ?,
                    expiry_date = ?,
                    batch_number = ?,
                    status = ?,
                    branch_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $medication_name,
                $category,
                $unit,
                $quantity,
                $reorder_level,
                $unit_cost,
                $selling_price,
                $supplier,
                $expiry_date ?: null,
                $batch_number,
                $status,
                $branch_id_update,
                $inventory_id
            ]);
            
            // Log activity
            $details = "Updated inventory item: " . htmlspecialchars($medication_name) . 
                       " (ID: #$inventory_id) in branch ID " . $branch_id_update;
            
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'inventory_updated', ?, NOW())
            ");
            $stmt->execute([$user_id, $branch_id_update, $details]);
            
            // Refresh item data
            $stmt = $db->prepare("
                SELECT 
                    mi.*,
                    b.name as branch_name
                FROM medications_inventory mi
                LEFT JOIN branches b ON mi.branch_id = b.id
                WHERE mi.id = ?
            ");
            $stmt->execute([$inventory_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = "✅ Inventory item updated successfully!";
            $message_type = "success";
            
            // Redirect after success
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "pharmacy_inventory.php?branch=' . $branch_id_update . '&updated=1"; 
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            $message = "❌ Error updating inventory: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "danger";
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
        --inv-primary: #0B5ED7;
        --inv-primary-dark: #0A4CA8;
        --inv-primary-light: #3B82F6;
        --inv-primary-bg: #EFF6FF;
        --inv-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --inv-success: #059669;
        --inv-success-bg: #D1FAE5;
        --inv-danger: #DC2626;
        --inv-danger-bg: #FEE2E2;
        --inv-warning: #D97706;
        --inv-warning-bg: #FEF3C7;
        --inv-purple: #7C3AED;
        --inv-purple-bg: #F5F3FF;
        --inv-bg-body: #F0F4F8;
        --inv-bg-card: #FFFFFF;
        --inv-text-primary: #1E293B;
        --inv-text-secondary: #64748B;
        --inv-border-color: #E2E8F0;
        --inv-radius: 12px;
        --inv-radius-lg: 18px;
        --inv-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --inv-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --inv-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --inv-bg-body: #0F172A;
        --inv-bg-card: #1E293B;
        --inv-text-primary: #F1F5F9;
        --inv-text-secondary: #94A3B8;
        --inv-border-color: #334155;
        --inv-primary: #3B82F6;
        --inv-primary-bg: #1E3A5F;
        --inv-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --inv-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
    .page-header-custom {
        background: var(--inv-primary-gradient);
        border-radius: var(--inv-radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
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

    .page-header-custom .page-title {
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

    .page-header-custom .page-title i { font-size: 2rem; opacity: 0.9; }

    .page-header-custom .page-subtitle {
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

    .page-header-custom .role-badge-display {
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

    .page-header-custom .header-badge {
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

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--inv-radius);
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        transition: all 0.3s ease;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-custom {
        background: var(--inv-bg-card);
        border-radius: var(--inv-radius-lg);
        border: 2px solid var(--inv-border-color);
        padding: 28px 32px;
        transition: all 0.3s ease;
        box-shadow: var(--inv-shadow-sm);
        margin-bottom: 24px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .form-card-custom:hover {
        border-color: var(--inv-primary);
        box-shadow: var(--inv-shadow-md);
    }

    .form-card-custom .form-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--inv-primary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-group-custom {
        margin-bottom: 16px;
    }

    .form-group-custom label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--inv-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
    }

    .form-group-custom .required {
        color: #DC2626;
        font-weight: 700;
    }

    .form-control-custom {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--inv-border-color);
        border-radius: var(--inv-radius);
        font-size: 0.85rem;
        color: var(--inv-text-primary);
        background: var(--inv-bg-body);
        transition: all 0.3s ease;
        outline: none;
        font-family: inherit;
    }

    .form-control-custom:focus {
        border-color: var(--inv-primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .form-control-custom:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-control-custom::placeholder {
        color: var(--inv-text-secondary);
        opacity: 0.7;
    }

    [data-theme="dark"] .form-control-custom option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .form-hint-custom {
        font-size: 0.65rem;
        color: var(--inv-text-secondary);
        margin-top: 4px;
    }

    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--inv-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        box-shadow: var(--inv-shadow-sm);
        font-family: inherit;
    }

    .btn-custom:hover {
        transform: translateY(-3px);
        box-shadow: var(--inv-shadow-lg);
    }

    .btn-custom:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    .btn-primary-custom {
        background: var(--inv-primary-gradient);
        color: white;
    }

    .btn-primary-custom:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--inv-text-primary);
        border: 2px solid var(--inv-border-color);
    }

    .btn-outline-custom:hover {
        background: var(--inv-bg-body);
        border-color: var(--inv-primary);
        color: var(--inv-primary);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert-custom {
        padding: 14px 20px;
        border-radius: var(--inv-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 2px solid transparent;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-custom.alert-success {
        background: var(--inv-success-bg);
        border-color: var(--inv-success);
        color: #065F46;
    }

    .alert-custom.alert-danger {
        background: var(--inv-danger-bg);
        border-color: var(--inv-danger);
        color: #991B1B;
    }

    [data-theme="dark"] .alert-custom.alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #059669;
    }

    [data-theme="dark"] .alert-custom.alert-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #DC2626;
    }

    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stat-card-custom {
        text-align: center;
        padding: 12px;
        border-radius: var(--inv-radius);
        transition: all 0.3s ease;
    }

    .stat-card-custom:hover {
        transform: translateY(-2px);
    }

    .stat-card-custom.blue { background: #EFF6FF; }
    .stat-card-custom.green { background: #D1FAE5; }
    .stat-card-custom.purple { background: #F5F3FF; }
    .stat-card-custom.orange { background: #FFFBEB; }

    [data-theme="dark"] .stat-card-custom.blue { background: #1E3A5F; }
    [data-theme="dark"] .stat-card-custom.green { background: #1A3A2A; }
    [data-theme="dark"] .stat-card-custom.purple { background: #2D1B4E; }
    [data-theme="dark"] .stat-card-custom.orange { background: #3D2E0A; }

    .stat-card-custom .stat-value-custom {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }

    .stat-card-custom.blue .stat-value-custom { color: #0B5ED7; }
    .stat-card-custom.green .stat-value-custom { color: #059669; }
    .stat-card-custom.purple .stat-value-custom { color: #7C3AED; }
    .stat-card-custom.orange .stat-value-custom { color: #D97706; }

    .stat-card-custom .stat-label-custom {
        font-size: 0.75rem;
        color: var(--inv-text-secondary);
        margin: 4px 0 0 0;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-custom {
        padding: 14px 0;
        border-top: 2px solid var(--inv-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--inv-text-secondary);
    }

    .footer-custom .footer-brand {
        color: var(--inv-primary);
        font-weight: 500;
    }

    /* ================================================================
       SPINNER
       ================================================================ */
    .spinner-custom {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

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
        .page-header-custom { padding: 16px 18px; }
        .page-header-custom .page-title { font-size: 1.3rem; }
        .form-card-custom { padding: 16px; }
        .form-row-2 { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .page-header-custom { flex-direction: column; align-items: flex-start !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Inventory Item
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-pills"></i>
                Editing: <strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-hashtag"></i> ID: #<?= $item['id'] ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_inventory.php?id=<?= $item['id'] ?>&branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="pharmacy_inventory.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if (!empty($message)): ?>
        <div class="alert-custom alert-<?= $message_type ?> animate-fade-in-up" style="animation-delay:0.05s;">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="form-card-custom animate-fade-in-up" style="animation-delay:0.1s;">
        <h3 class="form-title">
            <i class="fas fa-pen"></i> Edit Inventory Item
        </h3>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update">
            
            <!-- Row 1: Medication Name & Category -->
            <div class="form-row-2">
                <div class="form-group-custom">
                    <label>Medication Name <span class="required">*</span></label>
                    <input type="text" name="medication_name" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" 
                           placeholder="e.g. Paracetamol 500mg" required>
                </div>
                <div class="form-group-custom">
                    <label>Category</label>
                    <input type="text" name="category" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['category'] ?? '') ?>" 
                           placeholder="e.g. Pain Relief, Antibiotic">
                </div>
            </div>
            
            <!-- Row 2: Unit & Branch -->
            <div class="form-row-2">
                <div class="form-group-custom">
                    <label>Unit <span class="required">*</span></label>
                    <input type="text" name="unit" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['unit'] ?? '') ?>" 
                           placeholder="e.g. Tablets, Capsules, Box" required>
                </div>
                <div class="form-group-custom">
                    <label>Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control-custom" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($item['branch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Row 3: Quantity & Reorder Level -->
            <div class="form-row-2">
                <div class="form-group-custom">
                    <label>Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['quantity'] ?? 0) ?>" 
                           placeholder="0" min="0" required>
                    <p class="form-hint-custom">Current stock quantity</p>
                </div>
                <div class="form-group-custom">
                    <label>Reorder Level</label>
                    <input type="number" name="reorder_level" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['reorder_level'] ?? 10) ?>" 
                           placeholder="10" min="0">
                    <p class="form-hint-custom">Alert when quantity falls below this level</p>
                </div>
            </div>
            
            <!-- Row 4: Unit Cost & Selling Price -->
            <div class="form-row-2">
                <div class="form-group-custom">
                    <label>Unit Cost (TSh)</label>
                    <input type="number" name="unit_cost" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['unit_cost'] ?? 0) ?>" 
                           placeholder="0" min="0" step="0.01">
                    <p class="form-hint-custom">Cost price per unit</p>
                </div>
                <div class="form-group-custom">
                    <label>Selling Price (TSh) <span class="required">*</span></label>
                    <input type="number" name="selling_price" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['selling_price'] ?? 0) ?>" 
                           placeholder="0" min="0" step="0.01" required>
                    <p class="form-hint-custom">Selling price per unit</p>
                </div>
            </div>
            
            <!-- Row 5: Expiry Date & Batch Number -->
            <div class="form-row-2">
                <div class="form-group-custom">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control-custom" 
                           value="<?= !empty($item['expiry_date']) ? date('Y-m-d', strtotime($item['expiry_date'])) : '' ?>">
                    <p class="form-hint-custom">Leave empty if no expiry date</p>
                </div>
                <div class="form-group-custom">
                    <label>Batch Number</label>
                    <input type="text" name="batch_number" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['batch_number'] ?? '') ?>" 
                           placeholder="e.g. BATCH-2026-001">
                </div>
            </div>
            
            <!-- Row 6: Supplier & Status -->
            <div class="form-row-2">
                <div class="form-group-custom">
                    <label>Supplier</label>
                    <input type="text" name="supplier" class="form-control-custom" 
                           value="<?= htmlspecialchars($item['supplier'] ?? '') ?>" 
                           placeholder="e.g. Dodoma Pharma">
                </div>
                <div class="form-group-custom">
                    <label>Status</label>
                    <select name="status" class="form-control-custom">
                        <option value="active" <?= ($item['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($item['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;padding-top:16px;border-top:2px solid var(--inv-border-color);">
                <button type="submit" class="btn-custom btn-primary-custom">
                    <i class="fas fa-save"></i> Update Item
                </button>
                <a href="view_inventory.php?id=<?= $item['id'] ?>&branch=<?= $branch_id ?>" class="btn-custom btn-outline-custom">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="pharmacy_inventory.php?branch=<?= $branch_id ?>" class="btn-custom btn-outline-custom">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- ITEM STATISTICS SUMMARY -->
    <!-- ================================================================ -->
    <div class="form-card-custom animate-fade-in-up" style="animation-delay:0.15s;">
        <h3 class="form-title">
            <i class="fas fa-chart-bar"></i> Item Statistics
        </h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;" class="stats-grid-responsive">
            <div class="stat-card-custom blue">
                <p class="stat-value-custom"><?= number_format($item['quantity'] ?? 0) ?></p>
                <p class="stat-label-custom">Current Quantity</p>
            </div>
            <div class="stat-card-custom green">
                <p class="stat-value-custom">TSh <?= number_format(($item['selling_price'] ?? 0) * ($item['quantity'] ?? 0), 0) ?></p>
                <p class="stat-label-custom">Stock Value</p>
            </div>
            <div class="stat-card-custom purple">
                <p class="stat-value-custom"><?= number_format($item['reorder_level'] ?? 0) ?></p>
                <p class="stat-label-custom">Reorder Level</p>
            </div>
            <div class="stat-card-custom orange">
                <p class="stat-value-custom">
                    <?= ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) ? '⚠️' : '✅' ?>
                </p>
                <p class="stat-label-custom">
                    <?= ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) ? 'Below Reorder' : 'Above Reorder' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-custom">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Inventory Item - <?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?>
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
    // FORM SUBMISSION
    // ================================================================
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        var submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<span class="spinner-custom"></span> Updating...';
        submitBtn.disabled = true;
        return true;
    });

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

    // ================================================================
    // RESPONSIVE STATS GRID
    // ================================================================
    (function() {
        function adjustStatsGrid() {
            var grid = document.querySelector('.stats-grid-responsive');
            if (!grid) return;
            
            if (window.innerWidth <= 768) {
                grid.style.gridTemplateColumns = '1fr 1fr';
            } else {
                grid.style.gridTemplateColumns = 'repeat(4, 1fr)';
            }
        }
        
        adjustStatsGrid();
        window.addEventListener('resize', adjustStatsGrid);
    })();

    console.log('%c✏️ Braick Dispensary - Edit Inventory Item', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📦 Item: <?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?> (ID: <?= $item['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Quantity: <?= number_format($item['quantity'] ?? 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Price: TSh <?= number_format($item['selling_price'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>