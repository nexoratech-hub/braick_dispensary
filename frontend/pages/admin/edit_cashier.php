<?php
// ================================================================
// FILE: frontend/pages/admin/edit_cashier.php
// SUPER ADMIN - EDIT CASHIER BRANCH
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
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
// GET CASHIER ID
// ================================================================
$cashier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($cashier_id <= 0) {
    header('Location: cashiers.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH CASHIER DETAILS
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$cashier_id]);
$cashier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cashier) {
    header('Location: cashiers.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET BRANCH STAFF COUNT
// ================================================================
$staff_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role = 'cashier' AND status = 'active'");
    $stmt->execute([$cashier_id]);
    $staff_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $staff_count = 0;
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cashier') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $logo = trim($_POST['logo'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    
    if (empty($name)) $errors[] = 'Cashier name is required';
    if (empty($location)) $errors[] = 'Location is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (!empty($name)) {
        try {
            $stmt = $db->prepare("SELECT id FROM branches WHERE name = ? AND id != ?");
            $stmt->execute([$name, $cashier_id]);
            if ($stmt->fetch()) $errors[] = 'A branch with this name already exists';
        } catch (Exception $e) {}
    }
    
    if (!empty($email)) {
        try {
            $stmt = $db->prepare("SELECT id FROM branches WHERE email = ? AND id != ?");
            $stmt->execute([$email, $cashier_id]);
            if ($stmt->fetch()) $errors[] = 'A branch with this email already exists';
        } catch (Exception $e) {}
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE branches 
                SET name = ?, location = ?, phone = ?, email = ?, logo = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $location, $phone, $email, $logo, $status, $cashier_id]);
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'cashier_updated', ?, NOW())
                ");
                $details = "Cashier updated: " . $name . " (ID: " . $cashier_id . ") by " . $user_full_name;
                $stmt->execute([$user_id, $cashier_id, $details]);
            } catch (Exception $e) {}
            
            $message = '✅ Cashier updated successfully!';
            $message_type = 'success';
            
            // Refresh data
            $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
            $stmt->execute([$cashier_id]);
            $cashier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "cashiers.php?branch=' . $selected_branch_id . '&updated=1"; 
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            $message = '❌ Error updating cashier: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-cashier {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-cashier::before {
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

    .page-header-cashier .page-title {
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

    .page-header-cashier .page-subtitle {
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

    .page-header-cashier .role-badge-display {
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

    .page-header-cashier .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .page-header-cashier .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-cashier .btn-outline-light {
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

    .page-header-cashier .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-cashier {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        transition: all 0.3s ease;
        max-width: 800px;
        margin: 0 auto 20px;
    }

    .form-card-cashier:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .form-card-header-cashier {
        padding: 20px 28px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .form-card-header-cashier .form-title-cashier {
        color: white;
        font-size: 1.05rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-card-header-cashier .form-title-cashier i {
        color: rgba(255,255,255,0.8);
    }

    .form-card-body-cashier {
        padding: 28px;
        background: var(--page-bg-card, #FFFFFF);
    }

    /* ================================================================
       FORM GROUP
       ================================================================ */
    .form-group-cashier {
        margin-bottom: 20px;
    }

    .form-group-cashier label {
        display: block;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
    }

    .form-group-cashier label .required {
        color: #DC2626;
        margin-left: 2px;
    }

    .form-group-cashier label .label-icon {
        color: var(--page-primary, #0B5ED7);
        margin-right: 4px;
    }

    .form-control-cashier {
        width: 100%;
        padding: 10px 14px;
        font-size: 0.9rem;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        transition: all 0.3s ease;
        outline: none;
        font-family: inherit;
    }

    .form-control-cashier:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .form-control-cashier::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    .form-control-cashier.error {
        border-color: #DC2626;
        box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
    }

    .form-help-cashier {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    /* ================================================================
       STATUS TOGGLE
       ================================================================ */
    .status-toggle-cashier {
        display: inline-flex;
        gap: 10px;
        padding: 4px;
        background: var(--page-hover, #F8FAFC);
        border-radius: 12px;
        border: 2px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .status-toggle-cashier {
        background: #0F172A;
        border-color: #334155;
    }

    .status-toggle-cashier .status-option {
        padding: 8px 20px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: inherit;
    }

    .status-toggle-cashier .status-option:hover {
        background: var(--page-bg-card, #FFFFFF);
    }

    [data-theme="dark"] .status-toggle-cashier .status-option:hover {
        background: #1E293B;
    }

    .status-toggle-cashier .status-option.active-success {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .status-toggle-cashier .status-option.active-danger {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-cashier {
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
        font-family: inherit;
    }

    .btn-primary-cashier {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary-cashier:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-outline-cashier {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-cashier:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
        transform: translateY(-2px);
    }

    [data-theme="dark"] .btn-outline-cashier {
        color: #94A3B8;
        border-color: #334155;
    }

    [data-theme="dark"] .btn-outline-cashier:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .btn-danger-cashier {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    .btn-danger-cashier:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35);
        color: white;
    }

    .form-actions-cashier {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 8px;
        padding-top: 20px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-cashier {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 600;
        color: white;
    }

    .badge-success-cashier { background: #059669; }
    .badge-danger-cashier { background: #DC2626; }
    .badge-warning-cashier { background: #D97706; color: #1E293B; }
    .badge-info-cashier { background: #0B5ED7; }

    [data-theme="dark"] .badge-warning-cashier { color: #1E293B; }

    /* ================================================================
       INFO CARD
       ================================================================ */
    .info-card-cashier {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        max-width: 800px;
        margin: 0 auto 20px;
    }

    .info-card-header-cashier {
        padding: 16px 28px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    [data-theme="dark"] .info-card-header-cashier {
        background: #0F172A;
    }

    .info-card-header-cashier .form-title-cashier {
        color: var(--page-text-primary, #1E293B);
        font-size: 0.95rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-card-body-cashier {
        padding: 20px 28px;
    }

    .info-grid-cashier {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .info-item-cashier .info-label-cashier {
        font-size: 0.65rem;
        font-weight: 500;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }

    .info-item-cashier .info-value-cashier {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    .info-item-cashier .info-value-cashier.primary {
        color: var(--page-primary, #0B5ED7);
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert-cashier {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 2px solid;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDownCashier 0.4s ease;
    }

    @keyframes slideDownCashier {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success-cashier {
        background: #D1FAE5;
        border-color: #34D399;
        color: #065F46;
    }

    .alert-error-cashier {
        background: #FEE2E2;
        border-color: #F87171;
        color: #991B1B;
    }

    [data-theme="dark"] .alert-success-cashier {
        background: #1A3A2A;
        border-color: #059669;
        color: #34D399;
    }

    [data-theme="dark"] .alert-error-cashier {
        background: #3A1A1A;
        border-color: #DC2626;
        color: #F87171;
    }

    .alert-cashier i {
        font-size: 1.2rem;
        margin-top: 2px;
        flex-shrink: 0;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-cashier {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }

    .footer-cashier .footer-brand-cashier {
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-cashier { padding: 18px 20px; }
        .page-header-cashier .page-title { font-size: 1.3rem; }
        .form-card-body-cashier { padding: 20px; }
        .form-card-header-cashier { padding: 16px 20px; }
        .form-actions-cashier { flex-direction: column; }
        .form-actions-cashier .btn-cashier { width: 100%; justify-content: center; }
        .status-toggle-cashier { width: 100%; }
        .status-toggle-cashier .status-option { flex: 1; text-align: center; justify-content: center; }
        .info-grid-cashier { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .page-header-cashier { flex-direction: column; align-items: flex-start !important; }
        .form-card-cashier { margin: 0 0 20px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-cashier">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Cashier
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-cash-register"></i>
                Editing: <strong><?= htmlspecialchars($cashier['name']) ?></strong>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-id-badge"></i> ID: #<?= $cashier['id'] ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-users"></i> <?= $staff_count ?> Staff
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_cashier.php?id=<?= $cashier['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="cashiers.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if (!empty($message)): ?>
        <div class="alert-cashier alert-<?= $message_type === 'success' ? 'success-cashier' : 'error-cashier' ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM CARD -->
    <!-- ================================================================ -->
    <div class="form-card-cashier">
        <div class="form-card-header-cashier">
            <span class="form-title-cashier">
                <i class="fas fa-pen"></i>
                Edit Cashier Details
            </span>
            <span class="badge-cashier badge-<?= $cashier['status'] === 'active' ? 'success-cashier' : 'danger-cashier' ?>">
                Current: <?= ucfirst($cashier['status']) ?>
            </span>
        </div>
        
        <div class="form-card-body-cashier">
            <form method="POST" action="" id="editCashierForm">
                <input type="hidden" name="action" value="update_cashier">
                
                <!-- Cashier Name -->
                <div class="form-group-cashier">
                    <label for="name">
                        <i class="fas fa-cash-register label-icon"></i> Cashier Name <span class="required">*</span>
                    </label>
                    <input type="text" id="name" name="name" class="form-control-cashier" 
                           placeholder="Enter cashier name" 
                           value="<?= htmlspecialchars($cashier['name'] ?? '') ?>" required>
                    <div class="form-help-cashier">The official name of the cashier branch.</div>
                </div>
                
                <!-- Location -->
                <div class="form-group-cashier">
                    <label for="location">
                        <i class="fas fa-map-marker-alt label-icon"></i> Location <span class="required">*</span>
                    </label>
                    <input type="text" id="location" name="location" class="form-control-cashier" 
                           placeholder="Enter location (e.g., Dodoma City, Tanzania)" 
                           value="<?= htmlspecialchars($cashier['location'] ?? '') ?>" required>
                    <div class="form-help-cashier">Physical address of the cashier branch.</div>
                </div>
                
                <!-- Phone -->
                <div class="form-group-cashier">
                    <label for="phone">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                    </label>
                    <input type="tel" id="phone" name="phone" class="form-control-cashier" 
                           placeholder="Enter phone number (e.g., +255 700 000 001)" 
                           value="<?= htmlspecialchars($cashier['phone'] ?? '') ?>">
                    <div class="form-help-cashier">Contact phone number for the cashier.</div>
                </div>
                
                <!-- Email -->
                <div class="form-group-cashier">
                    <label for="email">
                        <i class="fas fa-envelope label-icon"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control-cashier" 
                           placeholder="Enter email address" 
                           value="<?= htmlspecialchars($cashier['email'] ?? '') ?>">
                    <div class="form-help-cashier">Official email address for the cashier.</div>
                </div>
                
                <!-- Logo URL -->
                <div class="form-group-cashier">
                    <label for="logo">
                        <i class="fas fa-image label-icon"></i> Logo URL
                    </label>
                    <input type="text" id="logo" name="logo" class="form-control-cashier" 
                           placeholder="Enter logo image URL" 
                           value="<?= htmlspecialchars($cashier['logo'] ?? '') ?>">
                    <div class="form-help-cashier">Optional - URL to cashier logo image.</div>
                </div>
                
                <!-- Status -->
                <div class="form-group-cashier">
                    <label>
                        <i class="fas fa-toggle-on label-icon"></i> Status <span class="required">*</span>
                    </label>
                    <div class="status-toggle-cashier" id="statusToggle">
                        <button type="button" class="status-option <?= ($cashier['status'] ?? 'active') === 'active' ? 'active-success' : '' ?>" 
                                data-value="active" onclick="selectStatus('active')">
                            <i class="fas fa-check-circle"></i> Active
                        </button>
                        <button type="button" class="status-option <?= ($cashier['status'] ?? 'active') === 'inactive' ? 'active-danger' : '' ?>" 
                                data-value="inactive" onclick="selectStatus('inactive')">
                            <i class="fas fa-times-circle"></i> Inactive
                        </button>
                    </div>
                    <input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($cashier['status'] ?? 'active') ?>">
                    <div class="form-help-cashier">Set the cashier branch as active or inactive.</div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions-cashier">
                    <button type="submit" class="btn-cashier btn-primary-cashier">
                        <i class="fas fa-save"></i> Update Cashier
                    </button>
                    <a href="cashiers.php?branch=<?= $selected_branch_id ?>" class="btn-cashier btn-outline-cashier">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="button" class="btn-cashier btn-danger-cashier" onclick="confirmDelete()" style="margin-left:auto;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
                
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CASHIER INFO CARD -->
    <!-- ================================================================ -->
    <div class="info-card-cashier">
        <div class="info-card-header-cashier">
            <span class="form-title-cashier">
                <i class="fas fa-info-circle" style="color:var(--page-primary);"></i>
                Additional Information
            </span>
        </div>
        <div class="info-card-body-cashier">
            <div class="info-grid-cashier">
                <div class="info-item-cashier">
                    <p class="info-label-cashier">Branch ID</p>
                    <p class="info-value-cashier primary">#<?= $cashier['id'] ?></p>
                </div>
                <div class="info-item-cashier">
                    <p class="info-label-cashier">Created</p>
                    <p class="info-value-cashier"><?= date('M d, Y h:i A', strtotime($cashier['created_at'] ?? 'now')) ?></p>
                </div>
                <div class="info-item-cashier">
                    <p class="info-label-cashier">Last Updated</p>
                    <p class="info-value-cashier"><?= date('M d, Y h:i A', strtotime($cashier['updated_at'] ?? 'now')) ?></p>
                </div>
                <div class="info-item-cashier">
                    <p class="info-label-cashier">Status</p>
                    <p>
                        <span class="badge-cashier badge-<?= $cashier['status'] === 'active' ? 'success-cashier' : 'danger-cashier' ?>">
                            <?= ucfirst($cashier['status']) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-cashier">
        <p>
            <span class="footer-brand-cashier">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Cashier - <?= htmlspecialchars($cashier['name']) ?>
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
    // ✅ FOOTER TIME
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
    // ✅ STATUS TOGGLE
    // ================================================================
    function selectStatus(value) {
        var statusInput = document.getElementById('statusInput');
        var options = document.querySelectorAll('.status-toggle-cashier .status-option');
        
        options.forEach(function(opt) {
            opt.classList.remove('active-success', 'active-danger');
            if (opt.getAttribute('data-value') === value) {
                if (value === 'active') {
                    opt.classList.add('active-success');
                } else {
                    opt.classList.add('active-danger');
                }
            }
        });
        
        statusInput.value = value;
    }

    // ================================================================
    // ✅ DELETE CONFIRMATION
    // ================================================================
    function confirmDelete() {
        if (confirm('⚠️ Are you sure you want to delete this cashier branch?\n\nThis action cannot be undone!')) {
            if (confirm('⚠️ WARNING: This will permanently delete all data associated with this cashier including transactions and payment records.\n\nAre you absolutely sure?')) {
                window.location.href = 'delete_cashier.php?id=<?= $cashier['id'] ?>&branch=<?= $selected_branch_id ?>&confirm=yes';
            }
        }
    }

    // ================================================================
    // ✅ FORM VALIDATION
    // ================================================================
    document.getElementById('editCashierForm')?.addEventListener('submit', function(e) {
        var name = document.getElementById('name').value.trim();
        var location = document.getElementById('location').value.trim();
        var email = document.getElementById('email').value.trim();
        var isValid = true;
        
        document.querySelectorAll('.form-control-cashier.error').forEach(function(el) {
            el.classList.remove('error');
        });
        
        if (name.length < 2) {
            document.getElementById('name').classList.add('error');
            isValid = false;
        }
        
        if (location.length < 2) {
            document.getElementById('location').classList.add('error');
            isValid = false;
        }
        
        if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            document.getElementById('email').classList.add('error');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showToast('⚠️ Validation Error', 'Please fix the errors in the form.', 'warning');
            var firstError = document.querySelector('.form-control-cashier.error');
            if (firstError) firstError.focus();
        }
    });

    // ================================================================
    // ✅ TOAST NOTIFICATION
    // ================================================================
    function showToast(title, message, type) {
        var existing = document.getElementById('pageToast');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.id = 'pageToast';
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
            animation: slideInCashier 0.4s ease; font-size: 0.85rem;
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
            toast.style.animation = 'slideOutCashier 0.4s ease';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3500);
    }

    if (!document.getElementById('toastAnimationsCashier')) {
        var style = document.createElement('style');
        style.id = 'toastAnimationsCashier';
        style.textContent = `
            @keyframes slideInCashier { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutCashier { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
        `;
        document.head.appendChild(style);
    }

    console.log('%c💵 Braick - Edit Cashier', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c💵 Cashier: <?= htmlspecialchars($cashier['name']) ?> (ID: <?= $cashier['id'] ?>)', 'font-size:13px; color:#059669;');
</script>

</body>
</html>