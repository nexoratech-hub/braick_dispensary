<?php
// ================================================================
// FILE: frontend/pages/laboratory/add_request_result.php
// LABORATORY - ADD RESULTS FOR LAB REQUEST
// WITH COMMON RESULTS DROPDOWN + CUSTOM INPUT
// FIXED: Custom result now works correctly
// FIXED: Dark mode - patient details visible
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Lab Technician
// ================================================================
if (!isset($_SESSION['user_id'] ) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

// ================================================================
// GET REQUEST ID
// ================================================================
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
    header('Location: in_progress.php?error=invalid_request');
    exit;
}

$message = '';
$message_type = '';
$show_error = false;

// ================================================================
// COMMON RESULTS DROPDOWN OPTIONS
// ================================================================
$common_results = [
    // General
    'Normal' => 'Normal',
    'Abnormal' => 'Abnormal',
    'Positive' => 'Positive',
    'Negative' => 'Negative',
    'Not Detected' => 'Not Detected',
    'Detected' => 'Detected',
    'Reactive' => 'Reactive',
    'Non-Reactive' => 'Non-Reactive',
    'Inconclusive' => 'Inconclusive',
    'Equivocal' => 'Equivocal',
    
    // Blood related
    'High' => 'High',
    'Low' => 'Low',
    'Elevated' => 'Elevated',
    'Decreased' => 'Decreased',
    'Within Range' => 'Within Range',
    'Out of Range' => 'Out of Range',
    
    // Microbiology
    'No Growth' => 'No Growth',
    'Growth' => 'Growth',
    'Mixed Growth' => 'Mixed Growth',
    'Heavy Growth' => 'Heavy Growth',
    'Moderate Growth' => 'Moderate Growth',
    'Scanty Growth' => 'Scanty Growth',
    'Sterile' => 'Sterile',
    'Contaminated' => 'Contaminated',
    
    // Specific Tests
    'Malaria Positive' => 'Malaria Positive',
    'Malaria Negative' => 'Malaria Negative',
    'HIV Positive' => 'HIV Positive',
    'HIV Negative' => 'HIV Negative',
    'COVID-19 Positive' => 'COVID-19 Positive',
    'COVID-19 Negative' => 'COVID-19 Negative',
    'Pregnant' => 'Pregnant',
    'Not Pregnant' => 'Not Pregnant',
    'Diabetic' => 'Diabetic',
    'Non-Diabetic' => 'Non-Diabetic',
    
    // Quantitative
    'Within Normal Limits' => 'Within Normal Limits',
    'Borderline' => 'Borderline',
    'Critical' => 'Critical',
    'Panic Value' => 'Panic Value'
];

// ================================================================
// FUNCTION TO REFRESH DATA
// ================================================================
function refreshData($request_id, $db, &$request, &$request_items, &$total_items, &$completed_items) {
    try {
        $stmt = $db->prepare("
            SELECT lr.*, 
                   p.full_name as patient_name,
                   p.patient_id as patient_code,
                   p.phone,
                   p.gender,
                   p.date_of_birth,
                   p.address,
                   p.blood_group,
                   u.full_name as doctor_name,
                   u2.full_name as technician_name,
                   v.visit_number,
                   v.visit_type,
                   v.visit_date
            FROM lab_requests lr
            JOIN visits v ON lr.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lr.doctor_id = u.id
            LEFT JOIN users u2 ON lr.lab_technician_id = u2.id
            WHERE lr.id = ? AND lr.branch_id = ?
        ");
        $stmt->execute([$request_id, $_SESSION['branch_id'] ?? 1]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            SELECT lri.*, ltc.reference_range, ltc.category
            FROM lab_request_items lri
            LEFT JOIN lab_tests_catalog ltc ON lri.test_id = ltc.id
            WHERE lri.request_id = ?
            ORDER BY lri.id ASC
        ");
        $stmt->execute([$request_id]);
        $request_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_items = count($request_items);
        $completed_items = 0;
        foreach ($request_items as $item) {
            if ($item['status'] === 'completed') {
                $completed_items++;
            }
        }
    } catch (Exception $e) {
        // Keep existing data
    }
}

// ================================================================
// GET REQUEST DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lr.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.gender,
               p.date_of_birth,
               p.address,
               p.blood_group,
               u.full_name as doctor_name,
               u2.full_name as technician_name,
               v.visit_number,
               v.visit_type,
               v.visit_date
        FROM lab_requests lr
        JOIN visits v ON lr.visit_id = v.id
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lr.doctor_id = u.id
        LEFT JOIN users u2 ON lr.lab_technician_id = u2.id
        WHERE lr.id = ? AND lr.branch_id = ?
    ");
    $stmt->execute([$request_id, $user_branch_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        header('Location: in_progress.php?error=request_not_found');
        exit;
    }
    
    // Get request items
    $stmt = $db->prepare("
        SELECT lri.*, ltc.reference_range, ltc.category
        FROM lab_request_items lri
        LEFT JOIN lab_tests_catalog ltc ON lri.test_id = ltc.id
        WHERE lri.request_id = ?
        ORDER BY lri.id ASC
    ");
    $stmt->execute([$request_id]);
    $request_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_items = count($request_items);
    $completed_items = 0;
    foreach ($request_items as $item) {
        if ($item['status'] === 'completed') {
            $completed_items++;
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $request = null;
    $request_items = [];
    $total_items = 0;
    $completed_items = 0;
}

// ================================================================
// HANDLE FORM SUBMISSION - FIXED
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_results') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    // MUHIMU: Angalia BOTH result NA result_custom
    $result = trim($_POST['result'] ?? '');
    $result_custom = trim($_POST['result_custom'] ?? '');
    $comments = trim($_POST['comments'] ?? '');
    $reference_range = trim($_POST['reference_range'] ?? '');
    $status = $_POST['status'] ?? 'completed';
    
    // If custom result is provided, USE IT
    if (!empty($result_custom)) {
        $result = $result_custom;
    }
    
    // Also check if there's a result_final (hidden field from JavaScript)
    if (empty($result)) {
        $result_final = trim($_POST['result_final_' . $item_id] ?? '');
        if (!empty($result_final)) {
            $result = $result_final;
        }
    }
    
    // Check if result is empty
    if ($item_id > 0 && empty($result)) {
        $message = "⚠️ Please enter a result value";
        $message_type = 'warning';
        $show_error = true;
        refreshData($request_id, $db, $request, $request_items, $total_items, $completed_items);
    } else if ($item_id > 0 && !empty($result)) {
        try {
            $db->beginTransaction();
            
            // Update lab_request_item
            $stmt = $db->prepare("
                UPDATE lab_request_items 
                SET result = ?,
                    comments = ?,
                    reference_range = ?,
                    status = ?,
                    performed_by = ?,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND request_id = ?
            ");
            $stmt->execute([
                $result,
                $comments,
                $reference_range,
                $status,
                $user_id,
                $item_id,
                $request_id
            ]);
            
            // Check if all items are completed
            $stmt = $db->prepare("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM lab_request_items 
                WHERE request_id = ?
            ");
            $stmt->execute([$request_id]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $all_completed = ($counts['total'] > 0 && $counts['total'] == $counts['completed']);
            
            // Update lab_requests status
            if ($all_completed) {
                $stmt = $db->prepare("
                    UPDATE lab_requests 
                    SET status = 'completed', 
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$request_id]);
                
                // Also update lab_tests for this visit
                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET status = 'completed', 
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE visit_id = ? AND status IN ('pending', 'in_progress')
                ");
                $stmt->execute([$request['visit_id']]);
            } else {
                $stmt = $db->prepare("
                    UPDATE lab_requests 
                    SET status = 'in_progress', 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$request_id]);
            }
            
            $db->commit();
            
            $message = "✅ Result saved successfully!";
            $message_type = 'success';
            
            // Refresh data
            refreshData($request_id, $db, $request, $request_items, $total_items, $completed_items);
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// SAVE ALL PENDING RESULTS - FIXED
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all']) && $_POST['save_all'] == 1) {
    try {
        $db->beginTransaction();
        $saved_count = 0;
        $error_count = 0;
        
        foreach ($request_items as $item) {
            if ($item['status'] !== 'completed') {
                // MUHIMU: Angalia BOTH result_ NA result_custom_
                $result = trim($_POST['result_' . $item['id']] ?? '');
                $result_custom = trim($_POST['result_custom_' . $item['id']] ?? '');
                $result_final = trim($_POST['result_final_' . $item['id']] ?? '');
                $comments = trim($_POST['comments_' . $item['id']] ?? '');
                
                // Use custom result if provided
                if (!empty($result_custom)) {
                    $result = $result_custom;
                }
                if (empty($result) && !empty($result_final)) {
                    $result = $result_final;
                }
                
                if (!empty($result)) {
                    $stmt = $db->prepare("
                        UPDATE lab_request_items 
                        SET result = ?,
                            comments = ?,
                            status = 'completed',
                            performed_by = ?,
                            completed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ? AND request_id = ?
                    ");
                    $stmt->execute([
                        $result,
                        $comments,
                        $user_id,
                        $item['id'],
                        $request_id
                    ]);
                    $saved_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        // Check if all items are completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM lab_request_items 
            WHERE request_id = ?
        ");
        $stmt->execute([$request_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $all_completed = ($counts['total'] > 0 && $counts['total'] == $counts['completed']);
        
        // Update lab_requests status
        if ($all_completed) {
            $stmt = $db->prepare("
                UPDATE lab_requests 
                SET status = 'completed', 
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            
            // Also update lab_tests for this visit
            $stmt = $db->prepare("
                UPDATE lab_tests 
                SET status = 'completed', 
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE visit_id = ? AND status IN ('pending', 'in_progress')
            ");
            $stmt->execute([$request['visit_id']]);
        }
        
        $db->commit();
        
        if ($saved_count > 0) {
            $message = "✅ $saved_count result(s) saved successfully!";
            if ($error_count > 0) {
                $message .= " ⚠️ $error_count test(s) skipped (no result entered)";
            }
            $message_type = 'success';
        } else {
            $message = "⚠️ No results to save. Please enter results first.";
            $message_type = 'warning';
        }
        
        // Refresh data
        refreshData($request_id, $db, $request, $request_items, $total_items, $completed_items);
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Results - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-dark: #0F172A;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-dark: #F1F5F9;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --primary-light: #6EA8FE;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            border-radius: 10px;
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
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
        
        .page-header::before {
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
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
        
        .page-header .btn-outline-light {
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i { color: var(--primary); }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group:last-child { margin-bottom: 0; }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .status-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .status-badge.in_progress {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .status-badge.pending {
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin: 12px 0;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
        }
        
        .items-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .items-table tbody tr.completed td {
            background: var(--success-bg);
        }
        
        .result-input {
            min-width: 200px;
        }
        
        .result-select {
            min-width: 180px;
            cursor: pointer;
        }
        
        .result-custom-input {
            margin-top: 4px;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
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
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
        /* ================================================================
           PATIENT INFO GRID - FIXED FOR DARK MODE
           ================================================================ */
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            background: var(--primary-bg);
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border: 1px solid var(--primary-light);
        }
        
        [data-theme="dark"] .patient-info-grid {
            background: #1E3A5F;
            border-color: #0B5ED7;
        }
        
        .patient-info-grid .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .patient-info-grid .info-item .label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        [data-theme="dark"] .patient-info-grid .info-item .label {
            color: #9EC5FE;
        }
        
        .patient-info-grid .info-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        [data-theme="dark"] .patient-info-grid .info-item .value {
            color: #F1F5F9;
        }
        
        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .progress-indicator .bar {
            flex: 1;
            height: 6px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            min-width: 100px;
        }
        
        [data-theme="dark"] .progress-indicator .bar {
            background: var(--gray-700);
        }
        
        .progress-indicator .bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-indicator .bar .fill.success { background: var(--success); }
        .progress-indicator .bar .fill.warning { background: var(--warning); }
        .progress-indicator .bar .fill.primary { background: var(--primary); }
        
        /* Custom Select Styling */
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .result-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .result-row .select-wrapper {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .result-row .select-wrapper select {
            flex: 1;
            min-width: 150px;
        }
        
        .result-row .select-wrapper .or-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .result-row .custom-input {
            margin-top: 4px;
        }
        
        .result-row .custom-input input {
            width: 100%;
            padding: 6px 10px;
            border: 2px dashed var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--bg-body);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .result-row .custom-input input:focus {
            border-color: var(--primary);
            border-style: solid;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .result-row .custom-input input::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .card { padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .form-row-2 {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .patient-info-grid {
                grid-template-columns: 1fr 1fr;
            }
            .items-table { font-size: 0.7rem; }
            .items-table thead th, .items-table tbody td { padding: 4px 6px; }
            .result-input { min-width: 100px; }
            .result-select { min-width: 100px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .result-row .select-wrapper {
                flex-direction: column;
                align-items: stretch;
            }
            .result-row .select-wrapper .or-text {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-title { font-size: 1.1rem; }
            .card { padding: 12px; }
            .patient-info-grid {
                grid-template-columns: 1fr;
            }
            .form-control { font-size: 0.75rem; padding: 6px 10px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
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
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Add Results
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">LABORATORY</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                Enter results for lab request <strong>#<?= htmlspecialchars($request['request_number'] ?? 'N/A') ?></strong>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);color:white;">
                    <i class="fas fa-check-circle"></i>
                    <?= $completed_items ?> / <?= $total_items ?> completed
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="in_progress.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="view_request.php?id=<?= $request_id ?>" class="btn-outline-light" style="background:rgba(255,255,255,0.2);">
                <i class="fas fa-eye"></i> View Request
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" id="alertMessage">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MAIN CARD -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask"></i>
                Request: <?= htmlspecialchars($request['request_number'] ?? 'N/A') ?>
            </h3>
            <div class="progress-indicator">
                <span><?= $completed_items ?> / <?= $total_items ?></span>
                <div class="bar">
                    <div class="fill <?= $completed_items == $total_items ? 'success' : ($completed_items > 0 ? 'primary' : 'warning') ?>" 
                         style="width: <?= $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0 ?>%;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Information - FIXED FOR DARK MODE -->
        <div class="patient-info-grid">
            <div class="info-item">
                <span class="label">Patient</span>
                <span class="value"><?= htmlspecialchars($request['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Patient ID</span>
                <span class="value"><?= htmlspecialchars($request['patient_code'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Gender / Age</span>
                <span class="value"><?= htmlspecialchars($request['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Doctor</span>
                <span class="value">Dr. <?= htmlspecialchars($request['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Visit Number</span>
                <span class="value"><?= htmlspecialchars($request['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status-badge <?= $request['status'] ?? 'pending' ?>">
                        <?= ucfirst($request['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- ITEMS TABLE WITH RESULTS - WITH COMMON RESULTS DROPDOWN -->
        <!-- ================================================================ -->
        <form method="POST" action="" id="resultsForm">
            <input type="hidden" name="action" value="save_results">
            
            <div style="overflow-x:auto;">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:30px;">#</th>
                            <th>Test Name</th>
                            <th>Reference Range</th>
                            <th style="min-width:250px;">Result</th>
                            <th style="min-width:150px;">Comments</th>
                            <th style="text-align:center;width:100px;">Status</th>
                            <th style="text-align:center;width:40px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($request_items) > 0): ?>
                            <?php $i = 1; foreach ($request_items as $item): 
                                $is_completed = $item['status'] === 'completed';
                                $row_class = $is_completed ? 'completed' : '';
                                $current_result = htmlspecialchars($item['result'] ?? '');
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-semibold text-sm"><?= htmlspecialchars($item['test_name'] ?? 'N/A') ?></div>
                                    <?php if (!empty($item['category'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['category']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['reference_range'])): ?>
                                        <span class="text-xs font-mono"><?= htmlspecialchars($item['reference_range']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_completed): ?>
                                        <div class="font-semibold text-success"><?= $current_result ?></div>
                                        <input type="hidden" name="result_<?= $item['id'] ?>" value="<?= $current_result ?>">
                                    <?php else: ?>
                                        <div class="result-row">
                                            <div class="select-wrapper">
                                                <select name="result_<?= $item['id'] ?>" class="form-control result-select" 
                                                        style="font-size:0.8rem;padding:6px 10px;min-width:150px;"
                                                        onchange="handleResultSelect(this, <?= $item['id'] ?>)">
                                                    <option value="">-- Select Result --</option>
                                                    <option value="" disabled style="border-bottom:1px solid #ddd;">──────────</option>
                                                    <?php foreach ($common_results as $key => $value): ?>
                                                        <option value="<?= htmlspecialchars($value) ?>" <?= $current_result == $value ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($value) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="" disabled style="border-bottom:1px solid #ddd;">──────────</option>
                                                    <option value="__custom__">✏️ Enter Custom Result</option>
                                                </select>
                                                <span class="or-text">OR</span>
                                            </div>
                                            <div class="custom-input" id="custom_input_<?= $item['id'] ?>" style="<?= $current_result && !in_array($current_result, array_values($common_results)) ? 'display:block;' : 'display:none;' ?>">
                                                <input type="text" name="result_custom_<?= $item['id'] ?>" 
                                                       class="form-control" 
                                                       style="font-size:0.8rem;padding:6px 10px;border:2px dashed var(--primary);"
                                                       placeholder="Type custom result..."
                                                       value="<?= !in_array($current_result, array_values($common_results)) ? $current_result : '' ?>"
                                                       oninput="handleCustomInput(this, <?= $item['id'] ?>)">
                                            </div>
                                            <input type="hidden" name="result_final_<?= $item['id'] ?>" value="<?= $current_result ?>">
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_completed): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['comments'] ?? '') ?></div>
                                        <input type="hidden" name="comments_<?= $item['id'] ?>" value="<?= htmlspecialchars($item['comments'] ?? '') ?>">
                                    <?php else: ?>
                                        <input type="text" name="comments_<?= $item['id'] ?>" class="form-control" 
                                               style="font-size:0.8rem;padding:6px 10px;min-width:120px;"
                                               placeholder="Comments..."
                                               value="<?= htmlspecialchars($item['comments'] ?? '') ?>">
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $is_completed ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $is_completed ? '✅ Completed' : '⏳ Pending' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!$is_completed): ?>
                                        <button type="submit" name="item_id" value="<?= $item['id'] ?>" 
                                                class="btn btn-success btn-sm" 
                                                onclick="return confirm('Save result for <?= htmlspecialchars($item['test_name'])?>?')">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-success">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:20px;color:var(--text-secondary);">
                                    <i class="fas fa-info-circle"></i> No test items found for this request
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($completed_items < $total_items): ?>
                <div class="form-actions">
                    <button type="submit" name="save_all" value="1" class="btn btn-primary" 
                            onclick="return confirm('Save all pending results?')">
                        <i class="fas fa-save"></i> Save All Pending
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <a href="in_progress.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            <?php else: ?>
                <div class="form-actions">
                    <div style="width:100%;text-align:center;padding:12px;background:var(--success-bg);border-radius:var(--radius);border:2px solid var(--success);">
                        <i class="fas fa-check-circle" style="color:var(--success);font-size:1.2rem;"></i>
                        <strong style="color:var(--success);"> All tests completed! </strong>
                        <span class="text-sm text-gray-500"> This request is now complete.</span>
                    </div>
                    <a href="in_progress.php" class="btn btn-primary" style="margin:0 auto;">
                        <i class="fas fa-arrow-left"></i> Back to In Progress
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add Results
            <span class="text-gray-300 mx-2">|</span>
            Request #<?= htmlspecialchars($request['request_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // HANDLE RESULT SELECT - Show/Hide custom input
    // ================================================================
    function handleResultSelect(select, itemId) {
        var customInput = document.getElementById('custom_input_' + itemId);
        var customField = document.querySelector('input[name="result_custom_' + itemId + '"]');
        var hiddenField = document.querySelector('input[name="result_final_' + itemId + '"]');
        
        if (select.value === '__custom__') {
            customInput.style.display = 'block';
            if (customField) {
                customField.focus();
                customField.value = '';
            }
            if (hiddenField) {
                hiddenField.value = '';
            }
        } else if (select.value !== '') {
            customInput.style.display = 'none';
            if (customField) {
                customField.value = '';
            }
            if (hiddenField) {
                hiddenField.value = select.value;
            }
        } else {
            customInput.style.display = 'none';
            if (customField) {
                customField.value = '';
            }
            if (hiddenField) {
                hiddenField.value = '';
            }
        }
    }

    // ================================================================
    // HANDLE CUSTOM INPUT - Auto-update when typing
    // ================================================================
    function handleCustomInput(input, itemId) {
        var select = document.querySelector('select[name="result_' + itemId + '"]');
        var hiddenField = document.querySelector('input[name="result_final_' + itemId + '"]');
        
        if (input.value.trim() !== '') {
            // If custom input has value, deselect dropdown and update hidden field
            if (select) {
                select.value = '';
            }
            if (hiddenField) {
                hiddenField.value = input.value.trim();
            }
        } else {
            if (hiddenField) {
                hiddenField.value = '';
            }
        }
    }

    // ================================================================
    // VALIDATE FORM BEFORE SUBMIT - FIXED
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                // Check if there are any pending items with no result
                var pendingRows = document.querySelectorAll('tr:not(.completed)');
                var hasError = false;
                var isSaveAll = form.querySelector('input[name="save_all"]');
                
                pendingRows.forEach(function(row) {
                    var select = row.querySelector('select[name^="result_"]');
                    var customInput = row.querySelector('input[name^="result_custom_"]');
                    var hiddenField = row.querySelector('input[name^="result_final_"]');
                    
                    // Check if there's a value in either select, custom, or hidden
                    var hasValue = false;
                    if (select && select.value !== '' && select.value !== '__custom__') {
                        hasValue = true;
                    }
                    if (customInput && customInput.value.trim() !== '') {
                        hasValue = true;
                    }
                    if (hiddenField && hiddenField.value.trim() !== '') {
                        hasValue = true;
                    }
                    
                    if (!hasValue && !isSaveAll) {
                        hasError = true;
                        row.style.backgroundColor = '#fee2e2';
                        setTimeout(function() {
                            row.style.backgroundColor = '';
                        }, 3000);
                    }
                });
                
                if (hasError) {
                    e.preventDefault();
                    showToast('⚠️ Error', 'Please enter a result for all pending tests or use "Save All"', 'warning');
                }
            });
        });
    });

    // ================================================================
    // FIX: When clicking Save button, ensure custom result is captured
    // ================================================================
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('button[type="submit"]');
        if (btn && btn.name === 'item_id') {
            var row = btn.closest('tr');
            if (row) {
                var select = row.querySelector('select[name^="result_"]');
                var customInput = row.querySelector('input[name^="result_custom_"]');
                var hiddenField = row.querySelector('input[name^="result_final_"]');
                
                // If custom input has value, use it
                if (customInput && customInput.value.trim() !== '') {
                    if (hiddenField) {
                        hiddenField.value = customInput.value.trim();
                    }
                    if (select) {
                        select.value = '';
                    }
                }
                
                // If select has a value (not custom), use it
                if (select && select.value !== '' && select.value !== '__custom__') {
                    if (hiddenField) {
                        hiddenField.value = select.value;
                    }
                }
            }
        }
    });

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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'add_request_result.php?id=<?= $request_id ?>&search=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // AUTO-TOAST FOR MESSAGE
    // ================================================================
    <?php if ($message): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : '❌ Error' ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c🧪 Add Results with Common Results Dropdown', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Request: <?= htmlspecialchars($request['request_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($request['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🧪 Tests: <?= $total_items ?> (<?= $completed_items ?> completed)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Common Results: <?= count($common_results) ?> options available', 'font-size:13px; color:#D97706;');
    console.log('%c✏️ Select from dropdown OR type custom result', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode - Patient details now visible', 'font-size:13px; color:#6EA8FE;');
</script>

</body>
</html>