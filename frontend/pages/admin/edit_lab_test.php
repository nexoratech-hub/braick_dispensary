<?php
// ================================================================
// FILE: frontend/pages/admin/edit_lab_test.php
// ADMIN - EDIT LAB TEST
// BRAICK DISPENSARY - BLUE THEME
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
// GET PARAMETERS
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? $_GET['branch_id'] ?? 'all';

if ($test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH LAB TEST DETAILS
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
            b.name as branch_name
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
        LEFT JOIN branches b ON lt.branch_id = b.id
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
    $stmt = $db->prepare("
        SELECT id, full_name, status 
        FROM users 
        WHERE role = 'laboratory' AND status = 'active' AND branch_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$selected_branch_id]);
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
        
        // Update lab request status if lab_test is completed
        if ($status === 'completed') {
            // Find lab request for this test
            $stmt = $db->prepare("
                SELECT lr.id, lr.status, COUNT(lri.id) as total_items, 
                       SUM(CASE WHEN lri.status = 'completed' THEN 1 ELSE 0 END) as completed_items
                FROM lab_requests lr
                LEFT JOIN lab_request_items lri ON lr.id = lri.request_id
                WHERE lr.visit_id = ? AND lr.patient_id = ?
                GROUP BY lr.id
                ORDER BY lr.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$lab_test['visit_id'], $lab_test['patient_id']]);
            $lab_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lab_request) {
                // Check if all items in request are completed
                $all_completed = ($lab_request['total_items'] == $lab_request['completed_items']);
                
                if ($all_completed) {
                    $stmt = $db->prepare("
                        UPDATE lab_requests 
                        SET status = 'completed', 
                            completed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$lab_request['id']]);
                }
            }
        }
        
        // ================================================================
        // LOG ACTIVITY - FIXED: Use valid user_id from database
        // ================================================================
        $user_id = $_SESSION['user_id'] ?? 1;
        $branch_id = !empty($selected_branch_id) && $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1;
        
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
                // Log error but don't fail the transaction
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
                b.name as branch_name
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
            LEFT JOIN branches b ON lt.branch_id = b.id
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

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lab Test - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-hover: #1E293B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV - SHARED HEADER
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BOLDER BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
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
        
        .page-header::before {
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
        
        .page-header::after {
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
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
        
        .page-header .header-badge {
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
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 52px;
            height: 52px;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* ================================================================
           FORM ELEMENTS
           ================================================================ */
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        .form-label .label-badge {
            font-weight: 400;
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: var(--gray-100);
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row { margin-bottom: 20px; }
        .form-row:last-child { margin-bottom: 0; }
        
        /* ================================================================
           INFO BOX
           ================================================================ */
        .info-box {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border-left: 4px solid var(--primary);
            margin-bottom: 20px;
        }
        
        .info-box .info-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .info-box .info-item .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-box .info-item .value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-box .info-item .value .text-blue-600 {
            color: var(--primary) !important;
        }
        
        .info-box .info-item .value .text-gray-400 {
            color: var(--text-secondary) !important;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
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
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border-color: #34D399;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #F87171;
        }
        
        .alert i {
            font-size: 1.1rem;
        }
        
        [data-theme="dark"] .alert-success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #059669;
        }
        
        [data-theme="dark"] .alert-danger {
            background: #3A1A1A;
            color: #F87171;
            border-color: #DC2626;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .grid-2 { grid-template-columns: 1fr; gap: 14px; }
            .form-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .info-box .info-item { flex-direction: column; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
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
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .form-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

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
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Edit Lab Test
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
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
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
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
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" style="max-width:900px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB TEST INFO -->
    <!-- ================================================================ -->
    <div class="info-box" style="max-width:900px;margin:0 auto 20px;">
        <div class="info-item">
            <span class="label">Test ID</span>
            <span class="value">#<?= $test_id ?></span>
        </div>
        <div class="info-item">
            <span class="label">Patient</span>
            <span class="value">
                <?php if (!empty($lab_test['patient_id']) && !empty($lab_test['patient_name'])): ?>
                    <a href="view_patient.php?id=<?= $lab_test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="text-blue-600 hover:underline">
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
                    <a href="view_visit.php?id=<?= $lab_test['visit_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="text-blue-600 hover:underline">
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
                <span class="badge badge-<?= getStatusBadge($lab_test['status'] ?? 'pending') ?>">
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
            <span class="value" style="color:var(--success);"><?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
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
            
            <div class="grid-2">
                <!-- Status -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-info-circle label-icon"></i> Status <span class="required">*</span>
                    </label>
                    <select name="status" class="form-control" required>
                        <?php foreach ($status_options as $key => $label): ?>
                            <option value="<?= $key ?>" <?= (isset($lab_test['status']) && $lab_test['status'] === $key) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Test Price -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-money-bill-wave label-icon"></i> Test Price
                        <span class="label-badge">TSh</span>
                    </label>
                    <input type="number" name="test_price" class="form-control" 
                           value="<?= $lab_test['test_price'] ?? 0 ?>" step="100" min="0">
                </div>
            </div>
            
            <div class="grid-2">
                <!-- Reference Range -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-chart-bar label-icon"></i> Reference Range
                        <span class="label-badge">Optional</span>
                    </label>
                    <input type="text" name="reference_range" class="form-control" 
                           value="<?= htmlspecialchars($lab_test['reference_range'] ?? '') ?>" 
                           placeholder="e.g. 70-100 mg/dL">
                </div>
                
                <!-- Technician -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Lab Technician
                        <span class="label-badge">Optional</span>
                    </label>
                    <select name="technician_id" class="form-control">
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
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-file-medical-alt label-icon"></i> Results
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="results" class="form-control" rows="4" 
                          placeholder="Enter test results..."><?= htmlspecialchars($lab_test['results'] ?? '') ?></textarea>
            </div>
            
            <!-- Interpretation -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-stethoscope label-icon"></i> Interpretation
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="interpretation" class="form-control" rows="3" 
                          placeholder="Enter clinical interpretation..."><?= htmlspecialchars($lab_test['interpretation'] ?? '') ?></textarea>
            </div>
            
            <!-- Notes -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-sticky-note label-icon"></i> Notes
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="notes" class="form-control" rows="2" 
                          placeholder="Additional notes..."><?= htmlspecialchars($lab_test['notes'] ?? '') ?></textarea>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Lab Test
                </button>
                <a href="view_lab_result.php?id=<?= $test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php if (isset($lab_test['status']) && $lab_test['status'] !== 'completed' && $lab_test['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-success" onclick="markCompleted()">
                        <i class="fas fa-check-circle"></i> Mark as Completed
                    </button>
                <?php endif; ?>
                <?php if (!isset($lab_test['status']) || $lab_test['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-danger" onclick="markCancelled()" style="margin-left:auto;">
                        <i class="fas fa-times-circle"></i> Cancel Test
                    </button>
                <?php endif; ?>
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
            Edit Lab Test - <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

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

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('branch_id');
        window.location.href = url.toString();
    }

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
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        var status = document.querySelector('select[name="status"]').value;
        var results = document.querySelector('textarea[name="results"]').value.trim();
        
        if (status === 'completed' && !results) {
            e.preventDefault();
            showToast('⚠️ Warning', 'Please enter results before marking as completed', 'warning');
            document.querySelector('textarea[name="results"]').focus();
            return false;
        }
        
        return true;
    });

    console.log('%c🧪 Braick Dispensary - Edit Lab Test', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔬 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?> (ID: <?= $test_id ?>)', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($lab_test['status'] ?? 'Pending') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔵 Blue Theme Applied', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Activity logging fixed - User ID verified before insert', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>