<?php
// ================================================================
// FILE: frontend/pages/laboratory/completed_tests.php
// LABORATORY - COMPLETED TESTS (GROUPED BY PATIENT)
// ✅ FIXED: View button inapeleka view_test.php na patient_id + visit_id
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
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// ================================================================
// GET COMPLETED DATA - GROUPED BY PATIENT + VISIT
// ================================================================
$grouped_data = [];
$total_completed = 0;
$total_patients = 0;

try {
    $query = "
        SELECT 
            lt.id as test_id,
            lt.visit_id,
            lt.doctor_id,
            lt.test_name,
            lt.test_price,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.reference_range,
            lt.status as test_status,
            lt.created_at,
            lt.completed_at,
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
            v.symptoms
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.branch_id = ? 
        AND lt.status = 'completed'
    ";
    $params = [$user_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($date_filter)) {
        $query .= " AND DATE(lt.completed_at) = ?";
        $params[] = $date_filter;
    }
    
    $query .= " ORDER BY lt.completed_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $lab_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_completed = count($lab_items);
    
    // Group by patient + visit
    foreach ($lab_items as $item) {
        $group_key = ($item['patient_id'] ?? 'unknown') . '_' . ($item['visit_id'] ?? 'novisit');
        
        if (!isset($grouped_data[$group_key])) {
            $grouped_data[$group_key] = [
                'patient_id' => $item['patient_id'],
                'patient_name' => $item['patient_name'],
                'patient_code' => $item['patient_code'],
                'phone' => $item['phone'],
                'gender' => $item['gender'],
                'date_of_birth' => $item['date_of_birth'],
                'blood_group' => $item['blood_group'],
                'doctor_name' => $item['doctor_name'],
                'doctor_specialty' => $item['doctor_specialty'],
                'visit_id' => $item['visit_id'],
                'visit_number' => $item['visit_number'],
                'visit_type' => $item['visit_type'],
                'diagnosis' => $item['diagnosis'],
                'symptoms' => $item['symptoms'],
                'tests' => [],
                'test_count' => 0,
                'total_price' => 0,
                'latest_completed' => $item['completed_at'],
                'earliest_created' => $item['created_at']
            ];
        }
        
        $grouped_data[$group_key]['tests'][] = $item;
        $grouped_data[$group_key]['test_count']++;
        $grouped_data[$group_key]['total_price'] += (float)($item['test_price'] ?? 0);
        
        if (strtotime($item['completed_at']) > strtotime($grouped_data[$group_key]['latest_completed'])) {
            $grouped_data[$group_key]['latest_completed'] = $item['completed_at'];
        }
        if (strtotime($item['created_at']) < strtotime($grouped_data[$group_key]['earliest_created'])) {
            $grouped_data[$group_key]['earliest_created'] = $item['created_at'];
        }
    }
    
    $grouped_data = array_values($grouped_data);
    $total_patients = count($grouped_data);
    
    usort($grouped_data, function($a, $b) use ($sort) {
        if ($sort === 'oldest') {
            return strtotime($a['latest_completed']) - strtotime($b['latest_completed']);
        }
        return strtotime($b['latest_completed']) - strtotime($a['latest_completed']);
    });
    
} catch (Exception $e) {
    error_log("Completed tests error: " . $e->getMessage());
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $grouped_data = [];
}

// Statistics
$total_completed_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
    $stmt->execute([$user_branch_id]);
    $total_completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $total_completed_count = 0; }

$completed_today = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $completed_today = 0; }

$completed_week = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND YEARWEEK(completed_at) = YEARWEEK(CURDATE())");
    $stmt->execute([$user_branch_id]);
    $completed_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $completed_week = 0; }

$completed_month = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE())");
    $stmt->execute([$user_branch_id]);
    $completed_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $completed_month = 0; }

$pending_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND (status IS NULL OR status = 'pending')");
    $stmt->execute([$user_branch_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_count = 0; }

$in_progress_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_branch_id]);
    $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $in_progress_count = 0; }

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Tests - Laboratory</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #DBEAFE;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            line-height: 1.6;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #1E3A5F, #2563EB, #3B82F6);
            border-radius: 16px;
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
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
            gap: 14px;
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
            margin-top: 4px;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        
        .stat-card-custom {
            border-radius: 14px;
            padding: 22px 26px;
            color: white;
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-custom:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card-custom .stat-icon { font-size: 2rem; margin-bottom: 6px; display: block; opacity: 0.9; }
        .stat-card-custom .stat-number { font-size: 2.2rem; font-weight: 800; display: block; line-height: 1.2; }
        .stat-card-custom .stat-label { font-size: 0.8rem; opacity: 0.85; font-weight: 500; display: block; margin-top: 2px; }
        .stat-card-custom .stat-sub { font-size: 0.7rem; opacity: 0.7; display: block; margin-top: 4px; }
        
        .stat-card-custom.blue-dark { background: linear-gradient(135deg, #1E3A5F, #2563EB); }
        .stat-card-custom.blue-light { background: linear-gradient(135deg, #2563EB, #3B82F6); }
        .stat-card-custom.blue-mid { background: linear-gradient(135deg, #1D4ED8, #2563EB); }
        .stat-card-custom.purple-blue { background: linear-gradient(135deg, #4F46E5, #7C3AED); }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-form input, .filter-form select {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            min-width: 180px;
        }
        
        .filter-form input:focus, .filter-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        
        .filter-form .btn {
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            background: var(--primary);
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-form .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .filter-form .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .filter-form .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: transparent;
        }
        
        .patient-group-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .patient-group-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .patient-group-header {
            background: linear-gradient(135deg, #1E3A5F, #2563EB);
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .patient-group-header .patient-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .patient-group-header .patient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .patient-group-header .patient-details h3 {
            font-weight: 700;
            font-size: 1.15rem;
            margin: 0;
            color: white;
        }
        
        .patient-group-header .patient-details .patient-id {
            font-size: 0.8rem;
            opacity: 0.85;
            font-family: monospace;
            margin-top: 2px;
        }
        
        .patient-group-header .patient-details .patient-meta {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 4px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .patient-group-header .group-stats {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .test-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 0.9rem;
            font-weight: 700;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .test-count-badge .count-number {
            background: white;
            color: #1E3A5F;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
        }
        
        .total-price-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(5, 150, 105, 0.3);
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #6EE7B7;
            border: 1px solid rgba(5, 150, 105, 0.3);
        }
        
        .patient-group-body {
            padding: 20px 24px;
            background: var(--bg-card);
        }
        
        .tests-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .test-preview-item {
            background: var(--gray-50);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        [data-theme="dark"] .test-preview-item { background: #0F172A; }
        
        .test-preview-item:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-2px);
        }
        
        .test-preview-item .test-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .test-preview-item .test-name i { color: var(--primary); font-size: 0.85rem; }
        
        .test-preview-item .test-result {
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding: 6px 10px;
            background: var(--bg-card);
            border-radius: 6px;
            border-left: 3px solid var(--success);
            font-family: monospace;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .test-preview-item .test-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .test-preview-item .test-price {
            font-weight: 700;
            color: var(--success);
            font-family: monospace;
            font-size: 0.8rem;
        }
        
        .more-tests-note {
            text-align: center;
            padding: 10px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-style: italic;
            background: var(--gray-50);
            border-radius: 8px;
            border: 1px dashed var(--border-color);
        }
        
        [data-theme="dark"] .more-tests-note { background: #0F172A; }
        
        .group-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .btn-view-all {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            border: none;
            cursor: pointer;
        }
        
        .btn-view-all:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
            background: linear-gradient(135deg, #1D4ED8, #1E40AF);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 16px;
        }
        
        .empty-state h3 { font-size: 1.3rem; color: var(--text-primary); margin-bottom: 8px; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        
        .footer {
            padding: 16px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 28px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card-custom { padding: 16px 18px; }
            .stat-card-custom .stat-number { font-size: 1.6rem; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form input, .filter-form select { width: 100%; min-width: unset; }
            .card { padding: 16px 18px; }
            .patient-group-header { padding: 14px 16px; }
            .patient-group-body { padding: 14px 16px; }
            .tests-preview-grid { grid-template-columns: 1fr; }
            .btn-view-all { width: 100%; justify-content: center; }
            .patient-group-header .group-stats { width: 100%; justify-content: flex-start; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i>
                Completed Tests
                <span class="role-badge-display">LABORATORY</span>
                <span class="header-badge">
                    <i class="fas fa-users"></i> <?= $total_patients ?> Patients
                </span>
                <span class="header-badge">
                    <i class="fas fa-flask"></i> <?= $total_completed_count ?> Tests
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                View all completed lab tests grouped by patient
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= $completed_today ?> Today
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar-week"></i> <?= $completed_week ?> This Week
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar-alt"></i> <?= $completed_month ?> This Month
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="in_progress_tests.php" class="btn-outline-light">
                <i class="fas fa-spinner"></i> In Progress (<?= $in_progress_count ?>)
            </a>
            <a href="pending_requests.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending (<?= $pending_count ?>)
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" id="alertMessage">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card-custom blue-dark">
            <span class="stat-icon">✅</span>
            <span class="stat-number"><?= $total_completed_count ?></span>
            <span class="stat-label">Total Completed</span>
            <span class="stat-sub">All time</span>
        </div>
        <div class="stat-card-custom blue-light">
            <span class="stat-icon">📅</span>
            <span class="stat-number"><?= $completed_today ?></span>
            <span class="stat-label">Completed Today</span>
            <span class="stat-sub"><?= date('F d, Y') ?></span>
        </div>
        <div class="stat-card-custom blue-mid">
            <span class="stat-icon">📊</span>
            <span class="stat-number"><?= $completed_week ?></span>
            <span class="stat-label">This Week</span>
            <span class="stat-sub">Last 7 days</span>
        </div>
        <div class="stat-card-custom purple-blue">
            <span class="stat-icon">📈</span>
            <span class="stat-number"><?= $completed_month ?></span>
            <span class="stat-label">This Month</span>
            <span class="stat-sub"><?= date('F Y') ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <form method="GET" action="" class="filter-form">
            <input type="text" name="search" placeholder="🔍 Search patient or test..." value="<?= htmlspecialchars($search) ?>">
            <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            </select>
            <button type="submit" class="btn">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="completed_tests.php" class="btn btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- Patient Group Cards -->
    <?php if (count($grouped_data) > 0): ?>
        <?php foreach ($grouped_data as $group): 
            $color = '#' . substr(md5($group['patient_name'] ?? 'Unknown'), 0, 6);
            
            // ✅ FIXED: View All Tests → view_test.php na patient_id + visit_id + view=all
            $view_link = "view_test.php?patient_id=" . urlencode($group['patient_id'] ?? '') 
                       . "&visit_id=" . urlencode($group['visit_id'] ?? '') 
                       . "&view=all";
            
            $visible_tests = array_slice($group['tests'], 0, 6);
            $remaining_count = count($group['tests']) - count($visible_tests);
        ?>
            <div class="patient-group-card">
                <!-- Header -->
                <div class="patient-group-header">
                    <div class="patient-info">
                        <div class="patient-avatar" style="background: <?= $color ?>;">
                            <?= strtoupper(substr($group['patient_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="patient-details">
                            <h3><?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?></h3>
                            <div class="patient-id">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($group['patient_code'] ?? 'N/A') ?>
                                <?php if (!empty($group['gender'])): ?>
                                    <span style="margin-left:12px;">
                                        <i class="fas fa-<?= $group['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> 
                                        <?= htmlspecialchars($group['gender']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($group['phone'])): ?>
                                    <span style="margin-left:12px;">
                                        <i class="fas fa-phone"></i> 
                                        <?= htmlspecialchars($group['phone']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="patient-meta">
                                <?php if (!empty($group['visit_number'])): ?>
                                    <span><i class="fas fa-stethoscope"></i> Visit: <?= htmlspecialchars($group['visit_number']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($group['doctor_name'])): ?>
                                    <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($group['doctor_name']) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($group['latest_completed'])) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="group-stats">
                        <div class="test-count-badge">
                            <span class="count-number"><?= $group['test_count'] ?></span>
                            <span class="count-label">
                                <?= $group['test_count'] == 1 ? 'Test' : 'Tests' ?>
                            </span>
                        </div>
                        
                        <?php if ($group['total_price'] > 0): ?>
                        <div class="total-price-badge">
                            <i class="fas fa-money-bill-wave"></i>
                            TSh <?= number_format($group['total_price'], 0) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="patient-group-body">
                    <div class="tests-preview-grid">
                        <?php foreach ($visible_tests as $test): ?>
                            <div class="test-preview-item">
                                <div class="test-name">
                                    <i class="fas fa-flask"></i>
                                    <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
                                </div>
                                <?php if (!empty($test['results'])): ?>
                                    <div class="test-result" title="<?= htmlspecialchars($test['results']) ?>">
                                        <i class="fas fa-file-medical-alt"></i>
                                        <?= htmlspecialchars(strlen($test['results']) > 60 ? substr($test['results'], 0, 60) . '...' : $test['results']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="test-result" style="border-left-color:var(--warning);color:var(--warning);">
                                        <i class="fas fa-clock"></i> No result
                                    </div>
                                <?php endif; ?>
                                <div class="test-meta">
                                    <span>
                                        <?php if (!empty($test['test_type'])): ?>
                                            <i class="fas fa-tag"></i> <?= htmlspecialchars($test['test_type']) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="test-price">
                                        TSh <?= number_format((float)($test['test_price'] ?? 0), 0) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($remaining_count > 0): ?>
                        <div class="more-tests-note">
                            <i class="fas fa-plus-circle"></i>
                            + <?= $remaining_count ?> more <?= $remaining_count == 1 ? 'test' : 'tests' ?> 
                            (click View to see all)
                        </div>
                    <?php endif; ?>
                    
                    <!-- ✅ FIXED: View All button -->
                    <div class="group-actions">
                        <a href="<?= $view_link ?>" class="btn-view-all">
                            <i class="fas fa-eye"></i>
                            View All <?= $group['test_count'] ?> <?= $group['test_count'] == 1 ? 'Test' : 'Tests' ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card" style="text-align:center;">
            <span style="font-size:0.75rem;color:var(--text-secondary);">
                <i class="fas fa-info-circle"></i> Showing <?= $total_patients ?> patient(s) with <?= $total_completed ?> completed test(s)
            </span>
        </div>
        
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--primary);"></i>
                <h3>No Completed Tests</h3>
                <p>No lab tests have been completed yet.</p>
                <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:8px;">
                    <i class="fas fa-info-circle"></i> Check <a href="in_progress_tests.php" style="color:var(--primary);">In Progress Tests</a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--text-secondary);margin:0 8px;">|</span>
            Completed Tests (Grouped)
            <span style="color:var(--text-secondary);margin:0 8px;">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span style="color:var(--text-secondary);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    }

    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    console.log('%c🔵 Completed Tests (Grouped by Patient)', 'font-size:20px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ FIXED: View button inapeleka view_test.php na patient_id + visit_id + view=all', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c📊 Total Patients: <?= $total_patients ?> | Total Tests: <?= $total_completed_count ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>