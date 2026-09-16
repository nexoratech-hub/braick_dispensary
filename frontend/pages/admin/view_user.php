<?php
// ================================================================
// FILE: frontend/pages/admin/view_user.php
// ADMIN - VIEW USER DETAILS
// BRAICK DISPENSARY
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$view_user_id = $_GET['id'] ?? 0;
if ($view_user_id <= 0) {
    header('Location: users.php?error=invalid_id');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// GET USER DATA
$stmt = $db->prepare("
    SELECT 
        u.*,
        b.name as branch_name,
        b.location as branch_location,
        b.phone as branch_phone
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$view_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php?error=user_not_found');
    exit;
}

// GET UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// INCLUDE SHARED HEADER & SIDEBAR
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --vu-primary: #0B5ED7;
        --vu-primary-dark: #0A4CA8;
        --vu-primary-light: #6EA8FE;
        --vu-primary-bg: #E8F0FE;
        --vu-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --vu-success: #059669;
        --vu-success-bg: #D1FAE5;
        --vu-danger: #DC2626;
        --vu-danger-bg: #FEE2E2;
        --vu-warning: #D97706;
        --vu-warning-bg: #FEF3C7;
        --vu-purple: #7C3AED;
        --vu-purple-bg: #EDE9FE;
        --vu-bg-body: #F1F5F9;
        --vu-bg-card: #FFFFFF;
        --vu-text-primary: #0F172A;
        --vu-text-secondary: #64748B;
        --vu-text-muted: #94A3B8;
        --vu-border-color: #E2E8F0;
        --vu-radius: 12px;
        --vu-radius-lg: 18px;
        --vu-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --vu-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --vu-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --vu-bg-body: #0F172A;
        --vu-bg-card: #1E293B;
        --vu-text-primary: #F1F5F9;
        --vu-text-secondary: #94A3B8;
        --vu-text-muted: #64748B;
        --vu-border-color: #334155;
        --vu-primary-bg: #1E3A5F;
        --vu-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --vu-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    }

    /* ================================================================
       DARK MODE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-vu {
        background: var(--vu-primary-gradient);
        border-radius: var(--vu-radius-lg);
        padding: 24px 32px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-vu::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-vu .page-title-vu {
        color: white;
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-vu .page-title-vu i { font-size: 1.8rem; opacity: 0.9; }

    .page-header-vu .page-subtitle-vu {
        color: rgba(255,255,255,0.85);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-vu .page-subtitle-vu strong { color: white; font-weight: 600; }

    .page-header-vu .role-badge-display-vu {
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

    .page-header-vu .btn-outline-light-vu {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--vu-radius);
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        transition: all 0.3s;
    }

    .page-header-vu .btn-outline-light-vu:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       USER PROFILE CARD
       ================================================================ */
    .profile-card-vu {
        background: var(--vu-bg-card);
        border-radius: var(--vu-radius-lg);
        padding: 32px 36px;
        border: 2px solid var(--vu-primary-light);
        margin-bottom: 24px;
        box-shadow: var(--vu-shadow-md);
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        transition: all 0.3s ease;
    }

    .profile-card-vu:hover {
        border-color: var(--vu-primary);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.15);
    }

    [data-theme="dark"] .profile-card-vu {
        border-color: var(--vu-primary);
    }

    .profile-avatar-vu {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.8rem;
        font-weight: 700;
        color: white;
        background: var(--vu-primary-gradient);
        margin: 0 auto 16px auto;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        position: relative;
    }

    .profile-avatar-vu .online-indicator-vu {
        position: absolute;
        bottom: 4px;
        right: 4px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 3px solid var(--vu-bg-card);
    }

    .profile-avatar-vu .online-indicator-vu.online { background: #059669; }
    .profile-avatar-vu .online-indicator-vu.offline { background: #94A3B8; }

    .profile-name-vu {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--vu-text-primary);
        text-align: center;
        margin: 0;
    }

    .profile-username-vu {
        font-size: 0.9rem;
        color: var(--vu-text-secondary);
        text-align: center;
        font-family: 'Courier New', monospace;
        margin-top: 4px;
    }

    .profile-role-vu {
        text-align: center;
        margin-top: 12px;
    }

    /* ================================================================
       DETAIL GRID
       ================================================================ */
    .detail-grid-vu {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 2px solid var(--vu-border-color);
    }

    .detail-item-vu {
        padding: 8px 0;
    }

    .detail-item-vu .detail-label-vu {
        display: block;
        font-size: 0.7rem;
        color: var(--vu-text-secondary);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }

    .detail-item-vu .detail-label-vu i {
        color: var(--vu-primary);
        margin-right: 4px;
        width: 14px;
    }

    .detail-item-vu .detail-value-vu {
        font-size: 1rem;
        font-weight: 600;
        color: var(--vu-text-primary);
        word-break: break-word;
    }

    .detail-item-vu .detail-value-vu.mono {
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }

    .detail-item-vu .detail-value-vu.muted {
        font-weight: 400;
        color: var(--vu-text-secondary);
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-vu {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .badge-role-vu {
        background: var(--vu-primary-bg);
        color: var(--vu-primary);
        border: 1px solid rgba(11, 94, 215, 0.2);
    }

    [data-theme="dark"] .badge-role-vu {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: rgba(110, 168, 254, 0.2);
    }

    .badge-active-vu {
        background: var(--vu-success-bg);
        color: var(--vu-success);
    }

    [data-theme="dark"] .badge-active-vu {
        background: #1A3A2A;
        color: #34D399;
    }

    .badge-inactive-vu {
        background: var(--vu-danger-bg);
        color: var(--vu-danger);
    }

    [data-theme="dark"] .badge-inactive-vu {
        background: #3A1A1A;
        color: #F87171;
    }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .actions-vu {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 2px solid var(--vu-border-color);
    }

    .btn-vu {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: var(--vu-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-vu:hover {
        transform: translateY(-2px);
        box-shadow: var(--vu-shadow-lg);
    }

    .btn-primary-vu {
        background: var(--vu-primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary-vu:hover {
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-outline-vu {
        background: transparent;
        color: var(--vu-text-secondary);
        border: 2px solid var(--vu-border-color);
    }

    .btn-outline-vu:hover {
        background: var(--vu-bg-body);
        border-color: var(--vu-primary);
        color: var(--vu-primary);
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-vu {
        padding: 14px 0;
        border-top: 1px solid var(--vu-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--vu-text-secondary);
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .footer-vu .footer-brand-vu {
        color: var(--vu-primary);
        font-weight: 700;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-vu {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-vu { padding: 16px 18px; }
        .page-header-vu .page-title-vu { font-size: 1.2rem; }
        .profile-card-vu { padding: 20px 16px; }
        .detail-grid-vu { grid-template-columns: 1fr; }
        .actions-vu { flex-direction: column; }
        .actions-vu .btn-vu { width: 100%; justify-content: center; }
    }

    @media (max-width: 480px) {
        .page-header-vu { flex-direction: column; align-items: flex-start !important; }
        .profile-avatar-vu { width: 80px; height: 80px; font-size: 2rem; }
        .profile-name-vu { font-size: 1.2rem; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-vu, .btn-outline-light-vu, .actions-vu { display: none !important; }
        .profile-card-vu { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-vu {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-vu animate-fade-in-up-vu">
        <div>
            <h1 class="page-title-vu">
                <i class="fas fa-user-circle"></i>
                User Details
                <span class="role-badge-display-vu">ADMIN</span>
            </h1>
            <p class="page-subtitle-vu">
                <i class="fas fa-id-card"></i>
                Viewing: <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                <span style="background:rgba(255,255,255,0.15);padding:2px 10px;border-radius:12px;font-size:0.7rem;">
                    #<?= $user['id'] ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-outline-light-vu">
                <i class="fas fa-edit"></i> Edit User
            </a>
            <a href="users.php" class="btn-outline-light-vu">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <!-- PROFILE CARD -->
    <div class="profile-card-vu animate-fade-in-up-vu" style="animation-delay:0.05s;">
        
        <!-- Avatar -->
        <div class="profile-avatar-vu">
            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            <span class="online-indicator-vu <?= ($user['is_online'] ?? 0) ? 'online' : 'offline' ?>"></span>
        </div>
        
        <!-- Name & Username -->
        <h2 class="profile-name-vu"><?= htmlspecialchars($user['full_name']) ?></h2>
        <p class="profile-username-vu">@<?= htmlspecialchars($user['username']) ?></p>
        
        <!-- Role Badge -->
        <div class="profile-role-vu">
            <span class="badge-vu badge-role-vu">
                <i class="fas fa-user-tag"></i>
                <?= strtoupper($user['role']) ?>
            </span>
        </div>
        
        <!-- Detail Grid -->
        <div class="detail-grid-vu">
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-envelope"></i> Email Address
                </span>
                <span class="detail-value-vu mono"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-phone"></i> Phone Number
                </span>
                <span class="detail-value-vu <?= empty($user['phone']) ? 'muted' : '' ?>">
                    <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-toggle-on"></i> Account Status
                </span>
                <span class="detail-value-vu">
                    <span class="badge-vu badge-<?= $user['status'] === 'active' ? 'active' : 'inactive' ?>-vu">
                        <i class="fas fa-circle" style="font-size:6px;"></i>
                        <?= strtoupper($user['status']) ?>
                    </span>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-store-alt"></i> Branch
                </span>
                <span class="detail-value-vu <?= empty($user['branch_name']) ? 'muted' : '' ?>">
                    <?= htmlspecialchars($user['branch_name'] ?? 'No branch assigned') ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-stethoscope"></i> Specialty
                </span>
                <span class="detail-value-vu <?= empty($user['specialty']) ? 'muted' : '' ?>">
                    <?= htmlspecialchars($user['specialty'] ?? 'Not specified') ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-map-marker-alt"></i> Branch Location
                </span>
                <span class="detail-value-vu <?= empty($user['branch_location']) ? 'muted' : '' ?>">
                    <?= htmlspecialchars($user['branch_location'] ?? 'N/A') ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-calendar-plus"></i> Created At
                </span>
                <span class="detail-value-vu" style="font-size:0.85rem;">
                    <?= date('F d, Y h:i A', strtotime($user['created_at'])) ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-edit"></i> Last Updated
                </span>
                <span class="detail-value-vu" style="font-size:0.85rem;">
                    <?= date('F d, Y h:i A', strtotime($user['updated_at'])) ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-clock"></i> Last Online
                </span>
                <span class="detail-value-vu" style="font-size:0.85rem;">
                    <?= !empty($user['last_online']) ? date('F d, Y h:i A', strtotime($user['last_online'])) : 'Never' ?>
                </span>
            </div>
            
            <div class="detail-item-vu">
                <span class="detail-label-vu">
                    <i class="fas fa-key"></i> Password Status
                </span>
                <span class="detail-value-vu" style="font-size:0.85rem;">
                    <?php if (!empty($user['is_default_password'])): ?>
                        <span style="color:var(--vu-warning);">
                            <i class="fas fa-exclamation-triangle"></i> Default (needs change)
                        </span>
                    <?php else: ?>
                        <span style="color:var(--vu-success);">
                            <i class="fas fa-check-circle"></i> Changed by user
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            
        </div>
        
        <!-- Actions -->
        <div class="actions-vu">
            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-vu btn-primary-vu">
                <i class="fas fa-edit"></i> Edit User
            </a>
            <a href="users.php" class="btn-vu btn-outline-vu">
                <i class="fas fa-list"></i> All Users
            </a>
        </div>
        
    </div>

    <!-- FOOTER -->
    <footer class="footer-vu">
        <p>
            <span class="footer-brand-vu">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            View User - <?= htmlspecialchars($user['full_name']) ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c👤 Braick - View User', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user['full_name']) ?> (ID: <?= $user['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📧 Email: <?= htmlspecialchars($user['email']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🎭 Role: <?= strtoupper($user['role']) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Status: <?= strtoupper($user['status']) ?>', 'font-size:13px; color:<?= $user['status'] === 'active' ? '#059669' : '#DC2626' ?>;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>