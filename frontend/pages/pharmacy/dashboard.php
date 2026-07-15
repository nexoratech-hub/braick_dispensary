<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dashboard.php
// PHARMACY - DASHBOARD (FULLY FIXED)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$is_admin = $_SESSION['is_admin'] ?? false;

$db = getDB();

// ================================================================
// GET INVENTORY STATISTICS - WITH ERROR HANDLING
// ================================================================

$total_medicines = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $total_medicines = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $total_medicines = 0;
}

$total_stock = 0;
try {
    $stmt = $db->prepare("SELECT SUM(quantity) as total FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $total_stock = is_array($result) ? ($result['total'] ?? 0) : 0;
} catch (Exception $e) {
    $total_stock = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $low_stock_count = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$out_of_stock = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity = 0 AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $out_of_stock = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $out_of_stock = 0;
}

$expiring_soon = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $expiring_soon = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $expiring_soon = 0;
}

// ================================================================
// PRESCRIPTION STATISTICS
// ================================================================

$total_prescriptions = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescription_sales 
        WHERE branch_id = ? AND status = 'dispensed'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $total_prescriptions = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $total_prescriptions = 0;
}

$pending_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescription_sales 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $pending_count = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pending_count = 0;
}

$dispensed_today = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescription_sales 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'dispensed'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $dispensed_today = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $dispensed_today = 0;
}

$otc_today = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM otc_sales 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $otc_today = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $otc_today = 0;
}

// ================================================================
// TODAY'S REVENUE - ONLY FOR ADMIN
// ================================================================
$today_prescription_revenue = 0;
$today_otc_revenue = 0;
$today_revenue = 0;

if ($is_admin) {
    try {
        $stmt = $db->prepare("
            SELECT SUM(total_amount) as total 
            FROM prescription_sales 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'dispensed'
        ");
        $stmt->execute([$user_branch_id]);
        $result = $stmt->fetch();
        $today_prescription_revenue = is_array($result) ? ($result['total'] ?? 0) : 0;
    } catch (Exception $e) {
        $today_prescription_revenue = 0;
    }

    try {
        $stmt = $db->prepare("
            SELECT SUM(total_amount) as total 
            FROM otc_sales 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $result = $stmt->fetch();
        $today_otc_revenue = is_array($result) ? ($result['total'] ?? 0) : 0;
    } catch (Exception $e) {
        $today_otc_revenue = 0;
    }

    $today_revenue = $today_prescription_revenue + $today_otc_revenue;
}

// ================================================================
// GET PENDING PRESCRIPTIONS LIST - FIXED
// ================================================================
$pending_prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT ps.id, ps.sale_number, p.full_name as patient_name, ps.created_at
        FROM prescription_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        WHERE ps.branch_id = ? AND ps.status = 'pending'
        ORDER BY ps.created_at ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetchAll();
    
    if (!is_array($pending_prescriptions)) {
        $pending_prescriptions = [];
    }
} catch (Exception $e) {
    $pending_prescriptions = [];
}

// ================================================================
// GET RECENT ACTIVITY - FIXED (Always returns array)
// ================================================================
$recent_activity = [];

try {
    // Get prescriptions
    $stmt = $db->prepare("
        SELECT 
            'prescription' as type,
            ps.sale_number as reference,
            p.full_name as customer,
            ps.created_at,
            'dispensed' as status
        FROM prescription_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        WHERE ps.branch_id = ? AND ps.status = 'dispensed'
        ORDER BY ps.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_branch_id]);
    $prescriptions_activity = $stmt->fetchAll();
    
    if (!is_array($prescriptions_activity)) {
        $prescriptions_activity = [];
    }

    // Get OTC sales
    $stmt = $db->prepare("
        SELECT 
            'otc' as type,
            os.sale_number as reference,
            os.customer_name as customer,
            os.created_at,
            'completed' as status
        FROM otc_sales os
        WHERE os.branch_id = ?
        ORDER BY os.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_branch_id]);
    $otc_activity = $stmt->fetchAll();
    
    if (!is_array($otc_activity)) {
        $otc_activity = [];
    }

    // Combine
    $recent_activity = array_merge($prescriptions_activity, $otc_activity);

    // Sort by created_at DESC if there are items
    if (is_array($recent_activity) && count($recent_activity) > 0) {
        usort($recent_activity, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }

    // Limit to 10
    $recent_activity = array_slice($recent_activity, 0, 10);
    
} catch (Exception $e) {
    $recent_activity = [];
}

// FINAL SAFETY CHECK - Ensure $recent_activity is always an array
if (!is_array($recent_activity)) {
    $recent_activity = [];
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions_sidebar = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $pending_prescriptions_sidebar = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pending_prescriptions_sidebar = 0;
}

$low_stock_sidebar = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $result = $stmt->fetch();
    $low_stock_sidebar = is_array($result) ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $low_stock_sidebar = 0;
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $unread_notifications = is_array($result) ? ($result['total'] ?? 0) : 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A3D8A;
        --primary-light: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        --teal: #0D9488;
        --teal-light: #CCFBF1;
        --pink: #DB2777;
        --pink-light: #FCE7F3;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
    }
    
    .stats-grid-8 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        border: none;
        transition: all 0.3s;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        min-height: 100px;
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: rgba(255,255,255,0.08);
        transition: all 0.5s ease;
    }
    
    .stat-card:hover::after {
        transform: scale(1.2);
        right: -10%;
    }
    
    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    
    .stat-card:active {
        transform: scale(0.97);
    }
    
    .stat-card .stat-content {
        z-index: 1;
        position: relative;
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.18);
        color: white;
        flex-shrink: 0;
        z-index: 1;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
        background: rgba(255,255,255,0.28);
    }
    
    .stat-card .stat-trend {
        font-size: 0.55rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: rgba(255,255,255,0.18);
        color: white;
        display: inline-block;
        margin-top: 4px;
    }
    
    .stat-card .stat-arrow {
        opacity: 0;
        transition: all 0.3s ease;
        margin-left: 4px;
        font-size: 0.65rem;
    }
    
    .stat-card:hover .stat-arrow {
        opacity: 1;
        transform: translateX(4px);
    }
    
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
    .stat-card.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); }
    
    .admin-only {
        display: <?= $is_admin ? 'block' : 'none' ?>;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stats-row .stat-card {
        padding: 16px 20px;
        min-height: 80px;
    }
    
    .stats-row .stat-card .stat-number {
        font-size: 1.5rem;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .card-title i {
        color: var(--primary);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 4px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .pending-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
        text-decoration: none;
        color: var(--text-primary);
    }
    
    .pending-item:hover {
        background: var(--primary-light);
        border-radius: 8px;
    }
    
    [data-theme="dark"] .pending-item:hover {
        background: #1E3A5F;
    }
    
    .pending-item:last-child {
        border-bottom: none;
    }
    
    .pending-item .patient-info .patient-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .pending-item .patient-info .sale-number {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-family: monospace;
    }
    
    .pending-item .pending-time {
        font-size: 0.7rem;
        color: var(--text-secondary);
        text-align: right;
    }
    
    .pending-item .pending-time .time-text {
        display: block;
    }
    
    .pending-item .pending-time .date-text {
        font-size: 0.6rem;
        color: var(--text-muted);
    }
    
    .badge-pending {
        background: var(--warning-light);
        color: var(--warning);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    
    [data-theme="dark"] .badge-pending {
        background: #3D2E0A;
        color: #FBBF24;
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
        background: var(--primary-light);
        border-radius: 8px;
    }
    
    [data-theme="dark"] .activity-item:hover {
        background: #1E3A5F;
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
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    
    .activity-item .activity-icon.prescription {
        background: var(--primary-light);
        color: var(--primary);
    }
    
    .activity-item .activity-icon.otc {
        background: var(--purple-light);
        color: var(--purple);
    }
    
    [data-theme="dark"] .activity-item .activity-icon.prescription {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .activity-item .activity-icon.otc {
        background: #2A1A3A;
        color: #9B4DCA;
    }
    
    .activity-item .activity-info {
        flex: 1;
    }
    
    .activity-item .activity-info .activity-title {
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .activity-item .activity-info .activity-desc {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .activity-item .activity-info .activity-desc .ref {
        font-family: monospace;
        font-weight: 600;
    }
    
    .activity-item .activity-time {
        font-size: 0.65rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    
    .activity-item .activity-status {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
    }
    
    .activity-item .activity-status.dispensed {
        background: var(--success-light);
        color: var(--success);
    }
    
    .activity-item .activity-status.completed {
        background: var(--success-light);
        color: var(--success);
    }
    
    [data-theme="dark"] .activity-item .activity-status.dispensed {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .activity-item .activity-status.completed {
        background: #1A3A2A;
        color: #34D399;
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
        margin-bottom: 8px;
    }
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    .empty-state .sub {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .two-col-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
    .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
    .animate-fade-in-up:nth-child(6) { animation-delay: 0.3s; }
    .animate-fade-in-up:nth-child(7) { animation-delay: 0.35s; }
    .animate-fade-in-up:nth-child(8) { animation-delay: 0.4s; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 1200px) {
        .stats-grid-8 { grid-template-columns: repeat(4, 1fr); }
    }
    
    @media (max-width: 992px) {
        .stats-grid-8 { grid-template-columns: repeat(3, 1fr); }
    }
    
    @media (max-width: 768px) {
        .stats-grid-8 { grid-template-columns: repeat(2, 1fr); }
        .two-col-grid { grid-template-columns: 1fr; }
        .stats-row { grid-template-columns: 1fr; }
        .card { padding: 14px 16px; }
        .stat-card .stat-number { font-size: 1.3rem; }
        .stat-card { padding: 12px 16px; min-height: 80px; }
        .pending-item { flex-direction: column; align-items: flex-start; gap: 4px; }
        .pending-item .pending-time { text-align: left; width: 100%; }
        .stat-card .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
    }
    
    @media (max-width: 480px) {
        .stats-grid-8 { grid-template-columns: 1fr 1fr; }
        .stat-card .stat-number { font-size: 1.1rem; }
        .stat-card .stat-label { font-size: 0.6rem; }
        .stat-card .stat-icon { width: 30px; height: 30px; font-size: 0.8rem; }
        .stat-card { padding: 8px 12px; min-height: 70px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home mr-2" style="color: var(--primary);"></i> Pharmacy Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <?= htmlspecialchars($user_full_name) ?>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('l, F d, Y') ?>
                </span>
                <?php if ($is_admin): ?>
                    <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                        <i class="fas fa-crown mr-1"></i> Admin
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="new_otc_sale.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
            <a href="dispensing.php" class="btn btn-blue btn-sm">
                <i class="fas fa-prescription"></i> Dispense
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid-8 animate-fade-in-up">
        
        <a href="inventory.php" class="stat-card blue">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($total_stock) ?></p>
                <p class="stat-label"><i class="fas fa-boxes mr-1"></i> Total Stock <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-pills mr-1"></i> <?= $total_medicines ?> items</span>
            </div>
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        </a>
        
        <a href="inventory.php?expiry=expiring" class="stat-card red">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($expiring_soon) ?></p>
                <p class="stat-label"><i class="fas fa-clock mr-1"></i> Expire Soon <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-calendar-alt mr-1"></i> Within 30 days</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <a href="inventory.php?stock=low" class="stat-card orange">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($low_stock_count) ?></p>
                <p class="stat-label"><i class="fas fa-exclamation-triangle mr-1"></i> Low Stock <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-warehouse mr-1"></i> Need restock</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </a>
        
        <a href="inventory.php?stock=out" class="stat-card purple">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($out_of_stock) ?></p>
                <p class="stat-label"><i class="fas fa-times-circle mr-1"></i> Out of Stock <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-prescription mr-1"></i> Empty</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </a>
        
        <a href="prescription_history.php?filter=dispensed" class="stat-card teal">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($total_prescriptions) ?></p>
                <p class="stat-label"><i class="fas fa-prescription mr-1"></i> Total Prescriptions <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-check-circle mr-1"></i> Dispensed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
        </a>
        
        <a href="pending_prescriptions.php" class="stat-card pink">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($pending_count) ?></p>
                <p class="stat-label"><i class="fas fa-clock mr-1"></i> Pending <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-hourglass-half mr-1"></i> Awaiting</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <a href="prescription_history.php?filter=otc" class="stat-card green">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($otc_today) ?></p>
                <p class="stat-label"><i class="fas fa-shopping-cart mr-1"></i> OTC Sales <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-calendar-day mr-1"></i> Today</span>
            </div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </a>
        
        <a href="prescription_history.php?filter=today" class="stat-card indigo">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($dispensed_today) ?></p>
                <p class="stat-label"><i class="fas fa-prescription mr-1"></i> Dispensed Today <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span></p>
                <span class="stat-trend"><i class="fas fa-check-circle mr-1"></i> Completed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- ADMIN ONLY: REVENUE & TOTAL TRANSACTIONS -->
    <!-- ================================================================ -->
    <?php if ($is_admin): ?>
    <div class="stats-row animate-fade-in-up admin-only">
        <div class="stat-card teal" style="cursor:default;">
            <div class="stat-content">
                <p class="stat-number">TSh <?= number_format($today_revenue) ?></p>
                <p class="stat-label"><i class="fas fa-money-bill-wave mr-1"></i> Today's Revenue</p>
                <span class="stat-trend">
                    <i class="fas fa-prescription mr-1"></i> <?= number_format($today_prescription_revenue) ?>
                    <i class="fas fa-shopping-cart ml-2 mr-1"></i> <?= number_format($today_otc_revenue) ?>
                </span>
            </div>
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        
        <div class="stat-card blue" style="cursor:default;">
            <div class="stat-content">
                <p class="stat-number"><?= number_format($total_prescriptions + $otc_today) ?></p>
                <p class="stat-label"><i class="fas fa-chart-line mr-1"></i> Total Transactions</p>
                <span class="stat-trend">
                    <i class="fas fa-prescription mr-1"></i> <?= number_format($total_prescriptions) ?> Rx
                    <i class="fas fa-shopping-cart ml-2 mr-1"></i> <?= number_format($otc_today) ?> OTC
                </span>
            </div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- TWO COLUMN: PENDING PRESCRIPTIONS & RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="two-col-grid animate-fade-in-up">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                    Pending Prescriptions
                    <?php if ($pending_count > 0): ?>
                        <span class="badge badge-pending"><?= $pending_count ?></span>
                    <?php endif; ?>
                </h3>
                <a href="pending_prescriptions.php" class="btn-outline orange">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (is_array($pending_prescriptions) && count($pending_prescriptions) > 0): ?>
                <?php foreach ($pending_prescriptions as $pending): ?>
                    <a href="dispensing.php?id=<?= $pending['id'] ?>" class="pending-item">
                        <div class="patient-info">
                            <div class="patient-name"><?= htmlspecialchars($pending['patient_name'] ?? 'Unknown Patient') ?></div>
                            <div class="sale-number"><?= htmlspecialchars($pending['sale_number'] ?? 'N/A') ?></div>
                        </div>
                        <div class="pending-time">
                            <span class="time-text"><?= date('h:i A', strtotime($pending['created_at'])) ?></span>
                            <span class="date-text"><?= date('M d, Y', strtotime($pending['created_at'])) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    <p>No pending prescriptions</p>
                    <p class="sub">All prescriptions have been dispensed. Great job! 🎉</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history" style="color: var(--primary);"></i>
                    Recent Activity
                </h3>
                <a href="prescription_history.php" class="btn-outline">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (is_array($recent_activity) && count($recent_activity) > 0): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $activity['type'] ?? 'prescription' ?>">
                            <i class="fas <?= ($activity['type'] ?? 'prescription') === 'prescription' ? 'fa-prescription' : 'fa-shopping-cart' ?>"></i>
                        </div>
                        <div class="activity-info">
                            <div class="activity-title">
                                <?= ($activity['type'] ?? 'prescription') === 'prescription' ? 'Prescription Dispensed' : 'OTC Sale' ?>
                            </div>
                            <div class="activity-desc">
                                <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($activity['customer'] ?? 'Unknown') ?>
                                <span class="mx-1">|</span>
                                <span class="ref"><?= htmlspecialchars($activity['reference'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="activity-status <?= $activity['status'] ?? 'dispensed' ?>">
                                <?= ucfirst($activity['status'] ?? '') ?>
                            </span>
                            <div class="activity-time"><?= date('h:i A', strtotime($activity['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <p>No recent activity</p>
                    <p class="sub">Start dispensing prescriptions or making OTC sales</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTION BUTTONS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-bolt" style="color: var(--warning);"></i>
                Quick Actions
            </h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="pending_prescriptions.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-clock text-2xl text-orange-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Pending Prescriptions</span>
            </a>
            <a href="dispensing.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-prescription text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Dispensing</span>
            </a>
            <a href="new_otc_sale.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-plus-circle text-2xl text-green-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">New OTC Sale</span>
            </a>
            <a href="inventory.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-warehouse text-2xl text-purple-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Inventory</span>
            </a>
            <a href="prescription_history.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-history text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Sales History</span>
            </a>
            <a href="reports.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-chart-bar text-2xl text-teal-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Reports</span>
            </a>
            <a href="low_stock.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-exclamation-triangle text-2xl text-red-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Low Stock</span>
            </a>
            <a href="profile.php" class="text-center p-3 border rounded-lg hover:bg-primary-light transition-colors">
                <i class="fas fa-user-circle text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">My Profile</span>
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
            Pharmacy Dashboard
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
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

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            window.location.href = 'dashboard.php';
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
            e.preventDefault();
            window.location.href = 'inventory.php';
        }
    });

    console.log('%c💊 Braick - Pharmacy Dashboard (FULLY FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔑 Admin: <?= $is_admin ? 'YES' : 'NO' ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📦 Total Stock: <?= $total_stock ?> | Low Stock: <?= $low_stock_count ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📅 Expiring Soon: <?= $expiring_soon ?> (RED CARD)', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ All count() errors fixed with is_array() checks', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>