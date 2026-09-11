<?php
// ================================================================
// FILE: frontend/pages/laboratory/pending_tests.php
// LABORATORY - PENDING TESTS
// WITH START ALL BUTTON + LIVE SEARCH IN BLUE HEADER
// BUTTONS: View (top) + Start (bottom) - Vertical
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['laboratory', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$message = '';
$message_type = '';

// HANDLE START SINGLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
    
    if ($_POST['action'] === 'start_test' && $test_id > 0) {
        try {
            $stmt = $db->prepare("SELECT id, test_name, status FROM lab_tests WHERE id = ?");
            $stmt->execute([$test_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                $message = "❌ Test not found (ID: $test_id)";
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET status = 'in_progress', lab_technician_id = ?, started_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND (status IS NULL OR status = 'pending' OR status = '')
                ");
                $stmt->execute([$user_id, $test_id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "✅ Test started: " . htmlspecialchars($test['test_name']);
                    $message_type = 'success';
                    
                    try {
                        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'lab_test_started', ?, NOW())");
                        $stmt->execute([$user_id, $user_branch_id, "Started lab test ID: {$test_id} - " . $test['test_name']]);
                    } catch (Exception $e) {}
                } else {
                    $message = "⚠️ Test already in progress.";
                    $message_type = 'warning';
                }
            }
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // HANDLE START ALL
    if ($_POST['action'] === 'start_all') {
        try {
            $stmt = $db->prepare("SELECT id FROM lab_tests WHERE status IS NULL OR status = 'pending' OR status = '' ORDER BY created_at ASC");
            $stmt->execute();
            $pending_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($pending_tests)) {
                $message = "⚠️ No pending tests to start.";
                $message_type = 'warning';
            } else {
                $test_ids = array_column($pending_tests, 'id');
                $placeholders = implode(',', array_fill(0, count($test_ids), '?'));
                
                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET status = 'in_progress', lab_technician_id = ?, started_at = NOW(), updated_at = NOW()
                    WHERE id IN ($placeholders) AND (status IS NULL OR status = 'pending' OR status = '')
                ");
                $params = array_merge([$user_id], $test_ids);
                $stmt->execute($params);
                $started_count = $stmt->rowCount();
                
                if ($started_count > 0) {
                    $message = "✅ Started $started_count test(s) successfully!";
                    $message_type = 'success';
                    
                    try {
                        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'lab_tests_bulk_started', ?, NOW())");
                        $stmt->execute([$user_id, $user_branch_id, "Started $started_count lab test(s) in bulk"]);
                    } catch (Exception $e) {}
                } else {
                    $message = "⚠️ No tests were started.";
                    $message_type = 'warning';
                }
            }
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// GET PENDING TESTS
$query = "
    SELECT 
        lt.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone, pat.gender, pat.date_of_birth,
        u.full_name as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as waiting_time
    FROM lab_tests lt
    LEFT JOIN patients pat ON lt.patient_id = pat.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    WHERE (lt.status IS NULL OR lt.status = 'pending' OR lt.status = '')
";

$params = [];

if (!empty($search)) {
    $query .= " AND (pat.full_name LIKE ? OR pat.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_filter)) {
    $query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY lt.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET COUNTS
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE (status IS NULL OR status = 'pending' OR status = '')");
$stmt->execute();
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'in_progress'");
$stmt->execute();
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_count = $pending_count + $in_progress_count;

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeClass($status) {
    $map = ['pending' => 'badge-pending', 'in_progress' => 'badge-in-progress', 'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled'];
    return $map[$status] ?? 'badge-pending';
}

function getStatusLabel($status) {
    $map = ['pending' => '⏳ Pending', 'in_progress' => '🔄 In Progress', 'completed' => '✅ Completed', 'cancelled' => '❌ Cancelled'];
    return $map[$status] ?? ucfirst($status);
}

include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Tests - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #D97706, #B45309);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(217, 119, 6, 0.25);
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
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
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
        
        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 14px;
            padding: 18px 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-decoration: none;
            display: block;
            min-height: 110px;
            border: none;
        }
        
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.15); }
        .stat-card .stat-icon { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2px; display: block; }
        .stat-card .stat-number { font-size: 1.8rem; font-weight: 700; color: white; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.7rem; color: rgba(255,255,255,0.85); font-weight: 500; margin-top: 2px; }
        .stat-card .stat-sub { font-size: 0.55rem; color: rgba(255,255,255,0.55); margin-top: 2px; }
        .stat-card .stat-arrow { position: absolute; bottom: 10px; right: 14px; font-size: 0.6rem; color: rgba(255,255,255,0.3); transition: all 0.3s ease; }
        .stat-card:hover .stat-arrow { transform: translateX(4px); color: rgba(255,255,255,0.7); }
        
        .stat-card.orange { background: linear-gradient(135deg, #F59E0B, #D97706, #B45309); }
        .stat-card.blue { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #34D399, #059669, #047857); }
        .stat-card.purple { background: linear-gradient(135deg, #A78BFA, #7C3AED, #6D28D9); }
        
        /* ACTION BAR - START ALL BUTTON */
        .action-bar {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: var(--shadow);
        }
        
        .action-bar .action-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-bar .action-info .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        
        .action-bar .action-info .info-badge.orange {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .action-bar .action-info .info-badge.blue {
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-start-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            text-decoration: none;
        }
        
        .btn-start-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }
        
        .btn-start-all:active { transform: scale(0.98); }
        .btn-start-all:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none; }
        .btn-start-all i { font-size: 1rem; }
        
        /* TABLE CONTAINER */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        /* ================================================================ */
        /* TABLE HEADER WITH SEARCH - BLUE COLOR, REDUCED HEIGHT */
        /* ================================================================ */
        .table-header-toolbar {
            padding: 8px 16px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            min-height: 48px;
        }
        
        .table-header-toolbar .toolbar-title {
            color: white;
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-header-toolbar .toolbar-title i { font-size: 0.9rem; }
        
        /* LIVE SEARCH - BLUE, SHORTER */
        .table-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            border-radius: 8px;
            padding: 0 12px;
            transition: all 0.3s ease;
            min-width: 280px;
            height: 34px;
        }
        
        .table-search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }
        
        .table-search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            margin-right: 6px;
        }
        
        .table-search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.8rem;
            padding: 0;
            font-weight: 500;
            height: 100%;
        }
        
        .table-search-wrapper input::placeholder {
            color: rgba(255,255,255,0.65);
            font-size: 0.75rem;
        }
        
        .table-search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            transition: all 0.2s ease;
            margin-left: 6px;
            flex-shrink: 0;
        }
        
        .table-search-wrapper .search-clear:hover { background: rgba(255,255,255,0.35); }
        .table-search-wrapper .search-clear.visible { display: flex; }
        
        .table-search-wrapper .search-results-count {
            color: rgba(255,255,255,0.85);
            font-size: 0.65rem;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 6px;
            white-space: nowrap;
        }
        
        .table-scroll { overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        .data-table thead th:first-child { border-radius: 0; }
        .data-table thead th:last-child { border-radius: 0; }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        /* BADGES */
        .badge-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .badge-pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-in-progress { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        [data-theme="dark"] .badge-pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .badge-in-progress { background: #1E3A5F; color: #6EA8FE; }
        
        .waiting-time {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .waiting-time.long { background: var(--danger-bg); color: var(--danger); }
        .waiting-time.medium { background: var(--warning-bg); color: var(--warning); }
        .waiting-time.short { background: var(--success-bg); color: var(--success); }
        
        [data-theme="dark"] .waiting-time.long { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .waiting-time.medium { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .waiting-time.short { background: #1A3A2A; color: #34D399; }
        
        /* ================================================================ */
        /* ACTION BUTTONS - VERTICAL (View top, Start bottom) */
        /* ================================================================ */
        .action-buttons-vertical {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: stretch;
            justify-content: center;
            min-width: 80px;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 7px;
            font-weight: 700;
            font-size: 0.68rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            width: 100%;
            height: 30px;
            letter-spacing: 0.02em;
        }
        
        .btn-action i { font-size: 0.75rem; }
        
        .btn-action.btn-view-action {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 2px 6px rgba(11, 94, 215, 0.3);
        }
        
        .btn-action.btn-view-action:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.5);
        }
        
        .btn-action.btn-start-action {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.3);
        }
        
        .btn-action.btn-start-action:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.5);
        }
        
        .btn-action:active { transform: scale(0.96); }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--success);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }
        .empty-state.no-results i { color: var(--warning); }
        
        /* TABLE FOOTER */
        .table-footer {
            padding: 10px 18px;
            border-top: 1px solid var(--border-color);
            font-size: 0.72rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer { border-color: var(--gray-700); color: var(--gray-400); background: var(--gray-800); }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .count-badge.orange { background: var(--warning); }
        
        /* TOAST */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
        .toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .toast-custom.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px 14px; min-height: 90px; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .table-search-wrapper { min-width: 100%; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .btn-start-all { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; min-height: 75px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .btn-action { font-size: 0.6rem; padding: 4px 8px; height: 28px; }
            .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Pending Tests
                <span class="role-badge-display">LABORATORY</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Manage all pending laboratory tests
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FBBF24;">
                    <i class="fas fa-clock"></i> <?= $pending_count ?> Pending
                </span>
                <span class="branch-tag" style="background:rgba(96,165,250,0.2);border-color:rgba(96,165,250,0.2);color:#93C5FD;">
                    <i class="fas fa-spinner"></i> <?= $in_progress_count ?> In Progress
                </span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-check-circle"></i> <?= $completed_today_count ?> Completed Today
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div class="stats-grid animate-fade-in-up">
        <a href="pending_tests.php" class="stat-card orange">
            <span class="stat-icon">⏳</span>
            <div class="stat-number" id="statPending"><?= $pending_count ?></div>
            <div class="stat-label">Pending Tests</div>
            <div class="stat-sub">Awaiting processing</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
        
        <a href="in_progress_tests.php" class="stat-card blue">
            <span class="stat-icon">🔄</span>
            <div class="stat-number" id="statInProgress"><?= $in_progress_count ?></div>
            <div class="stat-label">In Progress</div>
            <div class="stat-sub">Currently running</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
        
        <a href="completed_tests.php" class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="statCompletedToday"><?= $completed_today_count ?></div>
            <div class="stat-label">Completed Today</div>
            <div class="stat-sub">Tests finished</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
        
        <a href="pending_tests.php" class="stat-card purple">
            <span class="stat-icon">📋</span>
            <div class="stat-number" id="statTotal"><?= $total_count ?></div>
            <div class="stat-label">Total Active</div>
            <div class="stat-sub">Pending + In Progress</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
    </div>

    <!-- START ALL BUTTON ACTION BAR -->
    <div class="action-bar animate-fade-in-up">
        <div class="action-info">
            <span class="info-badge orange">
                <i class="fas fa-clock"></i> <?= count($tests) ?> Pending Test(s)
            </span>
            <span class="info-badge blue">
                <i class="fas fa-flask"></i> <?= $total_count ?> Total Active
            </span>
        </div>
        <div>
            <form method="POST" action="" id="startAllForm" style="display:inline;"
                  onsubmit="return confirm('⚠️ START ALL PENDING TESTS?\n\nThis will start ALL <?= count($tests) ?> pending test(s).\n\nAre you sure you want to continue?');">
                <input type="hidden" name="action" value="start_all">
                <button type="submit" class="btn-start-all" id="startAllBtn" <?= count($tests) == 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-play-circle"></i>
                    START ALL (<?= count($tests) ?>)
                </button>
            </form>
        </div>
    </div>

    <!-- TESTS TABLE -->
    <div class="table-container animate-fade-in-up">
        
        <!-- TABLE HEADER WITH LIVE SEARCH - BLUE -->
        <div class="table-header-toolbar">
            <div class="toolbar-title">
                <i class="fas fa-list"></i>
                Pending Tests List
                <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;" id="headerCount">
                    <?= count($tests) ?>
                </span>
            </div>
            <div class="table-search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="tableSearch" 
                       placeholder="Search patient, test, ID..." 
                       autocomplete="off"
                       value="<?= htmlspecialchars($search) ?>">
                <span class="search-results-count" id="searchResultsCount" style="display:none;"></span>
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll">
            <table class="data-table" id="testTable">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th><i class="fas fa-flask"></i> Test</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-clock"></i> Waiting</th>
                        <th><i class="fas fa-calendar"></i> Requested</th>
                        <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="testTableBody">
                    <?php if (count($tests) > 0): ?>
                        <?php $i = 1; foreach ($tests as $test): 
                            $waiting = $test['waiting_time'] ?? 0;
                            if ($waiting < 15) {
                                $waiting_class = 'short';
                                $waiting_text = $waiting < 1 ? 'Just now' : $waiting . ' min';
                            } elseif ($waiting < 45) {
                                $waiting_class = 'medium';
                                $waiting_text = $waiting . ' min';
                            } else {
                                $waiting_class = 'long';
                                $waiting_text = $waiting < 60 ? $waiting . ' min' : floor($waiting / 60) . 'h ' . ($waiting % 60) . 'm';
                            }
                            $status = $test['status'] ?? 'pending';
                            $age = calculateAge($test['date_of_birth'] ?? '');
                        ?>
                            <tr class="test-row" 
                                data-id="<?= $test['id'] ?>"
                                data-search="<?= htmlspecialchars(strtolower($test['test_name'] . ' ' . $test['patient_name'] . ' ' . $test['patient_code'] . ' ' . ($test['doctor_name'] ?? '') . ' ' . $test['phone'])) ?>">
                                <td class="row-number"><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></div>
                                    <?php if (!empty($test['test_price']) && $test['test_price'] > 0): ?>
                                        <div class="text-xs text-gray-400">TSh <?= number_format($test['test_price']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400">
                                        <?= htmlspecialchars($test['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    </div>
                                    <?php if (!empty($test['phone'])): ?>
                                        <div class="text-xs text-gray-400">📱 <?= htmlspecialchars($test['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm">Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['specialty'] ?? 'GP') ?></div>
                                    <?php if (!empty($test['visit_number'])): ?>
                                        <div class="text-xs text-gray-400">Visit: <?= htmlspecialchars($test['visit_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($status) ?>">
                                        <?= getStatusLabel($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="waiting-time <?= $waiting_class ?>">
                                        <i class="fas <?= $waiting_class === 'long' ? 'fa-exclamation-circle' : ($waiting_class === 'medium' ? 'fa-clock' : 'fa-check-circle') ?>"></i>
                                        <?= $waiting_text ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= formatDate($test['created_at'] ?? '') ?></td>
                                <td>
                                    <!-- VERTICAL BUTTONS: View (top), Start (bottom) -->
                                    <div class="action-buttons-vertical">
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn-action btn-view-action" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <form method="POST" action="" style="display:block;margin:0;" 
                                              onsubmit="return confirm('Start this test?\n\nTest: <?= addslashes($test['test_name'] ?? 'N/A') ?>\nPatient: <?= addslashes($test['patient_name'] ?? 'Unknown') ?>');">
                                            <input type="hidden" name="action" value="start_test">
                                            <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                                            <button type="submit" class="btn-action btn-start-action" title="Start Test">
                                                <i class="fas fa-play"></i> Start
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No pending tests found</p>
                                    <p class="sub">All tests have been processed ✅</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr id="noSearchResults" style="display:none;">
                        <td colspan="8">
                            <div class="empty-state no-results">
                                <i class="fas fa-search"></i>
                                <p>No tests match your search</p>
                                <p class="sub">Try a different keyword</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- TABLE FOOTER -->
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong id="visibleCount"><?= count($tests) ?></strong> of <strong id="totalRecords"><?= count($tests) ?></strong> pending test(s)
                <span class="text-xs text-gray-400 ml-2">🏥 <?= htmlspecialchars($user_branch_name) ?></span>
            </span>
            <span>
                <span class="count-badge orange" id="totalCountBadge"><?= $total_count ?></span> Total active
                <span class="text-xs text-gray-400 ml-2" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pending Tests
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // DARK MODE
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // SIDEBAR
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // LIVE SEARCH
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var testRows = document.querySelectorAll('.test-row');
    var noSearchResults = document.getElementById('noSearchResults');
    var visibleCount = document.getElementById('visibleCount');
    var totalRecords = document.getElementById('totalRecords');
    var headerCount = document.getElementById('headerCount');
    
    var totalTestCount = testRows.length;
    if (totalRecords) totalRecords.textContent = totalTestCount;
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        var visibleRows = 0;
        
        testRows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleRows++;
                var rowNum = row.querySelector('.row-number');
                if (rowNum) rowNum.textContent = visibleRows;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (visibleRows === 0 && query !== '') {
            noSearchResults.style.display = '';
        } else {
            noSearchResults.style.display = 'none';
        }
        
        if (visibleCount) visibleCount.textContent = visibleRows;
        if (headerCount) headerCount.textContent = visibleRows;
        
        if (query !== '') {
            searchResultsCount.textContent = visibleRows + ' found';
            searchResultsCount.style.display = 'inline-block';
        } else {
            searchResultsCount.style.display = 'none';
        }
        
        var startAllBtn = document.getElementById('startAllBtn');
        if (startAllBtn) {
            startAllBtn.disabled = (visibleRows === 0);
        }
    }
    
    if (tableSearch) {
        tableSearch.addEventListener('input', performLiveSearch);
        tableSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                tableSearch.value = '';
                performLiveSearch();
                tableSearch.blur();
            }
        });
    }
    
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            tableSearch.value = '';
            performLiveSearch();
            tableSearch.focus();
        });
    }
    
    if (tableSearch && tableSearch.value.trim() !== '') {
        performLiveSearch();
    }
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (tableSearch) {
                tableSearch.focus();
                tableSearch.select();
            }
        }
    });

    // DATE & TIME
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
        
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) updateTimeDisplay.textContent = 'Last update: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // TOAST
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c🧪 Braick - Pending Tests', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Blue header with search', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Vertical buttons: View (top) + Start (bottom)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Start All button at top', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Live search - type to filter', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Pending: <?= $pending_count ?> | In Progress: <?= $in_progress_count ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>