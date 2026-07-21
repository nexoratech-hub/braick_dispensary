<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_request.php
// LABORATORY - VIEW REQUEST WITH UPDATE CAPABILITY (FIXED)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE LAB.DODOMA (ID: 8) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET REQUEST ID
// ================================================================
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
    header('Location: pending_requests.php');
    exit;
}

// ================================================================
// GET REQUEST DETAILS - FIXED: Use LEFT JOIN for visits
// ================================================================
$query = "
    SELECT lr.*, 
           p.full_name as patient_name, p.patient_id, p.phone, p.email,
           COALESCE(u.full_name, 'Not Assigned') as doctor_name,
           u.specialty,
           v.visit_number,
           b.name as branch_name,
           lab.full_name as lab_technician_name
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN branches b ON lr.branch_id = b.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.id = ? AND lr.branch_id = ?
";

$stmt = $db->prepare($query);
$stmt->execute([$request_id, $user_branch_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: pending_requests.php');
    exit;
}

// ================================================================
// GET REQUEST ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM lab_request_items 
    WHERE request_id = ? 
    ORDER BY id
");
$stmt->execute([$request_id]);
$test_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL DETAILS
// ================================================================
$bill = null;
if ($request['patient_id'] && $request['visit_id']) {
    $stmt = $db->prepare("
        SELECT * FROM patient_bills 
        WHERE patient_id = ? AND visit_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$request['patient_id'], $request['visit_id']]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<style>
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
    .status-badge-request {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    .status-badge-request.pending { background: #FEF3C7; color: #D97706; }
    .status-badge-request.accepted { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-request.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-request.completed { background: #D1FAE5; color: #059669; }
    .status-badge-request.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .test-item {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    .test-item:hover {
        border-color: var(--primary);
    }
    .test-item.completed {
        border-color: #059669;
        background: rgba(5, 150, 105, 0.05);
    }
    .test-item.in_progress {
        border-color: #0B5ED7;
        background: rgba(11, 94, 215, 0.05);
    }
    .test-item.pending {
        border-color: #D97706;
        background: rgba(217, 119, 6, 0.05);
    }
    
    .test-status-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .test-status-badge.pending { background: #FEF3C7; color: #D97706; }
    .test-status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .test-status-badge.completed { background: #D1FAE5; color: #059669; }
    .test-status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    .btn-green { background: #059669; color: white; }
    .btn-green:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
    .btn-orange { background: #D97706; color: white; }
    .btn-orange:hover { background: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
    .btn-red { background: #DC2626; color: white; }
    .btn-red:hover { background: #B91C1C; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
    .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 16px 20px;
        background: var(--bg-body);
        border-radius: 12px;
        border: 2px solid var(--border-color);
    }
    
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .branch-badge {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    .role-badge {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    .datetime {
        font-size: 0.78rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s;
    }
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
    .search-wrapper {
        display: flex;
        align-items: center;
        background: var(--bg-body);
        border-radius: 10px;
        border: 2px solid var(--border-color);
        transition: all 0.3s;
        flex: 1;
        max-width: 500px;
    }
    .search-wrapper:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
    }
    .search-wrapper input {
        border: none;
        background: transparent;
        padding: 8px 14px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    .search-wrapper .search-btn {
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
    .search-wrapper .search-btn:hover {
        background: var(--primary-dark);
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
    .icon-btn {
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
    .notif-dot {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        border: 2px solid var(--bg-nav);
    }
    .notif-dot.has-notif { background: var(--danger); }
    .notif-dot.no-notif { background: var(--gray-400); animation: none; }
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        transition: background 0.3s ease;
    }
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
    }
    .page-title {
        color: var(--primary-dark);
        font-size: 1.6rem;
        font-weight: 700;
    }
    .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
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
    
    @media (max-width: 1024px) {
        .top-nav { left: 0; }
        .main-content { margin-left: 0; padding: 16px; }
    }
    @media (max-width: 768px) {
        .top-nav .search-wrapper { max-width: 180px; }
        .top-nav .datetime { display: none; }
        .page-title { font-size: 1.2rem; }
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
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        <a href="profile.php">
            <img src="/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($request): ?>
    
    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2" style="color: var(--primary);"></i> Request Details
                <span class="role-badge ml-2">LAB</span>
            </h1>
            <p class="page-subtitle">
                <span class="font-mono font-semibold text-blue-600"><?= htmlspecialchars($request['request_number']) ?></span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($request['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="in_progress.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="javascript:window.print()" class="btn btn-blue btn-sm">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REQUEST OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Request Information
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <p class="detail-label">Request Number</p>
                    <p class="detail-value font-mono text-sm"><?= htmlspecialchars($request['request_number']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-request <?= $request['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Doctor</p>
                    <p class="detail-value"><?= htmlspecialchars($request['doctor_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Requested</p>
                    <p class="detail-value"><?= date('M d, Y h:i A', strtotime($request['requested_at'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($request['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Tests</p>
                    <p class="detail-value"><?= count($test_items) ?></p>
                </div>
                <?php if ($request['accepted_at']): ?>
                    <div>
                        <p class="detail-label">Accepted</p>
                        <p class="detail-value"><?= date('M d, Y h:i A', strtotime($request['accepted_at'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($request['completed_at']): ?>
                    <div>
                        <p class="detail-label">Completed</p>
                        <p class="detail-value"><?= date('M d, Y h:i A', strtotime($request['completed_at'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value"><?= htmlspecialchars($request['patient_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($request['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($request['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($request['email'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="quick-actions mb-5">
        <?php if ($request['status'] === 'pending'): ?>
            <a href="update_test_status.php?action=accept&id=<?= $request['id'] ?>" 
               class="btn btn-green" onclick="return confirm('Accept this request? This will add charges to the patient\'s bill.')">
                <i class="fas fa-check"></i> Accept & Start
            </a>
        <?php endif; ?>
        
        <?php if ($request['status'] === 'accepted' || $request['status'] === 'in_progress'): ?>
            <a href="update_test_status.php?action=complete&id=<?= $request['id'] ?>" 
               class="btn btn-green" onclick="return confirm('Complete this request? Results will be sent to the doctor.')">
                <i class="fas fa-check-circle"></i> Complete All
            </a>
        <?php endif; ?>
        
        <?php if ($request['status'] !== 'completed' && $request['status'] !== 'cancelled'): ?>
            <a href="update_test_status.php?action=cancel&id=<?= $request['id'] ?>" 
               class="btn btn-red" onclick="return confirm('Cancel this request?')">
                <i class="fas fa-times"></i> Cancel Request
            </a>
        <?php endif; ?>
        
        <?php if ($request['status'] === 'completed'): ?>
            <span class="btn btn-outline" style="border-color:#059669;color:#059669;">
                <i class="fas fa-check-circle"></i> Completed
            </span>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TEST ITEMS -->
    <!-- ================================================================ -->
    <div class="detail-card mb-5">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-flask text-purple-600 mr-2"></i> Tests
            <span class="text-sm font-normal text-gray-400">(<?= count($test_items) ?> items)</span>
        </h3>
        
        <?php if (count($test_items) > 0): ?>
            <?php foreach ($test_items as $index => $item): ?>
                <div class="test-item <?= $item['status'] ?? 'pending' ?>">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-semibold">#<?= $index + 1 ?></span>
                                <span class="font-medium"><?= htmlspecialchars($item['test_name']) ?></span>
                                <span class="test-status-badge <?= $item['status'] ?? 'pending' ?>">
                                    <?php if (($item['status'] ?? 'pending') === 'pending'): ?>
                                        ⏳ Pending
                                    <?php elseif ($item['status'] === 'in_progress'): ?>
                                        🔬 In Progress
                                    <?php elseif ($item['status'] === 'completed'): ?>
                                        ✅ Completed
                                    <?php else: ?>
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($item['price']) && $item['price'] > 0): ?>
                                    <span class="text-xs text-gray-500">TSh <?= number_format($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['reference_range'])): ?>
                                <p class="text-xs text-gray-400 mt-1">
                                    Reference: <?= htmlspecialchars($item['reference_range']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?php if ($item['completed_at']): ?>
                                Completed: <?= date('M d, Y h:i A', strtotime($item['completed_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Show result if completed -->
                    <?php if (($item['status'] ?? '') === 'completed' && !empty($item['result'])): ?>
                        <div class="mt-2 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                            <p class="text-xs text-gray-500">Result:</p>
                            <p class="font-mono text-sm whitespace-pre-wrap"><?= nl2br(htmlspecialchars($item['result'])) ?></p>
                            <?php if (!empty($item['comments'])): ?>
                                <p class="text-xs text-gray-400 mt-1">Notes: <?= htmlspecialchars($item['comments']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Update Form - Only if not completed -->
                    <?php if ($request['status'] !== 'completed' && $request['status'] !== 'cancelled'): ?>
                        <form method="POST" action="update_test_status.php?action=update_test&id=<?= $request['id'] ?>&test_id=<?= $item['id'] ?>" 
                              class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="form-label text-xs">Status</label>
                                    <select name="status" class="form-control" style="padding:4px 10px;font-size:0.8rem;">
                                        <option value="pending" <?= ($item['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                        <option value="in_progress" <?= ($item['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>🔬 In Progress</option>
                                        <option value="completed" <?= ($item['status'] ?? '') === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label text-xs">Result</label>
                                    <textarea name="result" class="form-control" rows="1" placeholder="Enter test result..." 
                                              style="padding:4px 10px;font-size:0.8rem;min-height:32px;"><?= htmlspecialchars($item['result'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="form-label text-xs">Notes</label>
                                    <input type="text" name="notes" class="form-control" placeholder="Notes..." 
                                           value="<?= htmlspecialchars($item['comments'] ?? '') ?>" 
                                           style="padding:4px 10px;font-size:0.8rem;">
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="submit" class="btn btn-blue btn-sm">
                                    <i class="fas fa-save"></i> Update Test
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-flask text-3xl block mb-2"></i>
                <p>No test items found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILLING SUMMARY -->
    <!-- ================================================================ -->
    <?php if ($bill): ?>
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-receipt text-green-600 mr-2"></i> Billing Summary
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="detail-label">Bill Number</p>
                    <p class="detail-value font-mono text-sm"><?= htmlspecialchars($bill['bill_number']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Total Amount</p>
                    <p class="detail-value text-green-600">TSh <?= number_format($bill['total_amount'] ?? 0) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-request <?= $bill['status'] ?? 'pending' ?>">
                            <?= ucfirst($bill['status'] ?? 'Pending') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Balance</p>
                    <p class="detail-value <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                        TSh <?= number_format($bill['balance'] ?? 0) ?>
                    </p>
                </div>
            </div>
            <div class="mt-3 text-sm text-gray-400">
                <i class="fas fa-info-circle mr-1"></i> 
                This bill has been sent to the cashier for payment processing.
            </div>
        </div>
    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-flask text-4xl block mb-3"></i>
            <p class="text-lg">Request not found</p>
            <a href="pending_requests.php" class="text-blue-600 hover:underline">Back to requests</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Request
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
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
        }, 5000);
    }

    // Check for success/error messages from redirect
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var success = urlParams.get('success');
        var message = urlParams.get('message');
        
        if (success === '1' && message) {
            setTimeout(function() {
                showToast('✅ Success', message, 'success');
            }, 500);
        } else if (success === '0' && message) {
            setTimeout(function() {
                showToast('❌ Error', message, 'error');
            }, 500);
        }
    })();

    console.log('%c🧪 Braick - View Request (Lab Technician)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Request: <?= htmlspecialchars($request['request_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($request['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= ucfirst($request['status'] ?? 'N/A') ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💰 Tests: <?= count($test_items) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>