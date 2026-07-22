<?php
// ================================================================
// FILE: frontend/pages/laboratory/in_progress.php
// LABORATORY - IN PROGRESS REQUESTS (TABLE DESIGN)
// WITH VIEW & ADD RESULT BUTTONS - OPENS NEW PAGES
// FIXED: Summary cards with background colors
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Lab Technician
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
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
$message = '';
$message_type = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// HANDLE POST ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // UPDATE LAB TEST STATUS
    // ================================================================
    if ($action === 'update_status') {
        $lab_test_id = (int)($_POST['lab_test_id'] ?? 0);
        $status = $_POST['status'] ?? 'completed';
        $notes = trim($_POST['notes'] ?? '');
        
        if ($lab_test_id > 0) {
            try {
                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET status = ?, notes = ?, updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$status, $notes, $lab_test_id, $user_branch_id]);
                
                // Check if all tests for this visit are completed
                $stmt = $db->prepare("SELECT visit_id FROM lab_tests WHERE id = ?");
                $stmt->execute([$lab_test_id]);
                $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lab_test) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as total,
                               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                        FROM lab_tests 
                        WHERE visit_id = ?
                    ");
                    $stmt->execute([$lab_test['visit_id']]);
                    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($counts['total'] > 0 && $counts['total'] == $counts['completed']) {
                        $stmt = $db->prepare("
                            UPDATE lab_requests 
                            SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                            WHERE visit_id = ?
                        ");
                        $stmt->execute([$lab_test['visit_id']]);
                    }
                }
                
                $message = "✅ Status updated successfully!";
                $message_type = 'success';
                
                echo '<script>
                    showToast("✅ Success", "Status updated successfully!", "success");
                    setTimeout(function(){ window.location.href = "in_progress.php?success=1"; }, 1500);
                </script>';
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET IN PROGRESS LAB TESTS
// ================================================================
$lab_tests_list = [];
$total_in_progress = 0;

try {
    $query = "
        SELECT lt.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.gender,
               p.date_of_birth,
               u.full_name as doctor_name,
               v.visit_number,
               v.visit_type,
               lr.request_number,
               lr.id as request_id,
               lr.status as request_status,
               lr.requested_at,
               lr.accepted_at,
               lr.completed_at,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = lt.visit_id) as total_tests,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = lt.visit_id AND status = 'completed') as completed_tests
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN lab_requests lr ON lt.visit_id = lr.visit_id
        WHERE lt.branch_id = ? AND lt.status = 'in_progress'
    ";
    $params = [$user_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ? OR lr.request_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY lt.created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $lab_tests_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_in_progress = count($lab_tests_list);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $lab_tests_list = [];
}

// ================================================================
// GET PENDING COUNT
// ================================================================
$pending_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// GET COMPLETED TODAY COUNT
// ================================================================
$completed_today = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $completed_today = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// GET TOTAL TESTS
// ================================================================
$total_tests = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_tests = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {}

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
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <title>In Progress - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           STYLES
           ================================================================ */
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
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
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
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
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
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           STATS CARDS - WITH BACKGROUND COLORS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card-custom {
            border-radius: 14px;
            padding: 20px 24px;
            color: white;
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            cursor: default;
        }
        
        .stat-card-custom::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        
        .stat-card-custom::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .stat-card-custom:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card-custom .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 4px;
            display: block;
            opacity: 0.9;
        }
        
        .stat-card-custom .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
            line-height: 1.2;
        }
        
        .stat-card-custom .stat-label {
            font-size: 0.75rem;
            opacity: 0.85;
            font-weight: 500;
            display: block;
            margin-top: 2px;
        }
        
        .stat-card-custom .stat-sub {
            font-size: 0.65rem;
            opacity: 0.7;
            display: block;
            margin-top: 4px;
        }
        
        /* Card Colors */
        .stat-card-custom.blue {
            background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        }
        
        .stat-card-custom.orange {
            background: linear-gradient(135deg, #D97706, #F59E0B);
        }
        
        .stat-card-custom.green {
            background: linear-gradient(135deg, #059669, #10B981);
        }
        
        .stat-card-custom.purple {
            background: linear-gradient(135deg, #7C3AED, #8B5CF6);
        }
        
        [data-theme="dark"] .stat-card-custom.blue {
            background: linear-gradient(135deg, #1E3A5F, #0B5ED7);
        }
        
        [data-theme="dark"] .stat-card-custom.orange {
            background: linear-gradient(135deg, #3D2E0A, #D97706);
        }
        
        [data-theme="dark"] .stat-card-custom.green {
            background: linear-gradient(135deg, #1A3A2A, #059669);
        }
        
        [data-theme="dark"] .stat-card-custom.purple {
            background: linear-gradient(135deg, #2D1B5F, #7C3AED);
        }
        
        /* ================================================================
           TABLE STYLES
           ================================================================ */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .table-container thead {
            background: var(--primary);
            color: white;
        }
        
        .table-container thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table-container thead th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        .table-container thead th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        .table-container tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-container tbody tr:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .table-container tbody tr:hover {
            background: #1E3A5F;
        }
        
        .table-container tbody td {
            padding: 10px 16px;
            vertical-align: middle;
            color: var(--text-primary);
        }
        
        .table-container tbody tr:last-child {
            border-bottom: none;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
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
        
        .status-badge.in_progress {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .status-badge.pending {
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.65rem;
            border-radius: 4px;
        }
        
        .btn-xs {
            padding: 2px 6px;
            font-size: 0.6rem;
            border-radius: 3px;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
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
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
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
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        
        /* ================================================================
           FOOTER
           ================================================================ */
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
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        [data-theme="dark"] .empty-state i {
            color: var(--gray-600);
        }
        
        .text-truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }
        
        .patient-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .patient-avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        .progress-bar {
            height: 4px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        
        .progress-bar .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-bar .progress-fill.primary { background: var(--primary); }
        .progress-bar .progress-fill.success { background: var(--success); }
        .progress-bar .progress-fill.warning { background: var(--warning); }
        
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }
        .text-danger { color: var(--danger); }
        .font-mono { font-family: monospace; }
        .font-semibold { font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .stat-card-custom {
                padding: 14px 16px;
            }
            .stat-card-custom .stat-number {
                font-size: 1.5rem;
            }
            .table-container thead th { padding: 8px 10px; font-size: 0.6rem; }
            .table-container tbody td { padding: 6px 10px; font-size: 0.7rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .page-title { font-size: 1.1rem; }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .stat-card-custom {
                padding: 10px 14px;
            }
            .stat-card-custom .stat-number {
                font-size: 1.2rem;
            }
            .stat-card-custom .stat-icon {
                font-size: 1.2rem;
            }
            .table-container tbody td { padding: 4px 6px; font-size: 0.65rem; }
            .btn { padding: 2px 6px; font-size: 0.55rem; }
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
            <input type="text" id="searchInput" placeholder="Search in progress..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-spinner"></i>
                In Progress
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">LABORATORY</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                Manage lab tests currently in progress
                <span class="header-badge">
                    <i class="fas fa-clock"></i>
                    <?= $total_in_progress ?> In Progress
                </span>
                <span class="header-badge">
                    <i class="fas fa-hourglass-start"></i>
                    <?= $pending_count ?> Pending
                </span>
                <span class="header-badge">
                    <i class="fas fa-check-circle"></i>
                    <?= $completed_today ?> Completed Today
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="pending_requests.php" class="btn-outline-light" style="background:rgba(255,255,255,0.2);">
                <i class="fas fa-clock"></i> Pending (<?= $pending_count ?>)
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
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
    <!-- STATS CARDS - WITH BACKGROUND COLORS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        
        <!-- Card 1: Pending - Orange -->
        <div class="stat-card-custom orange">
            <span class="stat-icon">⏳</span>
            <span class="stat-number" id="statPending"><?= $pending_count ?></span>
            <span class="stat-label">Pending Tests</span>
            <span class="stat-sub">Awaiting processing</span>
        </div>
        
        <!-- Card 2: In Progress - Blue -->
        <div class="stat-card-custom blue">
            <span class="stat-icon">🔄</span>
            <span class="stat-number" id="statInProgress"><?= $total_in_progress ?></span>
            <span class="stat-label">In Progress</span>
            <span class="stat-sub">Currently being processed</span>
        </div>
        
        <!-- Card 3: Completed Today - Green -->
        <div class="stat-card-custom green">
            <span class="stat-icon">✅</span>
            <span class="stat-number" id="statCompletedToday"><?= $completed_today ?></span>
            <span class="stat-label">Completed Today</span>
            <span class="stat-sub">Tests finished today</span>
        </div>
        
        <!-- Card 4: Total Tests - Purple -->
        <div class="stat-card-custom purple">
            <span class="stat-icon">🧪</span>
            <span class="stat-number" id="statTotal"><?= $total_tests ?></span>
            <span class="stat-label">Total Tests</span>
            <span class="stat-sub">All time</span>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TABLE: IN PROGRESS TESTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                In Progress Lab Tests
                <span class="text-sm font-normal text-gray-400">(<?= $total_in_progress ?> tests)</span>
            </h3>
            <span class="text-xs text-gray-400" id="lastUpdateTime">⏱ Auto-update</span>
        </div>
        
        <?php if (count($lab_tests_list) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Test Name</th>
                            <th>Doctor</th>
                            <th>Visit</th>
                            <th>Request #</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($lab_tests_list as $test): 
                            $total = $test['total_tests'] ?? 1;
                            $completed = $test['completed_tests'] ?? 0;
                            $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
                            $color = '#' . substr(md5($test['patient_name'] ?? 'Unknown'), 0, 6);
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="patient-cell">
                                        <div class="patient-avatar-sm" style="background: <?= $color ?>;">
                                            <?= strtoupper(substr($test['patient_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-sm"><?= htmlspecialchars($test['patient_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-sm"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></div>
                                    <?php if (!empty($test['test_type'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm">Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <span class="text-xs font-mono"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></span>
                                    <div class="text-xs text-gray-400"><?= ucfirst($test['visit_type'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="text-xs font-mono"><?= htmlspecialchars($test['request_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-400"><?= $percent ?>%</span>
                                        <div class="progress-bar flex-1">
                                            <div class="progress-fill <?= $percent >= 100 ? 'success' : ($percent >= 50 ? 'primary' : 'warning') ?>" style="width: <?= $percent ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400"><?= $completed ?> / <?= $total ?> completed</div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center;">
                                        <!-- View Button -->
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-outline btn-xs" title="View Test Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- Add Result Button -->
                                        <a href="add_result.php?id=<?= $test['id'] ?>" class="btn btn-primary btn-xs" title="Add Result">
                                            <i class="fas fa-edit"></i> Result
                                        </a>
                                        
                                        <!-- Quick Status Update -->
                                        <?php if (($test['status'] ?? '') !== 'completed'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="lab_test_id" value="<?= $test['id'] ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-success btn-xs" title="Mark as Completed" onclick="return confirm('Mark this test as completed?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer Info -->
            <div class="mt-3 text-xs text-gray-400 flex justify-between items-center flex-wrap gap-2">
                <span><i class="fas fa-info-circle mr-1"></i> Showing <?= $total_in_progress ?> test(s) in progress</span>
                <span><i class="fas fa-clock mr-1"></i> Last updated: <?= date('h:i:s A') ?></span>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <h3>No In Progress Tests</h3>
                <p>All lab tests have been completed or are pending.</p>
                <p class="text-xs text-gray-400 mt-2">
                    <i class="fas fa-info-circle"></i> Check <a href="pending_requests.php" class="text-primary hover:underline">Pending Tests</a>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            In Progress
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
        var footer = document.getElementById('footerTimestamp');
        if (footer) {
            footer.textContent = 'Last updated: ' + timeStr;
        }
        var updateTime = document.getElementById('lastUpdateTime');
        if (updateTime) {
            updateTime.textContent = '⏱ ' + timeStr;
        }
        var updateBadge = document.getElementById('updateBadge');
        if (updateBadge) {
            updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'in_progress.php?search=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // AUTO-UPDATE (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;

    function fetchUpdates() {
        if (isUpdating) return;
        isUpdating = true;
        
        var branchId = <?= json_encode($user_branch_id) ?>;
        var url = '/dispensary_system/frontend/api/get_lab_tests_status.php?branch_id=' + branchId + '&status=in_progress&t=' + new Date().getTime();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.hash && data.hash !== lastHash) {
                        lastHash = data.hash;
                        window.location.reload();
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Auto-update error:', error);
                isUpdating = false;
            });
    }

    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        fetchUpdates();
        updateInterval = setInterval(fetchUpdates, 3000);
        console.log('🔄 Auto-update started (every 3s)');
    }

    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('⏹️ Auto-update stopped');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    console.log('%c🧪 Laboratory - In Progress (TABLE DESIGN)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📊 Total In Progress: <?= $total_in_progress ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 View: view_test.php?id=X', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✏️ Add Result: add_result.php?id=X', 'font-size:13px; color:#D97706;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
    console.log('%c🎨 Summary cards with background colors', 'font-size:13px; color:#059669;');
</script>

</body>
</html>