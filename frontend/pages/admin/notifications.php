<?php
// ================================================================
// FILE: frontend/pages/admin/notifications.php
// SUPER ADMIN - NOTIFICATIONS (V2 - WITH DELETE ALL)
// ✅ BLUE THEME ONLY
// ✅ Dark mode inafanya kazi kikamilifu
// ✅ Inatumia shared header
// ✅ NEW: Delete All button (with filter options)
// ✅ NEW: Delete All Read button
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$alert_message = '';
$alert_type = '';

// ================================================================
// MARK ALL AS READ
// ================================================================
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {}
}

// ================================================================
// MARK SINGLE AS READ
// ================================================================
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$_GET['read'], $user_id]);
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {}
}

// ================================================================
// DELETE SINGLE NOTIFICATION
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$_GET['delete'], $user_id]);
        header('Location: notifications.php');
        exit;
    } catch (Exception $e) {}
}

// ================================================================
// DELETE ALL NOTIFICATIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // DELETE ALL (with filter)
    if ($_POST['action'] === 'delete_all') {
        try {
            $del_filter = $_POST['del_filter'] ?? 'all';
            
            if ($del_filter === 'read') {
                $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
                $stmt->execute([$user_id]);
                $deleted = $stmt->rowCount();
                $alert_message = "Successfully deleted $deleted read notification(s)!";
            } elseif ($del_filter === 'unread') {
                $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$user_id]);
                $deleted = $stmt->rowCount();
                $alert_message = "Successfully deleted $deleted unread notification(s)!";
            } elseif ($del_filter === 'old') {
                // Delete older than 30 days
                $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute([$user_id]);
                $deleted = $stmt->rowCount();
                $alert_message = "Successfully deleted $deleted old notification(s) (30+ days)!";
            } else {
                // Delete ALL
                $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $deleted = $stmt->rowCount();
                $alert_message = "Successfully deleted ALL $deleted notification(s)!";
            }
            
            $alert_type = 'success';
            
            // Refresh page after delete
            header('Location: notifications.php?deleted=1&count=' . $deleted);
            exit;
        } catch (Exception $e) {
            $alert_message = "Error deleting notifications: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
}

// Handle delete success message from redirect
if (isset($_GET['deleted']) && isset($_GET['count'])) {
    $alert_message = "Successfully deleted " . (int)$_GET['count'] . " notification(s)!";
    $alert_type = 'success';
}

// ================================================================
// GET NOTIFICATIONS
// ================================================================
$notifications = [];
$filter = $_GET['filter'] ?? 'all';

try {
    $query = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$user_id];
    
    if ($filter === 'unread') {
        $query .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $query .= " AND is_read = 1";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notifications = [];
}

// STATS
$total_notifications = 0;
$unread_count = 0;
$read_count = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $read_count = $total_notifications - $unread_count;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================
   BLUE THEME ONLY — DARK MODE SUPPORT
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --danger-dark: #B91C1C;
    --success: #059669;
    --success-bg: #D1FAE5;
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
}

[data-theme="dark"] {
    --primary-bg: #1E3A5F;
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #334155;
    --danger-bg: #3A1A1A;
    --success-bg: #1A3A2A;
}

[data-theme="dark"] body {
    background: #0F172A !important;
    color: #F1F5F9 !important;
}

[data-theme="dark"] .main-content {
    background: #0F172A !important;
}

/* ALERT */
.alert {
    padding: 12px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    animation: slideDown 0.4s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert i { font-size: 1.1rem; }

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
}

.page-header-custom .page-title i { color: rgba(255,255,255,0.85); }

.page-header-custom .badge-new {
    background: rgba(255,255,255,0.25);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid rgba(255,255,255,0.2);
}

.page-header-custom .badge-branch {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.btn-header-action {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(4px);
}

.btn-header-action:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    color: white;
}

.btn-header-danger {
    background: linear-gradient(135deg, #991B1B, #7F1D1D) !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
}

.btn-header-danger:hover {
    background: linear-gradient(135deg, #DC2626, #991B1B) !important;
    box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5);
    transform: translateY(-2px) scale(1.02) !important;
}

/* STATS GRID */
.stats-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card-custom {
    border-radius: 14px;
    padding: 18px 20px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 100px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s;
    text-decoration: none;
}

.stat-card-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card-custom .stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-card-custom .stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-card-custom .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.stat-card-custom.blue-1 { background: #0B5ED7; }
.stat-card-custom.blue-2 { background: #0A4CA8; }
.stat-card-custom.blue-3 { background: #1A73E8; }

/* FILTER TABS */
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

/* NOTIFICATION CARD */
.notif-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 16px 20px;
    border: 2px solid var(--border-color);
    margin-bottom: 12px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: all 0.3s ease;
}

.notif-card:hover {
    transform: translateX(4px);
    border-color: var(--primary);
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.1);
}

.notif-card.unread {
    background: var(--primary-bg);
    border-left: 4px solid var(--primary);
}

.notif-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    background: var(--primary-bg);
    color: var(--primary);
    border: 2px solid var(--primary-light);
}

[data-theme="dark"] .notif-icon {
    background: #1E3A5F;
    color: #6EA8FE;
    border-color: #3B82F6;
}

.notif-content { flex: 1; min-width: 0; }

.notif-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.notif-message {
    font-size: 0.78rem;
    color: var(--text-secondary);
    margin: 0 0 8px 0;
    line-height: 1.5;
}

.notif-time {
    font-size: 0.7rem;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    background: var(--primary-bg);
    border-radius: 12px;
    font-weight: 500;
}

/* NOTIF ACTIONS */
.notif-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.notif-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: white;
}

.notif-btn.read { background: #0B5ED7; }
.notif-btn.read:hover {
    background: #0A4CA8;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
}

.notif-btn.delete { background: #DC2626; }
.notif-btn.delete:hover {
    background: #B91C1C;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px dashed var(--border-color);
}

.empty-state i {
    font-size: 4rem;
    color: var(--primary-light);
    display: block;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state .empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 6px 0;
}

.empty-state .empty-sub {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0;
}

/* FOOTER */
.footer {
    padding: 16px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* ================================================================
   DELETE ALL MODAL
   ================================================================ */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(6px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.active { display: flex; }

.modal-box {
    background: var(--bg-card);
    border-radius: 20px;
    max-width: 480px;
    width: 100%;
    padding: 30px;
    box-shadow: var(--shadow-xl);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.8) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-icon {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: var(--danger-bg);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
    animation: iconPulse 1.5s infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
}

.modal-title {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.modal-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 22px;
    line-height: 1.7;
}

.modal-warning {
    color: var(--danger);
    font-weight: 700;
    display: block;
    margin-top: 6px;
    font-size: 0.78rem;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modal-btn {
    padding: 10px 24px;
    border-radius: 11px;
    font-weight: 700;
    font-size: 0.82rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.modal-btn.cancel {
    background: var(--border-color);
    color: var(--text-primary);
}

.modal-btn.cancel:hover {
    background: #CBD5E1;
    transform: translateY(-2px);
}

.modal-btn.danger {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
}

.modal-btn.danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
}

/* Delete options */
.delete-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
    text-align: left;
}

.delete-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    cursor: pointer;
    transition: all 0.3s ease;
}

.delete-option:hover {
    border-color: var(--danger);
    background: var(--danger-bg);
    transform: translateX(3px);
}

.delete-option input[type="radio"] {
    width: 18px;
    height: 18px;
    accent-color: var(--danger);
    cursor: pointer;
}

.delete-option .option-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.delete-option .option-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
}

.delete-option .option-desc {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.delete-option.danger-option {
    border-color: rgba(220, 38, 38, 0.4);
}

.delete-option.danger-option:hover {
    background: var(--danger-bg);
    border-color: var(--danger);
}

.delete-option .option-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 6px;
    background: var(--danger-bg);
    color: var(--danger);
    font-family: 'JetBrains Mono', monospace;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .stats-grid-3 { grid-template-columns: 1fr; }
    .page-header-custom { padding: 18px 20px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .notif-card { padding: 12px 14px; gap: 10px; }
    .notif-icon { width: 38px; height: 38px; font-size: 0.95rem; }
    .notif-title { font-size: 0.82rem; }
    .notif-message { font-size: 0.72rem; }
    .notif-actions { flex-direction: column; }
    .header-actions { width: 100%; }
    .btn-header-action { width: 100%; justify-content: center; }
}

@media (max-width: 480px) {
    .notif-card { flex-wrap: wrap; }
    .notif-actions { width: 100%; flex-direction: row; justify-content: flex-end; margin-top: 8px; }
}
</style>

<main class="main-content">

    <!-- ALERT -->
    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-bell"></i>
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge-new">
                        <i class="fas fa-circle" style="font-size:0.4rem;vertical-align:middle;"></i>
                        <?= $unread_count ?> New
                    </span>
                <?php endif; ?>
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:6px;">
                <i class="fas fa-user"></i> Welcome back, <?= htmlspecialchars($user_full_name) ?>
            </p>
        </div>
        <div class="header-actions">
            <?php if ($unread_count > 0): ?>
                <a href="notifications.php?mark_all_read=1" class="btn-header-action" 
                   onclick="return confirm('Mark all notifications as read?');">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
            <?php endif; ?>
            
            <?php if ($total_notifications > 0): ?>
                <button type="button" class="btn-header-action btn-header-danger" onclick="openDeleteAllModal()">
                    <i class="fas fa-trash-alt"></i> Delete All
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid-3">
        <a href="notifications.php?filter=all" class="stat-card-custom blue-1">
            <div>
                <div class="stat-label">Total Notifications</div>
                <div class="stat-number"><?= number_format($total_notifications) ?></div>
                <div style="font-size:0.65rem;opacity:0.8;margin-top:2px;">
                    <i class="fas fa-list"></i> All notifications
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-bell"></i></div>
        </a>
        
        <a href="notifications.php?filter=unread" class="stat-card-custom blue-2">
            <div>
                <div class="stat-label">Unread</div>
                <div class="stat-number"><?= number_format($unread_count) ?></div>
                <div style="font-size:0.65rem;opacity:0.8;margin-top:2px;">
                    <i class="fas fa-envelope"></i> Needs attention
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-envelope"></i></div>
        </a>
        
        <a href="notifications.php?filter=read" class="stat-card-custom blue-3">
            <div>
                <div class="stat-label">Read</div>
                <div class="stat-number"><?= number_format($read_count) ?></div>
                <div style="font-size:0.65rem;opacity:0.8;margin-top:2px;">
                    <i class="fas fa-envelope-open"></i> Already seen
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-envelope-open"></i></div>
        </a>
    </div>

    <!-- FILTER TABS -->
    <div class="filter-tabs">
        <a href="notifications.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All (<?= $total_notifications ?>)
        </a>
        <a href="notifications.php?filter=unread" class="filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Unread (<?= $unread_count ?>)
        </a>
        <a href="notifications.php?filter=read" class="filter-btn <?= $filter === 'read' ? 'active' : '' ?>">
            <i class="fas fa-envelope-open"></i> Read (<?= $read_count ?>)
        </a>
    </div>

    <!-- NOTIFICATIONS LIST -->
    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notif): 
            $type = $notif['type'] ?? 'info';
            
            $icon = 'fa-info-circle';
            if ($type === 'success') $icon = 'fa-check-circle';
            elseif ($type === 'warning') $icon = 'fa-exclamation-triangle';
            elseif ($type === 'error') $icon = 'fa-times-circle';
            elseif ($type === 'prescription') $icon = 'fa-prescription';
            elseif ($type === 'payment') $icon = 'fa-money-bill-wave';
            elseif ($type === 'appointment') $icon = 'fa-calendar-check';
            elseif ($type === 'lab_test') $icon = 'fa-flask';
        ?>
            <div class="notif-card <?= ($notif['is_read'] ?? 0) == 0 ? 'unread' : '' ?>">
                <div class="notif-icon">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div class="notif-content">
                    <p class="notif-title"><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></p>
                    <p class="notif-message"><?= htmlspecialchars($notif['message'] ?? '') ?></p>
                    <span class="notif-time">
                        <i class="fas fa-clock"></i>
                        <?= date('M d, Y • h:i A', strtotime($notif['created_at'] ?? 'now')) ?>
                    </span>
                </div>
                <div class="notif-actions">
                    <?php if (($notif['is_read'] ?? 0) == 0): ?>
                        <a href="notifications.php?read=<?= $notif['id'] ?>" class="notif-btn read" title="Mark as Read">
                            <i class="fas fa-check"></i>
                        </a>
                    <?php endif; ?>
                    <a href="notifications.php?delete=<?= $notif['id'] ?>" 
                       class="notif-btn delete" title="Delete"
                       onclick="return confirm('⚠️ Delete this notification?\n\nThis action cannot be undone!');">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p class="empty-title">
                <?php if ($filter === 'unread'): ?>
                    No unread notifications
                <?php elseif ($filter === 'read'): ?>
                    No read notifications
                <?php else: ?>
                    No notifications yet
                <?php endif; ?>
            </p>
            <p class="empty-sub">
                <?php if ($filter === 'unread'): ?>
                    You're all caught up! 🎉
                <?php elseif ($filter === 'read'): ?>
                    No notifications have been marked as read yet
                <?php else: ?>
                    When you receive notifications, they will appear here
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Notifications
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- DELETE ALL MODAL -->
<div class="modal-overlay" id="deleteAllModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 class="modal-title">Delete Notifications?</h3>
        <p class="modal-text">
            Choose what you want to delete.<br>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> This action CANNOT be undone!
            </span>
        </p>
        
        <form method="POST" id="deleteAllForm">
            <input type="hidden" name="action" value="delete_all">
            
            <div class="delete-options">
                <!-- Delete Read only -->
                <label class="delete-option">
                    <input type="radio" name="del_filter" value="read" checked>
                    <div class="option-info">
                        <span class="option-title"><i class="fas fa-envelope-open" style="color:#0B5ED7;"></i> Delete Read Notifications</span>
                        <span class="option-desc">Delete only notifications that you've already read</span>
                    </div>
                    <span class="option-badge"><?= number_format($read_count) ?></span>
                </label>
                
                <!-- Delete Unread -->
                <label class="delete-option">
                    <input type="radio" name="del_filter" value="unread">
                    <div class="option-info">
                        <span class="option-title"><i class="fas fa-envelope" style="color:#0B5ED7;"></i> Delete Unread Notifications</span>
                        <span class="option-desc">Delete only notifications you haven't read yet</span>
                    </div>
                    <span class="option-badge"><?= number_format($unread_count) ?></span>
                </label>
                
                <!-- Delete Old (30+ days) -->
                <label class="delete-option">
                    <input type="radio" name="del_filter" value="old">
                    <div class="option-info">
                        <span class="option-title"><i class="fas fa-clock" style="color:#D97706;"></i> Delete Old (30+ days)</span>
                        <span class="option-desc">Delete notifications older than 30 days</span>
                    </div>
                    <span class="option-badge" style="background:#FEF3C7;color:#D97706;">30d+</span>
                </label>
                
                <!-- Delete ALL -->
                <label class="delete-option danger-option">
                    <input type="radio" name="del_filter" value="all">
                    <div class="option-info">
                        <span class="option-title" style="color:#DC2626;"><i class="fas fa-exclamation-triangle"></i> Delete ALL Notifications</span>
                        <span class="option-desc" style="color:#DC2626;">Delete every single notification (⚠️ PERMANENT)</span>
                    </div>
                    <span class="option-badge"><?= number_format($total_notifications) ?></span>
                </label>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteAllModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash-alt"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// DELETE ALL MODAL
// ================================================================
function openDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on outside click
document.getElementById('deleteAllModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteAllModal();
});

// Close modal on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteAllModal();
});

// ================================================================
// FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// ================================================================
// AUTO-REFRESH EVERY 30 SECONDS (paused if modal open)
// ================================================================
setInterval(function() {
    var modal = document.getElementById('deleteAllModal');
    if (!modal || !modal.classList.contains('active')) {
        window.location.reload();
    }
}, 30000);

console.log('%c🔔 Admin - Notifications V2 (With Delete All)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Dark mode inafanya kazi kikamilifu', 'font-size:12px;color:#34D399;');
console.log('%c✅ Blue theme everywhere', 'font-size:12px;color:#34D399;');
console.log('%c🗑️ Delete All with filter options', 'font-size:12px;color:#DC2626;font-weight:bold;');
console.log('%c📊 Total: <?= $total_notifications ?> | Unread: <?= $unread_count ?> | Read: <?= $read_count ?>', 'font-size:12px;color:#64748B;');
</script>

</body>
</html>