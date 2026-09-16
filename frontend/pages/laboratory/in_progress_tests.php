<?php
// ================================================================
// FILE: frontend/pages/laboratory/in_progress_tests.php
// LABORATORY - IN PROGRESS TESTS (GROUPED BY PATIENT) - BLUE THEME
// ================================================================
// ✅ GROUPED BY PATIENT - Kila patient ana card yake
// ✅ LIVE SEARCH - Filter patients kwa jina, ID, test, doctor
// ✅ BLUE THEME - Rangi zote ni blue
// ✅ Same style as pending_tests.php
// ✅ NEW: Success message baada ya ku-submit result
// ✅ NEW: Haendi completed_tests.php - inabaki hapo hapo
// ✅ NEW: Progress indicator kwa kila patient
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
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
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

$message = '';
$message_type = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// ✅ CHECK SESSION FLASH MESSAGE (Kutoka add_result.php)
// ================================================================
if (isset($_SESSION['lab_message'])) {
    $message = $_SESSION['lab_message'];
    $message_type = $_SESSION['lab_message_type'] ?? 'success';
    unset($_SESSION['lab_message']);
    unset($_SESSION['lab_message_type']);
}

// ================================================================
// GET IN PROGRESS DATA
// ================================================================
$lab_items = [];
$total_in_progress = 0;

try {
    $query = "
        SELECT 
            lt.id as test_id,
            lt.visit_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.test_name,
            lt.test_price,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.status as test_status,
            lt.branch_id,
            lt.created_at,
            lt.started_at,
            lt.updated_at,
            lt.formatted_result,
            lt.printed_at,
            lt.printed_by,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            v.visit_type,
            v.diagnosis,
            v.status as visit_status
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.branch_id = ? 
        AND lt.status = 'in_progress'
    ";
    $params = [$user_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY p.full_name ASC, lt.started_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $all_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_in_progress = count($all_tests);
    
} catch (Exception $e) {
    error_log("In Progress error: " . $e->getMessage());
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $all_tests = [];
}

// ================================================================
// ✅ GROUP TESTS BY PATIENT
// ================================================================
$patients = [];
foreach ($all_tests as $test) {
    $patient_id = $test['patient_id'] ?? 0;
    
    if (!isset($patients[$patient_id])) {
        $patients[$patient_id] = [
            'patient_id' => $patient_id,
            'patient_name' => $test['patient_name'] ?? 'Unknown Patient',
            'patient_code' => $test['patient_code'] ?? 'N/A',
            'phone' => $test['phone'] ?? '',
            'gender' => $test['gender'] ?? '',
            'date_of_birth' => $test['date_of_birth'] ?? '',
            'blood_group' => $test['blood_group'] ?? '',
            'visit_number' => $test['visit_number'] ?? '',
            'visit_type' => $test['visit_type'] ?? '',
            'doctor_name' => $test['doctor_name'] ?? 'N/A',
            'specialty' => $test['doctor_specialty'] ?? 'GP',
            'tests' => [],
            'total_waiting' => 0,
            'longest_waiting' => 0
        ];
    }
    
    // Calculate waiting time (from started_at)
    $waiting = 0;
    if (!empty($test['started_at'])) {
        $waiting = round((time() - strtotime($test['started_at'])) / 60);
    } elseif (!empty($test['created_at'])) {
        $waiting = round((time() - strtotime($test['created_at'])) / 60);
    }
    
    $test['waiting_time'] = $waiting;
    
    $patients[$patient_id]['tests'][] = $test;
    $patients[$patient_id]['total_waiting'] += $waiting;
    if ($waiting > $patients[$patient_id]['longest_waiting']) {
        $patients[$patient_id]['longest_waiting'] = $waiting;
    }
}

$patients_array = array_values($patients);
foreach ($patients_array as &$p) {
    $p['test_count'] = count($p['tests']);
    $p['avg_waiting'] = $p['test_count'] > 0 ? round($p['total_waiting'] / $p['test_count']) : 0;
}
unset($p);

// ================================================================
// STATISTICS
// ================================================================
$pending_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND (status IS NULL OR status = 'pending')");
    $stmt->execute([$user_branch_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

$in_progress_total_count = $total_in_progress;

$completed_today = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

$total_tests = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

$total_patients = count($patients_array);

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
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

function getWaitingClass($waiting) {
    if ($waiting < 15) return 'short';
    if ($waiting < 45) return 'medium';
    return 'long';
}

function getWaitingText($waiting) {
    if ($waiting < 1) return 'Just now';
    if ($waiting < 60) return $waiting . ' min';
    return floor($waiting / 60) . 'h ' . ($waiting % 60) . 'm';
}

function getWaitingIcon($waiting) {
    if ($waiting < 15) return 'fa-check-circle';
    if ($waiting < 45) return 'fa-clock';
    return 'fa-exclamation-circle';
}

include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In Progress Tests - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-darker: #083C8A;
            --primary-light: #6EA8FE;
            --primary-lighter: #93C5FD;
            --primary-bg: #E8F0FE;
            --primary-bg-dark: #1E3A5F;
            --info: #3B82F6;
            --info-dark: #2563EB;
            --info-bg: #DBEAFE;
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
        
        /* ================================================================ */
        /* ✅ SUCCESS MESSAGE BOX - ILIYOONGEZWA */
        /* ================================================================ */
        .message-box {
            padding: 16px 24px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            font-size: 0.95rem;
            font-weight: 500;
            animation: slideDown 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 5px solid;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
        }
        
        .message-box.success {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #065F46;
            border-left-color: #059669;
        }
        
        .message-box.error {
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            color: #991B1B;
            border-left-color: #DC2626;
        }
        
        .message-box.info {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: #1E40AF;
            border-left-color: #3B82F6;
        }
        
        [data-theme="dark"] .message-box.success {
            background: linear-gradient(135deg, #1A3A2A, #065F46);
            color: #A7F3D0;
        }
        
        [data-theme="dark"] .message-box.error {
            background: linear-gradient(135deg, #3A1A1A, #991B1B);
            color: #FECACA;
        }
        
        [data-theme="dark"] .message-box.info {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            color: #BFDBFE;
        }
        
        .message-box i.msg-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .message-box .msg-content {
            flex: 1;
            line-height: 1.6;
        }
        
        .message-box .msg-content strong {
            font-weight: 700;
            font-size: 1.02rem;
            display: block;
            margin-bottom: 4px;
        }
        
        .message-box .msg-content .msg-hint {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(0,0,0,0.05);
            padding: 6px 12px;
            border-radius: 8px;
        }
        
        [data-theme="dark"] .message-box .msg-content .msg-hint {
            background: rgba(255,255,255,0.1);
        }
        
        .message-box .msg-close {
            background: rgba(0,0,0,0.1);
            border: none;
            color: inherit;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        
        .message-box .msg-close:hover {
            background: rgba(0,0,0,0.2);
            transform: rotate(90deg);
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        /* PAGE HEADER - BLUE */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
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
        
        /* ALL BLUE VARIANTS */
        .stat-card.blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
        .stat-card.blue-2 { background: linear-gradient(135deg, #0EA5E9, #0284C7, #075985); }
        .stat-card.blue-3 { background: linear-gradient(135deg, #0B5ED7, #083C8A, #062E6B); }
        .stat-card.blue-4 { background: linear-gradient(135deg, #4F46E5, #4338CA, #3730A3); }
        
        /* ACTION BAR */
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
        
        .action-bar .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        
        .action-bar .info-badge.blue {
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .action-bar .info-badge.info {
            background: var(--info-bg);
            color: var(--info);
            border: 1px solid var(--info);
        }
        
        .action-bar .info-badge.purple {
            background: var(--purple-bg);
            color: var(--purple);
            border: 1px solid var(--purple);
        }
        
        /* SEARCH TOOLBAR */
        .search-toolbar {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
        }
        
        .search-toolbar .toolbar-title {
            color: white;
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-toolbar .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            border-radius: 10px;
            padding: 0 14px;
            transition: all 0.3s ease;
            min-width: 320px;
            height: 40px;
        }
        
        .search-toolbar .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }
        
        .search-toolbar .search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-right: 8px;
        }
        
        .search-toolbar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.85rem;
            padding: 0;
            font-weight: 500;
        }
        
        .search-toolbar .search-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .search-toolbar .search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            transition: all 0.2s ease;
            margin-left: 8px;
        }
        
        .search-toolbar .search-wrapper .search-clear.visible { display: flex; }
        .search-toolbar .search-wrapper .search-clear:hover { background: rgba(255,255,255,0.35); }
        
        .search-results-count {
            color: rgba(255,255,255,0.9);
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 12px;
            margin-left: 8px;
            white-space: nowrap;
            display: none;
        }
        
        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        /* ✅ HIGHLIGHT PATIENT CARD YA TEST ILIYOMALIZIKA */
        .patient-card.just-updated {
            border-color: var(--success);
            animation: successPulse 2s ease;
        }
        
        @keyframes successPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); }
            50% { box-shadow: 0 0 0 12px rgba(5, 150, 105, 0.15); }
        }
        
        /* PATIENT HEADER - BLUE */
        .patient-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .patient-header:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
        }
        
        .patient-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 250px;
        }
        
        .patient-header .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(4px);
        }
        
        .patient-header .patient-name {
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            opacity: 0.9;
            flex-wrap: wrap;
            margin-top: 3px;
        }
        
        .patient-header .patient-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-header .patient-stats {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-stats .stat-pill {
            background: rgba(255,255,255,0.2);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .patient-header .chevron {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .patient-header .chevron.rotated {
            transform: rotate(180deg);
        }
        
        .patient-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            background: var(--bg-card);
        }
        
        .patient-body.open {
            max-height: 5000px;
            padding: 16px 22px 20px;
        }
        
        /* PATIENT ACTIONS */
        .patient-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        
        .patient-actions .patient-actions-info {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* TABLE */
        .table-scroll { overflow-x: auto; border-radius: 10px; }
        
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
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
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
        
        .badge-in-progress { 
            background: var(--warning-bg); 
            color: var(--warning); 
            border: 1px solid var(--warning); 
        }
        
        [data-theme="dark"] .badge-in-progress { 
            background: #3D2E0A; 
            color: #FBBF24; 
            border-color: #D97706;
        }
        
        /* WAITING TIME */
        .waiting-time {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .waiting-time.long { 
            background: #FEE2E2; 
            color: #DC2626; 
        }
        
        .waiting-time.medium { 
            background: #FEF3C7; 
            color: #D97706; 
        }
        
        .waiting-time.short { 
            background: var(--info-bg); 
            color: var(--info); 
        }
        
        [data-theme="dark"] .waiting-time.long { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .waiting-time.medium { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .waiting-time.short { background: #1E3A5F; color: #93C5FD; }
        
        /* ACTION BUTTONS */
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
        }
        
        .btn-action i { font-size: 0.75rem; }
        
        .btn-action.btn-view-action {
            background: linear-gradient(135deg, #64748B, #475569);
            color: white;
            box-shadow: 0 2px 6px rgba(100, 116, 139, 0.3);
        }
        
        .btn-action.btn-view-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.5);
        }
        
        .btn-action.btn-result-action {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 2px 6px rgba(11, 94, 215, 0.3);
        }
        
        .btn-action.btn-result-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.5);
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--success);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }
        .empty-state.no-results i { color: var(--primary); }
        
        /* PROGRESS BAR */
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
            .search-toolbar { flex-direction: column; align-items: stretch; }
            .search-toolbar .search-wrapper { min-width: 100%; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .patient-header { flex-direction: column; align-items: stretch; }
            .patient-actions { flex-direction: column; align-items: stretch; }
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

    <!-- ================================================================ -->
    <!-- ✅ SUCCESS MESSAGE BAADA YA KU-SUBMIT RESULT -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="successMessage">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?> msg-icon"></i>
            <div class="msg-content">
                <strong><?= $message_type === 'success' ? '✅ Imefanikiwa!' : ($message_type === 'info' ? 'ℹ️ Taarifa' : '❌ Kuna Tatizo') ?></strong>
                <div><?= $message ?></div>
                <?php if ($message_type === 'success'): ?>
                    <div class="msg-hint">
                        <i class="fas fa-lightbulb"></i>
                        <span>Unaweza kuendelea kujaza results za tests zingine hapa hapa.</span>
                    </div>
                <?php endif; ?>
            </div>
            <button class="msg-close" onclick="closeSuccessMessage()" title="Funga">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER - BLUE -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-spinner"></i>
                In Progress Tests
                <span class="role-badge-display">LABORATORY</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Manage all in progress laboratory tests (Grouped by Patient)
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-spinner"></i> <?= $in_progress_total_count ?> In Progress
                </span>
                <span class="branch-tag" style="background:rgba(251,191,36,0.25);color:#FBBF24;">
                    <i class="fas fa-clock"></i> <?= $pending_count ?> Pending
                </span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                    <i class="fas fa-check-circle"></i> <?= $completed_today ?> Completed Today
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-users"></i> <?= $total_patients ?> Patients
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="pending_tests.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending (<?= $pending_count ?>)
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- STATS CARDS - BLUE VARIANTS -->
    <div class="stats-grid animate-fade-in-up">
        <a href="pending_tests.php" class="stat-card blue-1">
            <span class="stat-icon">⏳</span>
            <div class="stat-number" id="statPending"><?= $pending_count ?></div>
            <div class="stat-label">Pending Tests</div>
            <div class="stat-sub">Awaiting processing</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
        
        <a href="in_progress_tests.php" class="stat-card blue-2">
            <span class="stat-icon">🔄</span>
            <div class="stat-number" id="statInProgress"><?= $in_progress_total_count ?></div>
            <div class="stat-label">In Progress</div>
            <div class="stat-sub">Currently running</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
        
        <a href="completed_tests.php" class="stat-card blue-3">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="statCompletedToday"><?= $completed_today ?></div>
            <div class="stat-label">Completed Today</div>
            <div class="stat-sub">Tests finished</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
        
        <a href="in_progress_tests.php" class="stat-card blue-4">
            <span class="stat-icon">🧪</span>
            <div class="stat-number" id="statTotal"><?= $total_tests ?></div>
            <div class="stat-label">Total Tests</div>
            <div class="stat-sub">All time</div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
        </a>
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar animate-fade-in-up">
        <div class="action-info">
            <span class="info-badge blue">
                <i class="fas fa-spinner"></i> <?= $in_progress_total_count ?> In Progress Test(s)
            </span>
            <span class="info-badge info">
                <i class="fas fa-flask"></i> <?= $total_tests ?> Total Tests
            </span>
            <span class="info-badge purple">
                <i class="fas fa-users"></i> <?= $total_patients ?> Patients
            </span>
        </div>
    </div>

    <!-- SEARCH TOOLBAR -->
    <div class="search-toolbar animate-fade-in-up">
        <div class="toolbar-title">
            <i class="fas fa-list"></i>
            Patient List
            <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;" id="headerCount">
                <?= $total_patients ?>
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="tableSearch" 
                       placeholder="Search patient name, ID, test, doctor..." 
                       autocomplete="off"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
        </div>
    </div>

    <!-- PATIENTS LIST -->
    <div id="patientsContainer">
        <?php if (count($patients_array) > 0): ?>
            <?php foreach ($patients_array as $patient): 
                $patient_id = $patient['patient_id'];
                $test_count = $patient['test_count'];
                $avg_waiting = $patient['avg_waiting'];
                $age = calculateAge($patient['date_of_birth']);
            ?>
                <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient_id ?>" id="patient-card-<?= $patient_id ?>">
                    <!-- PATIENT HEADER - BLUE -->
                    <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <?= htmlspecialchars($patient['patient_name']) ?>
                                    <?php if ($test_count > 1): ?>
                                        <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">
                                            <i class="fas fa-flask"></i> <?= $test_count ?> Tests
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_code']) ?></span>
                                    <?php if (!empty($patient['phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['visit_number'])): ?>
                                        <span><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($patient['visit_number']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['doctor_name']) ?>
                            </span>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-clock"></i> In Progress: <?= getWaitingText($avg_waiting) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <!-- PATIENT BODY -->
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <!-- PATIENT ACTIONS -->
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $test_count ?></strong> test(s) in progress for this patient.
                                <?php if ($test_count > 1): ?>
                                    Click <strong>"Add Result"</strong> kwa kila test ili kuweka matokeo.
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- PATIENT TESTS TABLE -->
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th><i class="fas fa-flask"></i> Test</th>
                                        <th><i class="fas fa-info-circle"></i> Status</th>
                                        <th><i class="fas fa-clock"></i> Started</th>
                                        <th><i class="fas fa-calendar"></i> Requested</th>
                                        <th style="text-align:center;width:120px;"><i class="fas fa-cog"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($patient['tests'] as $test): 
                                        $waiting = $test['waiting_time'] ?? 0;
                                        $waiting_class = getWaitingClass($waiting);
                                        $waiting_text = getWaitingText($waiting);
                                        $waiting_icon = getWaitingIcon($waiting);
                                        $test_id = $test['test_id'];
                                        $view_link = "view_test.php?id=" . $test_id;
                                        $add_link = "add_result.php?id=" . $test_id;
                                    ?>
                                        <tr class="test-row"
                                            data-search="<?= htmlspecialchars(strtolower($test['test_name'] . ' ' . $patient['patient_name'] . ' ' . $patient['patient_code'] . ' ' . ($test['doctor_name'] ?? '') . ' ' . $patient['phone'])) ?>">
                                            <td class="row-number"><?= $i++ ?></td>
                                            <td>
                                                <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></div>
                                                <div style="font-size:0.65rem;color:var(--text-secondary);"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></div>
                                                <?php if (!empty($test['sample_type'])): ?>
                                                    <div style="font-size:0.6rem;color:var(--text-secondary);">
                                                        <i class="fas fa-vial"></i> <?= htmlspecialchars($test['sample_type']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-in-progress">
                                                    🔄 In Progress
                                                </span>
                                            </td>
                                            <td>
                                                <span class="waiting-time <?= $waiting_class ?>">
                                                    <i class="fas <?= $waiting_icon ?>"></i>
                                                    <?= $waiting_text ?>
                                                </span>
                                                <?php if (!empty($test['started_at'])): ?>
                                                    <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;">
                                                        <?= date('h:i A', strtotime($test['started_at'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:0.7rem;"><?= formatDate($test['created_at'] ?? '') ?></td>
                                            <td>
                                                <div class="action-buttons-vertical">
                                                    <a href="<?= $view_link ?>" class="btn-action btn-view-action" title="View Details">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="<?= $add_link ?>" class="btn-action btn-result-action" title="Add Result">
                                                        <i class="fas fa-edit"></i> Result
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- NO SEARCH RESULTS -->
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state no-results">
                    <i class="fas fa-search"></i>
                    <p>No patients match your search</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>Hongera! Hakuna test in progress</p>
                <p class="sub">Tests zote zimekamilika au zinasubiri ✅</p>
                <a href="pending_tests.php" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;padding:10px 24px;background:var(--primary);color:white;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.85rem;box-shadow:0 4px 12px rgba(11,94,215,0.3);">
                    <i class="fas fa-clock"></i> Nenda kwa Pending Tests
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            In Progress Tests (Grouped by Patient)
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
    // ================================================================
    // ✅ CLOSE SUCCESS MESSAGE
    // ================================================================
    function closeSuccessMessage() {
        var msg = document.getElementById('successMessage');
        if (msg) {
            msg.style.transition = 'all 0.3s ease';
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-15px)';
            setTimeout(function() {
                msg.style.display = 'none';
            }, 300);
        }
    }
    
    // Auto-hide success message baada ya seconds 8
    document.addEventListener('DOMContentLoaded', function() {
        var msg = document.getElementById('successMessage');
        if (msg) {
            setTimeout(function() {
                closeSuccessMessage();
            }, 8000);
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

    // ================================================================
    // SIDEBAR
    // ================================================================
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

    // ================================================================
    // TOGGLE PATIENT CARD
    // ================================================================
    function togglePatient(patientId) {
        var body = document.getElementById('body-' + patientId);
        var chevron = document.getElementById('chevron-' + patientId);
        
        if (body) {
            body.classList.toggle('open');
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }
    
    // Auto-open first patient card (kama hakuna success message)
    document.addEventListener('DOMContentLoaded', function() {
        var successMsg = document.getElementById('successMessage');
        var firstBody = document.querySelector('.patient-body');
        var firstChevron = document.querySelector('.chevron');
        
        // Kama kuna success message, usifungue card yoyote (ili uone message)
        if (successMsg) {
            return;
        }
        
        // Kama hakuna success message, fungua first card
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });

    // ================================================================
    // LIVE SEARCH
    // ================================================================
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var patientCards = document.querySelectorAll('.patient-card');
    var noSearchResults = document.getElementById('noSearchResults');
    var headerCount = document.getElementById('headerCount');
    
    var totalPatients = patientCards.length;
    if (headerCount) headerCount.textContent = totalPatients;
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        var visiblePatients = 0;
        
        patientCards.forEach(function(card) {
            var testRows = card.querySelectorAll('.test-row');
            var hasMatch = false;
            
            testRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.includes(query)) {
                    hasMatch = true;
                }
            });
            
            if (hasMatch) {
                card.style.display = '';
                visiblePatients++;
                
                if (query !== '') {
                    var body = card.querySelector('.patient-body');
                    var chevron = card.querySelector('.chevron');
                    if (body && !body.classList.contains('open')) {
                        body.classList.add('open');
                        if (chevron) chevron.classList.add('rotated');
                    }
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
            } else {
                card.style.display = 'none';
            }
        });
        
        if (visiblePatients === 0 && query !== '') {
            noSearchResults.style.display = '';
        } else {
            noSearchResults.style.display = 'none';
        }
        
        if (headerCount) headerCount.textContent = visiblePatients;
        
        if (query !== '') {
            searchResultsCount.textContent = visiblePatients + ' patients found';
            searchResultsCount.style.display = 'inline-block';
        } else {
            searchResultsCount.style.display = 'none';
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

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c🧪 Braick - In Progress Tests (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Tests grouped by patient', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Live search filter', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ BLUE THEME (same as pending_tests.php)', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
    console.log('%c✅ NEW: Inabaki hapo hapo baada ya ku-submit result', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c📊 In Progress: <?= $in_progress_total_count ?> | Patients: <?= $total_patients ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>