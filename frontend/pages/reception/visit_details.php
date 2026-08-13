<?php
// ================================================================
// FILE: frontend/pages/reception/visit_details.php
// RECEPTION - VIEW VISIT DETAILS (BRANCH FILTERED)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if ($visit_id <= 0) {
    header('Location: visits.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS COUNT
    // ================================================================
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
    
    // ================================================================
    // GET VISIT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name, 
               p.patient_id as patient_number, 
               p.phone, 
               p.email, 
               p.address, 
               p.gender, 
               p.date_of_birth,
               p.blood_group,
               p.allergies,
               p.marital_status,
               u.id as doctor_id,
               u.full_name as doctor_name, 
               u.specialty, 
               u.phone as doctor_phone,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$visit_id, $branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $error = "Visit not found or you don't have permission to view it.";
    }
    
    // ================================================================
    // GET PRESCRIPTIONS FOR THIS VISIT
    // ================================================================
    $prescriptions = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM prescriptions 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET LAB TESTS FOR THIS VISIT
    // ================================================================
    $lab_tests = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM lab_tests 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET BILLS FOR THIS VISIT
    // ================================================================
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM patient_bills 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bills as $bill) {
            $total_amount += $bill['total_amount'] ?? 0;
            $total_paid += $bill['paid_amount'] ?? 0;
            $total_balance += $bill['balance'] ?? 0;
        }
    }
    
    // ================================================================
    // GET TIMELINE (activities related to this visit)
    // ================================================================
    $activities = [];
    if ($visit) {
        try {
            $stmt = $db->prepare("
                SELECT action, details, created_at 
                FROM activity_logs 
                WHERE details LIKE ? OR action = 'visit_created'
                ORDER BY created_at DESC
                LIMIT 15
            ");
            $search = '%' . $visit['visit_number'] . '%';
            $stmt->execute([$search]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $activities = [];
        }
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $visit = null;
    $prescriptions = [];
    $lab_tests = [];
    $activities = [];
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    /* ================================================================
       VISIT DETAILS STYLES
       ================================================================ */
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .detail-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
    }
    .detail-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .detail-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .detail-value .text-muted {
        color: var(--text-secondary);
        font-weight: 400;
    }
    
    .status-badge-visit {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    .status-badge-visit.pending { background: #FEF3C7; color: #D97706; }
    .status-badge-visit.assigned { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-visit.with_doctor { background: #FEF3C7; color: #D97706; }
    .status-badge-visit.completed { background: #D1FAE5; color: #059669; }
    .status-badge-visit.cancelled { background: #FEE2E2; color: #DC2626; }
    .status-badge-visit.scheduled { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-visit.paid { background: #D1FAE5; color: #059669; }
    .status-badge-visit.partial { background: #FEF3C7; color: #D97706; }
    
    [data-theme="dark"] .status-badge-visit.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-visit.assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge-visit.with_doctor { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-visit.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-visit.cancelled { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge-visit.scheduled { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge-visit.paid { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-visit.partial { background: #3D2E0A; color: #FBBF24; }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s;
    }
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
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
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .card-title .title-blue { color: #0B5ED7; }
    .card-title .title-green { color: #059669; }
    .card-title .title-purple { color: #7C3AED; }
    .card-title .title-orange { color: #D97706; }
    
    .timeline-item {
        display: flex;
        gap: 14px;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
        align-items: flex-start;
    }
    .timeline-item:last-child {
        border-bottom: none;
    }
    .timeline-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        flex-shrink: 0;
        background: var(--primary-bg);
        color: var(--primary);
    }
    .timeline-content {
        flex: 1;
    }
    .timeline-content .action {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    .timeline-content .details {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    .timeline-content .time {
        font-size: 0.65rem;
        color: var(--text-secondary);
        opacity: 0.7;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
    }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .role-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
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
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .patient-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.7rem;
        flex-shrink: 0;
    }
    
    .scroll-container {
        max-height: 200px;
        overflow-y: auto;
    }
    .scroll-container::-webkit-scrollbar {
        width: 4px;
    }
    .scroll-container::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    .scroll-container::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    
    .error-box {
        background: var(--danger-bg);
        border: 2px solid var(--danger);
        border-radius: 12px;
        padding: 20px 24px;
        text-align: center;
        max-width: 600px;
        margin: 40px auto;
    }
    .error-box i {
        font-size: 3rem;
        color: var(--danger);
        display: block;
        margin-bottom: 12px;
    }
    .error-box h3 {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--danger-dark);
    }
    .error-box p {
        color: var(--text-secondary);
        margin: 8px 0 16px;
    }
    
    .bill-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-top: 8px;
    }
    .bill-item {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 10px 14px;
        text-align: center;
        border: 1px solid var(--border-color);
    }
    .bill-item .bill-amount {
        font-size: 1.1rem;
        font-weight: 700;
    }
    .bill-item .bill-amount.total { color: var(--primary); }
    .bill-item .bill-amount.paid { color: var(--success); }
    .bill-item .bill-amount.balance { color: var(--danger); }
    .bill-item .bill-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-weight: 500;
    }
    .bill-item .bill-amount.balance.zero { color: var(--success); }
    
    [data-theme="dark"] .bill-item {
        background: #1E293B;
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
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <!-- ============================================================ -->
        <!-- DATE & TIME - WITH ID FOR JAVASCRIPT -->
        <!-- ============================================================ -->
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i>
            <?= date('D, M d, Y h:i:s A') ?>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-circle"></i>
            <h3>❌ Error</h3>
            <p><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" class="btn btn-blue">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>
    <?php elseif ($visit): ?>
    
    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical mr-2" style="color: var(--primary);"></i> Visit Details
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View complete visit information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
                <?php if ($visit['is_completed'] == 1): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-check-circle mr-1"></i> Completed
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="visits.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                <a href="visit_status.php?id=<?= $visit['id'] ?>&status=completed&redirect=visit_details.php?id=<?= $visit['id'] ?>" class="btn btn-green btn-sm">
                    <i class="fas fa-check"></i> Complete
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Visit Info -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Visit Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-visit <?= $visit['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Date & Time</p>
                    <p class="detail-value"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></p>
                </div>
                <div class="col-span-2">
                    <p class="detail-label">Branch</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($visit['symptoms'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Symptoms</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['symptoms'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['complaint'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Complaint</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['complaint'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['notes'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Notes</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['notes'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Diagnosis</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['diagnosis'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Treatment</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($visit['follow_up_date']): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Follow-up Date</p>
                        <p class="detail-value"><?= date('F d, Y', strtotime($visit['follow_up_date'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Info - NO BUTTONS -->
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient
            </h3>
            <div class="flex items-center gap-3 mb-3">
                <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($visit['patient_name']), 0, 6) ?>;">
                    <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($visit['patient_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value"><?= ucfirst(htmlspecialchars($visit['gender'] ?? 'N/A')) ?></p>
                </div>
                <div>
                    <p class="detail-label">Date of Birth</p>
                    <p class="detail-value"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="detail-label">Blood Group</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($visit['allergies'])): ?>
                    <div>
                        <p class="detail-label">Allergies</p>
                        <p class="detail-value text-red-600"><?= htmlspecialchars($visit['allergies']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR INFO -->
    <!-- ================================================================ -->
    <div class="detail-card mb-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-user-md text-primary mr-2"></i> Doctor
        </h3>
        <?php if ($visit['doctor_id']): ?>
            <div class="flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold">
                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></p>
                    </div>
                </div>
                <?php if (!empty($visit['doctor_phone'])): ?>
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($visit['doctor_phone']) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No doctor assigned</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <?php if (!empty($bills)): ?>
    <div class="detail-card mb-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-money-bill-wave text-primary mr-2"></i> Bill Summary
            <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bill(s))</span>
        </h3>
        <div class="bill-summary">
            <div class="bill-item">
                <p class="bill-amount total">TSh <?= number_format($total_amount, 2) ?></p>
                <p class="bill-label">Total Amount</p>
            </div>
            <div class="bill-item">
                <p class="bill-amount paid">TSh <?= number_format($total_paid, 2) ?></p>
                <p class="bill-label">Paid Amount</p>
            </div>
            <div class="bill-item">
                <p class="bill-amount balance <?= $total_balance <= 0 ? 'zero' : '' ?>">TSh <?= number_format($total_balance, 2) ?></p>
                <p class="bill-label">Balance</p>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-sm font-medium">Overall Status: </span>
            <span class="status-badge-visit <?= $total_balance <= 0 ? 'paid' : 'partial' ?>">
                <?= $total_balance <= 0 ? '✅ Paid' : '⏳ Partial / Pending' ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS & LAB TESTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Prescriptions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-prescription title-blue mr-2"></i> Prescriptions
                    <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container">
                <?php if (count($prescriptions) > 0): ?>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                            <div>
                                <p class="font-medium text-sm text-gray-800">#<?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></p>
                                <p class="text-xs text-gray-500"><?= isset($prescription['created_at']) ? date('M d, Y h:i A', strtotime($prescription['created_at'])) : 'N/A' ?></p>
                            </div>
                            <span class="status-badge-visit <?= $prescription['status'] ?? 'pending' ?>">
                                <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-prescription text-xl block mb-2"></i>
                        <p class="text-sm">No prescriptions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lab Tests -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-flask title-purple mr-2"></i> Lab Tests
                    <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container">
                <?php if (count($lab_tests) > 0): ?>
                    <?php foreach ($lab_tests as $test): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                            <div>
                                <p class="font-medium text-sm text-gray-800"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></p>
                                <p class="text-xs text-gray-500"><?= isset($test['created_at']) ? date('M d, Y h:i A', strtotime($test['created_at'])) : 'N/A' ?></p>
                            </div>
                            <span class="status-badge-visit <?= $test['status'] ?? 'pending' ?>">
                                <?= ucfirst($test['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-flask text-xl block mb-2"></i>
                        <p class="text-sm">No lab tests</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TIMELINE -->
    <!-- ================================================================ -->
    <?php if (count($activities) > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock title-blue mr-2"></i> Activity Timeline
            </h3>
        </div>
        <div class="timeline">
            <?php foreach ($activities as $activity): ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-circle text-[6px]"></i>
                    </div>
                    <div class="timeline-content">
                        <p class="action"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                        <p class="details"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                        <p class="time"><?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-clinic-medical text-4xl block mb-3"></i>
            <p class="text-lg">Visit not found</p>
            <a href="visits.php" class="text-primary hover:underline">Back to visits</a>
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
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
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
    // DATE & TIME - FIXED: Updates every second
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short', 
            day: 'numeric', 
            year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true
        });
        
        // Update top nav clock
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) {
            dtEl.innerHTML = '<i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i> ' + dateStr + ' • ' + timeStr;
        }
        
        // Update footer timestamp
        var ftEl = document.getElementById('footerTimestamp');
        if (ftEl) {
            ftEl.textContent = 'Last updated: ' + timeStr;
        }
    }
    
    // Update immediately and every second
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
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // TIME AGO
    // ================================================================
    function time_ago(timestamp) {
        if (!timestamp) return 'Just now';
        var now = new Date();
        var past = new Date(timestamp);
        var diff = Math.floor((now - past) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return past.toLocaleDateString();
    }

    console.log('%c🏥 Braick - Visit Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= ucfirst($visit['status'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c🕐 Clock: Updates every second', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>