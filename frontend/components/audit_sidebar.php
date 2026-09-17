<?php
// ================================================================
// FILE: frontend/components/audit_sidebar.php
// AUDIT - SIDEBAR (COMPACT + GREEN HOVER + JETBRAINS MONO)
// ✅ Audit role only
// ✅ View-only links
// ✅ Size: 260px
// ✅ Hover = GREEN (#10B981)
// ✅ Font: JetBrains Mono
// ✅ BLUE THEME: #0B5ED7
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

$selected_branch_id = $_GET['branch'] ?? 'all';

if (!isset($db) || $db === null) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/backend/config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Audit sidebar DB error: " . $e->getMessage());
        $db = null;
    }
}

$branch_cond = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_cond = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

$total_revenue_today = 0;
$total_patients = 0;
$total_employees = 0;
$total_inventory_items = 0;
$low_stock_count = 0;
$total_audit_logs = 0;
$today_audit_logs = 0;

if ($db !== null) {
    try {
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE status = 'paid' AND DATE(updated_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_revenue_today = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM patients WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role NOT IN ('admin', 'audit') AND status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_inventory_items = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active' AND quantity <= 10" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $low_stock_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_audit_logs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $today_audit_logs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
}

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $page === $current_page ? 'active' : '';
}

function isAuditPage($pages) {
    global $current_page;
    return in_array($current_page, $pages) ? 'active' : '';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
.sidebar,
.sidebar *:not(i):not(.fas):not(.far):not(.fab):not(.fa-solid):not(.fa-regular):not(.fa-brands),
.sidebar *::before:not(i),
.sidebar *::after:not(i) {
    font-family: 'JetBrains Mono', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', monospace !important;
    -webkit-font-smoothing: antialiased;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
}

.sidebar i,
.sidebar i::before,
.sidebar .fas,
.sidebar .fas::before {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
    display: inline-block;
    font-style: normal;
    line-height: 1;
}

.sidebar {
    position: fixed; 
    top: 0; left: 0; bottom: 0;
    width: 260px;
    background: linear-gradient(180deg, #0B5ED7 0%, #0A4CA8 100%);
    color: white;
    z-index: 50; 
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-100%);
    box-shadow: 4px 0 20px rgba(11, 94, 215, 0.25);
}

[data-theme="dark"] .sidebar {
    background: linear-gradient(180deg, #0A4CA8 0%, #083A7F 100%);
    box-shadow: 4px 0 30px rgba(0,0,0,0.5);
}

.sidebar.open { transform: translateX(0) !important; }

.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { background: #60A5FA; border-radius: 10px; }

.sidebar-brand {
    padding: 14px 14px 12px;
    border-bottom: 1.5px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 5;
    backdrop-filter: blur(10px);
}

.sidebar-brand .logo {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover; 
    background: white; 
    padding: 3px;
    border: 1.5px solid rgba(255,255,255,0.25);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.sidebar-brand .brand-text { 
    color: white; 
    font-weight: 800;
    font-size: 0.88rem;
    line-height: 1.2; 
    letter-spacing: 0.02em;
}

.sidebar-brand .brand-sub { 
    color: #BFDBFE; 
    font-size: 0.65rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}

.audit-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: white;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.5);
}

.audit-role-badge.view-only-role {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5);
}

.sidebar-branch-selector {
    padding: 10px 14px;
    border-bottom: 1.5px solid rgba(255,255,255,0.08);
    background: rgba(0,0,0,0.05);
}

.sidebar-branch-selector select {
    width: 100%; 
    padding: 7px 10px;
    border-radius: 8px; 
    border: none;
    background: rgba(255,255,255,0.15);
    color: white; 
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer; 
    outline: none;
    appearance: none; 
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}

.sidebar-branch-selector select option { 
    background: #0B5ED7; 
    color: white; 
    padding: 8px; 
}

.sidebar-nav { padding: 8px 8px 20px; }

.sidebar-nav .nav-label {
    font-size: 0.55rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #BFDBFE;
    padding: 8px 10px 4px;
    margin: 8px 0 3px;
    font-weight: 800;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 5px;
}

.sidebar-nav .nav-label:first-of-type { margin-top: 0; }

.sidebar-link {
    display: flex; 
    align-items: center; 
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    color: #DBEAFE; 
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.8rem;
    font-weight: 600;
    margin: 1px 0; 
    position: relative;
    letter-spacing: 0.01em;
}

.sidebar-link:hover {
    background: linear-gradient(90deg, rgba(5, 150, 105, 0.35), rgba(16, 185, 129, 0.2));
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    transform: translateX(4px);
    border-left: 3px solid #10B981;
}

.sidebar-link.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    box-shadow: 0 4px 12px rgba(96, 165, 250, 0.4);
}

.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: 0; top: 15%; bottom: 15%;
    width: 3px; 
    background: #BFDBFE;
    border-radius: 0 3px 3px 0;
}

.sidebar-link i { 
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0; 
    opacity: 0.95;
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
}

.sidebar-link .fa-home { color: #93C5FD; }
.sidebar-link .fa-chart-line { color: #34D399; }
.sidebar-link .fa-pills { color: #C4B5FD; }
.sidebar-link .fa-user-injured { color: #FBBF24; }
.sidebar-link .fa-users { color: #93C5FD; }
.sidebar-link .fa-clipboard-list { color: #F9A8D4; }
.sidebar-link .fa-user-circle { color: #7DD3FC; }
.sidebar-link .fa-sign-out-alt { color: #FCA5A5; }

.sidebar-link:hover i {
    color: #34D399 !important;
    filter: drop-shadow(0 2px 6px rgba(52, 211, 153, 0.6));
}

.sidebar-link.active i {
    color: white !important;
}

.sidebar-link .link-text {
    flex: 1; 
    white-space: nowrap;
    overflow: hidden; 
    text-overflow: ellipsis;
}

.sidebar-link .badge {
    margin-left: auto;
    background: #3B82F6 !important;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 0.62rem;
    font-weight: 800;
    color: #FFFFFF !important;
    min-width: 24px;
    text-align: center;
    border: 1.5px solid rgba(255,255,255,0.35);
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.5);
    line-height: 1.4;
}

.sidebar-link .badge.badge-new {
    background: #10B981 !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.7);
    animation: pulse-new 2s infinite;
    font-size: 0.55rem;
    padding: 2px 8px;
}

.sidebar-link .badge.badge-warning {
    background: #F59E0B !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.6);
}

.sidebar-link .badge.badge-revenue {
    background: linear-gradient(135deg, #10B981, #059669) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
}

@keyframes pulse-new {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}

.sidebar-link.logout-link {
    border-top: 1.5px solid rgba(255,255,255,0.1);
    padding-top: 10px;
    margin-top: 6px;
    color: #FCA5A5;
    font-weight: 700;
}

.sidebar-link.logout-link:hover {
    background: linear-gradient(90deg, rgba(220, 38, 38, 0.5), rgba(220, 38, 38, 0.3));
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.5);
    transform: translateX(4px);
    border-left: 3px solid #DC2626;
}

.sidebar-link.logout-link i { color: #FCA5A5; }
.sidebar-link.logout-link:hover i { color: white !important; }

.sidebar-status {
    padding: 9px 14px;
    border-top: 1.5px solid rgba(255,255,255,0.1);
    display: flex; 
    align-items: center; 
    gap: 10px;
    background: rgba(0,0,0,0.15);
    position: sticky; 
    bottom: 0;
    backdrop-filter: blur(10px);
}

.sidebar-status .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block; 
}

.sidebar-status .status-dot.online {
    background: #34D399;
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.6);
    animation: pulse-dot 1.5s infinite;
}

.sidebar-status .status-dot.offline { background: #94A3B8; }

.sidebar-status .status-text {
    font-size: 0.68rem;
    color: #DBEAFE; 
    font-weight: 700;
}

.sidebar-status .update-time {
    font-size: 0.55rem;
    color: #BFDBFE;
    margin-left: auto; 
    display: flex; 
    align-items: center; 
    gap: 5px;
}

.sidebar-live-indicator {
    display: inline-flex; 
    align-items: center; 
    gap: 4px;
    font-size: 0.55rem; 
    color: #93C5FD;
    font-weight: 700;
    text-transform: uppercase;
}

.sidebar-live-indicator .dot {
    width: 6px; 
    height: 6px;
    border-radius: 50%;
    background: #93C5FD; 
    animation: pulse-dot 1.5s infinite;
    display: inline-block;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.8); }
}

#sidebarOverlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 45; 
    display: none;
    backdrop-filter: blur(4px);
}

#sidebarOverlay.active { display: block !important; }

#sidebarToggle {
    display: none;
    position: fixed;
    top: 16px;
    left: 16px;
    z-index: 9999;
    width: 44px;
    height: 44px;
    border-radius: 11px;
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.5);
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

@media (min-width: 1025px) {
    .sidebar { transform: translateX(0) !important; z-index: 50; }
    #sidebarOverlay { display: none !important; }
    #sidebarToggle { display: none; }
}

@media (max-width: 1024px) {
    .sidebar { width: 270px; z-index: 99999 !important; }
    #sidebarOverlay { z-index: 99998 !important; }
    #sidebarToggle { display: flex; }
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-3 { gap: 10px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div id="sidebarOverlay"></div>

<button id="sidebarToggle" aria-label="Toggle Sidebar">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Audit Sidebar">
    
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B5ED7%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">
                    <i class="fas fa-search"></i>
                    Audit & Reports
                </p>
            </div>
        </div>
        <div style="margin-top:6px;padding:0 2px;display:flex;gap:5px;flex-wrap:wrap;">
            <span class="audit-role-badge">
                <i class="fas fa-shield-alt"></i> AUDIT
            </span>
            <span class="audit-role-badge view-only-role">
                <i class="fas fa-eye"></i> VIEW ONLY
            </span>
        </div>
    </div>
    
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php
            try {
                if ($db !== null) {
                    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $sel = ($selected_branch_id == $row['id']) ? 'selected' : '';
                        echo '<option value="' . $row['id'] . '" ' . $sel . '>🏥 ' . htmlspecialchars($row['name']) . '</option>';
                    }
                }
            } catch (Exception $e) {}
            ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        
        <div class="nav-label">
            <span class="label-icon">📋</span> Main Menu
        </div>
        
        <a href="/dispensary_system/frontend/pages/audit/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i>
            <span class="link-text">Dashboard</span>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">📊</span> Reports
        </div>
        
        <a href="/dispensary_system/frontend/pages/audit/revenue.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('revenue.php') || isAuditPage(['revenue_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span class="link-text">Revenue</span>
            <span class="badge badge-revenue" id="badgeRevenue">TSh <?= number_format($total_revenue_today, 0) ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/audit/inventory.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('inventory.php') || isAuditPage(['inventory_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-pills"></i>
            <span class="link-text">Inventory</span>
            <span class="badge" id="badgeInventory"><?= $total_inventory_items ?></span>
            <?php if ($low_stock_count > 0): ?>
                <span class="badge badge-warning" id="badgeLowStock"><?= $low_stock_count ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/audit/patients.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('patients.php') || isAuditPage(['patient_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i>
            <span class="link-text">Patients</span>
            <span class="badge" id="badgePatients"><?= $total_patients ?></span>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">👥</span> Performance
        </div>
        
        <a href="/dispensary_system/frontend/pages/audit/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') || isAuditPage(['employee_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span class="link-text">Employees</span>
            <span class="badge" id="badgeEmployees"><?= $total_employees ?></span>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">🔍</span> Audit
        </div>
        
        <a href="/dispensary_system/frontend/pages/audit/audit_logs.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('audit_logs.php') || isAuditPage(['audit_log_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span class="link-text">Audit Logs</span>
            <span class="badge" id="badgeAuditLogs"><?= $total_audit_logs ?></span>
            <?php if ($today_audit_logs > 0): ?>
                <span class="badge badge-new" id="badgeTodayAudit">+<?= $today_audit_logs ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label">
            <span class="label-icon">👤</span> Account
        </div>
        
        <a href="/dispensary_system/frontend/pages/audit/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
    <div class="sidebar-status">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>"></span>
        <span class="status-text"><?= $user_is_online ? 'Online' : 'Offline' ?></span>
        <span class="update-time">
            <span class="sidebar-live-indicator">
                <span class="dot"></span> Live
            </span>
        </span>
    </div>
</aside>

<script>
function switchBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
    if (url.searchParams.has('id')) {
        url.searchParams.delete('id');
    }
    window.location.href = url.toString();
}

(function() {
    function initSidebar() {
        var sidebar = document.getElementById('sidebar');
        var toggleBtn = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            document.body.appendChild(overlay);
        }
        
        if (!sidebar) return;
        
        function openSidebar() {
            sidebar.classList.add('open');
            overlay.style.display = 'block';
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            var icon = toggleBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-times';
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            var icon = toggleBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-bars';
        }
        
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('open')) closeSidebar();
            else openSidebar();
        });
        
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeSidebar();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
        });
        
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('open')) {
                    setTimeout(closeSidebar, 100);
                }
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();

console.log('%c🔍 Audit Sidebar (VIEW ONLY)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Role: AUDIT', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>