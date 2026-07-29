<?php
// ================================================================
// FILE: frontend/pages/admin/patient_details.php
// PATIENT DETAILS - VIEW ALL PATIENT INFORMATION
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET PATIENT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN users u ON p.assigned_doctor_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET STATISTICS
// ================================================================

// Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Bills
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as total_amount FROM patient_bills WHERE patient_id = ? AND status != 'cancelled'");
$stmt->execute([$patient_id]);
$bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_bills = $bills_data['total'] ?? 0;
$total_bill_amount = $bills_data['total_amount'] ?? 0;

// Total Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Lab Tests - FIXED: Use visit_id to get patient
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    WHERE v.patient_id = ?
");
$stmt->execute([$patient_id]);
$total_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Payments
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM payments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_payments = $payments_data['total'] ?? 0;
$total_payments_amount = $payments_data['total_amount'] ?? 0;

// ================================================================
// GET RECENT VISITS
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name, 
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'info'
           END as status_color
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT pb.*, 
           CASE 
               WHEN pb.status = 'pending' THEN 'warning'
               WHEN pb.status = 'paid' THEN 'success'
               WHEN pb.status = 'partial' THEN 'info'
               WHEN pb.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM patient_bills pb
    WHERE pb.patient_id = ?
    ORDER BY pb.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT LAB TESTS - FIXED: Use visit_id to get patient
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, 
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color,
           u.full_name as doctor_name
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT APPOINTMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT a.*, u.full_name as doctor_name,
           CASE 
               WHEN a.status = 'scheduled' THEN 'warning'
               WHEN a.status = 'confirmed' THEN 'info'
               WHEN a.status = 'completed' THEN 'success'
               WHEN a.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES
       ================================================================ */
    
    /* Profile Header */
    .profile-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .profile-header .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        border: 3px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
    }
    
    .profile-header .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .profile-header .profile-id {
        font-size: 0.85rem;
        opacity: 0.8;
        font-family: monospace;
    }
    
    .profile-header .profile-badge {
        background: rgba(255,255,255,0.15);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    /* Stat Cards */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green {
        color: #059669;
    }
    
    .stat-card-mini .stat-number.orange {
        color: #F59E0B;
    }
    
    .stat-card-mini .stat-number.red {
        color: #EF4444;
    }
    
    .stat-card-mini .stat-number.purple {
        color: #7B2FBE;
    }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    .stat-card-mini .stat-amount {
        font-size: 0.8rem;
        font-weight: 600;
        color: #059669;
        margin-top: 2px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* Badge styles */
    .badge {
        padding: 3px 12px !important;
        border-radius: 20px !important;
        font-size: 0.6rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-success {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    .badge-warning {
        background: #FEF3C7 !important;
        color: #D97706 !important;
    }
    
    .badge-danger {
        background: #FEE2E2 !important;
        color: #EF4444 !important;
    }
    
    .badge-info {
        background: #E8F0FE !important;
        color: #0B5ED7 !important;
    }
    
    .badge-secondary {
        background: #E2E8F0 !important;
        color: #64748B !important;
    }
    
    [data-theme="dark"] .badge-success {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    [data-theme="dark"] .badge-warning {
        background: #3A2A1A !important;
        color: #FBBF24 !important;
    }
    
    [data-theme="dark"] .badge-danger {
        background: #3A1A1A !important;
        color: #F87171 !important;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F !important;
        color: #6EA8FE !important;
    }
    
    [data-theme="dark"] .badge-secondary {
        background: #2D3748 !important;
        color: #94A3B8 !important;
    }
    
    /* Info rows */
    .info-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row .info-label {
        width: 140px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.82rem;
        flex-shrink: 0;
    }
    
    .info-row .info-value {
        flex: 1;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    /* Section title */
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0B5ED7;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }
    
    [data-theme="dark"] .section-title {
        color: #6EA8FE;
    }
    
    .section-title .badge-count {
        font-size: 0.7rem;
        font-weight: 400;
        color: var(--text-secondary);
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .profile-header {
            padding: 16px 18px;
        }
        .profile-header .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.8rem;
        }
        .profile-header .profile-name {
            font-size: 1.2rem;
        }
        .info-row {
            flex-direction: column;
            gap: 2px;
        }
        .info-row .info-label {
            width: 100%;
            font-size: 0.75rem;
        }
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
        .table-blue tbody td {
            font-size: 0.7rem;
            padding: 6px 10px !important;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
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
            <form method="GET" action="patients.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search patients..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
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
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header mb-5">
        <div class="flex items-center gap-4 flex-wrap" style="position:relative;z-index:1;">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="profile-name"><?= htmlspecialchars($patient['full_name']) ?></h1>
                    <span class="profile-badge">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                    </span>
                    <span class="profile-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);">
                        <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($patient['created_at'])) ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 flex-wrap mt-1" style="opacity:0.85;">
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
                    <?php if ($patient['assigned_doctor_name']): ?>
                        <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="patients.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_visits ?></p>
            <p class="stat-label">Total Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💰</div>
            <p class="stat-number green"><?= $total_bills ?></p>
            <p class="stat-label">Total Bills</p>
            <p class="stat-amount">TSh <?= number_format($total_bill_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number purple"><?= $total_prescriptions ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🔬</div>
            <p class="stat-number orange"><?= $total_lab_tests ?></p>
            <p class="stat-label">Lab Tests</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📅</div>
            <p class="stat-number"><?= $total_appointments ?></p>
            <p class="stat-label">Appointments</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💵</div>
            <p class="stat-number green"><?= $total_payments ?></p>
            <p class="stat-label">Payments</p>
            <p class="stat-amount">TSh <?= number_format($total_payments_amount) ?></p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i> Patient Information
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                <span class="info-value"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-id-card"></i> Patient ID</span>
                <span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Date of Birth</span>
                <span class="info-value"><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                <span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-ring"></i> Marital Status</span>
                <span class="info-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-tint"></i> Blood Group</span>
                <span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone-alt"></i> Emergency Contact</span>
                <span class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-allergies"></i> Allergies</span>
                <span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT VISITS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-notes-medical title-blue mr-2"></i> Recent Visits
                <span class="badge-count">(<?= $total_visits ?> total)</span>
            </h3>
            <a href="visits.php?patient=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Visit #</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_visits) > 0): ?>
                        <?php foreach ($recent_visits as $visit): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number']) ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($visit['visit_date'])) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $visit['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="visit_details.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-gray-400">No visits found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT BILLS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice title-blue mr-2"></i> Recent Bills
                <span class="badge-count">(<?= $total_bills ?> total)</span>
            </h3>
            <a href="bills.php?patient=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_bills) > 0): ?>
                        <?php foreach ($recent_bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number']) ?></td>
                                <td class="font-bold">TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0) ?></td>
                                <td>TSh <?= number_format($bill['balance'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= $bill['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($bill['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                <td>
                                    <a href="bill_details.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-gray-400">No bills found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue mr-2"></i> Recent Prescriptions
                <span class="badge-count">(<?= $total_prescriptions ?> total)</span>
            </h3>
            <a href="prescriptions.php?patient=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Prescription #</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_prescriptions) > 0): ?>
                        <?php foreach ($recent_prescriptions as $prescription): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $prescription['status'] === 'dispensed' ? 'success' : ($prescription['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                <td>
                                    <a href="prescription_details.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No prescriptions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT LAB TESTS - FIXED -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue mr-2"></i> Recent Lab Tests
                <span class="badge-count">(<?= $total_lab_tests ?> total)</span>
            </h3>
            <a href="lab_tests.php?patient=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_lab_tests) > 0): ?>
                        <?php foreach ($recent_lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $test['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No lab tests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-check title-blue mr-2"></i> Recent Appointments
                <span class="badge-count">(<?= $total_appointments ?> total)</span>
            </h3>
            <a href="appointments.php?patient=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Appointment Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_appointments) > 0): ?>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($appointment['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $appointment['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($appointment['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No appointments found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Details
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
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

    console.log('%c🏥 Braick Dispensary - Patient Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Visits: <?= $total_visits ?> | Bills: <?= $total_bills ?> | Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔬 Lab Tests: <?= $total_lab_tests ?> (via visits table)', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>