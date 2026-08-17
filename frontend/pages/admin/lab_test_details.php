<?php
// ================================================================
// FILE: frontend/pages/admin/lab_test_details.php
// SUPER ADMIN - LAB TEST DETAILS
// VIEW COMPLETE LAB TEST INFORMATION
// BLUE THEME
// BRAICK DISPENSARY
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET LAB TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($test_id <= 0) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH LAB TEST DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        lt.*,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        d.full_name as doctor_name,
        l.full_name as lab_technician_name,
        v.visit_number,
        v.visit_date,
        v.visit_type,
        b.name as branch_name,
        b.location as branch_location,
        (SELECT COUNT(*) FROM lab_tests lt2 
         INNER JOIN visits v2 ON lt2.visit_id = v2.id 
         WHERE v2.patient_id = p.id) as total_lab_requests
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users d ON lt.doctor_id = d.id
    LEFT JOIN users l ON lt.lab_technician_id = l.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    WHERE lt.id = ?
");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET STATUS COLOR
// ================================================================
function getStatusColor($status) {
    $colors = [
        'pending' => '#F59E0B',
        'in_progress' => '#0B5ED7',
        'completed' => '#059669',
        'cancelled' => '#EF4444'
    ];
    return $colors[$status] ?? '#64748B';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       BLUE THEME STYLES
       ================================================================ */
    
    /* Profile Header - Blue Theme */
    .profile-header-custom {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .profile-header-custom::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .profile-header-custom .profile-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        border: 3px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
    }
    
    .profile-header-custom .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .profile-header-custom .profile-badge {
        background: rgba(255,255,255,0.15);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    /* Stat Cards - Blue Theme */
    .stat-card-custom {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-custom .stat-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-custom .stat-number.green {
        color: #059669;
    }
    
    .stat-card-custom .stat-number.orange {
        color: #F59E0B;
    }
    
    .stat-card-custom .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-custom .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    [data-theme="dark"] .stat-card-custom {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-custom .stat-number {
        color: #6EA8FE;
    }
    
    /* Info rows - Blue Theme */
    .info-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
        align-items: center;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-row .info-label {
        width: 150px;
        font-weight: 600;
        color: #0B5ED7;
        font-size: 0.78rem;
        flex-shrink: 0;
    }
    
    .info-row .info-value {
        flex: 1;
        color: var(--text-primary);
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .info-row .info-value .empty-value {
        color: #94A3B8;
        font-style: italic;
        font-weight: 400;
    }
    
    .info-row .info-value .badge-value {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .info-row .info-value .price-value {
        color: #059669;
        font-weight: 700;
    }
    
    /* Card Custom - Blue Theme */
    .card-custom {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card-custom:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title-custom {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .card-title-custom .title-icon {
        color: #0B5ED7;
        margin-right: 8px;
    }
    
    /* Status Badge - Blue Theme */
    .status-badge-large {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    
    .status-badge-large.pending {
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid #FBBF24;
    }
    
    .status-badge-large.in_progress {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #6EA8FE;
    }
    
    .status-badge-large.completed {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #34D399;
    }
    
    .status-badge-large.cancelled {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #F87171;
    }
    
    [data-theme="dark"] .status-badge-large.pending {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #FBBF24;
    }
    
    [data-theme="dark"] .status-badge-large.in_progress {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
    }
    
    [data-theme="dark"] .status-badge-large.completed {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .status-badge-large.cancelled {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    /* Badge styles - Blue Theme */
    .badge-custom {
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
        background: #3D2E0A !important;
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
    
    /* Results Section */
    .results-box {
        background: #F8FAFC;
        border-radius: 10px;
        padding: 16px 20px;
        border: 1px solid #E2E8F0;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        min-height: 60px;
    }
    
    [data-theme="dark"] .results-box {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }
    
    /* Buttons - Blue Theme */
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-edit {
        background: #F59E0B;
        color: white;
    }
    .btn-edit:hover {
        background: #D97706;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .btn-print {
        background: #64748B;
        color: white;
    }
    .btn-print:hover {
        background: #475569;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        font-weight: 600;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .profile-header-custom {
            padding: 16px 18px;
        }
        .profile-header-custom .profile-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
        .profile-header-custom .profile-name {
            font-size: 1.1rem;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            padding: 6px 0;
        }
        .info-row .info-label {
            width: 100%;
            font-size: 0.7rem;
        }
        .info-row .info-value {
            font-size: 0.8rem;
        }
        .stat-card-custom .stat-number {
            font-size: 1.3rem;
        }
        .results-box {
            font-size: 0.75rem;
            padding: 12px 14px;
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
            <form method="GET" action="lab_tests.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search lab tests..." 
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
            <?php foreach ($branches as $branch): ?>
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
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PROFILE HEADER - BLUE -->
    <!-- ================================================================ -->
    <div class="profile-header-custom mb-5">
        <div class="flex items-center gap-4 flex-wrap" style="position:relative;z-index:1;">
            <div class="profile-icon">
                <i class="fas fa-flask"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="profile-name"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></h1>
                    <span class="profile-badge">
                        <i class="fas fa-id-card"></i> Test ID: #<?= $test['id'] ?>
                    </span>
                    <?php if (!empty($test['patient_name'])): ?>
                        <span class="profile-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($test['patient_name']) ?>
                        </span>
                    <?php endif; ?>
                    <span class="profile-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($test['branch_name'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 flex-wrap mt-1" style="opacity:0.85;">
                    <span><i class="fas fa-user-md"></i> Doctor: <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($test['created_at'])) ?></span>
                    <span>
                        <i class="fas fa-circle" style="color:<?= getStatusColor($test['status']) ?>;font-size:0.6rem;"></i>
                        <?= ucfirst(str_replace('_', ' ', $test['status'])) ?>
                    </span>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn-outline btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <?php if ($test['status'] !== 'completed' && $test['status'] !== 'cancelled'): ?>
                    <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-edit btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                <?php endif; ?>
                <?php if ($test['status'] === 'completed'): ?>
                    <button onclick="window.print()" class="btn-print btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-print"></i> Print Result
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        
        <div class="stat-card-custom">
            <div class="stat-icon">🔬</div>
            <p class="stat-number">#<?= $test['id'] ?></p>
            <p class="stat-label">Test ID</p>
        </div>
        
        <div class="stat-card-custom">
            <div class="stat-icon">💰</div>
            <p class="stat-number green">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></p>
            <p class="stat-label">Test Price</p>
        </div>
        
        <div class="stat-card-custom">
            <div class="stat-icon">📋</div>
            <p class="stat-number orange"><?= $test['total_lab_requests'] ?? 0 ?></p>
            <p class="stat-label">Total Lab Requests</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TEST INFORMATION -->
    <!-- ================================================================ -->
    <div class="card-custom mb-5">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-info-circle title-icon"></i> Test Information
            </h3>
            <span class="status-badge-large <?= $test['status'] ?>">
                <i class="fas <?= getStatusIcon($test['status']) ?>"></i>
                <?= ucfirst(str_replace('_', ' ', $test['status'])) ?>
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
            <div class="info-row">
                <span class="info-label">Test Name</span>
                <span class="info-value font-semibold"><?= htmlspecialchars($test['test_name'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Type</span>
                <span class="info-value">
                    <?php if (!empty($test['test_type']) && $test['test_type'] !== 'N/A'): ?>
                        <?= htmlspecialchars($test['test_type']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not specified</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Sample Type</span>
                <span class="info-value">
                    <?php if (!empty($test['sample_type']) && $test['sample_type'] !== 'N/A'): ?>
                        <?= htmlspecialchars($test['sample_type']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not specified</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Price</span>
                <span class="info-value price-value">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Date</span>
                <span class="info-value">
                    <?php if (!empty($test['test_date']) && $test['test_date'] !== '0000-00-00'): ?>
                        <?= date('M d, Y', strtotime($test['test_date'])) ?>
                    <?php else: ?>
                        <span class="empty-value">Not scheduled</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Created</span>
                <span class="info-value"><?= date('M d, Y h:i A', strtotime($test['created_at'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Completed</span>
                <span class="info-value">
                    <?php if (!empty($test['completed_at'])): ?>
                        <?= date('M d, Y h:i A', strtotime($test['completed_at'])) ?>
                    <?php else: ?>
                        <span class="empty-value">Not completed yet</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Performed By</span>
                <span class="info-value">
                    <?php if (!empty($test['lab_technician_name'])): ?>
                        <?= htmlspecialchars($test['lab_technician_name']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Reference Range</span>
                <span class="info-value">
                    <?php if (!empty($test['reference_range']) && $test['reference_range'] !== 'N/A'): ?>
                        <?= htmlspecialchars($test['reference_range']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not specified</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT & VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
        
        <!-- Patient Information -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-user title-icon"></i> Patient Information
                </h3>
                <?php if (!empty($test['patient_id']) && !empty($test['patient_id_number'])): ?>
                    <a href="patient_details.php?id=<?= $test['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-blue btn-sm">
                        View Patient <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($test['patient_name'])): ?>
                <div class="grid grid-cols-1 gap-1">
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value font-semibold"><?= htmlspecialchars($test['patient_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Patient ID</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($test['patient_id_number'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($test['patient_phone'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?= htmlspecialchars($test['patient_gender'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of Birth</span>
                        <span class="info-value"><?= $test['patient_dob'] ? date('M d, Y', strtotime($test['patient_dob'])) : '—' ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-3">
                    <i class="fas fa-info-circle"></i> No patient associated with this test
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Visit Information -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-notes-medical title-icon" style="color:#059669;"></i> Visit Information
                </h3>
                <?php if (!empty($test['visit_id']) && !empty($test['visit_number'])): ?>
                    <a href="visit_details.php?id=<?= $test['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-green btn-sm">
                        View Visit <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($test['visit_number'])): ?>
                <div class="grid grid-cols-1 gap-1">
                    <div class="info-row">
                        <span class="info-label">Visit Number</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($test['visit_number']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Visit Type</span>
                        <span class="info-value"><?= ucfirst($test['visit_type'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Visit Date</span>
                        <span class="info-value"><?= date('M d, Y h:i A', strtotime($test['visit_date'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Doctor</span>
                        <span class="info-value"><?= htmlspecialchars($test['doctor_name'] ?? '—') ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-3">
                    <i class="fas fa-info-circle"></i> No visit associated with this test
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULTS -->
    <!-- ================================================================ -->
    <div class="card-custom mb-5">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-flask title-icon"></i> Test Results                <span class="badge-custom <?= $test['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                    <?= $test['status'] === 'completed' ? '✅ Completed' : '⏳ Pending' ?>
                </span>
            </h3>
        </div>
        
        <?php if (!empty($test['results'])): ?>
            <div class="results-box">
                <?= htmlspecialchars($test['results']) ?>
            </div>
        <?php else: ?>
            <div class="text-center text-gray-400 text-sm py-3">
                <i class="fas fa-inbox text-2xl block mb-2"></i>
                No results available for this test
            </div>
        <?php endif; ?>
        
        <?php if (!empty($test['interpretation'])): ?>
            <div class="mt-3">
                <h4 class="font-semibold text-sm" style="color:#0B5ED7;">Interpretation</h4>
                <div class="mt-1 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <?= nl2br(htmlspecialchars($test['interpretation'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- NOTES -->
    <!-- ================================================================ -->
    <?php if (!empty($test['notes'])): ?>
        <div class="card-custom mb-5">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-sticky-note title-icon" style="color:#F59E0B;"></i> Notes
                </h3>
            </div>
            <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <?= nl2br(htmlspecialchars($test['notes'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-bolt title-icon"></i> Quick Actions
            </h3>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Lab Tests
            </a>
            <?php if (!empty($test['patient_id']) && !empty($test['patient_name'])): ?>
                <a href="patient_details.php?id=<?= $test['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-blue btn-sm">
                    <i class="fas fa-user"></i> View Patient
                </a>
            <?php endif; ?>
            <?php if (!empty($test['visit_id']) && !empty($test['visit_number'])): ?>
                <a href="visit_details.php?id=<?= $test['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-green btn-sm">
                    <i class="fas fa-notes-medical"></i> View Visit
                </a>
            <?php endif; ?>
            <?php if ($test['status'] !== 'completed' && $test['status'] !== 'cancelled'): ?>
                <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-edit btn-sm">
                    <i class="fas fa-edit"></i> Edit Test
                </a>
            <?php endif; ?>
            <?php if ($test['status'] === 'completed'): ?>
                <button onclick="window.print()" class="btn-print btn-sm">
                    <i class="fas fa-print"></i> Print Results
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Test Details
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

    console.log('%c🔵 Braick Dispensary - Lab Test Details (Blue Theme)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🧪 Test: <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Status: <?= ucfirst($test['status']) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c💙 Blue Theme Applied', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>