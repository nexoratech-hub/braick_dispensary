<?php
// ================================================================
// FILE: frontend/pages/admin/add_branch.php
// SUPER ADMIN - ADD NEW BRANCH
// ✅ USES SHARED header & sidebar
// ✅ FULL DARK MODE - Page nzima inakuwa dark
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// PROCESS FORM
// ================================================================
$errors = [];
$success = false;
$form_data = [];
$branch_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
        'logo' => trim($_POST['logo'] ?? '')
    ];

    if (empty($form_data['name'])) $errors['name'] = 'Branch name is required';
    if (empty($form_data['location'])) $errors['location'] = 'Location is required';
    if (!empty($form_data['phone']) && !preg_match('/^[0-9+\-\s()]{7,20}$/', $form_data['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number';
    }
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (empty($errors['name'])) {
        $stmt = $db->prepare("SELECT id FROM branches WHERE name = ?");
        $stmt->execute([$form_data['name']]);
        if ($stmt->fetch()) $errors['name'] = 'A branch with this name already exists';
    }

    if (empty($errors['email']) && !empty($form_data['email'])) {
        $stmt = $db->prepare("SELECT id FROM branches WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) $errors['email'] = 'A branch with this email already exists';
    }

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO branches (name, location, phone, email, logo, status, created_at, updated_at) 
                    VALUES (:name, :location, :phone, :email, :logo, :status, NOW(), NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':name' => $form_data['name'],
                ':location' => $form_data['location'],
                ':phone' => $form_data['phone'] ?: null,
                ':email' => $form_data['email'] ?: null,
                ':logo' => $form_data['logo'] ?: null,
                ':status' => $form_data['status']
            ]);

            $branch_id = $db->lastInsertId();
            $success = true;

            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'branch_added', ?, NOW())");
                $stmt->execute([$user_id, $branch_id, "New branch created: " . $form_data['name'] . " (ID: $branch_id) by user: $user_full_name"]);
            } catch (Exception $log_error) {
                error_log('Activity log error: ' . $log_error->getMessage());
            }

            $form_data = [];
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to create branch: ' . $e->getMessage();
        }
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- FULL DARK MODE CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ✅ FULL DARK MODE - Ina-override kila kitu
       ================================================================ */
    
    /* LIGHT MODE (Default) */
    :root {
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #0F172A;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-input-bg: #FFFFFF;
        --page-input-border: #D1D5DB;
        --page-hover: #F8FAFC;
    }

    /* DARK MODE */
    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-input-bg: #0F172A;
        --page-input-border: #334155;
        --page-hover: #0F172A;
    }

    /* ================================================================
       ✅ BODY & HTML - DARK MODE BACKGROUND
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    /* ================================================================
       ✅ MAIN CONTENT - DARK MODE BACKGROUND
       ================================================================ */
    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       ✅ FIXED BODY BACKGROUND - KWA LIGHT MODE
       ================================================================ */
    body {
        background: #F1F5F9;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 1px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        max-width: 900px;
        margin: 0 auto;
        transition: all 0.3s ease;
    }

    [data-theme="dark"] .form-card {
        background: #1E293B !important;
        border-color: #334155 !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.4);
    }

    .form-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] .form-card:hover {
        box-shadow: 0 4px 24px rgba(0,0,0,0.5);
    }

    .form-card-header {
        padding: 20px 28px;
        background: #0B5ED7 !important;
        border-bottom: 2px solid #0A4FB0;
    }

    .form-card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #FFFFFF !important;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-card-title i {
        color: rgba(255, 255, 255, 0.9);
    }

    .form-card-subtitle {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.8) !important;
        margin: 4px 0 0 34px;
    }

    .form-container {
        padding: 28px;
        background: var(--page-bg-card, #FFFFFF);
    }

    [data-theme="dark"] .form-container {
        background: #1E293B !important;
    }

    /* ================================================================
       FORM ROWS
       ================================================================ */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 0;
    }

    .form-row.single {
        grid-template-columns: 1fr;
    }

    .form-row .form-group {
        margin-bottom: 20px;
    }

    /* ================================================================
       FORM GROUPS
       ================================================================ */
    .form-group.has-error .form-control {
        border-color: #EF4444 !important;
    }

    .form-group.has-error .form-label {
        color: #EF4444 !important;
    }

    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--page-text-primary, #0F172A);
        margin-bottom: 6px;
    }

    [data-theme="dark"] .form-label {
        color: #F1F5F9 !important;
    }

    .form-label.required::after {
        content: ' *';
        color: #EF4444;
        font-weight: 700;
    }

    .form-label i {
        margin-right: 6px;
        color: #0B5ED7;
        width: 16px;
        text-align: center;
    }

    [data-theme="dark"] .form-label i {
        color: #6EA8FE !important;
    }

    .form-control {
        width: 100%;
        padding: 10px 14px;
        font-size: 0.9rem;
        font-weight: 400;
        color: var(--page-text-primary, #0F172A);
        background: var(--page-input-bg, #FFFFFF);
        border: 1.5px solid var(--page-input-border, #D1D5DB);
        border-radius: 8px;
        transition: all 0.3s ease;
        outline: none;
        font-family: inherit;
    }

    [data-theme="dark"] .form-control {
        color: #F1F5F9 !important;
        background: #0F172A !important;
        border-color: #334155 !important;
    }

    [data-theme="dark"] .form-control:focus {
        border-color: #6EA8FE !important;
        box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15) !important;
    }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .form-control::placeholder {
        color: var(--page-text-muted, #94A3B8);
        font-size: 0.85rem;
    }

    [data-theme="dark"] .form-control::placeholder {
        color: #64748B !important;
    }

    /* SELECT DROPDOWN */
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 40px;
        cursor: pointer;
    }

    [data-theme="dark"] select.form-control {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394A3B8' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
    }

    [data-theme="dark"] select.form-control option {
        background: #1E293B !important;
        color: #F1F5F9 !important;
    }

    .form-error {
        display: block;
        font-size: 0.75rem;
        color: #EF4444;
        margin-top: 4px;
    }

    .form-help {
        display: block;
        font-size: 0.7rem;
        color: var(--page-text-muted, #94A3B8);
        margin-top: 4px;
    }

    [data-theme="dark"] .form-help {
        color: #64748B !important;
    }

    /* ================================================================
       FORM ACTIONS
       ================================================================ */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 8px;
        padding-top: 20px;
        border-top: 1px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    [data-theme="dark"] .form-actions {
        border-top-color: #334155 !important;
    }

    .form-actions .btn {
        min-width: 140px;
        justify-content: center;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--page-text-primary, #0F172A);
        margin: 0;
        display: flex;
        align-items: center;
    }

    [data-theme="dark"] .page-title {
        color: #F1F5F9 !important;
    }

    .page-title i {
        color: #0B5ED7;
    }

    [data-theme="dark"] .page-title i {
        color: #6EA8FE !important;
    }

    .page-subtitle {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin: 4px 0 0 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }

    [data-theme="dark"] .page-subtitle {
        color: #94A3B8 !important;
    }

    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--page-text-secondary, #64748B);
        font-size: 0.75rem;
    }

    [data-theme="dark"] .date-badge {
        color: #94A3B8 !important;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #0F172A);
        border: 1.5px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .btn {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] .btn:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    }

    .btn-sm {
        padding: 5px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    .btn-primary {
        background: #0B5ED7 !important;
        color: white !important;
        border-color: #0B5ED7 !important;
    }

    .btn-primary:hover {
        background: #0A4FB0 !important;
        border-color: #0A4FB0 !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 1.5px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .btn-outline {
        color: #94A3B8 !important;
        border-color: #334155 !important;
        background: transparent !important;
    }

    .btn-outline:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    [data-theme="dark"] .btn-outline:hover {
        background: #0F172A !important;
        border-color: #6EA8FE !important;
        color: #6EA8FE !important;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 500;
        border: 1px solid transparent;
        position: relative;
        max-width: 900px;
        margin: 0 auto;
    }

    .alert-success {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0;
    }

    .alert-danger {
        background: #FEF2F2;
        color: #991B1B;
        border-color: #FECACA;
    }

    [data-theme="dark"] .alert-success {
        background: #1A3A2A !important;
        color: #34D399 !important;
        border-color: #065F46 !important;
    }

    [data-theme="dark"] .alert-danger {
        background: #3A1A1A !important;
        color: #F87171 !important;
        border-color: #7F1D1D !important;
    }

    .alert i {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .alert-link {
        color: inherit;
        text-decoration: underline;
        font-weight: 600;
    }

    .alert-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.5;
        margin-left: auto;
        padding: 0 4px;
        transition: opacity 0.3s;
    }

    .alert-close:hover {
        opacity: 1;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .form-container { padding: 20px; }
        .form-card-header { padding: 16px 20px; }
        .form-card-title { font-size: 1rem; }
        .form-card-subtitle { font-size: 0.7rem; margin-left: 0; }
        .form-row { grid-template-columns: 1fr; gap: 0; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; min-width: unset; }
        .page-title { font-size: 1.2rem; }
        .page-subtitle { font-size: 0.75rem; }
        .alert { flex-wrap: wrap; font-size: 0.8rem; padding: 12px 16px; }
    }

    @media (max-width: 480px) {
        .form-container { padding: 16px; }
        .form-card-header { padding: 14px 16px; }
        .form-control { font-size: 0.8rem; padding: 8px 12px; }
        .form-label { font-size: 0.75rem; }
        .btn { font-size: 0.75rem; padding: 8px 14px; }
        .btn-sm { font-size: 0.6rem; padding: 3px 8px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn, .form-actions, .page-header .flex.gap-2 { display: none !important; }
        .form-card { box-shadow: none !important; border: 1px solid #ddd !important; }
        .form-card-header {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .form-card-title { color: white !important; }
        .form-card-subtitle { color: rgba(255, 255, 255, 0.8) !important; }
        .form-control {
            border: 1px solid #ddd !important;
            background: white !important;
            color: black !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content" style="background: var(--page-bg-body, #F1F5F9);">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2"></i> Add New Branch
            </h1>
            <p class="page-subtitle">
                Create a new branch for the dispensary
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="branches.php?branch=all" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Branches
            </a>
        </div>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if ($success && $branch_id): ?>
        <div class="alert alert-success mb-5">
            <i class="fas fa-check-circle"></i>
            <span>✅ Branch created successfully! 
                <strong>Branch ID: <?= $branch_id ?></strong>
                <a href="branches.php?branch=all" class="alert-link">View all branches</a>
            </span>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- GENERAL ERROR -->
    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger mb-5">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($errors['general']) ?></span>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="form-card">
        <div class="form-card-header">
            <h3 class="form-card-title">
                <i class="fas fa-hospital"></i> Branch Information
            </h3>
            <p class="form-card-subtitle">Fill in the details to create a new branch</p>
        </div>
        
        <form method="POST" action="" class="form-container" id="branchForm" novalidate>
            <div class="form-row">
                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name" class="form-label required">
                        <i class="fas fa-store"></i> Branch Name
                    </label>
                    <input type="text" id="name" name="name" class="form-control" 
                           placeholder="Enter branch name (e.g. Dodoma)"
                           value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['location']) ? 'has-error' : '' ?>">
                    <label for="location" class="form-label required">
                        <i class="fas fa-map-marker-alt"></i> Location
                    </label>
                    <input type="text" id="location" name="location" class="form-control" 
                           placeholder="Enter branch address"
                           value="<?= htmlspecialchars($form_data['location'] ?? '') ?>" required>
                    <?php if (isset($errors['location'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['location']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone"></i> Phone Number
                    </label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           placeholder="Enter phone number (e.g. +255 700 000 000)"
                           value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['phone']) ?></span>
                    <?php endif; ?>
                    <span class="form-help">Contact phone number for the branch (optional)</span>
                </div>

                <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter email address"
                           value="<?= htmlspecialchars($form_data['email'] ?? '') ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['email']) ?></span>
                    <?php endif; ?>
                    <span class="form-help">Contact email for the branch (optional)</span>
                </div>
            </div>

            <div class="form-row single">
                <div class="form-group">
                    <label for="status" class="form-label required">
                        <i class="fas fa-toggle-on"></i> Status
                    </label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= (isset($form_data['status']) && $form_data['status'] === 'active') ? 'selected' : '' ?>>🟢 Active</option>
                        <option value="inactive" <?= (isset($form_data['status']) && $form_data['status'] === 'inactive') ? 'selected' : '' ?>>🔴 Inactive</option>
                    </select>
                    <span class="form-help">Active branches are visible and operational</span>
                </div>
            </div>

            <div class="form-row single">
                <div class="form-group">
                    <label for="logo" class="form-label">
                        <i class="fas fa-image"></i> Logo URL (Optional)
                    </label>
                    <input type="text" id="logo" name="logo" class="form-control" 
                           placeholder="Enter logo image URL (optional)"
                           value="<?= htmlspecialchars($form_data['logo'] ?? '') ?>">
                    <span class="form-help">Upload a logo image or enter a URL</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Create Branch
                </button>
                <a href="branches.php?branch=all" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ✅ DARK MODE BACKGROUND ENFORCEMENT
    // Inahakikisha body & main-content zinakuwa dark
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    // Run on load
    enforceDarkModeBackground();

    // Listen for dark mode changes from header
    document.addEventListener('darkModeChanged', function(e) {
        setTimeout(enforceDarkModeBackground, 50);
    });

    // Also listen for attribute changes (safety net)
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    var submitBtn = document.getElementById('submitBtn');
    var branchForm = document.getElementById('branchForm');

    branchForm?.addEventListener('submit', function(e) {
        var name = document.getElementById('name').value.trim();
        var location = document.getElementById('location').value.trim();
        var email = document.getElementById('email').value.trim();
        var phone = document.getElementById('phone').value.trim();
        var isValid = true;

        document.querySelectorAll('.form-error').forEach(function(el) { el.remove(); });
        document.querySelectorAll('.has-error').forEach(function(el) { el.classList.remove('has-error'); });

        if (!name) { showError('name', 'Branch name is required'); isValid = false; }
        if (!location) { showError('location', 'Location is required'); isValid = false; }
        if (phone && !/^[0-9+\-\s()]{7,20}$/.test(phone)) {
            showError('phone', 'Please enter a valid phone number');
            isValid = false;
        }
        if (email && !isValidEmail(email)) {
            showError('email', 'Please enter a valid email address');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            var firstError = document.querySelector('.has-error');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            }
        }
    });

    function showError(fieldId, message) {
        var field = document.getElementById(fieldId);
        if (!field) return;
        var group = field.closest('.form-group');
        if (!group) return;
        group.classList.add('has-error');
        var error = document.createElement('span');
        error.className = 'form-error';
        error.textContent = message;
        group.appendChild(error);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ================================================================
    // PHONE FORMAT
    // ================================================================
    var phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
        });
    }

    console.log('%c🏢 Braick Dispensary - Add New Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>