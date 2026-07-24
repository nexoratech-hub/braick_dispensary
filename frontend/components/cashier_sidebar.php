<?php
// ================================================================
// FILE: frontend/components/cashier_sidebar.php
// CASHIER - SHARED SIDEBAR (GREEN THEME)
// WITH REAL-TIME STATS AUTO-UPDATE (3 SECONDS) - SELF-CONTAINED
// REMOVED: Patients, Invoice History, Receive Payment, Reports
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$paid_today = 0;
$total_paid = 0;
$patients_waiting = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // Pending Bills
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Partial Payments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'partial'");
        $stmt->execute([$user_branch_id]);
        $partial_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Paid Today
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM patient_bills 
            WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $paid_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Total Paid Bills (All time)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM patient_bills 
            WHERE branch_id = ? AND status = 'paid'
        ");
        $stmt->execute([$user_branch_id]);
        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Patients Waiting for Payment (pending + partial)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT patient_id) as count 
            FROM patient_bills 
            WHERE branch_id = ? AND status IN ('pending', 'partial')
        ");
        $stmt->execute([$user_branch_id]);
        $patients_waiting = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    } catch (Exception $e) {
        // Keep counts as 0
        error_log("Cashier sidebar stats error: " . $e->getMessage());
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

// ================================================================
// HANDLE AJAX REQUEST FOR SIDEBAR DATA (SELF-CONTAINED)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_cashier_sidebar_data') {
    header('Content-Type: application/json');
    
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    
    $response = [
        'success' => false,
        'pending_bills' => 0,
        'partial_payments' => 0,
        'paid_today' => 0,
        'total_paid' => 0,
        'patients_waiting' => 0,
        'hash' => ''
    ];
    
    if (isset($db) && $db !== null) {
        try {
            // Pending Bills
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'pending'");
            $stmt->execute([$branch_id]);
            $response['pending_bills'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Partial Payments
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'partial'");
            $stmt->execute([$branch_id]);
            $response['partial_payments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Paid Today
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM patient_bills 
                WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
            ");
            $stmt->execute([$branch_id]);
            $response['paid_today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Total Paid Bills (All time)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM patient_bills 
                WHERE branch_id = ? AND status = 'paid'
            ");
            $stmt->execute([$branch_id]);
            $response['total_paid'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Patients Waiting for Payment
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT patient_id) as count 
                FROM patient_bills 
                WHERE branch_id = ? AND status IN ('pending', 'partial')
            ");
            $stmt->execute([$branch_id]);
            $response['patients_waiting'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $response['success'] = true;
            
            // Create hash to detect changes
            $response['hash'] = md5(
                $response['pending_bills'] . 
                $response['partial_payments'] . 
                $response['paid_today'] . 
                $response['total_paid'] . 
                $response['patients_waiting']
            );
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
        }
    }
    
    echo json_encode($response);
    exit;
}
?>
<style>
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
        background: #064E3A;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #064E3A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EE7B7; border-radius: 10px; }
    
    .sidebar-brand {
        padding: 22px 20px 16px;
        border-bottom: 2px solid #064E3A;
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
        color: #A7F3D0; 
        font-size: 0.7rem; 
    }
    
    .sidebar-nav { 
        padding: 14px 10px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.55rem; 
        text-transform: uppercase;
        letter-spacing: 0.1em; 
        color: #A7F3D0;
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
        color: #D1FAE5; 
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
        min-width: 20px;
        text-align: center;
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
    
    .sidebar-link .badge.orange {
        background: #D97706;
    }
    
    .sidebar-link .badge.blue {
        background: #0B5ED7;
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
        border-top: 2px solid #064E3A;
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.1);
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
        color: #D1FAE5;
    }
    
    .sidebar-status .status-time {
        font-size: 0.6rem;
        color: #A7F3D0;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .sidebar-status .status-time .live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34D399;
        display: inline-block;
        animation: pulse-dot 1.5s infinite;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    
    .mt-2 { margin-top: 8px; }
    
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
<!-- SIDEBAR - CASHIER (GREEN THEME) -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
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
        
        <div class="nav-label">Cashier</div>
        
        <!-- 1. Dashboard -->
        <a href="../cashier/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- 2. Billing -->
        <div class="nav-label mt-2">Billing</div>
        
        <!-- Pending Bills -->
        <a href="../cashier/pending_bills.php" class="sidebar-link <?= isActive('pending_bills.php') ?>">
            <i class="fas fa-clock"></i> Pending Bills
            <?php if ($pending_bills > 0): ?>
                <span class="badge orange" id="sidebarPendingBadge"><?= $pending_bills ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPendingBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Paid Bills -->
        <a href="../cashier/paid_bills.php" class="sidebar-link <?= isActive('paid_bills.php') ?>">
            <i class="fas fa-check-circle"></i> Paid Bills
            <span class="badge green" id="sidebarPaidBadge"><?= $total_paid ?></span>
        </a>
        
        <!-- Partial Payments -->
        <a href="../cashier/partial_payments.php" class="sidebar-link <?= isActive('partial_payments.php') ?>">
            <i class="fas fa-hand-holding-usd"></i> Partial Payments
            <?php if ($partial_payments > 0): ?>
                <span class="badge blue" id="sidebarPartialBadge"><?= $partial_payments ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPartialBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Cancelled Bills -->
        <a href="../cashier/cancelled_bills.php" class="sidebar-link <?= isActive('cancelled_bills.php') ?>">
            <i class="fas fa-times-circle"></i> Cancelled Bills
        </a>
        
        <!-- 3. Payments -->
        <div class="nav-label mt-2">Payments</div>
        
        <!-- Payment History -->
        <a href="../cashier/payment_history.php" class="sidebar-link <?= isActive('payment_history.php') ?>">
            <i class="fas fa-history"></i> Payment History
        </a>
        
        <!-- 4. Receipts -->
        <div class="nav-label mt-2">Receipts</div>
        
        <!-- Receipt History -->
        <a href="../cashier/receipt_history.php" class="sidebar-link <?= isActive('receipt_history.php') ?>">
            <i class="fas fa-receipt"></i> Receipt History
        </a>
        
        <!-- 5. Account -->
        <div class="nav-label mt-2">Account</div>
        
        <a href="../cashier/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="../../../logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- Online Status with Live Update Indicator -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
        <span class="status-time" id="sidebarStatusTime">
            <span class="live-dot"></span>
            <span id="sidebarLiveTime"><?= date('H:i:s') ?></span>
        </span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- SIDEBAR AUTO-UPDATE SCRIPT (SELF-CONTAINED) -->
<!-- ================================================================ -->
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
    // UPDATE SIDEBAR BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        // Pending Bills
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge) {
            var pending = data.pending_bills || 0;
            pendingBadge.textContent = pending;
            pendingBadge.className = pending > 0 ? 'badge orange' : 'badge';
        }
        
        // Total Paid Bills (All time)
        var paidBadge = document.getElementById('sidebarPaidBadge');
        if (paidBadge) {
            var totalPaid = data.total_paid || 0;
            paidBadge.textContent = totalPaid;
            paidBadge.className = totalPaid > 0 ? 'badge green' : 'badge';
        }
        
        // Partial Payments
        var partialBadge = document.getElementById('sidebarPartialBadge');
        if (partialBadge) {
            var partial = data.partial_payments || 0;
            partialBadge.textContent = partial;
            partialBadge.className = partial > 0 ? 'badge blue' : 'badge';
        }
        
        // Paid Today (if exists)
        var paidTodayBadge = document.getElementById('sidebarPaidTodayBadge');
        if (paidTodayBadge) {
            var paidToday = data.paid_today || 0;
            paidTodayBadge.textContent = paidToday;
            paidTodayBadge.className = paidToday > 0 ? 'badge green' : 'badge';
        }
        
        // Patients Waiting (if exists)
        var patientsBadge = document.getElementById('sidebarPatientsWaitingBadge');
        if (patientsBadge) {
            var waiting = data.patients_waiting || 0;
            patientsBadge.textContent = waiting;
            patientsBadge.className = waiting > 0 ? 'badge danger' : 'badge';
        }
        
        // Update time
        var timeEl = document.getElementById('sidebarLiveTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            });
            timeEl.textContent = timeStr;
        }
    }

    // ================================================================
    // FETCH SIDEBAR DATA (Self-contained - uses same file)
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;
    var branchId = <?= json_encode($_SESSION['branch_id'] ?? 1) ?>;
    var lastDataHash = null;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_cashier_sidebar_data');
        formData.append('branch_id', branchId);
        
        // Send request to the SAME FILE (self-contained)
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Only update if data has changed
                if (lastDataHash !== data.hash) {
                    lastDataHash = data.hash;
                    updateSidebarBadges(data);
                }
            }
            sidebarIsUpdating = false;
        })
        .catch(function(error) {
            // Silent fail - don't spam console
            // console.warn('Sidebar update error:', error.message);
            sidebarIsUpdating = false;
        });
    }

    // ================================================================
    // START / STOP AUTO-UPDATE
    // ================================================================
    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // Initial update
        fetchSidebarData();
        // Then every 3 seconds
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
        console.log('%c🔄 Cashier Sidebar auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
            console.log('%c⏹️ Cashier Sidebar auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    // ================================================================
    // VISIBILITY CHANGE
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    // ================================================================
    // EXPOSE FUNCTIONS FOR OTHER SCRIPTS
    // ================================================================
    window.updateSidebarBadges = updateSidebarBadges;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 1000);
    });

    console.log('%c💰 Cashier Sidebar (SELF-CONTAINED - Auto-update every 3s)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c📋 Pending: <?= $pending_bills ?> | Partial: <?= $partial_payments ?> | Total Paid: <?= $total_paid ?>', 'font-size:12px; color:#A7F3D0;');
    console.log('%c🔄 Data fetched from the SAME file via AJAX POST', 'font-size:12px; color:#34D399;');
    console.log('%c✅ NO EXTERNAL API NEEDED - Self-contained', 'font-size:12px; color:#059669;');
    console.log('%c✅ Removed: Patients, Invoice History, Receive Payment, Reports', 'font-size:12px; color:#059669;');
</script>