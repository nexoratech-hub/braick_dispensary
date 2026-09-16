<?php
// ================================================================
// FILE: frontend/pages/admin/edit_branch.php
// SUPER ADMIN - EDIT BRANCH
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support
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

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($branch_id <= 0) {
    header('Location: branches.php');
    exit;
}

// ================================================================
// GET BRANCH DATA
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php');
    exit;
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// GET BRANCH STAFF COUNT
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$branch_id]);
$staff_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$form_data = [
    'name' => $branch['name'],
    'location' => $branch['location'] ?? '',
    'phone' => $branch['phone'] ?? '',
    'email' => $branch['email'] ?? '',
    'status' => $branch['status'] ?? 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['location'] = trim($_POST['location'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['status'] = $_POST['status'] ?? 'active';
    
    if (empty($form_data['name'])) {
        $message = "Branch name is required!";
        $message_type = 'error';
    } else {
        $stmt = $db->prepare("SELECT id FROM branches WHERE name = ? AND id != ?");
        $stmt->execute([$form_data['name'], $branch_id]);
        if ($stmt->fetch()) {
            $message = "Another branch with this name already exists!";
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("UPDATE branches SET name = ?, location = ?, phone = ?, email = ?, status = ?, updated_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$form_data['name'], $form_data['location'], $form_data['phone'], $form_data['email'], $form_data['status'], $branch_id])) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'branch_updated', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $branch_id,
                        "Branch updated: {$form_data['name']} (ID: $branch_id)"
                    ]);
                } catch (Exception $e) {}
                
                $message = "Branch updated successfully!";
                $message_type = 'success';
                
                $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
                $stmt->execute([$branch_id]);
                $branch = $stmt->fetch(PDO::FETCH_ASSOC);
                $form_data = [
                    'name' => $branch['name'],
                    'location' => $branch['location'] ?? '',
                    'phone' => $branch['phone'] ?? '',
                    'email' => $branch['email'] ?? '',
                    'status' => $branch['status'] ?? 'active'
                ];
                echo '<script>setTimeout(function(){ window.location.href = "branches.php?branch=' . $selected_branch_id . '&updated=1"; }, 1500);</script>';
            } else {
                $message = "Failed to update branch!";
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET CREATED DATE
// ================================================================
$created_date = 'N/A';
if (isset($branch['created_at']) && !empty($branch['created_at'])) {
    $created_date = date('F d, Y', strtotime($branch['created_at']));
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
       PAGE HEADER - BLUE GRADIENT
       ================================================================ */
    .page-header-branch {
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

    .page-header-branch::before {
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

    .page-header-branch .page-title {
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

    .page-header-branch .page-subtitle {
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

    .page-header-branch .header-badge {
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

    .page-header-branch .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-branch .btn-outline-light {
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

    .page-header-branch .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-branch {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
    }

    .form-card-branch:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }

    .form-header-branch {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    .form-header-branch .form-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .form-header-branch h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .form-header-branch p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       FORM LABELS & CONTROLS
       ================================================================ */
    .form-label-branch {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
        display: block;
    }

    .form-label-branch i {
        width: 20px;
        text-align: center;
        font-size: 0.85rem;
    }

    .form-label-branch .required {
        color: #EF4444;
        margin-left: 2px;
    }

    .form-control-branch {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .form-control-branch:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    .form-control-branch::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    .form-control-branch:disabled {
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-secondary, #64748B);
        cursor: not-allowed;
    }

    [data-theme="dark"] .form-control-branch:disabled {
        background: #0F172A;
    }

    select.form-control-branch { appearance: auto; cursor: pointer; }

    /* Form Row with Icon */
    .form-row-icon {
        position: relative;
    }

    .form-row-icon .form-control-branch {
        padding-left: 44px;
    }

    .form-row-icon .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--page-text-secondary, #64748B);
        font-size: 1rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }

    .form-row-icon .form-control-branch:focus ~ .input-icon {
        color: var(--page-primary, #0B5ED7);
    }

    /* ================================================================
       INFO CARD
       ================================================================ */
    .info-card-branch {
        background: var(--page-hover, #F8FAFC);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
    }

    .info-card-branch:hover {
        border-color: var(--page-primary, #0B5ED7);
        transform: translateY(-2px);
    }

    [data-theme="dark"] .info-card-branch {
        background: #0F172A;
        border-color: #334155;
    }

    .info-card-branch .info-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        background: #E8F0FE;
        color: #0B5ED7;
    }

    [data-theme="dark"] .info-card-branch .info-icon {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .info-card-branch .info-text h4 {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .info-card-branch .info-text p {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-branch {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        min-width: 120px;
    }

    .btn-primary-branch {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }

    .btn-primary-branch:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-outline-branch {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-branch:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
        transform: translateY(-2px);
    }

    [data-theme="dark"] .btn-outline-branch {
        color: #F1F5F9;
        border-color: #334155;
    }

    [data-theme="dark"] .btn-outline-branch:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .form-actions-branch {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box-branch {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDownBranch 0.4s ease;
    }

    @keyframes slideDownBranch {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-branch.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }

    .message-box-branch.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }

    [data-theme="dark"] .message-box-branch.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .message-box-branch.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-branch {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-branch .footer-brand-branch {
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 640px) {
        .form-card-branch {
            padding: 18px 16px;
        }
        .form-header-branch {
            flex-direction: column;
            text-align: center;
        }
        .form-header-branch .form-header-icon {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .btn-branch {
            padding: 8px 16px;
            font-size: 0.8rem;
            min-height: 38px;
            min-width: 100%;
        }
        .form-actions-branch {
            flex-direction: column;
        }
        .form-actions-branch .btn-branch {
            width: 100%;
            justify-content: center;
        }
        .page-header-branch {
            padding: 18px 20px;
        }
        .page-header-branch .page-title {
            font-size: 1.3rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-branch">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Branch
            </h1>
            <p class="page-subtitle">
                Update branch information
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch['name']) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-users"></i> <?= $staff_count ?> Staff
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="branches.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Branches
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box-branch <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM CARD -->
    <!-- ================================================================ -->
    <div class="form-card-branch">
        <div class="form-header-branch">
            <div class="form-header-icon">
                <i class="fas fa-store-alt"></i>
            </div>
            <div>
                <h3>Edit Branch Information</h3>
                <p>Update the details of this branch</p>
            </div>
        </div>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Branch Name -->
                <div>
                    <label class="form-label-branch">
                        <i class="fas fa-tag" style="color:#0B5ED7;"></i> Branch Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="name" class="form-control-branch" 
                               placeholder="e.g. Braick Dispensary - Dodoma" 
                               value="<?= htmlspecialchars($form_data['name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-store"></i></span>
                    </div>
                </div>
                
                <!-- Location -->
                <div>
                    <label class="form-label-branch">
                        <i class="fas fa-location-dot" style="color:#059669;"></i> Location
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="location" class="form-control-branch" 
                               placeholder="e.g. Chang'ombe, Dodoma"
                               value="<?= htmlspecialchars($form_data['location']) ?>">
                        <span class="input-icon"><i class="fas fa-map-pin"></i></span>
                    </div>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="form-label-branch">
                        <i class="fas fa-phone" style="color:#0B5ED7;"></i> Phone
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="phone" class="form-control-branch" 
                               placeholder="e.g. +255 759 154 160"
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <!-- Email -->
                <div>
                    <label class="form-label-branch">
                        <i class="fas fa-envelope" style="color:#059669;"></i> Email
                    </label>
                    <div class="form-row-icon">
                        <input type="email" name="email" class="form-control-branch" 
                               placeholder="e.g. dodoma@dispensary.com"
                               value="<?= htmlspecialchars($form_data['email']) ?>">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="form-label-branch">
                        <i class="fas fa-circle" style="color:#0B5ED7;"></i> Status
                    </label>
                    <div class="form-row-icon">
                        <select name="status" class="form-control-branch">
                            <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>
                                ✅ Active
                            </option>
                            <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>
                                ⛔ Inactive
                            </option>
                        </select>
                        <span class="input-icon"><i class="fas fa-toggle-on"></i></span>
                    </div>
                </div>
                
                <!-- Created Date -->
                <div>
                    <label class="form-label-branch">
                        <i class="fas fa-calendar" style="color:#94A3B8;"></i> Created Date
                    </label>
                    <div class="form-row-icon">
                        <input type="text" class="form-control-branch" 
                               value="<?= $created_date ?>" disabled>
                        <span class="input-icon"><i class="fas fa-calendar-day"></i></span>
                    </div>
                </div>
                
            </div>
            
            <!-- ================================================================ -->
            <!-- BRANCH INFO CARDS -->
            <!-- ================================================================ -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="info-card-branch">
                    <div class="info-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="info-text">
                        <h4><?= $staff_count ?></h4>
                        <p>Staff Members</p>
                    </div>
                </div>
                <div class="info-card-branch">
                    <div class="info-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="info-text">
                        <h4><?= $created_date ?></h4>
                        <p>Created Date</p>
                    </div>
                </div>
                <div class="info-card-branch">
                    <div class="info-icon">
                        <i class="fas fa-hashtag"></i>
                    </div>
                    <div class="info-text">
                        <h4>#<?= $branch_id ?></h4>
                        <p>Branch ID</p>
                    </div>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ================================================================ -->
            <div class="form-actions-branch">
                <button type="submit" class="btn-branch btn-primary-branch">
                    <i class="fas fa-save"></i> Update Branch
                </button>
                <a href="branches.php?branch=<?= $selected_branch_id ?>" class="btn-branch btn-outline-branch">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn-branch btn-outline-branch">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-branch">
        <p>
            <span class="footer-brand-branch">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Branch
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
    // ✅ FORM VALIDATION (PAGE-SPECIFIC)
    // ================================================================
    document.querySelector('form')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="name"]').value.trim();
        if (!name) {
            e.preventDefault();
            alert('⚠️ Branch name is required');
            return false;
        }
    });

    console.log('%c🏢 Braick - Edit Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>