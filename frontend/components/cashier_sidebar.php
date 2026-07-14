<?php
// ================================================================
// FILE: frontend/components/cashier_sidebar.php
// CASHIER SIDEBAR - GREEN COLOR
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
// ================================================================
$pending_bills = 0;
$pending_payments = 0;
$today_payments = 0;
$today_revenue = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // 1. Pending Bills (unpaid)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status IN ('pending','partial')");
        $stmt->execute([$user_branch_id]);
        $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Pending Payments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 3. Today's Payments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE branch_id = ? AND DATE(payment_date) = CURDATE() AND status = 'completed'");
        $stmt->execute([$user_branch_id]);
        $today_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 4. Today's Revenue
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments WHERE branch_id = ? AND DATE(payment_date) = CURDATE() AND status = 'completed'");
        $stmt->execute([$user_branch_id]);
        $today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
        
    } catch (Exception $e) {
        // Keep counts as 0
    }
}

// ================================================================
// DETECT CURRENT PAGE
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE
// ================================================================
function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<style>
    /* ================================================================
       SIDEBAR STYLES - GREEN BACKGROUND
       ================================================================ */
    .sidebar {
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0;
        width: 270px; 
        background: #065F46;
        color: white;
        z-index: 50; 
        overflow-y: auto;
        transition: transform 0.3s ease;
    }
    
    [data-theme="dark"] .sidebar {
        background: #064E3B;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #064E3B; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EE7B7; border-radius: 10px; }
    
    .sidebar-brand {
        padding: 22px 20px 16px;
        border-bottom: 2px solid #064E3B;
    }
    
    .sidebar-brand .logo {
        width: 48px; 
        height: 48px; 
        border-radius: 12px;
        object-fit: cover; 
        background: white; 
        padding: 4px;
    }
    
    .sidebar-brand .brand-text { 
        color: white; 
        font-weight: 700; 
        font-size: 1rem; 
    }
    
    .sidebar-brand .brand-sub { 
        color: #6EE7B7; 
        font-size: 0.7rem; 
    }
    
    .sidebar-nav { 
        padding: 14px 10px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.55rem; 
        text-transform: uppercase;
        letter-spacing: 0.1em; 
        color: #6EE7B7;
        padding: 0 12px; 
        margin: 12px 0 6px; 
        font-weight: 700;
    }
    
    .sidebar-link {
        display: flex; 
        align-items: center; 
        gap: 12px;
        padding: 9px 14px; 
        border-radius: 10px;
        color: #A7F3D0; 
        text-decoration: none;
        transition: all 0.3s ease; 
        font-size: 0.85rem; 
        font-weight: 500;
        margin: 2px 0;
        background: transparent;
        cursor: pointer;
    }
    
    .sidebar-link:hover {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
    }
    
    .sidebar-link i { 
        width: 20px; 
        text-align: center; 
        font-size: 1rem; 
    }
    
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        padding: 1px 9px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-link .badge.green {
        background: #059669;
    }
    
    .sidebar-link .badge.yellow {
        background: #D97706;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.1);
        padding-top: 12px;
        margin-top: 8px;
        color: #FCA5A5;
    }
    
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    .sidebar-status {
        padding: 12px 20px;
        border-top: 2px solid #064E3B;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-status .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .sidebar-status .status-dot.online {
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
    }
    
    .sidebar-status .status-text {
        font-size: 0.75rem;
        color: #A7F3D0;
    }
    
    .sidebar-status .status-time {
        font-size: 0.6rem;
        color: #6EE7B7;
        margin-left: auto;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    
    @media (max-width: 1024px) {
        .sidebar { 
            transform: translateX(-100%); 
        }
        .sidebar.open { 
            transform: translateX(0); 
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR - CASHIER (GREEN) -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%23065F46%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Cashier Panel</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ===== CASHIER MENU ===== -->
        <div class="nav-label">Cashier</div>
        
        <!-- 1. Dashboard -->
        <a href="../cashier/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- 2. New Payment -->
        <a href="../cashier/payments.php" class="sidebar-link <?= isActive('payments.php') ?>">
            <i class="fas fa-plus-circle"></i> New Payment
            <?php if ($pending_bills > 0): ?>
                <span class="badge danger"><?= $pending_bills ?></span>
            <?php endif; ?>
        </a>
        
        <!-- 3. Payment History -->
        <a href="../cashier/payment_history.php" class="sidebar-link <?= isActive('payment_history.php') ?>">
            <i class="fas fa-history"></i> Payment History
            <span class="badge"><?= $today_payments ?></span>
        </a>
        
        <!-- 4. Receipt - DIRECT LINK TO receipt.php -->
        <a href="../cashier/receipt.php" class="sidebar-link <?= isActive('receipt.php') ?>">
            <i class="fas fa-receipt"></i> Receipt
        </a>
        
        <!-- 5. Pending Payments -->
        <a href="../cashier/pending_payments.php" class="sidebar-link <?= isActive('pending_payments.php') ?>">
            <i class="fas fa-clock"></i> Pending Payments
            <?php if ($pending_payments > 0): ?>
                <span class="badge yellow"><?= $pending_payments ?></span>
            <?php else: ?>
                <span class="badge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ===== STATISTICS ===== -->
        <div class="nav-label mt-2">Statistics</div>
        
        <!-- 6. Today's Revenue -->
        <a href="../cashier/daily_summary.php" class="sidebar-link">
            <i class="fas fa-money-bill-wave"></i> Today's Revenue
            <span class="badge green">TSh <?= number_format($today_revenue) ?></span>
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- Profile -->
        <a href="../cashier/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- Logout -->
        <a href="../../../logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- Online Status -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
        <span class="status-time" id="sidebarStatusTime"><?= date('H:i:s') ?></span>
    </div>
</aside>

<script>
    // ================================================================
    // SIDEBAR TOGGLE (Mobile)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (sidebar && sidebarToggle) {
                    if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                        sidebar.classList.remove('open');
                    }
                }
            }
        });
    });

    // ================================================================
    // UPDATE SIDEBAR BADGES (AJAX every 3 seconds)
    // ================================================================
    function updateSidebarBadges(pendingBills, pendingPayments, todayPayments, todayRevenue) {
        var links = document.querySelectorAll('.sidebar-link');
        links.forEach(function(link) {
            var text = link.textContent.trim();
            if (text.includes('New Payment')) {
                var badge = link.querySelector('.badge');
                if (badge) {
                    if (pendingBills > 0) {
                        badge.textContent = pendingBills;
                        badge.className = 'badge danger';
                    } else {
                        badge.textContent = '0';
                        badge.className = 'badge';
                    }
                }
            }
            if (text.includes('Today\'s Revenue')) {
                var badge = link.querySelector('.badge');
                if (badge) {
                    badge.textContent = 'TSh ' + todayRevenue.toLocaleString();
                }
            }
            if (text.includes('Pending Payments')) {
                var badge = link.querySelector('.badge');
                if (badge) {
                    if (pendingPayments > 0) {
                        badge.textContent = pendingPayments;
                        badge.className = 'badge yellow';
                    } else {
                        badge.textContent = '0';
                        badge.className = 'badge';
                    }
                }
            }
            if (text.includes('Payment History')) {
                var badge = link.querySelector('.badge');
                if (badge) {
                    badge.textContent = todayPayments;
                }
            }
        });
        
        var timeEl = document.getElementById('sidebarStatusTime');
        if (timeEl) {
            var now = new Date();
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }

    // ================================================================
    // AJAX AUTO-UPDATE
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        fetch('../cashier/get_sidebar_stats.php')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateSidebarBadges(
                        data.pending_bills,
                        data.pending_payments,
                        data.today_payments,
                        data.today_revenue
                    );
                }
                sidebarIsUpdating = false;
            })
            .catch(function(error) {
                console.error('Sidebar update error:', error);
                sidebarIsUpdating = false;
            });
    }

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
        fetchSidebarData();
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    console.log('%c💰 Cashier Sidebar (GREEN - FIXED)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c📋 Receipt: Direct link to receipt.php', 'font-size:12px; color:#6EE7B7;');
    console.log('%c💳 Pending Bills: <?= $pending_bills ?>', 'font-size:12px; color:#FBBF24;');
    console.log('%c📊 Today\'s Revenue: TSh <?= number_format($today_revenue) ?>', 'font-size:12px; color:#34D399;');
</script>