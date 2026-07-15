<?php
// ================================================================
// FILE: frontend/pages/cashier/profile.php
// CASHIER - PROFILE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose (Cashier)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['email'] = 'rose@braick.com';
    $_SESSION['phone'] = '+255 700 000 006';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 6;
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'reception.rose';
$user_email = $_SESSION['email'] ?? 'rose@braick.com';
$user_phone = $_SESSION['phone'] ?? '+255 700 000 006';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$db = getDB();

// ================================================================
// GET USER STATISTICS
// ================================================================

// 1. Total Payments Processed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM payments 
    WHERE received_by = ?
");
$stmt->execute([$user_id]);
$total_payments = $stmt->fetch()['count'] ?? 0;

// 2. Total Revenue Collected
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE received_by = ?
");
$stmt->execute([$user_id]);
$total_revenue = $stmt->fetch()['total'] ?? 0;

// 3. Today's Payments
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE received_by = ? AND DATE(received_at) = ?
");
$stmt->execute([$user_id, $today]);
$today_stats = $stmt->fetch();
$today_payments = $today_stats['count'] ?? 0;
$today_revenue = $today_stats['total'] ?? 0;

// 4. Recent Activity (Last 5 payments)
$stmt = $db->prepare("
    SELECT p.*, 
           b.bill_number,
           pat.full_name as patient_name
    FROM payments p
    JOIN bills b ON p.bill_id = b.id
    JOIN patients pat ON p.patient_id = pat.id
    WHERE p.received_by = ?
    ORDER BY p.received_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll();

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$paid_today = 0;
$patients_waiting = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
    $stmt->execute([$user_branch_id]);
    $partial_payments = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $paid_today = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
    $stmt->execute([$user_branch_id]);
    $patients_waiting = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // Keep counts as 0
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       PROFILE STYLES
       ================================================================ */
    
    .profile-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 30px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 30px;
    }
    
    .profile-header .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--primary);
        flex-shrink: 0;
    }
    
    .profile-header .profile-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        color: white;
        background: var(--primary);
        flex-shrink: 0;
        border: 4px solid var(--primary);
    }
    
    .profile-header .profile-info .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .profile-header .profile-info .profile-role {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .profile-header .profile-info .profile-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    
    .profile-header .profile-info .profile-badges .badge {
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
    }
    
    .profile-header .profile-info .profile-badges .badge-blue {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .profile-header .profile-info .profile-badges .badge-green {
        background: #D1FAE5;
        color: #059669;
    }
    
    .profile-header .profile-info .profile-badges .badge-purple {
        background: #F3E8FF;
        color: #7C3AED;
    }
    
    .profile-header .profile-info .profile-badges .badge-orange {
        background: #FEF3C7;
        color: #D97706;
    }
    
    [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-blue {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-green {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-purple {
        background: #2A1A3A;
        color: #9B4DCA;
    }
    
    [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-orange {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .stat-box .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .stat-box .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-box .stat-icon {
        font-size: 1.2rem;
        margin-bottom: 4px;
        color: var(--primary);
    }
    
    .stat-box .stat-icon .fa-money-bill-wave { color: #0D9488; }
    .stat-box .stat-icon .fa-receipt { color: #0B5ED7; }
    .stat-box .stat-icon .fa-calendar-day { color: #D97706; }
    .stat-box .stat-icon .fa-hand-holding-usd { color: #7C3AED; }
    
    .info-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .info-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .info-card .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-card .card-title i {
        color: var(--primary);
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-row .info-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .info-row .info-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
    }
    
    .activity-item:hover {
        background: var(--primary-bg);
        border-radius: 8px;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-item .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary-bg);
        color: var(--primary);
        flex-shrink: 0;
    }
    
    [data-theme="dark"] .activity-item .activity-icon {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .activity-item .activity-info .activity-title {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .activity-item .activity-info .activity-desc {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .activity-item .activity-time {
        font-size: 0.65rem;
        color: var(--text-secondary);
        margin-left: auto;
        white-space: nowrap;
    }
    
    .btn-edit {
        background: var(--primary);
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-edit:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 6px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-edit-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 2rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        .profile-header .profile-info .profile-badges {
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
        .activity-item {
            flex-wrap: wrap;
        }
        .activity-item .activity-time {
            margin-left: 0;
            width: 100%;
            padding-left: 48px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .profile-header .profile-avatar,
        .profile-header .profile-avatar-placeholder {
            width: 80px;
            height: 80px;
            font-size: 2rem;
        }
        .profile-header .profile-info .profile-name {
            font-size: 1.2rem;
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i> My Profile
            </h1>
            <p class="page-subtitle">
                View and manage your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="edit_profile.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <a href="dashboard.php" class="btn-edit-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header animate-fade-in-up">
        <?php if (!empty($profile_pic)): ?>
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="profile-avatar">
        <?php else: ?>
            <div class="profile-avatar-placeholder">
                <?= strtoupper(substr($user_full_name, 0, 1)) ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($user_full_name) ?></div>
            <div class="profile-role">
                <i class="fas fa-cash-register mr-1"></i> Cashier / Receptionist
            </div>
            <div class="profile-badges">
                <span class="badge badge-blue">
                    <i class="fas fa-user mr-1"></i> <?= ucfirst($user_role) ?>
                </span>
                <span class="badge badge-green">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="badge badge-orange">
                    <i class="fas fa-receipt mr-1"></i> <?= $total_payments ?> payments
                </span>
                <span class="badge badge-purple">
                    <i class="fas fa-money-bill-wave mr-1"></i> TSh <?= number_format($total_revenue) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <p class="stat-number"><?= $total_payments ?></p>
            <p class="stat-label">Total Payments</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
            <p class="stat-label">Total Revenue</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <p class="stat-number"><?= $today_payments ?></p>
            <p class="stat-label">Today's Payments</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <p class="stat-number">TSh <?= number_format($today_revenue) ?></p>
            <p class="stat-label">Today's Revenue</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE DETAILS & RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Personal Information -->
        <div class="info-card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-user-circle"></i>
                Personal Information
            </div>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($user_full_name) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?= htmlspecialchars($user_username) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($user_email) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($user_phone) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">
                    <span class="badge badge-blue" style="font-size:0.7rem;">
                        <?= ucfirst($user_role) ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Branch</span>
                <span class="info-value"><?= htmlspecialchars($user_branch_name) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Member Since</span>
                <span class="info-value"><?= date('F d, Y') ?></span>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="info-card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-history"></i>
                Recent Activity
            </div>
            
            <?php if (count($recent_activity) > 0): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="activity-info">
                            <div class="activity-title">
                                Payment Received
                            </div>
                            <div class="activity-desc">
                                <i class="fas fa-user mr-1"></i>
                                <?= htmlspecialchars($activity['patient_name'] ?? 'Unknown') ?>
                                <span class="mx-1">|</span>
                                <i class="fas fa-file-invoice mr-1"></i>
                                <?= htmlspecialchars($activity['bill_number'] ?? 'N/A') ?>
                                <span class="mx-1">|</span>
                                <i class="fas fa-money-bill-wave mr-1"></i>
                                TSh <?= number_format($activity['amount'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="activity-time">
                            <?= isset($activity['received_at']) ? date('M d, Y h:i A', strtotime($activity['received_at'])) : 'Just now' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No recent activity</p>
                    <p class="text-xs text-gray-400 mt-1">Process some payments to see activity here</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="info-card animate-fade-in-up mt-4">
        <div class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="pending_bills.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-clock text-2xl text-orange-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Pending Bills</span>
            </a>
            <a href="receive_payment.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-hand-holding-usd text-2xl text-green-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Receive Payment</span>
            </a>
            <a href="payment_history.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-history text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Payment History</span>
            </a>
            <a href="reports.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-chart-bar text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Reports</span>
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Cashier Profile
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
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
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
        }, 3500);
    }

    console.log('%c💰 Braick - Cashier Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Payments: <?= $total_payments ?> | Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Today\'s Payments: <?= $today_payments ?> | Revenue: TSh <?= number_format($today_revenue) ?>', 'font-size:13px; color:#0D9488;');
</script>

</body>
</html>