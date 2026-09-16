<?php
// ================================================================
// FILE: frontend/pages/admin/edit_laboratory.php
// ADMIN - EDIT LABORATORY BRANCH
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
// GET PARAMETERS
// ================================================================
$lab_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($lab_id <= 0) {
    header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH LABORATORY DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as active_technicians,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory') as total_technicians,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'inactive') as inactive_technicians
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$lab_id]);
    $lab = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab) {
        header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching laboratory: " . $e->getMessage());
    header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches_list = [];
}

// ================================================================
// PROCESS FORM
// ================================================================
$message = '';
$message_type = '';
$update_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_laboratory') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $logo = trim($_POST['logo'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    if (empty($name)) $errors[] = "Laboratory name is required";
    if (empty($location)) $errors[] = "Location is required";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }
    
    if (!empty($name)) {
        try {
            $stmt = $db->prepare("SELECT id FROM branches WHERE name = ? AND id != ?");
            $stmt->execute([$name, $lab_id]);
            if ($stmt->fetch()) $errors[] = "A branch with this name already exists";
        } catch (Exception $e) {}
    }
    
    if (!empty($email)) {
        try {
            $stmt = $db->prepare("SELECT id FROM branches WHERE email = ? AND id != ?");
            $stmt->execute([$email, $lab_id]);
            if ($stmt->fetch()) $errors[] = "A branch with this email already exists";
        } catch (Exception $e) {}
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE branches 
                SET name = ?, location = ?, phone = ?, email = ?, logo = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $location, $phone, $email, $logo, $status, $lab_id]);
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'laboratory_updated', ?, NOW())
                ");
                $details = "Laboratory branch updated: {$name} (ID: {$lab_id}) by " . $user_full_name;
                $stmt->execute([$user_id, $lab_id, $details]);
            } catch (Exception $e) {}
            
            $update_success = true;
            $message = "✅ Laboratory updated successfully!";
            $message_type = 'success';
            
            // Refresh
            $stmt = $db->prepare("
                SELECT 
                    b.*,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as active_technicians,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory') as total_technicians,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'inactive') as inactive_technicians
                FROM branches b
                WHERE b.id = ?
            ");
            $stmt->execute([$lab_id]);
            $lab = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "laboratories.php?branch=' . $selected_branch_id . '&updated=1"; 
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Error updating laboratory: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
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
    .page-header-lab {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-lab::before {
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

    .page-header-lab .page-title {
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

    .page-header-lab .page-subtitle {
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

    .page-header-lab .role-badge-display {
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

    .page-header-lab .header-badge {
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

    .page-header-lab .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-lab .btn-outline-light {
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

    .page-header-lab .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-lab {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 32px 36px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        max-width: 800px;
        margin: 0 auto 20px;
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .form-card-lab:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-lg, 0 10px 25px rgba(0,0,0,0.1));
    }

    .form-header-lab {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    .form-header-lab .form-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        flex-shrink: 0;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
    }

    .form-header-lab .form-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .form-header-lab .form-subtitle {
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
    }

    /* ================================================================
       FORM LABELS & CONTROLS
       ================================================================ */
    .form-label-lab {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
        display: block;
    }

    .form-label-lab .required { color: #DC2626; margin-left: 2px; }
    .form-label-lab .label-icon { margin-right: 4px; color: var(--page-primary, #0B5ED7); }

    .form-control-lab {
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

    .form-control-lab:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    .form-control-lab.is-invalid { border-color: #DC2626; }
    .form-control-lab.is-valid { border-color: #059669; }

    .form-control-lab::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    select.form-control-lab { appearance: auto; cursor: pointer; }

    .form-row-lab { margin-bottom: 20px; }
    .form-row-lab:last-child { margin-bottom: 0; }

    .help-text-lab {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
        display: block;
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert-lab {
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border: 2px solid transparent;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDownLab 0.4s ease;
    }

    @keyframes slideDownLab {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success-lab {
        background: #D1FAE5;
        color: #065F46;
        border-color: #34D399;
    }

    .alert-danger-lab {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #F87171;
    }

    [data-theme="dark"] .alert-success-lab {
        background: #1A3A2A;
        color: #34D399;
        border-color: #059669;
    }

    [data-theme="dark"] .alert-danger-lab {
        background: #3A1A1A;
        color: #F87171;
        border-color: #DC2626;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-lab {
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

    .btn-lab:hover { transform: translateY(-2px); }

    .btn-primary-lab {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary-lab:hover {
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-outline-lab {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-lab:hover {
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    [data-theme="dark"] .btn-outline-lab {
        color: #94A3B8;
        border-color: #334155;
    }

    [data-theme="dark"] .btn-outline-lab:hover {
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .btn-danger-lab {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    .btn-danger-lab:hover {
        box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        color: white;
    }

    .form-actions-lab {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid-lab {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-top: 8px;
    }

    .stat-card-lab {
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .stat-card-lab:hover {
        transform: translateY(-3px);
    }

    .stat-card-lab.blue {
        background: #EFF6FF;
        border-color: #BFDBFE;
    }

    .stat-card-lab.green {
        background: #D1FAE5;
        border-color: #6EE7B7;
    }

    .stat-card-lab.red {
        background: #FEE2E2;
        border-color: #FCA5A5;
    }

    [data-theme="dark"] .stat-card-lab.blue {
        background: #1E3A5F;
        border-color: #3B82F6;
    }

    [data-theme="dark"] .stat-card-lab.green {
        background: #1A3A2A;
        border-color: #34D399;
    }

    [data-theme="dark"] .stat-card-lab.red {
        background: #3A1A1A;
        border-color: #F87171;
    }

    .stat-card-lab .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .stat-card-lab.blue .stat-number { color: #0B5ED7; }
    .stat-card-lab.green .stat-number { color: #059669; }
    .stat-card-lab.red .stat-number { color: #DC2626; }

    .stat-card-lab .stat-label {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        margin: 0;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-lab {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-lab .footer-brand-lab {
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-lab { padding: 18px 20px; }
        .page-header-lab .page-title { font-size: 1.3rem; }
        .form-card-lab { padding: 20px 18px; }
        .form-actions-lab { flex-direction: column; }
        .form-actions-lab .btn-lab { width: 100%; justify-content: center; }
        .stats-grid-lab { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .page-header-lab { flex-direction: column; align-items: flex-start !important; }
        .stat-card-lab .stat-number { font-size: 1.3rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-lab">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Edit Laboratory
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-microscope"></i>
                <strong><?= htmlspecialchars($lab['name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= ($lab['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($lab['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user-md"></i>
                    <?= ($lab['active_technicians'] ?? 0) ?> Active Technicians
                </span>
                <span class="header-badge" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                    <i class="fas fa-user-slash"></i>
                    <?= ($lab['inactive_technicians'] ?? 0) ?> Inactive
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_laboratory.php?id=<?= $lab_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="laboratories.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="alert-lab alert-<?= $message_type === 'success' ? 'success-lab' : 'danger-lab' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM CARD -->
    <!-- ================================================================ -->
    <div class="form-card-lab">
        <div class="form-header-lab">
            <div class="form-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div>
                <h3 class="form-title">Edit Laboratory Details</h3>
                <p class="form-subtitle">Update laboratory branch information</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update_laboratory">
            
            <!-- Laboratory Name -->
            <div class="form-row-lab">
                <label class="form-label-lab">
                    <i class="fas fa-flask label-icon"></i> Laboratory Name <span class="required">*</span>
                </label>
                <input type="text" name="name" class="form-control-lab" 
                       value="<?= htmlspecialchars($lab['name'] ?? '') ?>" 
                       placeholder="e.g. Dodoma Laboratory"
                       required>
            </div>
            
            <!-- Location -->
            <div class="form-row-lab">
                <label class="form-label-lab">
                    <i class="fas fa-map-marker-alt label-icon"></i> Location <span class="required">*</span>
                </label>
                <input type="text" name="location" class="form-control-lab" 
                       value="<?= htmlspecialchars($lab['location'] ?? '') ?>" 
                       placeholder="e.g. Dodoma City, Tanzania"
                       required>
            </div>
            
            <!-- Phone -->
            <div class="form-row-lab">
                <label class="form-label-lab">
                    <i class="fas fa-phone label-icon"></i> Phone Number
                </label>
                <input type="text" name="phone" class="form-control-lab" 
                       value="<?= htmlspecialchars($lab['phone'] ?? '') ?>" 
                       placeholder="e.g. +255 700 000 001">
            </div>
            
            <!-- Email -->
            <div class="form-row-lab">
                <label class="form-label-lab">
                    <i class="fas fa-envelope label-icon"></i> Email Address
                </label>
                <input type="email" name="email" class="form-control-lab" 
                       value="<?= htmlspecialchars($lab['email'] ?? '') ?>" 
                       placeholder="e.g. lab@braick.com">
            </div>
            
            <!-- Logo URL -->
            <div class="form-row-lab">
                <label class="form-label-lab">
                    <i class="fas fa-image label-icon"></i> Logo URL
                </label>
                <input type="text" name="logo" class="form-control-lab" 
                       value="<?= htmlspecialchars($lab['logo'] ?? '') ?>" 
                       placeholder="e.g. /path/to/logo.png">
                <span class="help-text-lab">Optional - URL to laboratory logo</span>
            </div>
            
            <!-- Status -->
            <div class="form-row-lab">
                <label class="form-label-lab">
                    <i class="fas fa-toggle-on label-icon"></i> Status <span class="required">*</span>
                </label>
                <select name="status" class="form-control-lab" required>
                    <option value="active" <?= ($lab['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                        ✅ Active
                    </option>
                    <option value="inactive" <?= ($lab['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>
                        ❌ Inactive
                    </option>
                </select>
                <span class="help-text-lab">Active laboratories can receive and process lab tests</span>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions-lab">
                <button type="submit" class="btn-lab btn-primary-lab">
                    <i class="fas fa-save"></i> Update Laboratory
                </button>
                <a href="view_laboratory.php?id=<?= $lab_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-lab btn-outline-lab">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="button" class="btn-lab btn-danger-lab" onclick="confirmDelete()" style="margin-left:auto;">
                    <i class="fas fa-trash"></i> Delete Laboratory
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- LABORATORY STATISTICS -->
    <!-- ================================================================ -->
    <div class="form-card-lab">
        <h3 style="font-size:1rem;font-weight:600;color:var(--page-primary,#0B5ED7);margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-chart-bar"></i> Laboratory Statistics
        </h3>
        <div class="stats-grid-lab">
            <div class="stat-card-lab blue">
                <p class="stat-number"><?= number_format($lab['total_technicians'] ?? 0) ?></p>
                <p class="stat-label">Total Technicians</p>
            </div>
            <div class="stat-card-lab green">
                <p class="stat-number"><?= number_format($lab['active_technicians'] ?? 0) ?></p>
                <p class="stat-label">Active Technicians</p>
            </div>
            <div class="stat-card-lab red">
                <p class="stat-number"><?= number_format($lab['inactive_technicians'] ?? 0) ?></p>
                <p class="stat-label">Inactive Technicians</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-lab">
        <p>
            <span class="footer-brand-lab">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Laboratory - <?= htmlspecialchars($lab['name'] ?? 'N/A') ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar, NO date-time) -->
<!-- ================================================================ -->
<script>
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

    // ================================================================
    // ✅ CONFIRM DELETE
    // ================================================================
    function confirmDelete() {
        var confirmed = confirm(
            '⚠️ Are you sure you want to delete this laboratory?\n\n' +
            'Laboratory: <?= htmlspecialchars($lab['name'] ?? 'N/A') ?>\n' +
            'ID: #<?= $lab_id ?>\n\n' +
            'This action cannot be undone. All associated data will be affected.'
        );
        
        if (confirmed) {
            window.location.href = 'delete_laboratory.php?id=<?= $lab_id ?>&branch=<?= urlencode($selected_branch_id) ?>&confirm=yes';
        }
    }

    // ================================================================
    // ✅ FORM VALIDATION
    // ================================================================
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="name"]').value.trim();
        var location = document.querySelector('input[name="location"]').value.trim();
        var email = document.querySelector('input[name="email"]').value.trim();
        var isValid = true;
        
        document.querySelectorAll('.form-control-lab').forEach(function(el) {
            el.classList.remove('is-invalid');
            el.classList.remove('is-valid');
        });
        
        if (!name) {
            document.querySelector('input[name="name"]').classList.add('is-invalid');
            isValid = false;
        } else {
            document.querySelector('input[name="name"]').classList.add('is-valid');
        }
        
        if (!location) {
            document.querySelector('input[name="location"]').classList.add('is-invalid');
            isValid = false;
        } else {
            document.querySelector('input[name="location"]').classList.add('is-valid');
        }
        
        if (email && !isValidEmail(email)) {
            document.querySelector('input[name="email"]').classList.add('is-invalid');
            isValid = false;
        } else if (email) {
            document.querySelector('input[name="email"]').classList.add('is-valid');
        }
        
        if (!isValid) {
            e.preventDefault();
            var firstError = document.querySelector('.is-invalid');
            if (firstError) firstError.focus();
            showToast('⚠️ Validation Error', 'Please fill in all required fields correctly', 'warning');
        }
    });
    
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // ================================================================
    // ✅ REAL-TIME VALIDATION
    // ================================================================
    document.querySelector('input[name="name"]')?.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
    
    document.querySelector('input[name="location"]')?.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
    
    document.querySelector('input[name="email"]')?.addEventListener('blur', function() {
        var val = this.value.trim();
        if (val && !isValidEmail(val)) {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        } else if (val) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.remove('is-valid');
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
            animation: slideInLab 0.4s ease; font-size: 0.85rem;
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
            toast.style.animation = 'slideOutLab 0.4s ease';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3500);
    }

    if (!document.getElementById('toastAnimationsLab')) {
        var style = document.createElement('style');
        style.id = 'toastAnimationsLab';
        style.textContent = `
            @keyframes slideInLab { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutLab { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
        `;
        document.head.appendChild(style);
    }

    console.log('%c🧪 Braick - Edit Laboratory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔬 Laboratory: <?= htmlspecialchars($lab['name'] ?? 'N/A') ?> (ID: <?= $lab_id ?>)', 'font-size:13px; color:#059669;');
</script>

</body>
</html>