<?php
// ================================================================
// FILE: frontend/components/cashier_sidebar.php
// CASHIER SIDEBAR V11 FINAL
// ================================================================
// ✅ V11: Daily Activities menu (below Expenses)
// ✅ V11: Online/Offline status from DB (login/logout aware)
// ✅ V11: Heartbeat update last_online kila page load
// ✅ V11: AJAX handler ina-update last_online kila request
// ✅ V9: Toggle button (hamburger) kwa MOBILE ONLY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $db = null;
}

// ================================================================
// ✅ HEARTBEAT: Update user online status kila page load
// ================================================================
if ($db !== null && $user_id > 0) {
    try {
        $stmt = $db->prepare("UPDATE users SET is_online = 1, last_online = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['is_online'] = 1;
    } catch (Exception $e) {
        error_log("Heartbeat error: " . $e->getMessage());
    }
}

// ================================================================
// ✅ GET USER ONLINE STATUS FROM DATABASE
// ================================================================
$user_is_online = 0;
if ($db !== null && $user_id > 0) {
    try {
        $stmt = $db->prepare("SELECT is_online, last_online FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_status) {
            $is_online_db = (int)($user_status['is_online'] ?? 0);
            $last_online = $user_status['last_online'] ?? null;
            
            if ($is_online_db === 1) {
                $user_is_online = 1;
            } elseif ($last_online && (time() - strtotime($last_online)) < 300) {
                $user_is_online = 1;
            } else {
                $user_is_online = 0;
            }
            $_SESSION['is_online'] = $user_is_online;
        }
    } catch (Exception $e) {
        $user_is_online = $_SESSION['is_online'] ?? 1;
    }
}

// ================================================================
// ✅ INITIAL BADGE DATA
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$total_paid = 0;
$total_expenses = 0;
$patients_waiting = 0;
$paid_regular = 0;
$paid_otc = 0;
$daily_activities_count = 0;
$today_daily_activities = 0;

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        // PENDING
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_regular = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'pending'");
        $stmt->execute([$user_branch_id]);
        $pending_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $pending_bills = $pending_regular + $pending_otc;
        
        // PARTIAL
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT b.id) as count 
            FROM bills b
            INNER JOIN payments p ON b.id = p.bill_id
            WHERE b.branch_id = ? 
              AND b.status = 'partial'
              AND b.patient_id IS NOT NULL
              AND b.visit_id IS NOT NULL
              AND b.bill_number NOT LIKE 'BILL-OTC-%'
        ");
        $stmt->execute([$user_branch_id]);
        $partial_payments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // PAID
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT b.id) as count 
            FROM bills b
            INNER JOIN payments p ON b.id = p.bill_id
            WHERE b.branch_id = ? 
              AND b.status IN ('paid', 'partial')
              AND b.patient_id IS NOT NULL
              AND b.visit_id IS NOT NULL
              AND b.bill_number NOT LIKE 'BILL-OTC-%'
        ");
        $stmt->execute([$user_branch_id]);
        $paid_regular = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'paid'");
        $stmt->execute([$user_branch_id]);
        $paid_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $total_paid = $paid_regular + $paid_otc;
        
        // EXPENSES
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE branch_id = ? AND status = 'paid'");
        $stmt->execute([$user_branch_id]);
        $total_expenses = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // PATIENTS WAITING
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
        $stmt->execute([$user_branch_id]);
        $patients_waiting = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ✅ DAILY ACTIVITIES kwa cashier huyu
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM daily_activities WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $daily_activities_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM daily_activities WHERE user_id = ? AND activity_date = CURDATE()");
            $stmt->execute([$user_id]);
            $today_daily_activities = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $daily_activities_count = 0;
            $today_daily_activities = 0;
        }
        
    } catch (Exception $e) {
        error_log("Sidebar data error: " . $e->getMessage());
    }
}

$hash_data = [
    'pending_bills' => $pending_bills,
    'partial_payments' => $partial_payments,
    'paid_bills' => $total_paid,
    'total_expenses' => round($total_expenses, 2),
    'patients_waiting' => $patients_waiting,
    'daily_activities' => $daily_activities_count,
    'today_daily_activities' => $today_daily_activities,
    'is_online' => $user_is_online
];
$initial_hash = md5(json_encode($hash_data));

// ================================================================
// ✅ AJAX HANDLER - WITH HEARTBEAT
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_cashier_sidebar_data') {
    header('Content-Type: application/json');
    
    // ✅ HEARTBEAT: Update last_online kila AJAX request
    if ($db !== null && isset($_SESSION['user_id'])) {
        try {
            $stmt = $db->prepare("UPDATE users SET is_online = 1, last_online = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {}
    }
    
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $client_hash = $_POST['hash'] ?? '';
    $user_id_ajax = $_SESSION['user_id'] ?? 0;
    
    $response = ['success' => false, 'has_changed' => false, 'hash' => '', 'data' => null];
    
    try {
        $data = [
            'pending_bills' => 0,
            'partial_payments' => 0,
            'paid_bills' => 0,
            'total_expenses' => 0,
            'patients_waiting' => 0,
            'daily_activities' => 0,
            'today_daily_activities' => 0,
            'is_online' => 1
        ];
        
        if ($db !== null) {
            // Pending
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
            $stmt->execute([$branch_id]);
            $pending_regular = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'pending'");
            $stmt->execute([$branch_id]);
            $pending_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $data['pending_bills'] = $pending_regular + $pending_otc;
            
            // Partial
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT b.id) as count 
                FROM bills b
                INNER JOIN payments p ON b.id = p.bill_id
                WHERE b.branch_id = ? 
                  AND b.status = 'partial'
                  AND b.patient_id IS NOT NULL
                  AND b.visit_id IS NOT NULL
                  AND b.bill_number NOT LIKE 'BILL-OTC-%'
            ");
            $stmt->execute([$branch_id]);
            $data['partial_payments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Paid
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT b.id) as count 
                FROM bills b
                INNER JOIN payments p ON b.id = p.bill_id
                WHERE b.branch_id = ? 
                  AND b.status IN ('paid', 'partial')
                  AND b.patient_id IS NOT NULL
                  AND b.visit_id IS NOT NULL
                  AND b.bill_number NOT LIKE 'BILL-OTC-%'
            ");
            $stmt->execute([$branch_id]);
            $paid_regular = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'paid'");
            $stmt->execute([$branch_id]);
            $paid_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $data['paid_bills'] = $paid_regular + $paid_otc;
            
            // Expenses
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE branch_id = ? AND status = 'paid'");
            $stmt->execute([$branch_id]);
            $data['total_expenses'] = round((float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0), 2);
            
            // Patients waiting
            $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
            $stmt->execute([$branch_id]);
            $data['patients_waiting'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Daily Activities
            if ($user_id_ajax > 0) {
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM daily_activities WHERE user_id = ?");
                    $stmt->execute([$user_id_ajax]);
                    $data['daily_activities'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
                    
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM daily_activities WHERE user_id = ? AND activity_date = CURDATE()");
                    $stmt->execute([$user_id_ajax]);
                    $data['today_daily_activities'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
                } catch (Exception $e) {
                    $data['daily_activities'] = 0;
                    $data['today_daily_activities'] = 0;
                }
            }
        }
        
        $hash = md5(json_encode([
            'pending_bills' => $data['pending_bills'],
            'partial_payments' => $data['partial_payments'],
            'paid_bills' => $data['paid_bills'],
            'total_expenses' => $data['total_expenses'],
            'patients_waiting' => $data['patients_waiting'],
            'daily_activities' => $data['daily_activities'],
            'today_daily_activities' => $data['today_daily_activities'],
            'is_online' => 1
        ]));
        
        $response['hash'] = $hash;
        $response['has_changed'] = ($client_hash !== $hash);
        
        if ($response['has_changed'] || empty($client_hash)) {
            $response['data'] = $data;
        }
        
        $response['success'] = true;
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    if ($page === $current_page) return 'active';
    return '';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<!-- ================================================================ -->
<!-- ✅ MOBILE TOGGLE BUTTON (HAMBURGER) -->
<!-- ================================================================ -->
<button class="cashier-sidebar-toggle" id="cashierSidebarToggle" aria-label="Open Sidebar" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<style>
    /* ============================================================
       TOGGLE BUTTON (HAMBURGER) - MOBILE ONLY
       ============================================================ */
    .cashier-sidebar-toggle {
        display: none;
        position: fixed;
        top: 14px;
        left: 14px;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: none;
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: #FFFFFF;
        font-size: 1.1rem;
        cursor: pointer;
        z-index: 9997;
        box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        align-items: center;
        justify-content: center;
    }
    
    .cashier-sidebar-toggle:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(5, 150, 105, 0.55);
    }
    
    .cashier-sidebar-toggle:active {
        transform: scale(0.95);
    }
    
    .cashier-sidebar-toggle i {
        color: #FFFFFF;
    }
    
    @media (max-width: 1024px) {
        .cashier-sidebar-toggle {
            display: flex;
        }
    }
    
    @media (min-width: 1025px) {
        .cashier-sidebar-toggle {
            display: none !important;
        }
    }
    
    @media print {
        .cashier-sidebar-toggle {
            display: none !important;
        }
    }

    /* ============================================================
       SIDEBAR - GREEN THEME
       ============================================================ */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        background: linear-gradient(180deg, #065F46 0%, #064E3A 100%);
        color: white;
        z-index: 9999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(-100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        padding-bottom: 20px;
        scroll-behavior: smooth;
    }
    
    @media (min-width: 1025px) {
        .sidebar {
            transform: translateX(0) !important;
            z-index: 50;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
    }
    
    [data-theme="dark"] .sidebar {
        background: linear-gradient(180deg, #064E3A 0%, #043A2C 100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar.open { 
        transform: translateX(0) !important; 
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: #6EE7B7; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #A7F3D0; }
    
    /* OVERLAY */
    #sidebarOverlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 9998;
        display: none;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        transition: opacity 0.3s ease;
    }
    
    @media (min-width: 1025px) {
        #sidebarOverlay { display: none !important; }
    }
    
    #sidebarOverlay.active { 
        display: block !important; 
    }
    
    /* BRAND */
    .sidebar-brand {
        padding: 18px 16px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.1);
        position: sticky; top: 0; z-index: 5;
        backdrop-filter: blur(10px);
    }
    .sidebar-brand .logo {
        width: 42px; height: 42px;
        border-radius: 10px;
        object-fit: cover;
        background: white;
        padding: 4px;
        border: 2px solid rgba(255,255,255,0.15);
        transition: transform 0.3s ease;
    }
    .sidebar-brand .logo:hover { transform: rotate(-5deg) scale(1.05); }
    .sidebar-brand .brand-text {
        color: white; font-weight: 700; font-size: 0.95rem;
        line-height: 1.2; letter-spacing: 0.5px;
    }
    .sidebar-brand .brand-sub {
        color: #A7F3D0; font-size: 0.65rem;
        font-weight: 500; letter-spacing: 0.3px;
    }
    
    .sidebar-close-btn {
        display: none;
        background: rgba(255,255,255,0.1);
        border: none; color: white;
        font-size: 1.2rem; cursor: pointer;
        padding: 4px 10px; border-radius: 8px;
        transition: all 0.3s ease;
        margin-left: auto;
    }
    .sidebar-close-btn:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05); color: white;
    }
    
    @media (max-width: 1024px) {
        .sidebar-close-btn { display: block; }
    }
    @media (min-width: 1025px) {
        .sidebar-close-btn { display: none !important; }
    }
    
    /* NAV */
    .sidebar-nav { padding: 8px 8px 16px; }
    .sidebar-nav .nav-label {
        font-size: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #A7F3D0;
        padding: 8px 10px 4px;
        margin: 8px 0 2px;
        font-weight: 700;
        opacity: 0.8;
    }
    .sidebar-nav .nav-label:first-of-type { margin-top: 0; }
    .sidebar-nav .nav-label .label-icon { margin-right: 4px; }
    
    /* LINKS */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        color: #D1FAE5;
        text-decoration: none;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 0.8rem;
        font-weight: 500;
        margin: 1px 0;
        background: transparent;
        cursor: pointer;
        border: none;
        width: 100%;
        text-align: left;
        position: relative;
    }
    .sidebar-link:hover {
        background: rgba(5, 150, 105, 0.4);
        color: white;
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
    }
    .sidebar-link.active {
        background: rgba(5, 150, 105, 0.5);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0; top: 15%; bottom: 15%;
        width: 4px;
        background: #059669;
        border-radius: 0 4px 4px 0;
        box-shadow: 0 0 12px rgba(5, 150, 105, 0.5);
    }
    .sidebar-link i {
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        opacity: 0.8;
    }
    .sidebar-link:hover i,
    .sidebar-link.active i { opacity: 1; }
    .sidebar-link .link-text {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* BADGES */
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.12);
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-link .badge.orange { background: #D97706; border-color: #D97706; }
    .sidebar-link .badge.green { background: #059669; border-color: #059669; }
    .sidebar-link .badge.success { background: #059669; border-color: #059669; }
    .sidebar-link .badge.blue { background: #0B5ED7; border-color: #0B5ED7; }
    .sidebar-link .badge.red { background: #DC2626; border-color: #DC2626; }
    .sidebar-link .badge.yellow { background: #D97706; border-color: #D97706; }
    .sidebar-link .badge.purple { background: #7C3AED; border-color: #7C3AED; }
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
    }
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    /* BADGE ANIMATION */
    .badge-update {
        animation: badgePop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes badgePop {
        0% { transform: scale(0.3); opacity: 0; }
        60% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* DATA FLASH */
    .sidebar-data-flash { animation: flashGreen 0.6s ease; }
    @keyframes flashGreen {
        0% { background: rgba(52, 211, 153, 0.15); }
        50% { background: rgba(52, 211, 153, 0.03); }
        100% { background: transparent; }
    }
    
    /* LOGOUT */
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.06);
        padding-top: 10px;
        margin-top: 4px;
        color: #FCA5A5;
    }
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        transform: translateX(4px);
    }
    .sidebar-link.logout-link i { opacity: 1; }
    
    /* LIVE */
    .sidebar-live-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.5rem;
        color: #34D399;
        margin-left: auto;
        font-weight: 500;
    }
    .sidebar-live-indicator .dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
        display: inline-block;
    }
    
    /* ✅ STATUS FOOTER - ONLINE/OFFLINE ENHANCED */
    .sidebar-status {
        padding: 10px 16px;
        border-top: 2px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.1);
        position: sticky;
        bottom: 0;
        backdrop-filter: blur(10px);
    }
    .sidebar-status .status-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        display: inline-block;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    .sidebar-status .status-dot.online {
        background: #34D399;
        box-shadow: 0 0 8px rgba(52, 211, 153, 0.6);
        animation: pulse-dot 1.5s infinite;
    }
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
        box-shadow: none;
        animation: none;
    }
    .sidebar-status .status-text {
        font-size: 0.7rem;
        color: #D1FAE5;
        font-weight: 600;
        letter-spacing: 0.3px;
        transition: color 0.3s ease;
    }
    .sidebar-status .status-text.online {
        color: #34D399;
    }
    .sidebar-status .status-text.offline {
        color: #94A3B8;
    }
    .sidebar-status .update-time {
        font-size: 0.5rem;
        color: #A7F3D0;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
    }
    
    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            z-index: 9999;
            border-radius: 0 12px 12px 0;
        }
        .sidebar.open { transform: translateX(0) !important; }
        #sidebarOverlay { display: none; z-index: 9998; }
        #sidebarOverlay.active { display: block !important; }
        .sidebar-brand { padding: 14px 14px 10px; }
        .sidebar-brand .logo { width: 36px; height: 36px; }
        .sidebar-brand .brand-text { font-size: 0.85rem; }
        .sidebar-link { padding: 7px 10px; font-size: 0.75rem; gap: 8px; }
        .sidebar-link i { width: 18px; font-size: 0.8rem; }
        .sidebar-link .badge { font-size: 0.55rem; padding: 1px 7px; }
    }
    
    @media (max-width: 768px) {
        .sidebar { width: 300px; }
        .sidebar-brand { padding: 12px 12px 10px; }
        .sidebar-brand .logo { width: 34px; height: 34px; }
        .sidebar-brand .brand-text { font-size: 0.8rem; }
        .sidebar-link { padding: 6px 10px; font-size: 0.7rem; }
        .sidebar-link i { width: 16px; font-size: 0.75rem; }
    }
    
    @media (max-width: 480px) {
        .sidebar { width: 100%; max-width: 320px; }
        .sidebar-brand { padding: 10px 10px 8px; }
        .sidebar-brand .logo { width: 30px; height: 30px; }
        .sidebar-brand .brand-text { font-size: 0.75rem; }
        .sidebar-link { padding: 5px 8px; font-size: 0.65rem; gap: 6px; }
        .sidebar-link i { width: 14px; font-size: 0.7rem; }
        .sidebar-link .badge { font-size: 0.45rem; padding: 1px 5px; min-width: 16px; }
        .sidebar-nav .nav-label { font-size: 0.4rem; }
        .sidebar-status .status-text { font-size: 0.55rem; }
    }
    
    @media print {
        .sidebar { display: none !important; }
        #sidebarOverlay { display: none !important; }
        .cashier-sidebar-toggle { display: none !important; }
    }
    
    /* UTILITY */
    .flex { display: flex; }
    .items-center { align-items: center; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<!-- OVERLAY -->
<div id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Cashier Sidebar">
    
    <!-- BRAND -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%23065F46%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">💰 Cashier Panel</p>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- NAVIGATION -->
    <nav class="sidebar-nav">
        
        <div class="nav-label"><span class="label-icon">📋</span> Cashier</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i>
            <span class="link-text">Dashboard</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">💰</span> Billing</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/pending_bills.php" class="sidebar-link <?= isActive('pending_bills.php') ?>">
            <i class="fas fa-clock"></i>
            <span class="link-text">Pending Bills</span>
            <span class="badge <?= $pending_bills > 0 ? 'orange' : '' ?>" id="sidebarPendingBadge"><?= $pending_bills ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/cashier/paid_bills.php" class="sidebar-link <?= isActive('paid_bills.php') ?>">
            <i class="fas fa-check-circle"></i>
            <span class="link-text">Paid Bills</span>
            <span class="badge <?= $total_paid > 0 ? 'green' : '' ?>" id="sidebarPaidBadge"><?= $total_paid ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/cashier/partial_payments.php" class="sidebar-link <?= isActive('partial_payments.php') ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span class="link-text">Partial Payments</span>
            <span class="badge <?= $partial_payments > 0 ? 'blue' : '' ?>" id="sidebarPartialBadge"><?= $partial_payments ?></span>
        </a>
        
        <div class="nav-label"><span class="label-icon">💳</span> Payments</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/payment_history.php" class="sidebar-link <?= isActive('payment_history.php') ?>">
            <i class="fas fa-history"></i>
            <span class="link-text">Payment History</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">🧾</span> Receipts</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/receipt_history.php" class="sidebar-link <?= isActive('receipt_history.php') ?>">
            <i class="fas fa-receipt"></i>
            <span class="link-text">Receipt History</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">💰</span> Expenses</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/expenses.php" class="sidebar-link <?= isActive('expenses.php') ?>">
            <i class="fas fa-coins"></i>
            <span class="link-text">Expenses</span>
            <span class="badge <?= $total_expenses > 0 ? 'yellow' : '' ?>" id="sidebarExpensesBadge"><?= $total_expenses > 0 ? 'TSh ' . number_format($total_expenses) : '0' ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- ✅ NEW: DAILY ACTIVITIES - CHINI YA EXPENSES -->
        <!-- ============================================================ -->
        <a href="/dispensary_system/frontend/pages/cashier/daily_activities.php" class="sidebar-link <?= isActive('daily_activities.php') ?>">
            <i class="fas fa-tasks"></i>
            <span class="link-text">Daily Activities</span>
            <?php if ($daily_activities_count > 0): ?>
                <span class="badge success" id="cashierDailyActivitiesBadge"><?= $daily_activities_count ?></span>
            <?php else: ?>
                <span class="badge" id="cashierDailyActivitiesBadge">0</span>
            <?php endif; ?>
            <?php if ($today_daily_activities > 0): ?>
                <span class="badge blue" id="cashierDailyActivitiesTodayBadge" style="margin-left:2px;font-size:0.55rem;">+<?= $today_daily_activities ?></span>
            <?php else: ?>
                <span class="badge" id="cashierDailyActivitiesTodayBadge" style="display:none;margin-left:2px;font-size:0.55rem;">+0</span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        
        <a href="/dispensary_system/frontend/pages/cashier/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
    <!-- ✅ STATUS FOOTER - ONLINE/OFFLINE FROM DB -->
    <div class="sidebar-status">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>" id="sidebarStatusDot"></span>
        <span class="status-text <?= $user_is_online ? 'online' : 'offline' ?>" id="sidebarStatusText">
            <?= $user_is_online ? 'Online' : 'Offline' ?>
        </span>
        <span class="update-time" id="sidebarUpdateTime">
            <span class="sidebar-live-indicator">
                <span class="dot"></span> Live
            </span>
        </span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var SIDEBAR_CONFIG = {
        AJAX_URL: window.location.pathname,  // ✅ Same page - AJAX inatumia PHP handler ya juu
        CHECK_INTERVAL: 2000,
        FORCE_INTERVAL: 5000,
        BRANCH_ID: <?= json_encode($user_branch_id) ?>,
        USER_ID: <?= json_encode($user_id) ?>,
        INITIAL_HASH: '<?= $initial_hash ?>'
    };
    
    console.log('🔧 Cashier Sidebar V11 Config:', SIDEBAR_CONFIG);
    
    var sidebarState = {
        dataHash: SIDEBAR_CONFIG.INITIAL_HASH,
        isUpdating: false,
        hasInitialData: false,
        updateInterval: null,
        forceInterval: null,
        changeCount: 0,
        lastData: null
    };
    
    // ============================================================
    // SIDEBAR TOGGLE (MOBILE ONLY)
    // ============================================================
    (function() {
        function initSidebar() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('cashierSidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
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
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            initSidebar();
        }
    })();

    // ============================================================
    // ✅ UPDATE USER ONLINE/OFFLINE STATUS (FIXED)
    // ============================================================
    function updateUserStatus(data) {
        if (!data) return;
        
        // ✅ Kama is_online haipo kwenye data, TUSIBADILISHE
        if (data.is_online === undefined && data.online_status === undefined) {
            return;
        }
        
        var statusDot = document.getElementById('sidebarStatusDot');
        var statusText = document.getElementById('sidebarStatusText');
        
        var isOnline = false;
        if (data.is_online !== undefined) {
            isOnline = (data.is_online == 1);
        } else if (data.online_status !== undefined) {
            isOnline = (data.online_status === 'online');
        }
        
        if (statusDot) {
            statusDot.className = 'status-dot ' + (isOnline ? 'online' : 'offline');
        }
        if (statusText) {
            statusText.className = 'status-text ' + (isOnline ? 'online' : 'offline');
            statusText.textContent = isOnline ? 'Online' : 'Offline';
        }
    }

    // ============================================================
    // UPDATE BADGES
    // ============================================================
    function updateSidebarBadges(data) {
        if (!data) return false;
        var hasChanges = false;
        
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge && data.pending_bills !== undefined) {
            var oldVal = pendingBadge.textContent;
            var newVal = String(data.pending_bills);
            if (oldVal !== newVal) {
                hasChanges = true;
                pendingBadge.textContent = newVal;
                pendingBadge.className = parseInt(newVal) > 0 ? 'badge orange' : 'badge';
                pendingBadge.classList.remove('badge-update');
                void pendingBadge.offsetWidth;
                pendingBadge.classList.add('badge-update');
            }
        }
        
        var paidBadge = document.getElementById('sidebarPaidBadge');
        if (paidBadge && data.paid_bills !== undefined) {
            var oldVal = paidBadge.textContent;
            var newVal = String(data.paid_bills);
            if (oldVal !== newVal) {
                hasChanges = true;
                paidBadge.textContent = newVal;
                paidBadge.className = parseInt(newVal) > 0 ? 'badge green' : 'badge';
                paidBadge.classList.remove('badge-update');
                void paidBadge.offsetWidth;
                paidBadge.classList.add('badge-update');
            }
        }
        
        var partialBadge = document.getElementById('sidebarPartialBadge');
        if (partialBadge && data.partial_payments !== undefined) {
            var oldVal = partialBadge.textContent;
            var newVal = String(data.partial_payments);
            if (oldVal !== newVal) {
                hasChanges = true;
                partialBadge.textContent = newVal;
                partialBadge.className = parseInt(newVal) > 0 ? 'badge blue' : 'badge';
                partialBadge.classList.remove('badge-update');
                void partialBadge.offsetWidth;
                partialBadge.classList.add('badge-update');
            }
        }
        
        var expensesBadge = document.getElementById('sidebarExpensesBadge');
        if (expensesBadge && data.total_expenses !== undefined) {
            var oldVal = expensesBadge.textContent;
            var numericVal = Number(data.total_expenses);
            var newVal = numericVal > 0 ? 'TSh ' + numericVal.toLocaleString() : '0';
            if (oldVal !== newVal) {
                hasChanges = true;
                expensesBadge.textContent = newVal;
                expensesBadge.className = numericVal > 0 ? 'badge yellow' : 'badge';
                expensesBadge.classList.remove('badge-update');
                void expensesBadge.offsetWidth;
                expensesBadge.classList.add('badge-update');
            }
        }
        
        // ✅ Daily Activities Badge
        var daBadge = document.getElementById('cashierDailyActivitiesBadge');
        if (daBadge && data.daily_activities !== undefined) {
            var oldVal = daBadge.textContent;
            var newVal = data.daily_activities;
            if (oldVal !== String(newVal)) {
                hasChanges = true;
                daBadge.textContent = newVal;
                daBadge.className = parseInt(newVal) > 0 ? 'badge success badge-update' : 'badge badge-update';
                daBadge.classList.remove('badge-update');
                void daBadge.offsetWidth;
                daBadge.classList.add('badge-update');
            }
        }
        
        // ✅ Today Daily Activities Badge
        var daTodayBadge = document.getElementById('cashierDailyActivitiesTodayBadge');
        if (daTodayBadge && data.today_daily_activities !== undefined) {
            var oldVal = daTodayBadge.textContent;
            var newVal = data.today_daily_activities;
            if (oldVal !== '+' + newVal) {
                hasChanges = true;
                daTodayBadge.textContent = '+' + newVal;
                daTodayBadge.style.display = newVal > 0 ? 'inline-block' : 'none';
                daTodayBadge.classList.remove('badge-update');
                void daTodayBadge.offsetWidth;
                daTodayBadge.classList.add('badge-update');
            }
        }
        
        // ✅ UPDATE ONLINE/OFFLINE STATUS
        updateUserStatus(data);
        
        var timeEl = document.getElementById('sidebarUpdateTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            var newHtml = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr + '</span>';
            if (timeEl.innerHTML !== newHtml) timeEl.innerHTML = newHtml;
        }
        
        if (hasChanges) {
            var sidebarEl = document.getElementById('sidebar');
            if (sidebarEl) {
                sidebarEl.classList.remove('sidebar-data-flash');
                void sidebarEl.offsetWidth;
                sidebarEl.classList.add('sidebar-data-flash');
            }
            sidebarState.changeCount++;
        }
        
        sidebarState.lastData = data;
        return hasChanges;
    }

    // ============================================================
    // FETCH
    // ============================================================
    function fetchSidebarData(forceUpdate) {
        if (sidebarState.isUpdating && !forceUpdate) return;
        if (!SIDEBAR_CONFIG.BRANCH_ID) return;
        
        sidebarState.isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_cashier_sidebar_data');  // ✅ Action key
        formData.append('branch_id', SIDEBAR_CONFIG.BRANCH_ID);
        formData.append('user_id', SIDEBAR_CONFIG.USER_ID);
        formData.append('hash', sidebarState.dataHash);
        if (forceUpdate) formData.append('force_update', '1');
        
        fetch(SIDEBAR_CONFIG.AJAX_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Network error: ' + response.status);
            return response.json();
        })
        .then(function(data) {
            sidebarState.isUpdating = false;
            
            if (data.success) {
                if (data.has_changed && data.data) {
                    var hasUpdates = updateSidebarBadges(data.data);
                    if (hasUpdates) {
                        sidebarState.dataHash = data.hash;
                    }
                    sidebarState.hasInitialData = true;
                    
                    var event = new CustomEvent('sidebarDataUpdated', {
                        detail: {
                            data: data.data,
                            summary: data.summary,
                            timestamp: data.timestamp
                        }
                    });
                    document.dispatchEvent(event);
                } else {
                    var timeEl = document.getElementById('sidebarUpdateTime');
                    if (timeEl) {
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', {
                            hour: '2-digit', minute: '2-digit', second: '2-digit'
                        });
                        timeEl.innerHTML = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr + '</span>';
                    }
                    sidebarState.hasInitialData = true;
                }
                
                // ✅ Update status - LAKINI tu kama AJAX imetuma is_online
                if (data.data && data.data.is_online !== undefined) {
                    updateUserStatus(data.data);
                }
                
            } else {
                if (data.message && data.message.includes('Unauthorized')) {
                    window.location.href = '/dispensary_system/frontend/pages/login.php';
                }
            }
        })
        .catch(function(error) {
            sidebarState.isUpdating = false;
            // ✅ HATUBADILISHI STATUS KWA KOSA LA NETWORK
            // Status inabaki kama ilivyo (Online au Offline)
            console.warn('❌ Sidebar AJAX error (status unchanged):', error.message);
        });
    }

    // ============================================================
    // AUTO UPDATE
    // ============================================================
    function startSidebarAutoUpdate() {
        if (sidebarState.updateInterval) clearInterval(sidebarState.updateInterval);
        if (sidebarState.forceInterval) clearInterval(sidebarState.forceInterval);
        
        setTimeout(function() { fetchSidebarData(true); }, 300);
        
        sidebarState.updateInterval = setInterval(function() {
            if (!sidebarState.isUpdating) fetchSidebarData(false);
        }, SIDEBAR_CONFIG.CHECK_INTERVAL);
        
        sidebarState.forceInterval = setInterval(function() {
            if (!sidebarState.isUpdating && sidebarState.hasInitialData) {
                fetchSidebarData(true);
            }
        }, SIDEBAR_CONFIG.FORCE_INTERVAL);
    }

    function stopSidebarAutoUpdate() {
        if (sidebarState.updateInterval) {
            clearInterval(sidebarState.updateInterval);
            sidebarState.updateInterval = null;
        }
        if (sidebarState.forceInterval) {
            clearInterval(sidebarState.forceInterval);
            sidebarState.forceInterval = null;
        }
    }

    function refreshSidebarData() {
        fetchSidebarData(true);
        return true;
    }

    window.refreshSidebarData = refreshSidebarData;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopSidebarAutoUpdate();
        else {
            startSidebarAutoUpdate();
            setTimeout(function() { fetchSidebarData(true); }, 300);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() { startSidebarAutoUpdate(); }, 500);
    });

    console.log('%c💰 Braick - Cashier Sidebar V11', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c🟢 Status: <?= $user_is_online ? "ONLINE" : "OFFLINE" ?>', 
        'font-size:13px; color:<?= $user_is_online ? "#34D399" : "#94A3B8" ?>; font-weight:bold;');
    console.log('%c📅 Daily Activities: <?= $daily_activities_count ?> (Today: <?= $today_daily_activities ?>)', 
        'font-size:13px; color:#10B981;');
    console.log('%c✅ NEW: Daily Activities below Expenses', 
        'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ FIXED: Heartbeat keeps status online', 
        'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ FIXED: Status unchanged on network errors', 
        'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Paid Bills: <?= $total_paid ?> (matches page)', 'font-size:13px; color:#34D399;');
</script>