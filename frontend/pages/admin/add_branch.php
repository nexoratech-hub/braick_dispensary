<?php
// ================================================================
// FILE: frontend/pages/admin/add_branch.php
// SUPER ADMIN - ADD NEW BRANCH
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$errors = [];
$success = false;
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
        'description' => trim($_POST['description'] ?? '')
    ];

    // Validate
    if (empty($form_data['name'])) {
        $errors['name'] = 'Branch name is required';
    }

    if (empty($form_data['location'])) {
        $errors['location'] = 'Location is required';
    }

    if (!empty($form_data['contact_email']) && !filter_var($form_data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = 'Please enter a valid email address';
    }

    // Check if branch name already exists
    if (empty($errors['name'])) {
        $stmt = $db->prepare("SELECT id FROM branches WHERE name = ?");
        $stmt->execute([$form_data['name']]);
        if ($stmt->fetch()) {
            $errors['name'] = 'A branch with this name already exists';
        }
    }

    // If no errors, insert
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO branches (
                    name, 
                    location, 
                    contact_phone, 
                    contact_email, 
                    status, 
                    description,
                    created_at,
                    updated_at
                ) VALUES (
                    :name,
                    :location,
                    :contact_phone,
                    :contact_email,
                    :status,
                    :description,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                ':name' => $form_data['name'],
                ':location' => $form_data['location'],
                ':contact_phone' => $form_data['contact_phone'],
                ':contact_email' => $form_data['contact_email'],
                ':status' => $form_data['status'],
                ':description' => $form_data['description']
            ]);

            $branch_id = $db->lastInsertId();
            $success = true;
            
            // Clear form data on success
            $form_data = [];
            
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to create branch. Please try again.';
            error_log('Add branch error: ' . $e->getMessage());
        }
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
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
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

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

    <!-- ================================================================ -->
    <!-- SUCCESS MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($success): ?>
        <div class="alert alert-success mb-5">
            <i class="fas fa-check-circle"></i>
            <span>Branch created successfully! <a href="branches.php?branch=all" class="alert-link">View all branches</a></span>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- GENERAL ERROR -->
    <!-- ================================================================ -->
    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger mb-5">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($errors['general']) ?></span>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD BRANCH FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <div class="form-card-header">
            <h3 class="form-card-title">
                <i class="fas fa-hospital"></i> Branch Information
            </h3>
            <p class="form-card-subtitle">Fill in the details to create a new branch</p>
        </div>
        
        <form method="POST" action="" class="form-container" id="branchForm">
            <!-- Row 1: Branch Name & Location -->
            <div class="form-row">
                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name" class="form-label required">
                        <i class="fas fa-store"></i> Branch Name
                    </label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-control" 
                        placeholder="Enter branch name"
                        value="<?= htmlspecialchars($form_data['name'] ?? '') ?>"
                        required
                    >
                    <?php if (isset($errors['name'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['location']) ? 'has-error' : '' ?>">
                    <label for="location" class="form-label required">
                        <i class="fas fa-map-marker-alt"></i> Location
                    </label>
                    <input 
                        type="text" 
                        id="location" 
                        name="location" 
                        class="form-control" 
                        placeholder="Enter branch address"
                        value="<?= htmlspecialchars($form_data['location'] ?? '') ?>"
                        required
                    >
                    <?php if (isset($errors['location'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['location']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 2: Contact Phone & Contact Email -->
            <div class="form-row">
                <div class="form-group">
                    <label for="contact_phone" class="form-label">
                        <i class="fas fa-phone"></i> Contact Phone
                    </label>
                    <input 
                        type="tel" 
                        id="contact_phone" 
                        name="contact_phone" 
                        class="form-control" 
                        placeholder="Enter phone number"
                        value="<?= htmlspecialchars($form_data['contact_phone'] ?? '') ?>"
                    >
                </div>

                <div class="form-group <?= isset($errors['contact_email']) ? 'has-error' : '' ?>">
                    <label for="contact_email" class="form-label">
                        <i class="fas fa-envelope"></i> Contact Email
                    </label>
                    <input 
                        type="email" 
                        id="contact_email" 
                        name="contact_email" 
                        class="form-control" 
                        placeholder="Enter email address"
                        value="<?= htmlspecialchars($form_data['contact_email'] ?? '') ?>"
                    >
                    <?php if (isset($errors['contact_email'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['contact_email']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 3: Status (full width) -->
            <div class="form-row single">
                <div class="form-group">
                    <label for="status" class="form-label required">
                        <i class="fas fa-toggle-on"></i> Status
                    </label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= (isset($form_data['status']) && $form_data['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (isset($form_data['status']) && $form_data['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <span class="form-help">Active branches are visible and operational</span>
                </div>
            </div>

            <!-- Row 4: Description (full width) -->
            <div class="form-row single">
                <div class="form-group">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Enter a brief description of the branch (optional)"
                    ><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Branch
                </button>
                <a href="branches.php?branch=all" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add New Branch
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       ROOT VARIABLES
       ================================================================ */
    :root {
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-header: #0B5ED7;
        --text-primary: #0F172A;
        --text-secondary: #64748B;
        --text-muted: #94A3B8;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        --radius: 16px;
        --radius-sm: 10px;
        --input-bg: #FFFFFF;
        --input-border: #D1D5DB;
        --input-focus: #0B5ED7;
        --success: #059669;
        --danger: #EF4444;
    }

    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --border-color: #334155;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.4);
        --input-bg: #0F172A;
        --input-border: #334155;
        --input-focus: #6EA8FE;
    }

    /* ================================================================
       BASE STYLES
       ================================================================ */
    .main-content {
        padding: 20px 24px;
        background: var(--bg-body);
        min-height: 100vh;
        transition: all 0.3s ease;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        max-width: 900px;
        margin: 0 auto;
        transition: all 0.3s ease;
    }

    .form-card:hover {
        box-shadow: var(--shadow-md);
    }

    .form-card-header {
        padding: 20px 28px;
        background: #0B5ED7 !important;
        border-bottom: 2px solid #0A4FB0;
    }

    [data-theme="dark"] .form-card-header {
        background: #0B5ED7 !important;
        border-bottom: 2px solid #0A4FB0;
    }

    .form-card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #FFFFFF;
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
        color: rgba(255, 255, 255, 0.8);
        margin: 4px 0 0 34px;
    }

    .form-container {
        padding: 28px;
    }

    /* ================================================================
       FORM ROWS - TWO COLUMNS
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

    .form-row.single .form-group {
        margin-bottom: 20px;
    }

    /* ================================================================
       FORM GROUPS
       ================================================================ */
    .form-group.has-error .form-control {
        border-color: var(--danger);
    }

    .form-group.has-error .form-label {
        color: var(--danger);
    }

    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 6px;
    }

    .form-label.required::after {
        content: ' *';
        color: var(--danger);
        font-weight: 700;
    }

    .form-label i {
        margin-right: 6px;
        color: #0B5ED7;
        width: 16px;
        text-align: center;
    }

    [data-theme="dark"] .form-label i {
        color: #6EA8FE;
    }

    .form-control {
        width: 100%;
        padding: 10px 14px;
        font-size: 0.9rem;
        font-weight: 400;
        color: var(--text-primary);
        background: var(--input-bg);
        border: 1.5px solid var(--input-border);
        border-radius: 8px;
        transition: all 0.3s ease;
        outline: none;
        font-family: inherit;
    }

    .form-control:focus {
        border-color: var(--input-focus);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .form-control::placeholder {
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 40px;
        cursor: pointer;
    }

    select.form-control option {
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .form-error {
        display: block;
        font-size: 0.75rem;
        color: var(--danger);
        margin-top: 4px;
    }

    .form-help {
        display: block;
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 4px;
    }

    /* ================================================================
       FORM ACTIONS
       ================================================================ */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 8px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
        flex-wrap: wrap;
    }

    .form-actions .btn {
        min-width: 140px;
        justify-content: center;
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-radius: var(--radius-sm);
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
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }

    [data-theme="dark"] .alert-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
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

    .alert-link:hover {
        color: #0B5ED7;
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
       PAGE HEADER
       ================================================================ */
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }

    .page-title i {
        color: #0B5ED7;
    }

    .page-subtitle {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }

    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--text-secondary);
        font-size: 0.75rem;
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
        border: none;
        text-decoration: none;
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1.5px solid var(--border-color);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-sm {
        padding: 5px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    .btn-primary {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }

    .btn-primary:hover {
        background: #0A4FB0;
        border-color: #0A4FB0;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 1.5px solid var(--border-color);
    }

    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        text-align: center;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .footer p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .footer-brand {
        font-weight: 700;
        color: #0B5ED7;
    }

    /* ================================================================
       UTILITY CLASSES
       ================================================================ */
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .gap-4 { gap: 16px; }
    .gap-5 { gap: 20px; }
    .mb-5 { margin-bottom: 20px; }
    .ml-2 { margin-left: 8px; }
    .mr-1 { margin-right: 4px; }
    .mr-2 { margin-right: 8px; }
    .text-gray-300 { color: #94A3B8; }
    .mx-2 { margin-left: 8px; margin-right: 8px; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .main-content {
            padding: 12px;
        }

        .form-container {
            padding: 20px;
        }

        .form-card-header {
            padding: 16px 20px;
        }

        .form-card-title {
            font-size: 1rem;
        }

        .form-card-subtitle {
            font-size: 0.7rem;
            margin-left: 0;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            min-width: unset;
        }

        .page-title {
            font-size: 1.2rem;
        }

        .page-subtitle {
            font-size: 0.75rem;
        }

        .alert {
            flex-wrap: wrap;
            font-size: 0.8rem;
            padding: 12px 16px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 10px;
        }

        .form-container {
            padding: 16px;
        }

        .form-card-header {
            padding: 14px 16px;
        }

        .form-control {
            font-size: 0.8rem;
            padding: 8px 12px;
        }

        .form-label {
            font-size: 0.75rem;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .btn {
            font-size: 0.75rem;
            padding: 8px 14px;
        }

        .btn-sm {
            font-size: 0.6rem;
            padding: 3px 8px;
        }
    }

    /* ================================================================
       PRINT STYLES
       ================================================================ */
    @media print {
        .top-nav,
        .sidebar,
        #sidebarToggle,
        .btn,
        .dark-toggle-btn,
        .icon-btn,
        .search-wrapper,
        .page-header .flex.gap-2,
        .footer,
        .form-actions {
            display: none !important;
        }

        .main-content {
            padding: 0 !important;
            background: white !important;
        }

        .form-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }

        .form-card-header {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .form-card-title {
            color: white !important;
        }

        .form-card-subtitle {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .form-control {
            border: 1px solid #ddd !important;
            background: white !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=all';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('branchForm')?.addEventListener('submit', function(e) {
        var name = document.getElementById('name').value.trim();
        var location = document.getElementById('location').value.trim();
        var email = document.getElementById('contact_email').value.trim();
        var isValid = true;

        // Clear previous errors
        document.querySelectorAll('.form-error').forEach(el => el.remove());
        document.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));

        // Validate name
        if (!name) {
            showError('name', 'Branch name is required');
            isValid = false;
        }

        // Validate location
        if (!location) {
            showError('location', 'Location is required');
            isValid = false;
        }

        // Validate email
        if (email && !isValidEmail(email)) {
            showError('contact_email', 'Please enter a valid email address');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    function showError(fieldId, message) {
        var field = document.getElementById(fieldId);
        var group = field.closest('.form-group');
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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏢 Braick Dispensary - Add New Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📝 Fill in the form to create a new branch', 'font-size:13px; color:#059669;');
</script>

</body>
</html>