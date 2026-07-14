<?php
// ================================================================
// FILE: frontend/pages/doctor/view_visit.php
// DOCTOR - VIEW VISIT DETAILS (NO BUTTONS - VIEW ONLY)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA (ID: 2) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['username'] = 'dr.sarah';
    $_SESSION['email'] = 'sarah@braick.com';
    $_SESSION['phone'] = '+255 700 000 001';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['profile_pic'] = '';
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET VISIT ID
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($visit_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// FUNCTIONS
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        case 'assigned': return 'badge-info';
        case 'with_doctor': return 'badge-info';
        case 'lab_test': return 'badge-warning';
        case 'prescribed': return 'badge-info';
        default: return 'badge-info';
    }
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET VISIT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, 
           p.full_name as patient_name,
           p.patient_id,
           p.phone,
           p.date_of_birth,
           p.gender,
           u.full_name as doctor_name,
           u.specialty as doctor_specialty,
           r.full_name as receptionist_name,
           b.name as branch_name
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET PRESCRIPTIONS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, 
           GROUP_CONCAT(CONCAT(pi.medication_name, ' (', pi.quantity, ' ', pi.dosage, ')') SEPARATOR ', ') as medications
    FROM prescriptions pr
    LEFT JOIN prescription_items pi ON pr.id = pi.prescription_id
    WHERE pr.visit_id = ?
    GROUP BY pr.id
    ORDER BY pr.created_at DESC
");
$stmt->execute([$visit_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET LAB TESTS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, u.full_name as doctor_name
    FROM lab_tests lt
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.visit_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$visit_id]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET REFERRALS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT r.*,
           fd.full_name as from_doctor_name,
           td.full_name as to_doctor_name
    FROM referrals r
    LEFT JOIN users fd ON r.from_doctor_id = fd.id
    LEFT JOIN users td ON r.to_doctor_id = td.id
    WHERE r.visit_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$visit_id]);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<style>
    /* ================================================================
       VIEW VISIT PAGE STYLES - NO BUTTONS
       ================================================================ */
    
    .visit-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    
    .visit-header:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .visit-header .patient-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .visit-header .visit-number {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-family: monospace;
        background: var(--bg-body);
        padding: 2px 12px;
        border-radius: 12px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .info-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .info-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .info-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-card-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .info-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    
    .info-value.font-semibold { font-weight: 600; }
    .info-value.font-mono { font-family: monospace; }
    
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .text-purple-600 { color: #7C3AED; }
    .text-teal-600 { color: #0D9488; }
    .text-orange-600 { color: #D97706; }
    
    .result-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }
    
    .result-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .result-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #fff;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    .badge-purple { background: #7C3AED; }
    .badge-teal { background: #0D9488; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: var(--primary);
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
    }
    
    .page-header .page-title {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: var(--primary-light);
    }
    
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
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
    
    .text-center { text-align: center; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-2xl { font-size: 1.5rem; }
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-muted { color: var(--text-muted); }
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .font-mono { font-family: monospace; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    
    [data-theme="dark"] .info-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .info-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .info-row {
        border-color: #334155;
    }
    [data-theme="dark"] .info-value {
        color: #F1F5F9;
    }
    [data-theme="dark"] .result-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .result-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #1E293B;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .visit-header {
            padding: 16px;
        }
        .visit-header .patient-avatar {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .info-card {
            padding: 14px 16px;
        }
        .result-card {
            padding: 14px 16px;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
            max-width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .data-table th,
        .data-table td {
            padding: 4px 6px;
            font-size: 0.7rem;
        }
        .info-card {
            padding: 10px 12px;
        }
        .result-card {
            padding: 10px 12px;
        }
        .page-header .page-title {
            font-size: 1rem;
        }
        .page-header .page-subtitle {
            font-size: 0.75rem;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .info-card, .result-card { 
            border: 1px solid #ddd !important; 
            box-shadow: none !important; 
            page-break-inside: avoid;
        }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .data-table thead th { background: #0B5ED7 !important; color: white !important; }
        .info-card { background: white !important; }
        .result-card { background: white !important; }
    }
</style>

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
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($doctor_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot no-notif"></span>
        </button>
        <a href="profile.php">
            <img src="/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($doctor_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT - NO BUTTONS -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header - Back button only -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical mr-2" style="color: #0B5ED7;"></i> Visit Details
            </h1>
            <p class="page-subtitle">
                View visit information and history
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?>
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div>
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT HEADER -->
    <!-- ================================================================ -->
    <div class="visit-header">
        <div class="flex flex-wrap items-center gap-4">
            <div class="patient-avatar" style="background: <?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($visit['patient_name'] ?? 'Unknown Patient') ?></h2>
                    <span class="visit-number"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                    <span class="badge <?= getStatusBadgeClass($visit['status']) ?>">
                        <?= ucfirst($visit['status'] ?? 'Pending') ?>
                    </span>
                </div>
                <div class="flex flex-wrap gap-4 mt-1 text-sm text-gray-500">
                    <span><i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($visit['patient_id'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-venus-mars mr-1"></i> <?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-calendar-alt mr-1"></i> <?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?></span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">
                    <i class="fas fa-user-md mr-1"></i> Doctor: <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?>
                </div>
                <?php if ($visit['receptionist_name']): ?>
                <div class="text-sm text-gray-500">
                    <i class="fas fa-user mr-1"></i> Reception: <?= htmlspecialchars($visit['receptionist_name']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i> Visit Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Visit Number</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Type</span>
                    <span class="info-value"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="badge <?= getStatusBadgeClass($visit['status']) ?>">
                            <?= ucfirst($visit['status'] ?? 'Pending') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date & Time</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($visit['created_at'])) ?></span>
                </div>
                <?php if (!empty($visit['updated_at']) && $visit['updated_at'] != $visit['created_at']): ?>
                <div class="info-row">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($visit['updated_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-stethoscope text-green-600 mr-2"></i> Clinical Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Doctor</span>
                    <span class="info-value"><?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?></span>
                </div>
                <?php if (!empty($visit['doctor_specialty'])): ?>
                <div class="info-row">
                    <span class="info-label">Specialty</span>
                    <span class="info-value"><?= htmlspecialchars($visit['doctor_specialty']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($visit['symptoms'])): ?>
                <div class="info-row">
                    <span class="info-label">Symptoms</span>
                    <span class="info-value text-sm"><?= htmlspecialchars($visit['symptoms']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                <div class="info-row">
                    <span class="info-label">Diagnosis</span>
                    <span class="info-value text-sm font-semibold"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($visit['notes'])): ?>
                <div class="info-row">
                    <span class="info-label">Notes</span>
                    <span class="info-value text-sm"><?= htmlspecialchars($visit['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-prescription text-purple-600 mr-2"></i> Prescriptions
            <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?> prescriptions)</span>
        </h4>
        
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prescription #</th>
                            <th>Diagnosis</th>
                            <th>Medications</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $index => $pr): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-mono text-xs font-semibold text-blue-600"><?= htmlspecialchars($pr['prescription_number']) ?></td>
                                <td class="text-sm"><?= htmlspecialchars(substr($pr['diagnosis'] ?? '', 0, 30)) ?><?= strlen($pr['diagnosis'] ?? '') > 30 ? '...' : '' ?></td>
                                <td class="text-sm"><?= htmlspecialchars($pr['medications'] ?? 'No items') ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($pr['status']) ?>"><?= ucfirst($pr['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $pr['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-flask text-teal-600 mr-2"></i> Lab Tests
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?> tests)</span>
        </h4>
        
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $index => $test): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'Unknown') ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($test['status']) ?>"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- REFERRALS -->
    <!-- ================================================================ -->
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-share-alt text-orange-600 mr-2"></i> Referrals
            <span class="text-sm font-normal text-gray-400">(<?= count($referrals) ?> referrals)</span>
        </h4>
        
        <?php if (count($referrals) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>From Doctor</th>
                            <th>To Doctor</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $index => $ref): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($ref['from_doctor_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($ref['to_doctor_name'] ?? 'Unknown') ?></td>
                                <td class="text-sm"><?= htmlspecialchars(substr($ref['reason'] ?? '', 0, 30)) ?><?= strlen($ref['reason'] ?? '') > 30 ? '...' : '' ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($ref['status']) ?>"><?= ucfirst($ref['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($ref['created_at'])) ?></td>
                                <td>
                                    <a href="view_referral.php?id=<?= $ref['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-share-alt"></i>
                <p>No referrals for this visit</p>
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
            Visit Details
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c👨‍⚕️ View Visit - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?> (View Only)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= $visit['status'] ?? 'Pending' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🧪 Lab Tests: <?= count($lab_tests) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c🔄 Referrals: <?= count($referrals) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🚫 NO BUTTONS - View only', 'font-size:12px; color:#EF4444;');
    console.log('%c💰 No prices visible - Cashier only', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>