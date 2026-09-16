<?php
// ================================================================
// FILE: frontend/pages/admin/edit_lab_test.php
// ADMIN - EDIT LAB TEST
// BRAICK DISPENSARY - USING EXISTING DB TABLES
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
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
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
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? $_GET['branch_id'] ?? 'all';

if ($test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH LAB TEST DETAILS - USING CORRECT TABLE STRUCTURE
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            u2.full_name as technician_name,
            v.visit_number,
            v.visit_type,
            v.id as visit_id,
            b.name as branch_name,
            ltc.test_name as catalog_test_name,
            ltc.category as test_category,
            ltc.reference_range as catalog_reference_range
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON lt.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$test_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab_test) {
        header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching lab test: " . $e->getMessage());
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// GET TECHNICIANS FOR THIS BRANCH
// ================================================================
$technicians = [];
try {
    $branch_for_tech = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) 
        ? (int)$selected_branch_id 
        : $lab_test['branch_id'];
    
    $stmt = $db->prepare("
        SELECT id, full_name, status 
        FROM users 
        WHERE role = 'laboratory' AND status = 'active' AND branch_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$branch_for_tech]);
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $technicians = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$update_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_lab_test') {
    $status = $_POST['status'] ?? 'pending';
    $results = trim($_POST['results'] ?? '');
    $reference_range = trim($_POST['reference_range'] ?? '');
    $interpretation = trim($_POST['interpretation'] ?? '');
    $technician_id = isset($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $test_price = isset($_POST['test_price']) ? floatval($_POST['test_price']) : 0;
    
    try {
        $db->beginTransaction();
        
        // Validate status
        $allowed_status = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed_status)) {
            throw new Exception('Invalid status');
        }
        
        // Build update query
        $update_fields = [];
        $params = [];
        
        $update_fields[] = "status = ?";
        $params[] = $status;
        
        $update_fields[] = "results = ?";
        $params[] = $results;
        
        $update_fields[] = "reference_range = ?";
        $params[] = $reference_range;
        
        $update_fields[] = "interpretation = ?";
        $params[] = $interpretation;
        
        $update_fields[] = "notes = ?";
        $params[] = $notes;
        
        $update_fields[] = "test_price = ?";
        $params[] = $test_price;
        
        // Update technician if provided
        if ($technician_id > 0) {
            $update_fields[] = "lab_technician_id = ?";
            $params[] = $technician_id;
        }
        
        // If status is completed, set completed_at
        if ($status === 'completed') {
            $update_fields[] = "completed_at = NOW()";
        } elseif ($status === 'in_progress') {
            $update_fields[] = "completed_at = NULL";
        }
        
        $update_fields[] = "updated_at = NOW()";
        $params[] = $test_id;
        
        $sql = "UPDATE lab_tests SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Update visit status if lab test is completed or in progress
        if ($status === 'completed' || $status === 'in_progress') {
            $visit_id = $lab_test['visit_id'];
            if ($visit_id) {
                // Check if all lab tests for this visit are completed
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM lab_tests 
                    WHERE visit_id = ?
                ");
                $stmt->execute([$visit_id]);
                $test_stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($test_stats && $test_stats['total'] > 0 && $test_stats['total'] == $test_stats['completed']) {
                    // All tests completed - update visit status
                    $stmt = $db->prepare("
                        UPDATE visits 
                        SET status = 'lab_completed', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$visit_id]);
                } else {
                    // Some tests still pending
                    $stmt = $db->prepare("
                        UPDATE visits 
                        SET status = 'lab_test', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$visit_id]);
                }
            }
        }
        
        // ================================================================
        // LOG ACTIVITY
        // ================================================================
        $branch_id = !empty($selected_branch_id) && $selected_branch_id !== 'all' ? (int)$selected_branch_id : $lab_test['branch_id'] ?? $user_branch_id;
        
        // Verify user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_exists = $stmt->fetch();
        
        if ($user_exists) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'lab_test_updated', ?, NOW())
                ");
                $details = "Lab test #{$test_id} updated - Status: {$status} | Test: {$lab_test['test_name']} | Patient: {$lab_test['patient_name']}";
                $stmt->execute([$user_id, $branch_id, $details]);
            } catch (Exception $log_error) {
                error_log("Activity log error: " . $log_error->getMessage());
            }
        }
        
        $db->commit();
        
        $update_success = true;
        $message = "✅ Lab test updated successfully!";
        $message_type = 'success';
        
        // Refresh lab test data
        $stmt = $db->prepare("
            SELECT 
                lt.*,
                p.id as patient_id,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                u.full_name as doctor_name,
                u2.full_name as technician_name,
                v.visit_number,
                v.visit_type,
                v.id as visit_id,
                b.name as branch_name,
                ltc.test_name as catalog_test_name,
                ltc.category as test_category
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON lt.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
            LEFT JOIN branches b ON lt.branch_id = b.id
            LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
            WHERE lt.id = ?
        ");
        $stmt->execute([$test_id]);
        $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Update lab test error: " . $e->getMessage());
    }
}

// ================================================================
// GET STATUS OPTIONS
// ================================================================
$status_options = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
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
        --lab-primary: #0B5ED7;
        --lab-primary-dark: #0A4CA8;
        --lab-primary-light: #3B82F6;
        --lab-primary-bg: #EFF6FF;
        --lab-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --lab-primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
        --lab-success: #059669;
        --lab-success-dark: #047857;
        --lab-success-light: #34D399;
        --lab-success-bg: #D1FAE5;
        --lab-danger: #DC2626;
        --lab-danger-dark: #B91C1C;
        --lab-danger-light: #F87171;
        --lab-danger-bg: #FEE2E2;
        --lab-warning: #D97706;
        --lab-warning-bg: #FEF3C7;
        --lab-purple: #7C3AED;
        --lab-purple-bg: #EDE9FE;
        --lab-gray-50: #F8FAFC;
        --lab-gray-100: #F1F5F9;
        --lab-gray-200: #E2E8F0;
        --lab-gray-300: #CBD5E1;
        --lab-gray-400: #94A3B8;
        --lab-gray-500: #64748B;
        --lab-gray-600: #475569;
        --lab-gray-700: #334155;
        --lab-gray-800: #1E293B;
        --lab-gray-900: #0F172A;
        --lab-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --lab-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --lab-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --lab-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --lab-bg-body: #F0F4F8;
        --lab-bg-card: #FFFFFF;
        --lab-text-primary: #1E293B;
        --lab-text-secondary: #64748B;
        --lab-border-color: #E2E8F0;
        --lab-radius: 12px;
        --lab-radius-lg: 18px;
    }

    [data-theme="dark"] {
        --lab-bg-body: #0F172A;
        --lab-bg-card: #1E293B;
        --lab-text-primary: #F1F5F9;
        --lab-text-secondary: #94A3B8;
        --lab-border-color: #334155;
        --lab-primary: #3B82F6;
        --lab-primary-dark: #2563EB;
        --lab-primary-light: #60A5FA;
        --lab-primary-bg: #1E3A5F;
        --lab-primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
        --lab-primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
        --lab-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --lab-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --lab-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
        background: var(--lab-primary-gradient-strong);
        border-radius: var(--lab-radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
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

    .page-header-custom::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.03);
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
        transition: all 0.3s ease;
    }

    .page-header-custom .header-badge:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-1px);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--lab-radius);
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
       FORM CARD
       ================================================================ */
    .form-card-custom {
        background: var(--lab-bg-card);
        border-radius: var(--lab-radius-lg);
        padding: 32px 36px;
        border: 2px solid var(--lab-border-color);
        transition: all 0.3s ease;
        max-width: 900px;
        margin: 0 auto 24px;
        box-shadow: var(--lab-shadow-md);
    }

    .form-card-custom:hover {
        border-color: var(--lab-primary);
        box-shadow: var(--lab-shadow-lg);
    }

    .form-card-custom .form-header-custom {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--lab-border-color);
    }

    .form-card-custom .form-header-custom .form-icon {
        width: 52px;
        height: 52px;
        background: var(--lab-primary-gradient);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        flex-shrink: 0;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
    }

    .form-card-custom .form-header-custom .form-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--lab-text-primary);
        margin: 0;
    }

    .form-card-custom .form-header-custom .form-subtitle {
        font-size: 0.8rem;
        color: var(--lab-text-secondary);
        margin-top: 2px;
    }

    /* ================================================================
       FORM ELEMENTS
       ================================================================ */
    .form-label-custom {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--lab-text-primary);
        margin-bottom: 5px;
        display: block;
    }

    .form-label-custom .required { color: var(--lab-danger); margin-left: 2px; }
    .form-label-custom .label-icon { margin-right: 4px; color: var(--lab-primary); }
    .form-label-custom .label-badge {
        font-weight: 400;
        font-size: 0.6rem;
        padding: 1px 10px;
        border-radius: 12px;
        background: var(--lab-gray-100);
        color: var(--lab-text-secondary);
        margin-left: 6px;
    }

    [data-theme="dark"] .form-label-custom .label-badge {
        background: #334155;
        color: #94A3B8;
    }

    .form-control-custom {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--lab-border-color);
        border-radius: var(--lab-radius);
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--lab-bg-card);
        color: var(--lab-text-primary);
        font-family: inherit;
    }

    .form-control-custom:focus {
        border-color: var(--lab-primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    .form-control-custom::placeholder {
        color: var(--lab-text-secondary);
        opacity: 0.5;
    }

    .form-control-custom:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    select.form-control-custom {
        appearance: auto;
        cursor: pointer;
    }

    [data-theme="dark"] select.form-control-custom option {
        background: #1E293B;
        color: #F1F5F9;
    }

    textarea.form-control-custom {
        resize: vertical;
        min-height: 80px;
    }

    .grid-2-custom {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-row-custom { margin-bottom: 20px; }
    .form-row-custom:last-child { margin-bottom: 0; }

    /* ================================================================
       INFO BOX
       ================================================================ */
    .info-box-custom {
        background: var(--lab-primary-bg);
        border-radius: var(--lab-radius);
        padding: 16px 20px;
        border-left: 4px solid var(--lab-primary);
        margin-bottom: 20px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .info-box-custom .info-item {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.85rem;
        flex-wrap: wrap;
        gap: 4px;
    }

    .info-box-custom .info-item .label {
        color: var(--lab-text-secondary);
        font-weight: 500;
    }

    .info-box-custom .info-item .value {
        font-weight: 600;
        color: var(--lab-text-primary);
    }

    .info-box-custom .info-item .value a {
        color: var(--lab-primary);
        text-decoration: none;
    }

    .info-box-custom .info-item .value a:hover {
        text-decoration: underline;
    }

    .info-box-custom .info-item .value .text-gray-400 {
        color: var(--lab-text-secondary) !important;
        font-weight: 400;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success-custom { background: #059669; }
    .badge-danger-custom { background: #DC2626; }
    .badge-warning-custom { background: #D97706; color: #1E293B; }
    .badge-info-custom { background: #0B5ED7; }
    .badge-secondary-custom { background: #64748B; }
    .badge-purple-custom { background: #7C3AED; }

    [data-theme="dark"] .badge-warning-custom { color: #1E293B; }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: var(--lab-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-custom:hover {
        transform: translateY(-2px);
    }

    .btn-primary-custom {
        background: var(--lab-primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary-custom:hover {
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-success-custom {
        background: var(--lab-success);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }

    .btn-success-custom:hover {
        box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        color: white;
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--lab-text-secondary);
        border: 2px solid var(--lab-border-color);
    }

    .btn-outline-custom:hover {
        border-color: var(--lab-primary);
        color: var(--lab-primary);
    }

    .btn-danger-custom {
        background: var(--lab-danger);
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    .btn-danger-custom:hover {
        box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        color: white;
    }

    .form-actions-custom {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--lab-border-color);
        flex-wrap: wrap;
    }

    /* ================================================================
       ALERT
       ================================================================ */
    .alert-custom {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 0.82rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
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
        background: #D1FAE5;
        color: #065F46;
        border-color: #34D399;
    }

    .alert-custom.alert-danger {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #F87171;
    }

    .alert-custom i {
        font-size: 1.1rem;
        flex-shrink: 0;
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
       FOOTER
       ================================================================ */
    .footer-custom {
        padding: 14px 0;
        border-top: 2px solid var(--lab-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--lab-text-secondary);
    }

    .footer-custom .footer-brand {
        color: var(--lab-primary);
        font-weight: 700;
    }

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
        .grid-2-custom { grid-template-columns: 1fr; gap: 14px; }
        .form-card-custom { padding: 16px; }
        .form-actions-custom { flex-direction: column; }
        .form-actions-custom .btn-custom { width: 100%; justify-content: center; }
        .info-box-custom .info-item { flex-direction: column; }
    }

    @media (max-width: 480px) {
        .page-header-custom { flex-direction: column; align-items: flex-start !important; }
        .form-card-custom { padding: 14px 16px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Edit Lab Test
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? $lab_test['catalog_test_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= isset($lab_test['status']) && $lab_test['status'] === 'completed' ? 'check-circle' : 'clock' ?>"></i>
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_lab_result.php?id=<?= $test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-custom alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB TEST INFO -->
    <!-- ================================================================ -->
    <div class="info-box-custom">
        <div class="info-item">
            <span class="label">Test ID</span>
            <span class="value">#<?= $test_id ?></span>
        </div>
        <div class="info-item">
            <span class="label">Test Name</span>
            <span class="value"><?= htmlspecialchars($lab_test['test_name'] ?? $lab_test['catalog_test_name'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Category</span>
            <span class="value"><?= htmlspecialchars($lab_test['test_category'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Patient</span>
            <span class="value">
                <?php if (!empty($lab_test['patient_id']) && !empty($lab_test['patient_name'])): ?>
                    <a href="view_patient.php?id=<?= $lab_test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>">
                        <?= htmlspecialchars($lab_test['patient_name']) ?>
                    </a>
                    <?php if (!empty($lab_test['patient_code'])): ?>
                        (<?= htmlspecialchars($lab_test['patient_code']) ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Visit</span>
            <span class="value">
                <?php if (!empty($lab_test['visit_number']) && !empty($lab_test['visit_id'])): ?>
                    <a href="view_visit.php?id=<?= $lab_test['visit_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>">
                        <?= htmlspecialchars($lab_test['visit_number']) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Doctor</span>
            <span class="value">
                <?php if (!empty($lab_test['doctor_name'])): ?>
                    Dr. <?= htmlspecialchars($lab_test['doctor_name']) ?>
                <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Technician</span>
            <span class="value">
                <?php if (!empty($lab_test['technician_name'])): ?>
                    <?= htmlspecialchars($lab_test['technician_name']) ?>
                <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Status</span>
            <span class="value">
                <span class="badge-custom badge-<?= getStatusBadge($lab_test['status'] ?? 'pending') ?>-custom">
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Price</span>
            <span class="value">TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?></span>
        </div>
        <div class="info-item">
            <span class="label">Created</span>
            <span class="value"><?= date('M d, Y h:i A', strtotime($lab_test['created_at'] ?? 'now')) ?></span>
        </div>
        <?php if (!empty($lab_test['completed_at'])): ?>
        <div class="info-item">
            <span class="label">Completed</span>
            <span class="value" style="color:var(--lab-success);"><?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="form-card-custom animate-fade-in-up">
        <div class="form-header-custom">
            <div class="form-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div>
                <h3 class="form-title">Edit Lab Test Details</h3>
                <p class="form-subtitle">Update test results, status and other information</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update_lab_test">
            
            <div class="grid-2-custom">
                <!-- Status -->
                <div class="form-row-custom">
                    <label class="form-label-custom">
                        <i class="fas fa-info-circle label-icon"></i> Status <span class="required">*</span>
                    </label>
                    <select name="status" class="form-control-custom" required>
                        <?php foreach ($status_options as $key => $label): ?>
                            <option value="<?= $key ?>" <?= (isset($lab_test['status']) && $lab_test['status'] === $key) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Test Price -->
                <div class="form-row-custom">
                    <label class="form-label-custom">
                        <i class="fas fa-money-bill-wave label-icon"></i> Test Price
                        <span class="label-badge">TSh</span>
                    </label>
                    <input type="number" name="test_price" class="form-control-custom" 
                           value="<?= $lab_test['test_price'] ?? 0 ?>" step="100" min="0">
                </div>
            </div>
            
            <div class="grid-2-custom">
                <!-- Reference Range -->
                <div class="form-row-custom">
                    <label class="form-label-custom">
                        <i class="fas fa-chart-bar label-icon"></i> Reference Range
                        <span class="label-badge">Optional</span>
                    </label>
                    <input type="text" name="reference_range" class="form-control-custom" 
                           value="<?= htmlspecialchars($lab_test['reference_range'] ?? $lab_test['catalog_reference_range'] ?? '') ?>" 
                           placeholder="e.g. 70-100 mg/dL">
                </div>
                
                <!-- Technician -->
                <div class="form-row-custom">
                    <label class="form-label-custom">
                        <i class="fas fa-user-md label-icon"></i> Lab Technician
                        <span class="label-badge">Optional</span>
                    </label>
                    <select name="technician_id" class="form-control-custom">
                        <option value="">-- Select Technician --</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['id'] ?>" <?= (isset($lab_test['lab_technician_id']) && $lab_test['lab_technician_id'] == $tech['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tech['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Results -->
            <div class="form-row-custom">
                <label class="form-label-custom">
                    <i class="fas fa-file-medical-alt label-icon"></i> Results
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="results" class="form-control-custom" rows="4" 
                          placeholder="Enter test results..."><?= htmlspecialchars($lab_test['results'] ?? '') ?></textarea>
            </div>
            
            <!-- Interpretation -->
            <div class="form-row-custom">
                <label class="form-label-custom">
                    <i class="fas fa-stethoscope label-icon"></i> Interpretation
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="interpretation" class="form-control-custom" rows="3" 
                          placeholder="Enter clinical interpretation..."><?= htmlspecialchars($lab_test['interpretation'] ?? '') ?></textarea>
            </div>
            
            <!-- Notes -->
            <div class="form-row-custom">
                <label class="form-label-custom">
                    <i class="fas fa-sticky-note label-icon"></i> Notes
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="notes" class="form-control-custom" rows="2" 
                          placeholder="Additional notes..."><?= htmlspecialchars($lab_test['notes'] ?? '') ?></textarea>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions-custom">
                <button type="submit" class="btn-custom btn-primary-custom">
                    <i class="fas fa-save"></i> Update Lab Test
                </button>
                <a href="view_lab_result.php?id=<?= $test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-custom btn-outline-custom">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php if (isset($lab_test['status']) && $lab_test['status'] !== 'completed' && $lab_test['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn-custom btn-success-custom" onclick="markCompleted()">
                        <i class="fas fa-check-circle"></i> Mark as Completed
                    </button>
                <?php endif; ?>
                <?php if (!isset($lab_test['status']) || $lab_test['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn-custom btn-danger-custom" onclick="markCancelled()" style="margin-left:auto;">
                        <i class="fas fa-times-circle"></i> Cancel Test
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-custom">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Lab Test - <?= htmlspecialchars($lab_test['test_name'] ?? $lab_test['catalog_test_name'] ?? 'N/A') ?>
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
    // MARK AS COMPLETED
    // ================================================================
    function markCompleted() {
        if (confirm('Mark this lab test as COMPLETED?\n\nThis will update the status to completed and set completion date.')) {
            document.querySelector('select[name="status"]').value = 'completed';
            document.getElementById('editForm').submit();
        }
    }

    // ================================================================
    // MARK AS CANCELLED
    // ================================================================
    function markCancelled() {
        if (confirm('⚠️ Cancel this lab test?\n\nThis action cannot be undone.')) {
            document.querySelector('select[name="status"]').value = 'cancelled';
            document.getElementById('editForm').submit();
        }
    }

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        var status = document.querySelector('select[name="status"]').value;
        var results = document.querySelector('textarea[name="results"]').value.trim();
        
        if (status === 'completed' && !results) {
            e.preventDefault();
            alert('⚠️ Please enter results before marking as completed');
            document.querySelector('textarea[name="results"]').focus();
            return false;
        }
        
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

    console.log('%c🧪 Braick Dispensary - Edit Lab Test', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔬 Test: <?= htmlspecialchars($lab_test['test_name'] ?? $lab_test['catalog_test_name'] ?? 'N/A') ?> (ID: <?= $test_id ?>)', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($lab_test['status'] ?? 'Pending') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>