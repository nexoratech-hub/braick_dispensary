<?php
// ================================================================
// FILE: frontend/pages/admin/add_inventory.php
// ADMIN - ADD INVENTORY ITEM (MEDICINE) - GROUPED BY NAME
// ✅ Uses SHARED header & sidebar
// ✅ Blue theme + full dark mode
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
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
// STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
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
} catch (Exception $e) { $pending_lab_tests = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// GET ALL MEDICINE NAMES FOR AUTO-SEARCH (GROUPED)
// ================================================================
$all_medicine_names = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT medication_name, category, selling_price 
        FROM medications_inventory 
        WHERE branch_id = ? 
        ORDER BY medication_name
    ");
    $stmt->execute([$user_branch_id]);
    $all_medicine_names = $stmt->fetchAll();
} catch (Exception $e) {
    $all_medicine_names = [];
}

// ================================================================
// GET UNIQUE CATEGORIES
// ================================================================
$existing_categories = [];
try {
    $stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_categories[] = $row['category'];
    }
} catch (Exception $e) { $existing_categories = []; }

$predefined_categories = [
    'Antibiotics', 'Painkillers', 'Antipyretics', 'Antihistamines', 'Antacids',
    'Antivirals', 'Antifungals', 'Antimalarials', 'Vitamins', 'Supplements',
    'Respiratory', 'Cardiovascular', 'Diabetes', 'Hypertension', 'Dermatological',
    'Eye Drops', 'Ear Drops', 'Injectables', 'IV Fluids', 'Other'
];

$all_categories = array_unique(array_merge($predefined_categories, $existing_categories));
sort($all_categories);

// ================================================================
// FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$form_data = [
    'medication_name' => '',
    'category' => '',
    'unit' => 'pcs',
    'quantity' => '',
    'reorder_level' => 10,
    'unit_cost' => '',
    'selling_price' => '',
    'supplier' => '',
    'expiry_date' => '',
    'batch_number' => '',
    'branch_id' => $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id,
    'status' => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_medicine') {
    $form_data['medication_name'] = trim($_POST['medication_name'] ?? '');
    $form_data['category'] = trim($_POST['category'] ?? '');
    if (empty($form_data['category']) && !empty($_POST['category_manual'])) {
        $form_data['category'] = trim($_POST['category_manual']);
    }
    $form_data['unit'] = trim($_POST['unit'] ?? 'pcs');
    $form_data['quantity'] = (int)($_POST['quantity'] ?? 0);
    $form_data['reorder_level'] = (int)($_POST['reorder_level'] ?? 10);
    $form_data['unit_cost'] = (float)($_POST['unit_cost'] ?? 0);
    $form_data['selling_price'] = (float)($_POST['selling_price'] ?? 0);
    $form_data['supplier'] = trim($_POST['supplier'] ?? '');
    $form_data['expiry_date'] = $_POST['expiry_date'] ?? '';
    $form_data['batch_number'] = trim($_POST['batch_number'] ?? '');
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? $user_branch_id);
    $form_data['status'] = $_POST['status'] ?? 'active';

    if (empty($form_data['batch_number'])) {
        $form_data['batch_number'] = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    $errors = [];
    if (empty($form_data['medication_name'])) $errors[] = 'Medicine name is required';
    if ($form_data['quantity'] < 0) $errors[] = 'Quantity cannot be negative';
    if ($form_data['selling_price'] < 0) $errors[] = 'Selling price cannot be negative';
    if ($form_data['selling_price'] > 0 && $form_data['selling_price'] < 1) $errors[] = 'Selling price must be at least TSh 1';
    if ($form_data['branch_id'] <= 0) $errors[] = 'Please select a branch';
    if (!empty($form_data['expiry_date']) && strtotime($form_data['expiry_date']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Expiry date cannot be in the past';
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT id FROM medications_inventory 
            WHERE medication_name = ? AND batch_number = ? AND branch_id = ?
        ");
        $stmt->execute([$form_data['medication_name'], $form_data['batch_number'], $form_data['branch_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'A medicine with this name and batch number already exists in this branch';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO medications_inventory (
                    medication_name, category, unit, quantity, reorder_level,
                    unit_cost, selling_price, supplier, expiry_date, batch_number,
                    branch_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $form_data['medication_name'],
                $form_data['category'],
                $form_data['unit'],
                $form_data['quantity'],
                $form_data['reorder_level'],
                $form_data['unit_cost'],
                $form_data['selling_price'],
                $form_data['supplier'],
                $form_data['expiry_date'],
                $form_data['batch_number'],
                $form_data['branch_id'],
                $form_data['status']
            ]);

            $new_id = $db->lastInsertId();

            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'medicine_added', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $form_data['branch_id'],
                    "Added new medicine: " . $form_data['medication_name'] . " (Batch: " . $form_data['batch_number'] . ") - " . $form_data['quantity'] . " units"
                ]);
            } catch (Exception $e) {}

            $message = "✅ Medicine added successfully!<br>Batch: <strong>" . htmlspecialchars($form_data['batch_number']) . "</strong>";
            $message_type = 'success';

            $form_data = [
                'medication_name' => '', 'category' => '', 'unit' => 'pcs',
                'quantity' => '', 'reorder_level' => 10, 'unit_cost' => '',
                'selling_price' => '', 'supplier' => '', 'expiry_date' => '',
                'batch_number' => '',
                'branch_id' => $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id,
                'status' => 'active'
            ];

            echo '<script>setTimeout(function(){ window.location.href = "inventory.php?branch=' . $form_data['branch_id'] . '&success=1"; }, 2000);</script>';

        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
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

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS (HAKUNA top-nav, sidebar, body, footer) -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-light: #E8F0FE;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-border: #E2E8F0;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-input-bg: #FFFFFF;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-border: #334155;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-input-bg: #0F172A;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --page-shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }

    /* ================================================================
       ✅ BODY & MAIN CONTENT - DARK MODE
       ================================================================ */
    body {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    .main-content {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
    }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.3), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(11, 94, 215, 0.4), 0 6px 16px rgba(11, 94, 215, 0.25);
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        position: relative;
        z-index: 2;
        letter-spacing: -0.02em;
    }

    .page-header-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 2;
        white-space: nowrap;
    }

    .page-header-back-btn:hover {
        background: rgba(255,255,255,0.25);
        transform: translateX(-3px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        max-width: 900px;
        margin: 0 auto;
    }

    html[data-theme="dark"] .form-card {
        background: #1E293B;
        border-color: #334155;
    }

    .form-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }

    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .form-header {
        border-bottom-color: #334155;
    }

    .form-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .form-header h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    html[data-theme="dark"] .form-header h3 {
        color: #F1F5F9;
    }

    .form-header p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
        display: block;
    }

    html[data-theme="dark"] .form-label {
        color: #F1F5F9;
    }

    .form-label i {
        width: 20px;
        text-align: center;
        font-size: 0.85rem;
    }

    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }

    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }

    html[data-theme="dark"] .form-control {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control::placeholder {
        color: #64748B;
    }

    html[data-theme="dark"] .form-control option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    html[data-theme="dark"] .form-control:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 4px rgba(110, 168, 254, 0.15);
    }

    .form-control::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    /* Form Row with Icon */
    .form-row-icon {
        position: relative;
    }

    .form-row-icon .form-control {
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

    .form-row-icon .form-control:focus ~ .input-icon {
        color: #0B5ED7;
    }

    /* ================================================================
       AUTOCOMPLETE
       ================================================================ */
    .autocomplete-container {
        position: relative;
        width: 100%;
    }

    .autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        border-top: none;
        border-radius: 0 0 12px 12px;
        z-index: 100;
        max-height: 220px;
        overflow-y: auto;
        display: none;
        box-shadow: var(--page-shadow-md);
    }

    html[data-theme="dark"] .autocomplete-list {
        background: #1E293B;
        border-color: #334155;
    }

    .autocomplete-list.show {
        display: block;
    }

    .autocomplete-item {
        padding: 10px 16px;
        cursor: pointer;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        font-size: 0.82rem;
        transition: all 0.2s ease;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .autocomplete-item {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item:hover,
    .autocomplete-item.active {
        background: var(--page-primary-light, #E8F0FE);
        color: #0B5ED7;
    }

    html[data-theme="dark"] .autocomplete-item:hover,
    html[data-theme="dark"] .autocomplete-item.active {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .autocomplete-item .item-detail {
        font-size: 0.65rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-top: 2px;
    }

    /* ================================================================
       CATEGORY INPUT GROUP
       ================================================================ */
    .category-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .category-input-group .form-control {
        flex: 1;
    }

    .category-input-group .btn-category-toggle {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        height: 44px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .category-input-group .btn-category-toggle:hover {
        background: linear-gradient(135deg, #0A4CA8, #083A8A);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    /* ================================================================
       BATCH INPUT GROUP
       ================================================================ */
    .batch-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .batch-input-group .form-control {
        flex: 1;
    }

    .batch-input-group .btn-generate-batch {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 44px;
    }

    .batch-input-group .btn-generate-batch:hover {
        background: linear-gradient(135deg, #0A4CA8, #083A8A);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
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

    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #0A4CA8, #083A8A);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .btn-outline {
        color: #F1F5F9;
        border-color: #334155;
    }

    .btn-outline:hover {
        background: var(--page-bg-body, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    html[data-theme="dark"] .btn-outline:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .form-actions {
        border-top-color: #334155;
    }

    /* ================================================================
       TIP CARDS
       ================================================================ */
    .tip-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 16px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    html[data-theme="dark"] .tip-card {
        background: #1E293B;
        border-color: #334155;
    }

    .tip-card:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }

    .tip-card .tip-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .tip-card .tip-icon.blue { background: #E8F0FE; color: #0B5ED7; }
    .tip-card .tip-icon.green { background: #E6F7EE; color: #059669; }
    .tip-card .tip-icon.yellow { background: #FEF3C7; color: #F59E0B; }
    .tip-card .tip-icon.purple { background: #F3E8FF; color: #7C3AED; }

    html[data-theme="dark"] .tip-card .tip-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .tip-card .tip-icon.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .tip-card .tip-icon.yellow { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .tip-card .tip-icon.purple { background: #2A1A3A; color: #9B4DCA; }

    .tip-card .tip-text h4 {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0 0 2px 0;
    }

    html[data-theme="dark"] .tip-card .tip-text h4 {
        color: #F1F5F9;
    }

    .tip-card .tip-text p {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }

    .message-box.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }

    html[data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    html[data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    .help-text {
        font-size: 0.7rem;
        color: var(--page-text-muted, #94A3B8);
        margin-top: 4px;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .form-card { padding: 18px 16px; }
        .form-header { flex-direction: column; text-align: center; }
        .form-header-icon { width: 48px; height: 48px; font-size: 1.2rem; }
        .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; min-width: 100%; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .category-input-group, .batch-input-group { flex-direction: column; }
        .category-input-group .btn-category-toggle,
        .batch-input-group .btn-generate-batch { width: 100%; justify-content: center; }
        .tip-card { padding: 12px 16px; }
    }

    /* Print */
    @media print {
        .page-header-card { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .page-header-back-btn { display: none !important; }
        .form-actions { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header -->
    <div class="page-header-card">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-plus-circle"></i>
                Add Medicine
            </h1>
            <p class="page-header-subtitle">
                Add new medicine to inventory
                <span class="page-header-badge">
                    <i class="fas fa-prescription-bottle"></i> Inventory Management
                </span>
            </p>
        </div>
        <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="page-header-back-btn">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-prescription-bottle"></i>
            </div>
            <div>
                <h3>Medicine Information</h3>
                <p>Type medicine name to search existing. If found, it will add a new batch.</p>
            </div>
        </div>

        <form method="POST" action="" id="addMedicineForm">
            <input type="hidden" name="action" value="add_medicine">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Medicine Name -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-capsules" style="color:#0B5ED7;"></i> Medicine Name
                        <span class="required">*</span>
                    </label>
                    <div class="autocomplete-container">
                        <div class="form-row-icon">
                            <input type="text" name="medication_name" id="medicineNameInput" class="form-control"
                                   placeholder="e.g. Paracetamol 500mg, Amoxicillin 250mg"
                                   value="<?= htmlspecialchars($form_data['medication_name']) ?>" required autocomplete="off">
                            <span class="input-icon"><i class="fas fa-capsules"></i></span>
                        </div>
                        <div class="autocomplete-list" id="medicineAutocomplete"></div>
                    </div>
                    <p class="help-text">
                        <i class="fas fa-info-circle" style="color:#0B5ED7;"></i>
                        Type to search existing medicine. If found, a new batch will be added. If new, it will be created.
                    </p>
                </div>

                <!-- Category -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-tags" style="color:#0B5ED7;"></i> Category
                    </label>
                    <div class="category-input-group">
                        <select name="category" id="categorySelect" class="form-control">
                            <option value="">Select or type manually</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $form_data['category'] === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__other__">+ Other (Type manually)</option>
                        </select>
                        <input type="text" name="category_manual" id="categoryManual" class="form-control"
                               placeholder="Enter custom category..." style="display:none;"
                               value="<?= htmlspecialchars($form_data['category']) ?>">
                        <button type="button" class="btn-category-toggle" onclick="toggleCategoryInput()">
                            <i class="fas fa-edit"></i> Manual
                        </button>
                    </div>
                    <p class="help-text">Select existing or type a new category</p>
                </div>

                <!-- Unit -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-ruler" style="color:#0B5ED7;"></i> Unit
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="unit" class="form-control" required>
                            <option value="pcs" <?= $form_data['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                            <option value="tablets" <?= $form_data['unit'] === 'tablets' ? 'selected' : '' ?>>Tablets</option>
                            <option value="capsules" <?= $form_data['unit'] === 'capsules' ? 'selected' : '' ?>>Capsules</option>
                            <option value="ml" <?= $form_data['unit'] === 'ml' ? 'selected' : '' ?>>Milliliters (ml)</option>
                            <option value="mg" <?= $form_data['unit'] === 'mg' ? 'selected' : '' ?>>Milligrams (mg)</option>
                            <option value="g" <?= $form_data['unit'] === 'g' ? 'selected' : '' ?>>Grams (g)</option>
                            <option value="bottle" <?= $form_data['unit'] === 'bottle' ? 'selected' : '' ?>>Bottle</option>
                            <option value="box" <?= $form_data['unit'] === 'box' ? 'selected' : '' ?>>Box</option>
                            <option value="strip" <?= $form_data['unit'] === 'strip' ? 'selected' : '' ?>>Strip</option>
                            <option value="vial" <?= $form_data['unit'] === 'vial' ? 'selected' : '' ?>>Vial</option>
                            <option value="sachet" <?= $form_data['unit'] === 'sachet' ? 'selected' : '' ?>>Sachet</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-ruler"></i></span>
                    </div>
                </div>

                <!-- Quantity -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-boxes" style="color:#0B5ED7;"></i> Current Quantity
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="quantity" class="form-control"
                               placeholder="0" min="0"
                               value="<?= htmlspecialchars($form_data['quantity']) ?>" required>
                        <span class="input-icon"><i class="fas fa-boxes"></i></span>
                    </div>
                </div>

                <!-- Reorder Level -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-exclamation-triangle" style="color:#F59E0B;"></i> Reorder Level
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="reorder_level" class="form-control"
                               placeholder="10" min="0"
                               value="<?= htmlspecialchars($form_data['reorder_level']) ?>" required>
                        <span class="input-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    </div>
                    <p class="help-text">Alert when stock reaches this level</p>
                </div>

                <!-- Buying Price -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-shopping-cart" style="color:#059669;"></i> Buying Price (TSh)
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="unit_cost" class="form-control"
                               placeholder="0" step="1" min="0"
                               value="<?= htmlspecialchars($form_data['unit_cost']) ?>">
                        <span class="input-icon"><i class="fas fa-shopping-cart"></i></span>
                    </div>
                </div>

                <!-- Selling Price -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-money-bill-wave" style="color:#059669;"></i> Selling Price (TSh)
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="selling_price" class="form-control"
                               placeholder="1" step="1" min="1"
                               value="<?= htmlspecialchars($form_data['selling_price'] ?: '1') ?>" required>
                        <span class="input-icon"><i class="fas fa-money-bill-wave"></i></span>
                    </div>
                    <p class="help-text">Minimum TSh 1</p>
                </div>

                <!-- Supplier -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-truck" style="color:#0B5ED7;"></i> Supplier
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="supplier" class="form-control"
                               placeholder="e.g. AVANA MEDICS"
                               value="<?= htmlspecialchars($form_data['supplier']) ?>">
                        <span class="input-icon"><i class="fas fa-truck"></i></span>
                    </div>
                </div>

                <!-- Expiry Date -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-calendar-alt" style="color:#DC2626;"></i> Expiry Date
                    </label>
                    <div class="form-row-icon">
                        <input type="date" name="expiry_date" class="form-control"
                               value="<?= htmlspecialchars($form_data['expiry_date']) ?>">
                        <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                    <p class="help-text">System will show days remaining until expiry</p>
                </div>

                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store" style="color:#0B5ED7;"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" class="form-control" required>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $form_data['branch_id'] == $branch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                    <?= !empty($branch['location']) ? '- ' . htmlspecialchars($branch['location']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store"></i></span>
                    </div>
                </div>

                <!-- Batch Number -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-barcode" style="color:#0B5ED7;"></i> Batch Number
                    </label>
                    <div class="batch-input-group">
                        <input type="text" name="batch_number" id="batchNumberInput" class="form-control"
                               placeholder="BATCH-YYYYMMDD-XXXX"
                               value="<?= htmlspecialchars($form_data['batch_number'] ?: 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6))) ?>">
                        <button type="button" class="btn-generate-batch" onclick="generateBatchNumber()">
                            <i class="fas fa-sync-alt"></i> Generate
                        </button>
                    </div>
                    <p class="help-text">
                        <i class="fas fa-info-circle" style="color:#0B5ED7;"></i>
                        Auto-generated. Click "Generate" for a new batch number.
                    </p>
                </div>

                <!-- Status -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-circle" style="color:#0B5ED7;"></i> Status
                    </label>
                    <div class="flex items-center gap-4 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="active" <?= $form_data['status'] === 'active' ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="inactive" <?= $form_data['status'] === 'inactive' ? 'checked' : '' ?>>
                            <span>Inactive</span>
                        </label>
                    </div>
                    <p class="help-text">Active items will appear in inventory</p>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Medicine
                </button>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Tips -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5" style="max-width:900px;margin:20px auto 0;">
        <div class="tip-card">
            <div class="tip-icon blue"><i class="fas fa-search"></i></div>
            <div class="tip-text">
                <h4>Tip #1</h4>
                <p>Auto-search existing medicines</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon green"><i class="fas fa-money-bill-wave"></i></div>
            <div class="tip-text">
                <h4>Tip #2</h4>
                <p>Minimum selling price TSh 1</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="tip-text">
                <h4>Tip #3</h4>
                <p>Set reorder level for alerts</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon purple"><i class="fas fa-layer-group"></i></div>
            <div class="tip-text">
                <h4>Tip #4</h4>
                <p>Grouped by name - adds new batch</p>
            </div>
        </div>
    </div>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
// ================================================================
// DARK MODE BACKGROUND ENFORCEMENT
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

enforceDarkModeBackground();

document.addEventListener('darkModeChanged', function() {
    setTimeout(enforceDarkModeBackground, 50);
});

var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'data-theme') {
            enforceDarkModeBackground();
        }
    });
});
observer.observe(document.documentElement, { attributes: true });

// ================================================================
// AUTO-SEARCH - Medicine Name (Grouped)
// ================================================================
(function() {
    var medicineData = <?= json_encode($all_medicine_names) ?>;
    var input = document.getElementById('medicineNameInput');
    var autocomplete = document.getElementById('medicineAutocomplete');

    if (!input || !autocomplete) return;

    input.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();

        if (query.length < 1) {
            autocomplete.classList.remove('show');
            return;
        }

        var matches = medicineData.filter(function(item) {
            return item.medication_name.toLowerCase().includes(query);
        });

        if (matches.length === 0) {
            autocomplete.classList.remove('show');
            return;
        }

        var html = '';
        matches.slice(0, 10).forEach(function(item) {
            html += `
                <div class="autocomplete-item" data-name="${escapeHtml(item.medication_name)}">
                    <strong>${escapeHtml(item.medication_name)}</strong>
                    <span class="item-detail">
                        Category: ${escapeHtml(item.category || 'N/A')} |
                        Price: TSh ${Number(item.selling_price || 0).toLocaleString()}
                    </span>
                </div>
            `;
        });

        autocomplete.innerHTML = html;
        autocomplete.classList.add('show');

        autocomplete.querySelectorAll('.autocomplete-item').forEach(function(item) {
            item.addEventListener('click', function() {
                input.value = this.dataset.name;
                autocomplete.classList.remove('show');
            });
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            autocomplete.classList.remove('show');
        }
    });

    var selectedIndex = -1;
    input.addEventListener('keydown', function(e) {
        var items = autocomplete.querySelectorAll('.autocomplete-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(items);
        } else if (e.key === 'Enter') {
            if (selectedIndex >= 0 && items.length > 0) {
                e.preventDefault();
                input.value = items[selectedIndex].dataset.name;
                autocomplete.classList.remove('show');
                selectedIndex = -1;
            }
        } else if (e.key === 'Escape') {
            autocomplete.classList.remove('show');
            selectedIndex = -1;
        }
    });

    function updateSelection(items) {
        items.forEach(function(item, index) {
            if (index === selectedIndex) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
        if (selectedIndex >= 0 && items.length > 0) {
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }
})();

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ================================================================
// TOGGLE CATEGORY INPUT
// ================================================================
function toggleCategoryInput() {
    var select = document.getElementById('categorySelect');
    var manual = document.getElementById('categoryManual');
    var btn = document.querySelector('.btn-category-toggle');

    if (manual.style.display === 'none' || manual.style.display === '') {
        manual.style.display = 'block';
        select.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-list"></i> Select';
        manual.focus();
    } else {
        manual.style.display = 'none';
        select.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-edit"></i> Manual';
        if (manual.value) {
            var found = false;
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value === manual.value) {
                    found = true;
                    break;
                }
            }
            if (!found) {
                var opt = document.createElement('option');
                opt.value = manual.value;
                opt.text = manual.value;
                select.add(opt, select.options[select.options.length - 1]);
            }
            select.value = manual.value;
        }
    }
}

document.getElementById('categorySelect')?.addEventListener('change', function() {
    if (this.value === '__other__') {
        document.getElementById('categoryManual').style.display = 'block';
        document.getElementById('categoryManual').focus();
        document.querySelector('.btn-category-toggle').innerHTML = '<i class="fas fa-list"></i> Select';
    }
});

// ================================================================
// GENERATE BATCH NUMBER
// ================================================================
function generateBatchNumber() {
    var now = new Date();
    var dateStr = now.getFullYear() +
                  String(now.getMonth() + 1).padStart(2, '0') +
                  String(now.getDate()).padStart(2, '0');
    var random = Math.random().toString(36).substring(2, 8).toUpperCase();
    document.getElementById('batchNumberInput').value = 'BATCH-' + dateStr + '-' + random;
}

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('addMedicineForm')?.addEventListener('submit', function(e) {
    var name = document.querySelector('input[name="medication_name"]');
    var quantity = document.querySelector('input[name="quantity"]');
    var sellingPrice = document.querySelector('input[name="selling_price"]');
    var reorderLevel = document.querySelector('input[name="reorder_level"]');
    var errors = [];

    if (!name.value.trim()) {
        errors.push('Medicine name is required');
        name.style.borderColor = '#DC2626';
    }
    if (parseInt(quantity.value) < 0) {
        errors.push('Quantity cannot be negative');
        quantity.style.borderColor = '#DC2626';
    }
    if (parseFloat(sellingPrice.value) < 1 && sellingPrice.value !== '') {
        errors.push('Selling price must be at least TSh 1');
        sellingPrice.style.borderColor = '#DC2626';
    }
    if (parseFloat(sellingPrice.value) < 0) {
        errors.push('Selling price cannot be negative');
        sellingPrice.style.borderColor = '#DC2626';
    }
    if (parseInt(reorderLevel.value) < 0) {
        errors.push('Reorder level cannot be negative');
        reorderLevel.style.borderColor = '#DC2626';
    }

    if (errors.length > 0) {
        e.preventDefault();
        alert('⚠️ Please fix the following errors:\n\n' + errors.join('\n'));
        return false;
    }

    return true;
});

console.log('%c💊 Braick - Add Medicine', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
console.log('%c🎨 Blue theme + Dark mode', 'font-size:13px; color:#0B5ED7;');
console.log('%c💊 Grouped by name - Auto-search enabled', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>