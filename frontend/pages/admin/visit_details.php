<?php
// ================================================================
// FILE: frontend/pages/admin/visit_details.php
// VISIT DETAILS - VIEW ALL VISIT INFORMATION
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
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($visit_id <= 0) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET VISIT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, 
           p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_number,
           p.phone as patient_phone, p.email as patient_email,
           u.id as doctor_id, u.full_name as doctor_name,
           r.id as receptionist_id, r.full_name as receptionist_name,
           b.name as branch_name,
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'assigned' THEN 'info'
               WHEN v.status = 'with_doctor' THEN 'primary'
               WHEN v.status = 'lab_test' THEN 'orange'
               WHEN v.status = 'prescribed' THEN 'purple'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM visits v
    INNER JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

$patient_id = $visit['patient_id'];

// ================================================================
// GET VISIT STATISTICS
// ================================================================

// Get total visits for this patient
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_patient_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get bill for this visit
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
    WHERE pb.visit_id = ?
    ORDER BY pb.created_at DESC
    LIMIT 1
");
$stmt->execute([$visit_id]);
$visit_bill = $stmt->fetch(PDO::FETCH_ASSOC);

// Get bill items
$bill_items = [];
if ($visit_bill) {
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$visit_bill['id']]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get lab tests for this visit
$stmt = $db->prepare("
    SELECT lt.*,
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM lab_tests lt
    WHERE lt.visit_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$visit_id]);
$visit_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescriptions for this visit
$stmt = $db->prepare("
    SELECT p.*,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    WHERE p.visit_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$visit_id]);
$visit_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescription items
$prescription_items = [];
foreach ($visit_prescriptions as $prescription) {
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ?
    ");
    $stmt->execute([$prescription['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $prescription_items[$prescription['id']] = $items;
}

// Get vital signs for this visit
$stmt = $db->prepare("
    SELECT vs.*, u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.visit_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$visit_id]);
$vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

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
    
    /* Status Badges */
    .status-badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.primary { background: #DBEAFE; color: #2563EB; }
    .status-badge.orange { background: #FED7AA; color: #EA580C; }
    .status-badge.purple { background: #E9D5FF; color: #7B2FBE; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.primary { background: #1A2A4A; color: #60A5FA; }
    [data-theme="dark"] .status-badge.orange { background: #3A2A1A; color: #FB923C; }
    [data-theme="dark"] .status-badge.purple { background: #2A1A3A; color: #A78BFA; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    /* Visit Header */
    .visit-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .visit-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .visit-header .visit-number {
        font-size: 1.4rem;
        font-weight: 700;
        font-family: monospace;
    }
    
    .visit-header .visit-meta {
        font-size: 0.85rem;
        opacity: 0.85;
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
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    
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
    
    /* Card */
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
    
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    .title-purple { color: #7B2FBE; }
    .title-orange { color: #F59E0B; }
    
    /* Info Row */
    .info-row {
        display: flex;
        padding: 6px 0;
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
    
    /* Responsive */
    @media (max-width: 640px) {
        .visit-header {
            padding: 16px 18px;
        }
        .visit-header .visit-number {
            font-size: 1rem;
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
            <form method="GET" action="visits.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search visits..." 
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
    <!-- VISIT HEADER -->
    <!-- ================================================================ -->
    <div class="visit-header mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3" style="position:relative;z-index:1;">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="visit-number">
                        <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                    </span>
                    <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="visit-meta mt-1">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="visits.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_patient_visits ?></p>
            <p class="stat-label">Patient Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💰</div>
            <p class="stat-number green"><?= $visit_bill ? number_format($visit_bill['total_amount'] ?? 0) : '0' ?></p>
            <p class="stat-label">Bill Amount</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🔬</div>
            <p class="stat-number orange"><?= count($visit_lab_tests) ?></p>
            <p class="stat-label">Lab Tests</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number purple"><?= count($visit_prescriptions) ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION & PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        
        <!-- Visit Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle title-blue mr-2"></i> Visit Information
                </h3>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Visit Number</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['visit_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Date</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Type</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                            <?= ucfirst($visit['status'] ?? 'N/A') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor</span>
                    <span class="info-value">
                        <?php if ($visit['doctor_name']): ?>
                            <i class="fas fa-user-md text-blue-600"></i> 
                            <?= htmlspecialchars($visit['doctor_name']) ?>
                        <?php else: ?>
                            <span class="text-gray-400">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Receptionist</span>
                    <span class="info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
                </div>
                <?php if ($visit['follow_up_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Follow-up Date</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user title-green mr-2"></i> Patient Information
                </h3>
                <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i> View Patient
                </a>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($visit['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['patient_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Visits</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= $total_patient_visits ?></span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Complaint</span>
                    <span class="info-value"><?= htmlspecialchars($visit['complaint'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Symptoms</span>
                    <span class="info-value"><?= htmlspecialchars($visit['symptoms'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Diagnosis</span>
                    <span class="info-value"><?= htmlspecialchars($visit['diagnosis'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Treatment</span>
                    <span class="info-value"><?= htmlspecialchars($visit['treatment'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat title-red mr-2"></i> Vital Signs
            </h3>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
            <?php if ($vital_signs['temperature']): ?>
                <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-xs text-gray-500">Temperature</p>
                    <p class="font-bold text-lg"><?= $vital_signs['temperature'] ?>°C</p>
                </div>
            <?php endif; ?>
            <?php if ($vital_signs['blood_pressure_systolic']): ?>
                <div class="text-center p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <p class="text-xs text-gray-500">Blood Pressure</p>
                    <p class="font-bold text-lg"><?= $vital_signs['blood_pressure_systolic'] ?>/<?= $vital_signs['blood_pressure_diastolic'] ?></p>
                </div>
            <?php endif; ?>
            <?php if ($vital_signs['pulse_rate']): ?>
                <div class="text-center p-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <p class="text-xs text-gray-500">Pulse Rate</p>
                    <p class="font-bold text-lg"><?= $vital_signs['pulse_rate'] ?> bpm</p>
                </div>
            <?php endif; ?>
            <?php if ($vital_signs['respiratory_rate']): ?>
                <div class="text-center p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                    <p class="text-xs text-gray-500">Respiratory Rate</p>
                    <p class="font-bold text-lg"><?= $vital_signs['respiratory_rate'] ?> /min</p>
                </div>
            <?php endif; ?>
            <?php if ($vital_signs['oxygen_saturation']): ?>
                <div class="text-center p-2 bg-teal-50 dark:bg-teal-900/20 rounded-lg">
                    <p class="text-xs text-gray-500">Oxygen Saturation</p>
                    <p class="font-bold text-lg"><?= $vital_signs['oxygen_saturation'] ?>%</p>
                </div>
            <?php endif; ?>
            <?php if ($vital_signs['weight']): ?>
                <div class="text-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                    <p class="text-xs text-gray-500">Weight</p>
                    <p class="font-bold text-lg"><?= $vital_signs['weight'] ?> kg</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($vital_signs['notes']): ?>
            <div class="mt-2 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-xs text-gray-500">Notes</p>
                <p class="text-sm"><?= htmlspecialchars($vital_signs['notes']) ?></p>
            </div>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-2">
            Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?> 
            at <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILL & BILL ITEMS -->
    <!-- ================================================================ -->
    <?php if ($visit_bill): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice title-blue mr-2"></i> Bill Details
                <span class="badge-count">(<?= $visit_bill['bill_number'] ?>)</span>
            </h3>
            <a href="bill_details.php?id=<?= $visit_bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-external-link-alt"></i> View Bill
            </a>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
            <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Amount</p>
                <p class="font-bold text-lg text-blue-600">TSh <?= number_format($visit_bill['total_amount'] ?? 0) ?></p>
            </div>
            <div class="text-center p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Paid Amount</p>
                <p class="font-bold text-lg text-green-600">TSh <?= number_format($visit_bill['paid_amount'] ?? 0) ?></p>
            </div>
            <div class="text-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Balance</p>
                <p class="font-bold text-lg text-orange-600">TSh <?= number_format($visit_bill['balance'] ?? 0) ?></p>
            </div>
            <div class="text-center p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Status</p>
                <span class="status-badge <?= $visit_bill['status_color'] ?? 'secondary' ?>" style="font-size:0.8rem;">
                    <?= ucfirst($visit_bill['status'] ?? 'N/A') ?>
                </span>
            </div>
        </div>
        
        <?php if (count($bill_items) > 0): ?>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($bill_items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><span class="badge badge-info"><?= ucfirst($item['item_type'] ?? 'N/A') ?></span></td>
                            <td><?= $item['quantity'] ?? 1 ?></td>
                            <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                            <td class="font-bold">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <?php if (count($visit_lab_tests) > 0): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask title-orange mr-2"></i> Lab Tests
                <span class="badge-count">(<?= count($visit_lab_tests) ?> tests)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($visit_lab_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $test['status_color'] ?? 'secondary' ?>" style="font-size:0.65rem;">
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
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <?php if (count($visit_prescriptions) > 0): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription title-purple mr-2"></i> Prescriptions
                <span class="badge-count">(<?= count($visit_prescriptions) ?> prescriptions)</span>
            </h3>
        </div>
        <?php foreach ($visit_prescriptions as $prescription): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-3">
                <div class="flex justify-between items-start flex-wrap gap-2">
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($prescription['prescription_number']) ?></p>
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($prescription['created_at'])) ?>
                        </p>
                    </div>
                    <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>" style="font-size:0.65rem;">
                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                    </span>
                </div>
                
                <?php if (!empty($prescription['diagnosis'])): ?>
                    <p class="text-sm mt-1"><strong>Diagnosis:</strong> <?= htmlspecialchars($prescription['diagnosis']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($prescription['instructions'])): ?>
                    <p class="text-sm"><strong>Instructions:</strong> <?= htmlspecialchars($prescription['instructions']) ?></p>
                <?php endif; ?>
                
                <?php if (isset($prescription_items[$prescription['id']]) && count($prescription_items[$prescription['id']]) > 0): ?>
                    <div class="mt-2">
                        <p class="text-sm font-semibold text-gray-600">Medications:</p>
                        <div class="overflow-x-auto">
                            <table class="data-table table-blue w-full" style="font-size:0.75rem;">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Quantity</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescription_items[$prescription['id']] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['medication_name']) ?></td>
                                            <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                            <td><?= $item['quantity'] ?? 0 ?></td>
                                            <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                            <td>TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mt-2">
                    <a href="prescription_details.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View Prescription
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
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

    console.log('%c🏥 Braick Dispensary - Visit Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= ucfirst($visit['status'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>