<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR MY PATIENTS - FIXED: Lab button removed, View only for completed
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// ✅ FIX: Check if there's an error parameter and handle it quietly
// ================================================================
$error = isset($_GET['error']) ? $_GET['error'] : '';
// Don't show error message, just ignore it

// ================================================================
// GET PATIENTS - FIXED: EXCLUDE OTC PATIENTS WITHOUT DOCTOR
// ================================================================

// Base query: Get patients with visits assigned to this doctor
// AND exclude OTC patients who don't have a doctor assigned
$query = "
    SELECT 
        p.id,
        p.patient_id as patient_code,
        p.full_name,
        p.gender,
        p.date_of_birth,
        p.phone,
        p.email,
        p.blood_group,
        p.address,
        p.allergies,
        p.created_at as registered_at,
        v.status as current_visit_status,
        COUNT(DISTINCT v.id) as total_visits,
        MAX(v.created_at) as last_visit,
        v.id as current_visit_id,
        v.doctor_id as assigned_doctor_id,
        u.full_name as assigned_doctor_name,
        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
        CASE 
            WHEN v.status = 'completed' THEN 'completed'
            WHEN v.status IN ('pending', 'with_doctor', 'lab_test', 'prescribed', 'referred') THEN 'active'
            WHEN v.status = 'cancelled' THEN 'inactive'
            ELSE 'active'
        END as patient_status
    FROM patients p
    LEFT JOIN visits v ON v.patient_id = p.id AND v.status NOT IN ('cancelled')
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE 1=1
        AND v.doctor_id = ?
        AND p.patient_id NOT LIKE 'PAT-OTC-%'
        AND v.doctor_id IS NOT NULL
";

$params = [$doctor_id];

// Status filter - using visit status
if ($status_filter === 'active') {
    $query .= " AND v.status NOT IN ('completed', 'cancelled')";
} elseif ($status_filter === 'referred') {
    $query .= " AND v.status = 'referred'";
} elseif ($status_filter === 'inactive') {
    $query .= " AND v.status = 'completed'";
} else {
    // All: show all except cancelled
    $query .= " AND v.status != 'cancelled'";
}

// Search filter
if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Group by patient
$query .= " GROUP BY p.id ORDER BY last_visit DESC, p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - FIXED: Only count assigned patients
// ================================================================

// Total patients (assigned to this doctor, excluding OTC)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total
    FROM patients p
    JOIN visits v ON v.patient_id = p.id
    WHERE v.doctor_id = ? 
        AND v.status NOT IN ('cancelled')
        AND p.patient_id NOT LIKE 'PAT-OTC-%'
        AND v.doctor_id IS NOT NULL
");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Active patients (with active visits, excluding OTC)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total
    FROM patients p
    JOIN visits v ON v.patient_id = p.id
    WHERE v.doctor_id = ? 
        AND v.status NOT IN ('completed', 'cancelled')
        AND p.patient_id NOT LIKE 'PAT-OTC-%'
        AND v.doctor_id IS NOT NULL
");
$stmt->execute([$doctor_id]);
$active_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Referred patients
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total
    FROM patients p
    JOIN visits v ON v.patient_id = p.id
    WHERE v.doctor_id = ? 
        AND v.status = 'referred'
        AND p.patient_id NOT LIKE 'PAT-OTC-%'
        AND v.doctor_id IS NOT NULL
");
$stmt->execute([$doctor_id]);
$referred_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Completed patients
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total
    FROM patients p
    JOIN visits v ON v.patient_id = p.id
    WHERE v.doctor_id = ? 
        AND v.status = 'completed'
        AND p.patient_id NOT LIKE 'PAT-OTC-%'
        AND v.doctor_id IS NOT NULL
");
$stmt->execute([$doctor_id]);
$completed_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';

// Helper functions
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadge($status) {
    $map = [
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'referred' => 'badge-warning',
        'with_doctor' => 'badge-info',
        'lab_test' => 'badge-warning',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'pending' => 'badge-warning',
        'cancelled' => 'badge-danger',
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'active' => '✅ Active',
        'inactive' => '❌ Inactive',
        'referred' => '↗️ Referred',
        'with_doctor' => '🩺 With Doctor',
        'lab_test' => '🧪 Lab Test',
        'prescribed' => '💊 Prescribed',
        'completed' => '✅ Completed',
        'pending' => '⏳ Pending',
        'cancelled' => '❌ Cancelled',
    ];
    return $map[$status] ?? ucfirst($status);
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
    }
    return $colors[abs($hash) % count($colors)];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Braick Dispensary</title>
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
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }
        .page-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .page-title i { color: var(--primary); }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .page-subtitle .text-success { color: var(--success); }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-icon {
            font-size: 1.2rem;
        }
        .stat-card.primary .stat-number { color: var(--primary); }
        .stat-card.success .stat-number { color: var(--success); }
        .stat-card.warning .stat-number { color: var(--warning); }
        .stat-card.danger .stat-number { color: var(--danger); }
        
        /* Filters */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            align-items: center;
        }
        .filter-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        .filter-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: #ffffff;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }
        .search-box input {
            padding: 8px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            width: 250px;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.1);
        }
        .search-box button {
            padding: 8px 18px;
            border: none;
            border-radius: var(--radius);
            background: var(--primary);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-box button:hover {
            background: var(--primary-dark);
        }
        
        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        /* ✅ BLUE HEADER BACKGROUND */
        .table-container th {
            text-align: left;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #0B5ED7 !important;
            color: #ffffff !important;
            border-bottom: 3px solid #0A4CA8;
        }
        .table-container th i {
            color: #ffffff !important;
            margin-right: 6px;
        }
        
        [data-theme="dark"] .table-container th {
            background: #0B5ED7 !important;
            color: #ffffff !important;
            border-bottom: 3px solid #0A4CA8;
        }
        
        .table-container td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .table-container tr:hover td {
            background: var(--gray-50);
        }
        [data-theme="dark"] .table-container tr:hover td {
            background: var(--gray-700);
        }
        .table-container tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        [data-theme="dark"] .table-container tbody tr:nth-child(even) td {
            background: var(--gray-800);
        }
        
        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: #ffffff;
            flex-shrink: 0;
        }
        .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .patient-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .patient-id {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: monospace;
            display: block;
        }
        .patient-details {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .patient-details i { margin-right: 2px; }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-success {
            background: var(--success);
            color: #ffffff;
        }
        .btn-success:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        .btn-warning {
            background: var(--warning);
            color: #ffffff;
        }
        .btn-warning:hover {
            background: #B45309;
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
        }
        
        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .table-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .search-box { margin-left: 0; width: 100%; }
            .search-box input { width: 100%; }
            .table-container { overflow-x: auto; }
            .action-btns { flex-direction: column; gap: 4px; }
            .action-btns .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 1.2rem; }
        }
        
        /* Print */
        @media print {
            .stats-grid { display: none; }
            .filters-bar { display: none; }
            .action-btns .btn { display: none; }
            .main-content { margin: 0; padding: 20px; }
            .table-container { border: 1px solid #ddd; }
            .patient-avatar { background: #0B5ED7 !important; color: white !important; }
            .badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .btn { display: none !important; }
            .footer { position: fixed; bottom: 0; width: 100%; }
            .table-container th { background: #0B5ED7 !important; color: white !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i> My Patients
                <span style="font-size:0.8rem;font-weight:400;color:var(--text-secondary);">(<?= $total_patients ?> assigned)</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($doctor_name) ?> • 
                <?= htmlspecialchars($doctor_specialty) ?> • 
                <span class="text-success">🟢 Online</span>
            </p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-number"><?= $total_patients ?></div>
            <div class="stat-label"><i class="fas fa-users stat-icon"></i> Total Patients</div>
        </div>
        <div class="stat-card success">
            <div class="stat-number"><?= $active_patients ?></div>
            <div class="stat-label"><i class="fas fa-check-circle stat-icon" style="color:var(--success);"></i> Active</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-number"><?= $referred_patients ?></div>
            <div class="stat-label"><i class="fas fa-share stat-icon" style="color:var(--warning);"></i> Referred</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-number"><?= $completed_patients ?></div>
            <div class="stat-label"><i class="fas fa-check-double stat-icon" style="color:var(--gray-400);"></i> Completed</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
               class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?status=active<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
               class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">
                <i class="fas fa-check-circle" style="color:var(--success);"></i> Active
            </a>
            <a href="?status=referred<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
               class="filter-btn <?= $status_filter === 'referred' ? 'active' : '' ?>">
                <i class="fas fa-share" style="color:var(--warning);"></i> Referred
            </a>
            <a href="?status=inactive<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
               class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">
                <i class="fas fa-clock" style="color:var(--gray-400);"></i> Completed
            </a>
        </div>
        
        <form method="GET" class="search-box">
            <?php if ($status_filter !== 'all'): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search by name, ID, phone..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="?status=<?= $status_filter ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Patient Table -->
    <div class="table-container">
        <?php if (count($patients) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-phone"></i> Contact</th>
                        <th><i class="fas fa-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Registered</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $index => $patient):
                        $age = calculateAge($patient['date_of_birth'] ?? '');
                        $visit_status = $patient['current_visit_status'] ?? 'pending';
                        $visit_id = $patient['current_visit_id'] ?? 0;
                        $is_completed = ($visit_status === 'completed');
                        $color = getUserColor($patient['full_name'] ?? 'Unknown');
                    ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <div class="patient-info">
                                    <div class="patient-avatar" style="background:<?= $color ?>;">
                                        <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="patient-name">
                                            <?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?>
                                        </div>
                                        <span class="patient-id"><?= htmlspecialchars($patient['patient_code'] ?? 'N/A') ?></span>
                                        <?php if ($age !== 'N/A'): ?>
                                            <span class="patient-details">• <?= $age ?> yrs</span>
                                        <?php endif; ?>
                                        <?php if (!empty($patient['gender'])): ?>
                                            <span class="patient-details">• <?= $patient['gender'] === 'Male' ? '♂' : '♀' ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($patient['blood_group'])): ?>
                                            <span class="patient-details">• Blood: <?= htmlspecialchars($patient['blood_group']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($patient['phone'])): ?>
                                    <div><i class="fas fa-phone" style="color:var(--text-secondary);font-size:0.7rem;"></i> <?= htmlspecialchars($patient['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($patient['email'])): ?>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);"><?= htmlspecialchars($patient['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= getStatusBadge($visit_status) ?>">
                                    <?= getStatusLabel($visit_status) ?>
                                </span>
                                <?php if ($patient['assigned_doctor_name']): ?>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:2px;">
                                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:0.8rem;">
                                    <?php 
                                        $reg_date = $patient['registered_at'] ?? ($patient['last_visit'] ?? 'now');
                                        echo date('d/m/Y h:i A', strtotime($reg_date));
                                    ?>
                                </div>
                                <div style="font-size:0.65rem;color:var(--text-secondary);">
                                    <?= $patient['total_visits'] ?? 0 ?> visits
                                    <?php if (!empty($patient['last_visit'])): ?>
                                        • Last: <?= date('d/m/Y h:i A', strtotime($patient['last_visit'])) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($is_completed): ?>
                                        <!-- ✅ COMPLETED: Only View button -->
                                        <a href="consultation.php?visit_id=<?= $visit_id ?>&view=view" 
                                           class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php elseif ($visit_id > 0): ?>
                                        <!-- ✅ ACTIVE: Continue + View buttons -->
                                        <a href="consultation.php?visit_id=<?= $visit_id ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-stethoscope"></i> Continue
                                        </a>
                                        <a href="consultation.php?visit_id=<?= $visit_id ?>&view=view" 
                                           class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php else: ?>
                                        <!-- ✅ NO VISIT: Consult only -->
                                        <a href="consultation.php?patient_id=<?= $patient['id'] ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-user-md"></i> Consult
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- ✅ Refer button - available for all -->
                                    <a href="refer_patient.php?patient_id=<?= $patient['id'] ?>" 
                                       class="btn btn-warning btn-sm">
                                        <i class="fas fa-share"></i> Refer
                                    </a>
                                    
                                    <!-- ❌ LAB BUTTON REMOVED - NOT SHOWING -->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="table-footer">
                <span>Showing <?= count($patients) ?> patients</span>
                <span><?= $total_patients ?> Total patients</span>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Patients Found</h3>
                <p>
                    <?php if (!empty($search)): ?>
                        No patients match "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php elseif ($status_filter !== 'all'): ?>
                        No <?= $status_filter ?> patients found
                    <?php else: ?>
                        You don't have any assigned patients yet.
                        <br>OTC patients are not shown here until assigned to a doctor.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    <a href="?status=all" class="btn btn-primary" style="margin-top:12px;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="brand">Braick Dispensary</span> Management System 
            <span class="text-gray-300 mx-2">|</span>
            My Patients 
            <span class="text-gray-300 mx-2">|</span>
            Doctor: <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>
    
</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE TOGGLE
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });

    // ================================================================
    // REFER PATIENT
    // ================================================================
    function referPatient(patientId) {
        if (!confirm('Refer this patient to another doctor?')) return;
        window.location.href = 'refer_patient.php?patient_id=' + patientId;
    }

    console.log('%c👨‍⚕️ My Patients - FIXED', 'font-size:14px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ OTC patients hidden (not assigned to doctor)', 'font-size:12px; color:#059669;');
    console.log('%c✅ Error message "patient_not_found" removed', 'font-size:12px; color:#059669;');
    console.log('%c✅ Blue header background added', 'font-size:12px; color:#0B5ED7;');
    console.log('%c✅ Lab button removed', 'font-size:12px; color:#DC2626;');
    console.log('%c✅ Completed: Only View button', 'font-size:12px; color:#059669;');
    console.log('%c✅ Active: Continue + View buttons', 'font-size:12px; color:#0B5ED7;');
    console.log('%c📊 Total assigned patients: <?= $total_patients ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🟢 Active: <?= $active_patients ?>', 'font-size:12px; color:#059669;');
    console.log('%c🟡 Referred: <?= $referred_patients ?>', 'font-size:12px; color:#D97706;');
</script>

</body>
</html>