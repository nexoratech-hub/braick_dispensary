<?php
// ================================================================
// FILE: frontend/pages/admin/edit_equipment.php
// ADMIN - EDIT EQUIPMENT
// EDIT ALL EQUIPMENT DETAILS
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only
// ✅ Dark mode inatumia header toggle
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

// ================================================================
// SESSION START
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

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';

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
// GET EQUIPMENT DETAILS
// ================================================================
$equipment = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            b.name as branch_name
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $error_message = "Equipment not found. It may have been deleted.";
    }
} catch (Exception $e) {
    $error_message = "Error loading equipment: " . $e->getMessage();
    $equipment = null;
}

// ================================================================
// GET BRANCHES FOR DROPDOWN
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET SERVICE CATEGORIES FOR DROPDOWN
// ================================================================
$categories = [];
try {
    $stmt = $db->query("SELECT id, category_name FROM service_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_equipment') {
    $equipment_id = (int)($_POST['equipment_id'] ?? 0);
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name = trim($_POST['category_name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 5);
    $selling_price = isset($_POST['selling_price']) ? (float)str_replace(',', '', $_POST['selling_price']) : 0;
    $supplier = trim($_POST['supplier'] ?? '');
    $expiry_date = $_POST['expiry_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : null;
    
    if ($branch_id === 'all' || $branch_id === '' || $branch_id === 'NULL') {
        $branch_id = null;
    } elseif (is_numeric($branch_id)) {
        $branch_id = (int)$branch_id;
    } else {
        $branch_id = null;
    }
    
    // Determine category
    $final_category = '';
    if ($category_id > 0) {
        foreach ($categories as $cat) {
            if ($cat['id'] == $category_id) {
                $final_category = $cat['category_name'];
                break;
            }
        }
    } elseif (!empty($category_name)) {
        $final_category = $category_name;
    }
    
    // Validate
    $errors = [];
    if (empty($equipment_name)) { $errors[] = 'Equipment name is required'; }
    if ($quantity < 0) { $errors[] = 'Quantity cannot be negative'; }
    if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
    if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Expiry date cannot be in the past';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE medical_equipment 
                SET 
                    equipment_name = ?,
                    category = ?,
                    unit = ?,
                    quantity = ?,
                    reorder_level = ?,
                    selling_price = ?,
                    supplier = ?,
                    expiry_date = ?,
                    status = ?,
                    branch_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $equipment_name,
                $final_category,
                $unit,
                $quantity,
                $reorder_level,
                $selling_price,
                $supplier,
                $expiry_date ?: null,
                $status,
                $branch_id,
                $equipment_id
            ]);
            
            $message = "✅ Equipment updated successfully!";
            $message_type = 'success';
            
            // Refresh equipment data
            $stmt = $db->prepare("
                SELECT 
                    e.*,
                    b.name as branch_name
                FROM medical_equipment e
                LEFT JOIN branches b ON e.branch_id = b.id
                WHERE e.id = ?
            ");
            $stmt->execute([$equipment_id]);
            $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStockStatus($quantity, $reorder_level) {
    if ($quantity <= 0) return ['class' => 'out', 'label' => 'Out of Stock'];
    if ($quantity <= $reorder_level) return ['class' => 'low', 'label' => 'Low Stock'];
    return ['class' => 'ok', 'label' => 'In Stock'];
}

function getExpiryStatus($expiry_date) {
    if (empty($expiry_date) || $expiry_date === '0000-00-00') {
        return ['class' => 'no-expiry', 'label' => 'No Expiry', 'days' => null];
    }
    $days = floor((strtotime($expiry_date) - time()) / 86400);
    if ($days < 0) return ['class' => 'expired', 'label' => 'Expired', 'days' => $days];
    if ($days <= 30) return ['class' => 'expiring', 'label' => 'Expiring Soon', 'days' => $days];
    return ['class' => 'valid', 'label' => 'Valid', 'days' => $days];
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
        --eq-primary: #0B5ED7;
        --eq-primary-dark: #0A4CA8;
        --eq-primary-light: #6EA8FE;
        --eq-primary-bg: #E8F0FE;
        --eq-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --eq-success: #059669;
        --eq-success-bg: #D1FAE5;
        --eq-danger: #DC2626;
        --eq-danger-bg: #FEE2E2;
        --eq-warning: #D97706;
        --eq-warning-bg: #FEF3C7;
        --eq-purple: #7C3AED;
        --eq-purple-bg: #EDE9FE;
        --eq-teal: #0D9488;
        --eq-teal-bg: #CCFBF1;
        --eq-gray-50: #F8FAFC;
        --eq-gray-100: #F1F5F9;
        --eq-gray-200: #E2E8F0;
        --eq-gray-300: #CBD5E1;
        --eq-gray-400: #94A3B8;
        --eq-gray-500: #64748B;
        --eq-gray-600: #475569;
        --eq-gray-700: #334155;
        --eq-gray-800: #1E293B;
        --eq-gray-900: #0F172A;
        --eq-radius: 10px;
        --eq-radius-lg: 14px;
        --eq-shadow: 0 1px 3px rgba(0,0,0,0.06);
        --eq-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --eq-bg-body: #F0F4F8;
        --eq-bg-card: #FFFFFF;
        --eq-text-primary: #1E293B;
        --eq-text-secondary: #64748B;
        --eq-border-color: #E2E8F0;
    }

    [data-theme="dark"] {
        --eq-bg-body: #0F172A;
        --eq-bg-card: #1E293B;
        --eq-text-primary: #F1F5F9;
        --eq-text-secondary: #94A3B8;
        --eq-border-color: #334155;
        --eq-primary-bg: #1E3A5F;
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
        background: var(--eq-primary-gradient);
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
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-custom .page-title i {
        font-size: 2rem;
        opacity: 0.9;
    }

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

    .page-header-custom .page-subtitle strong {
        color: white;
        font-weight: 600;
    }

    .role-badge-display {
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

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-custom {
        background: var(--eq-bg-card);
        border-radius: var(--eq-radius-lg);
        padding: 20px 24px;
        border: 1px solid var(--eq-border-color);
        transition: all 0.3s ease;
        box-shadow: var(--eq-shadow);
        margin-bottom: 24px;
    }

    .card-custom:hover {
        border-color: var(--eq-primary);
        box-shadow: var(--eq-shadow-md);
    }

    .card-custom .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--eq-border-color);
    }

    .card-custom .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--eq-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .card-custom .card-title i { color: var(--eq-primary); }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .status-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .status-badge.active { background: var(--eq-success-bg); color: var(--eq-success); }
    .status-badge.inactive { background: var(--eq-danger-bg); color: var(--eq-danger); }

    [data-theme="dark"] .status-badge.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.inactive { background: #3A1A1A; color: #F87171; }

    .stock-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .stock-badge.ok { background: var(--eq-success-bg); color: var(--eq-success); }
    .stock-badge.low { background: var(--eq-warning-bg); color: var(--eq-warning); }
    .stock-badge.out { background: var(--eq-danger-bg); color: var(--eq-danger); }

    [data-theme="dark"] .stock-badge.ok { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stock-badge.low { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .stock-badge.out { background: #3A1A1A; color: #F87171; }

    .expiry-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .expiry-badge.valid { background: var(--eq-success-bg); color: var(--eq-success); }
    .expiry-badge.expiring { background: var(--eq-warning-bg); color: var(--eq-warning); }
    .expiry-badge.expired { background: var(--eq-danger-bg); color: var(--eq-danger); }
    .expiry-badge.no-expiry { background: var(--eq-gray-200); color: var(--eq-gray-500); }

    [data-theme="dark"] .expiry-badge.valid { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .expiry-badge.expiring { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .expiry-badge.expired { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .expiry-badge.no-expiry { background: #334155; color: #94A3B8; }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-group { margin-bottom: 14px; }

    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--eq-text-secondary);
        margin-bottom: 4px;
    }

    .form-label .required { color: var(--eq-danger); margin-left: 2px; }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--eq-border-color);
        border-radius: var(--eq-radius);
        font-size: 0.85rem;
        background: var(--eq-bg-card);
        color: var(--eq-text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-control:focus {
        border-color: var(--eq-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    .form-control:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-control::placeholder {
        color: var(--eq-text-secondary);
        opacity: 0.6;
    }

    select.form-control {
        appearance: auto;
        cursor: pointer;
    }

    [data-theme="dark"] .form-control option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 14px;
    }

    .form-help {
        font-size: 0.65rem;
        color: var(--eq-text-secondary);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .form-help i { font-size: 0.6rem; }

    .price-input {
        font-family: 'Courier New', monospace;
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 20px;
        border-radius: var(--eq-radius);
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-primary-custom {
        background: var(--eq-primary);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
    }
    .btn-primary-custom:hover {
        background: var(--eq-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-success-custom {
        background: var(--eq-success);
        color: white;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
    }
    .btn-success-custom:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        color: white;
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--eq-text-secondary);
        border: 2px solid var(--eq-border-color);
    }
    .btn-outline-custom:hover {
        background: var(--eq-gray-50);
        border-color: var(--eq-primary);
        color: var(--eq-primary);
        transform: translateY(-2px);
    }

    [data-theme="dark"] .btn-outline-custom:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert-custom {
        padding: 14px 20px;
        border-radius: var(--eq-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        border: 1px solid transparent;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-custom.alert-success { background: var(--eq-success-bg); color: var(--eq-success); border-color: var(--eq-success); }
    .alert-custom.alert-error { background: var(--eq-danger-bg); color: var(--eq-danger); border-color: var(--eq-danger); }
    .alert-custom.alert-warning { background: var(--eq-warning-bg); color: var(--eq-warning); border-color: var(--eq-warning); }
    .alert-custom.alert-info { background: var(--eq-primary-bg); color: var(--eq-primary); border-color: var(--eq-primary); }

    [data-theme="dark"] .alert-custom.alert-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
    [data-theme="dark"] .alert-custom.alert-error { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
    [data-theme="dark"] .alert-custom.alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #D97706; }
    [data-theme="dark"] .alert-custom.alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #0B5ED7; }

    /* ================================================================
       BATCH NUMBER
       ================================================================ */
    .batch-number {
        font-family: monospace;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 4px;
        background: var(--eq-primary-bg);
        color: var(--eq-primary);
    }

    [data-theme="dark"] .batch-number {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--eq-text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        color: var(--eq-gray-300);
        display: block;
        margin-bottom: 12px;
    }

    [data-theme="dark"] .empty-state i { color: var(--eq-gray-600); }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-custom {
        padding: 14px 0;
        border-top: 1px solid var(--eq-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--eq-text-secondary);
    }
    .footer-custom .footer-brand { color: var(--eq-primary); font-weight: 600; }

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
    @media (max-width: 1024px) {
        .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 16px 18px; }
        .page-header-custom .page-title { font-size: 1.3rem; }
        .card-custom { padding: 16px; }
    }

    @media (max-width: 480px) {
        .page-header-custom { flex-direction: column; align-items: flex-start !important; }
        .card-custom { padding: 14px 16px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($equipment && empty($error_message)): 
        $stock = getStockStatus($equipment['quantity'], $equipment['reorder_level']);
        $expiry = getExpiryStatus($equipment['expiry_date']);
    ?>
        <!-- ================================================================ -->
        <!-- PAGE HEADER -->
        <!-- ================================================================ -->
        <div class="page-header-custom">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-edit"></i>
                    Edit Equipment
                    <span class="role-badge-display">ADMIN</span>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-hashtag"></i> #<?= $equipment['id'] ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-tools"></i>
                    Editing <strong><?= htmlspecialchars($equipment['equipment_name']) ?></strong>
                    <span class="header-badge">
                        <i class="fas fa-boxes"></i> <?= number_format($equipment['quantity']) ?> in stock
                    </span>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-barcode"></i> <?= htmlspecialchars($equipment['batch_number']) ?>
                    </span>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
                <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- MESSAGE -->
        <!-- ================================================================ -->
        <?php if ($message): ?>
            <div class="alert-custom alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- EDIT FORM -->
        <!-- ================================================================ -->
        <div class="card-custom animate-fade-in-up">
            <div class="card-header-custom">
                <h3 class="card-title">
                    <i class="fas fa-pen"></i> Edit Equipment Details
                </h3>
                <div>
                    <span class="status-badge <?= $equipment['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= ucfirst($equipment['status'] ?? 'active') ?>
                    </span>
                    <span class="stock-badge <?= $stock['class'] ?>" style="margin-left:8px;">
                        <i class="fas <?= $stock['class'] === 'ok' ? 'fa-check-circle' : ($stock['class'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                        <?= $stock['label'] ?>
                    </span>
                </div>
            </div>
            
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="update_equipment">
                <input type="hidden" name="equipment_id" value="<?= $equipment['id'] ?>">
                
                <!-- Branch -->
                <div class="form-group">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <option value="">-- Select Branch --</option>
                        <option value="all" <?= $equipment['branch_id'] === null ? 'selected' : '' ?>>🌐 All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $equipment['branch_id'] == $b['id'] ? 'selected' : '' ?>>
                                🏥 <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Select "All Branches" if this equipment is available in all branches
                    </div>
                </div>
                
                <!-- Equipment Name -->
                <div class="form-group">
                    <label class="form-label">Equipment Name <span class="required">*</span></label>
                    <input type="text" name="equipment_name" class="form-control" value="<?= htmlspecialchars($equipment['equipment_name']) ?>" required>
                </div>
                
                <!-- Category -->
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" id="categorySelect">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $equipment['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="0">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control" style="margin-top:6px;display:none;" id="categoryManual" placeholder="Enter custom category..." value="<?= htmlspecialchars($equipment['category'] ?? '') ?>">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Select existing category or type a new one
                    </div>
                </div>
                
                <!-- Unit & Quantity -->
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Unit <span class="required">*</span></label>
                        <select name="unit" class="form-control">
                            <option value="pcs" <?= $equipment['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                            <option value="box" <?= $equipment['unit'] === 'box' ? 'selected' : '' ?>>Box</option>
                            <option value="pack" <?= $equipment['unit'] === 'pack' ? 'selected' : '' ?>>Pack</option>
                            <option value="set" <?= $equipment['unit'] === 'set' ? 'selected' : '' ?>>Set</option>
                            <option value="each" <?= $equipment['unit'] === 'each' ? 'selected' : '' ?>>Each</option>
                            <option value="roll" <?= $equipment['unit'] === 'roll' ? 'selected' : '' ?>>Roll</option>
                            <option value="bottle" <?= $equipment['unit'] === 'bottle' ? 'selected' : '' ?>>Bottle</option>
                            <option value="pair" <?= $equipment['unit'] === 'pair' ? 'selected' : '' ?>>Pair</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" required min="0" value="<?= $equipment['quantity'] ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control" required min="0" value="<?= $equipment['reorder_level'] ?>">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Alert when stock falls below this number
                        </div>
                    </div>
                </div>
                
                <!-- Price & Supplier -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Selling Price (TSh)</label>
                        <input type="text" name="selling_price" class="form-control price-input" value="<?= number_format($equipment['selling_price'] ?? 0, 0) ?>" oninput="formatPriceInput(this)">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Leave 0 for FREE equipment
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($equipment['supplier'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- Batch Number (Read Only) -->
                <div class="form-group">
                    <label class="form-label">Batch Number</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($equipment['batch_number']) ?>" disabled>
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Batch number cannot be changed
                    </div>
                </div>
                
                <!-- Expiry Date & Status -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= $equipment['expiry_date'] ?? '' ?>">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Leave empty for no expiry (Active Forever)
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $equipment['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $equipment['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Inactive equipment will not appear in lab test equipment lists
                        </div>
                    </div>
                </div>
                
                <!-- Current Status Info -->
                <div style="background:var(--eq-bg-body);border-radius:var(--eq-radius);padding:12px 16px;margin-top:8px;display:flex;flex-wrap:wrap;gap:16px;border:1px solid var(--eq-border-color);">
                    <div>
                        <span style="font-size:0.6rem;color:var(--eq-text-secondary);text-transform:uppercase;font-weight:600;">Current Stock</span>
                        <div style="font-weight:700;font-size:1.1rem;color:var(--eq-text-primary);"><?= number_format($equipment['quantity']) ?> <?= $equipment['unit'] ?></div>
                    </div>
                    <div>
                        <span style="font-size:0.6rem;color:var(--eq-text-secondary);text-transform:uppercase;font-weight:600;">Stock Status</span>
                        <div><span class="stock-badge <?= $stock['class'] ?>"><?= $stock['label'] ?></span></div>
                    </div>
                    <div>
                        <span style="font-size:0.6rem;color:var(--eq-text-secondary);text-transform:uppercase;font-weight:600;">Expiry</span>
                        <div><span class="expiry-badge <?= $expiry['class'] ?>"><?= $expiry['label'] ?></span></div>
                    </div>
                    <div>
                        <span style="font-size:0.6rem;color:var(--eq-text-secondary);text-transform:uppercase;font-weight:600;">Linked Lab Tests</span>
                        <div style="font-weight:700;font-size:1.1rem;color:var(--eq-text-primary);">
                            <?php 
                                $stmt = $db->prepare("SELECT COUNT(*) FROM lab_test_equipment WHERE equipment_id = ?");
                                $stmt->execute([$equipment['id']]);
                                $linked_count = $stmt->fetchColumn();
                                echo $linked_count;
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div style="display:flex;gap:10px;margin-top:20px;border-top:2px solid var(--eq-border-color);padding-top:16px;flex-wrap:wrap;">
                    <button type="submit" class="btn-custom btn-success-custom">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-custom btn-outline-custom">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-custom btn-outline-custom">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- ================================================================ -->
        <!-- EQUIPMENT NOT FOUND -->
        <!-- ================================================================ -->
        <div class="page-header-custom" style="background:linear-gradient(135deg, #DC2626, #B91C1C);">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Equipment Not Found
                    <span class="role-badge-display">ERROR</span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-tools"></i>
                    The equipment you are trying to edit could not be found.
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        
        <div class="card-custom">
            <div class="empty-state">
                <i class="fas fa-tools"></i>
                <h3 style="font-size:1.2rem;color:var(--eq-text-primary);margin-bottom:8px;">Equipment Not Found</h3>
                <p style="font-size:0.9rem;">The equipment with ID #<?= $equipment_id ?> could not be found in the system.</p>
                <p style="font-size:0.8rem;color:var(--eq-text-secondary);margin-top:4px;">It may have been deleted or the ID may be incorrect.</p>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-custom btn-primary-custom" style="margin-top:16px;">
                    <i class="fas fa-arrow-left"></i> Back to Equipment List
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-custom">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Equipment
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
    // FORMAT PRICE INPUT
    // ================================================================
    function formatPriceInput(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') {
            input.value = '';
            return;
        }
        var formatted = parseInt(raw).toLocaleString('en-US');
        input.value = formatted;
    }

    // ================================================================
    // CATEGORY - Toggle manual input
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var categorySelect = document.getElementById('categorySelect');
        var categoryManual = document.getElementById('categoryManual');
        
        if (categorySelect && categoryManual) {
            // Check if current value is custom (not in dropdown)
            var isCustom = true;
            var options = categorySelect.options;
            for (var i = 0; i < options.length; i++) {
                if (options[i].text === categoryManual.value) {
                    isCustom = false;
                    break;
                }
            }
            
            if (isCustom && categoryManual.value !== '') {
                categorySelect.value = '0';
                categoryManual.style.display = 'block';
            }
            
            categorySelect.addEventListener('change', function() {
                if (this.value === '0') {
                    categoryManual.style.display = 'block';
                    categoryManual.required = true;
                    categoryManual.focus();
                } else {
                    categoryManual.style.display = 'none';
                    categoryManual.required = false;
                    categoryManual.value = '';
                }
            });
        }
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

    console.log('%c🔧 Braick Dispensary - Edit Equipment', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    <?php if ($equipment): ?>
        console.log('%c📦 Editing: <?= htmlspecialchars($equipment['equipment_name']) ?> (ID: <?= $equipment['id'] ?>)', 'font-size:13px; color:#0B5ED7;');
        console.log('%c📊 Quantity: <?= $equipment['quantity'] ?> | Batch: <?= htmlspecialchars($equipment['batch_number']) ?>', 'font-size:13px; color:#D97706;');
    <?php else: ?>
        console.log('%c❌ Equipment not found (ID: <?= $equipment_id ?>)', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>