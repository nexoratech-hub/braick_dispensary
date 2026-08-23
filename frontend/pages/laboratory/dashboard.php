<?php
// ================================================================
// FILE: frontend/pages/laboratory/dashboard.php
// LABORATORY DASHBOARD - UPDATED FOR NEW DATABASE
// USING TABLES: lab_tests, lab_tests_catalog, prescriptions, visits
// BRAICK DISPENSARY - dispensary_db
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT LABORATORY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'laboratory';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET STATISTICS - USING NEW DATABASE TABLES
// ================================================================
$today = date('Y-m-d');
$start_of_month = date('Y-m-01');

// ================================================================
// 1. Pending Lab Tests (from lab_tests table)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'pending'
");
$stmt->execute([$user_branch_id]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 2. In Progress Lab Tests
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'in_progress'
");
$stmt->execute([$user_branch_id]);
$in_progress = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 3. Completed Today
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 4. Today's Tests (completed today)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 5. Total Tests (All Time)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ?
");
$stmt->execute([$user_branch_id]);
$total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 6. Total Lab Tests Catalog Items
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests_catalog 
    WHERE branch_id = ? AND is_active = 1
");
$stmt->execute([$user_branch_id]);
$total_catalog = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 7. Completion Rate
// ================================================================
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM lab_tests 
    WHERE branch_id = ?
");
$stmt->execute([$user_branch_id]);
$rate_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_tests_all = $rate_data['total'] ?? 0;
$completed_tests = $rate_data['completed'] ?? 0;
$completion_rate = $total_tests_all > 0 ? round(($completed_tests / $total_tests_all) * 100, 1) : 0;

// ================================================================
// 8. Most Requested Tests (from lab_tests)
// ================================================================
$stmt = $db->prepare("
    SELECT test_name, COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed'
    GROUP BY test_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$most_requested = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 9. Recent Lab Tests (Last 10)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        lt.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        u.full_name as doctor_name,
        v.visit_number
    FROM lab_tests lt
    LEFT JOIN patients pat ON lt.patient_id = pat.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    WHERE lt.branch_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 10. Daily Tests Chart (Last 7 days)
// ================================================================
$daily_labels = [];
$daily_tests = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $daily_tests[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// ================================================================
// 11. Monthly Tests Chart (Last 6 months)
// ================================================================
$monthly_labels = [];
$monthly_tests = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_labels[] = $month;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $monthly_tests[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// ================================================================
// 12. Tests by Status
// ================================================================
$status_counts = [];
foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $status) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND status = ?
    ");
    $stmt->execute([$user_branch_id, $status]);
    $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<style>
    /* ================================================================
       LABORATORY DASHBOARD STYLES - PHARMACY STYLE
       ================================================================ */
    
    /* Stats Grid - 8 cards in 4 columns */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    /* ================================================================
       MODERN STAT CARDS - PHARMACY STYLE WITH FIXED HEIGHT
       ================================================================ */
    .stat-card {
        border-radius: 14px;
        padding: 18px 20px;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        color: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        text-decoration: none;
        display: block;
        min-height: 130px;
        height: 130px;
        border: none;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -30%;
        width: 180px;
        height: 180px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.6s ease;
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -20%;
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.6s ease;
    }
    
    .stat-card:hover::before {
        transform: scale(1.3) translate(-20px, -10px);
        background: rgba(255,255,255,0.1);
    }
    
    .stat-card:hover::after {
        transform: scale(1.4) translate(30px, 20px);
        background: rgba(255,255,255,0.07);
    }
    
    .stat-card:hover {
        transform: translateY(-6px) scale(1.01);
        box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    
    .stat-card:active {
        transform: scale(0.97);
    }
    
    .stat-card .shine {
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
        transition: all 0.8s ease;
        pointer-events: none;
    }
    
    .stat-card:hover .shine {
        left: 100%;
    }
    
    .stat-card .stat-content {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
        justify-content: space-between;
    }
    
    .stat-card .stat-icon {
        font-size: 1.4rem;
        opacity: 0.9;
        display: block;
    }
    
    .stat-card .stat-number {
        font-size: 1.9rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .stat-card .stat-label {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card .stat-sub {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.6);
        margin-top: 2px;
        font-weight: 400;
    }
    
    .stat-card .stat-update {
        font-size: 0.55rem;
        color: rgba(255,255,255,0.5);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-card .stat-update .live-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    
    .stat-card .stat-arrow {
        position: absolute;
        bottom: 12px;
        right: 16px;
        font-size: 0.7rem;
        color: rgba(255,255,255,0.4);
        transition: all 0.3s ease;
        z-index: 2;
    }
    
    .stat-card:hover .stat-arrow {
        transform: translateX(4px);
        color: rgba(255,255,255,0.8);
    }
    
    .stat-card .dot-pattern {
        position: absolute;
        bottom: 8px;
        right: 12px;
        display: flex;
        gap: 4px;
        z-index: 0;
    }
    
    .stat-card .dot-pattern span {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: rgba(255,255,255,0.12);
    }
    
    /* Card Colors - Gradient Themes */
    .stat-card.orange { 
        background: linear-gradient(135deg, #F59E0B 0%, #D97706 50%, #B45309 100%);
        box-shadow: 0 4px 20px rgba(217, 119, 6, 0.3);
    }
    
    .stat-card.blue { 
        background: linear-gradient(135deg, #3B82F6 0%, #0B5ED7 50%, #0A4CA8 100%);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
    }
    
    .stat-card.green { 
        background: linear-gradient(135deg, #34D399 0%, #059669 50%, #047857 100%);
        box-shadow: 0 4px 20px rgba(5, 150, 105, 0.3);
    }
    
    .stat-card.purple { 
        background: linear-gradient(135deg, #A78BFA 0%, #7C3AED 50%, #6D28D9 100%);
        box-shadow: 0 4px 20px rgba(124, 58, 237, 0.3);
    }
    
    .stat-card.teal { 
        background: linear-gradient(135deg, #2DD4BF 0%, #0D9488 50%, #0F766E 100%);
        box-shadow: 0 4px 20px rgba(13, 148, 136, 0.3);
    }
    
    .stat-card.pink { 
        background: linear-gradient(135deg, #F472B6 0%, #DB2777 50%, #BE185D 100%);
        box-shadow: 0 4px 20px rgba(219, 39, 119, 0.3);
    }
    
    .stat-card.indigo { 
        background: linear-gradient(135deg, #818CF8 0%, #4F46E5 50%, #4338CA 100%);
        box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
    }
    
    .stat-card.rose { 
        background: linear-gradient(135deg, #FB7185 0%, #E11D48 50%, #BE123C 100%);
        box-shadow: 0 4px 20px rgba(225, 29, 72, 0.3);
    }
    
    /* ================================================================
       CARDS
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
        box-shadow: var(--shadow-sm);
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
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
    
    .card-title .title-blue { color: var(--primary); }
    .card-title .title-green { color: var(--success); }
    .card-title .title-purple { color: var(--purple); }
    .card-title .title-orange { color: #D97706; }
    .card-title .title-red { color: var(--danger); }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .status-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
    
    /* ================================================================
       SCROLL CONTAINER
       ================================================================ */
    .scroll-container {
        max-height: 300px;
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
    
    /* ================================================================
       MOST REQUESTED ITEMS
       ================================================================ */
    .most-requested-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .most-requested-item:last-child {
        border-bottom: none;
    }
    
    .most-requested-item .rank {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-secondary);
        min-width: 28px;
    }
    
    .most-requested-item .test-name {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
        flex: 1;
    }
    
    .most-requested-item .count {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary);
        background: var(--primary-bg);
        padding: 2px 12px;
        border-radius: 20px;
    }
    
    /* ================================================================
       RECENT TESTS TABLE
       ================================================================ */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        background: var(--bg-body);
        border-bottom: 2px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .data-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border-color);
    }
    
    .data-table tbody tr:hover {
        background: var(--primary-bg);
    }
    
    .data-table tbody td {
        padding: 8px 12px;
        vertical-align: middle;
        color: var(--text-primary);
    }
    
    /* ================================================================
       QUICK ACTIONS
       ================================================================ */
    .quick-action {
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: block;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }
    
    .quick-action:hover {
        transform: translateY(-3px);
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .quick-action .icon {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 6px;
    }
    
    .quick-action .label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    /* ================================================================
       PAGE HEADER OVERRIDES
       ================================================================ */
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
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
    
    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .page-header .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .page-header .page-title i {
        font-size: 2rem;
        opacity: 0.9;
    }
    
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    
    .page-header .page-subtitle strong {
        color: white;
        font-weight: 600;
    }
    
    .role-badge-display {
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
    
    .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }
    
    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .update-badge-light {
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.8);
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        backdrop-filter: blur(4px);
    }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: var(--shadow-lg);
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-custom.success { background: var(--success); }
    .toast-custom.error { background: var(--danger); }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
    }
    
    @media (max-width: 992px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        .stat-card {
            min-height: 120px;
            height: 120px;
            padding: 14px 16px;
        }
        .stat-card .stat-number {
            font-size: 1.6rem;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .stat-card {
            min-height: 110px;
            height: 110px;
            padding: 12px 14px;
            border-radius: 12px;
        }
        .stat-card .stat-number {
            font-size: 1.4rem;
        }
        .stat-card .stat-label {
            font-size: 0.65rem;
        }
        .stat-card .stat-icon {
            font-size: 1.2rem;
        }
        .stat-card .stat-arrow {
            display: none;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table thead th,
        .data-table tbody td {
            padding: 4px 8px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            min-height: 100px;
            height: 100px;
            padding: 10px 12px;
            border-radius: 10px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .stat-card .stat-label {
            font-size: 0.55rem;
            letter-spacing: 0.02em;
        }
        .stat-card .stat-icon {
            font-size: 1rem;
        }
        .stat-card .stat-sub {
            font-size: 0.5rem;
        }
        .stat-card .stat-update {
            font-size: 0.5rem;
        }
        .quick-action {
            padding: 12px;
        }
        .quick-action .icon {
            font-size: 1.2rem;
        }
        .quick-action .label {
            font-size: 0.6rem;
        }
    }
    
    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Laboratory Dashboard
                <span class="role-badge-display">LABORATORY</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Welcome back, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_requests.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending (<span id="statPending"><?= $pending ?></span>)
            </a>
            <a href="in_progress_tests.php" class="btn-outline-light">
                <i class="fas fa-spinner"></i> In Progress (<span id="statInProgress"><?= $in_progress ?></span>)
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 8 CARDS WITH FIXED HEIGHT -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        
        <!-- 1. Pending Tests - Orange -->
        <a href="pending_requests.php" class="stat-card orange">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">⏳</span>
                    <div class="stat-number" id="statPendingCard"><?= $pending ?></div>
                    <div class="stat-label">Pending Tests</div>
                    <div class="stat-sub">Awaiting processing</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 2. In Progress - Blue -->
        <a href="in_progress_tests.php" class="stat-card blue">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">🔄</span>
                    <div class="stat-number" id="statInProgressCard"><?= $in_progress ?></div>
                    <div class="stat-label">In Progress</div>
                    <div class="stat-sub">Currently running</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 3. Completed Today - Green -->
        <a href="completed_tests.php" class="stat-card green">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">✅</span>
                    <div class="stat-number" id="statCompletedToday"><?= $completed_today ?></div>
                    <div class="stat-label">Completed Today</div>
                    <div class="stat-sub">Tests finished</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 4. Today's Tests - Purple -->
        <a href="results_history.php?filter=today" class="stat-card purple">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">🧪</span>
                    <div class="stat-number" id="statTodayTests"><?= $today_tests ?></div>
                    <div class="stat-label">Today's Tests</div>
                    <div class="stat-sub">Completed today</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 5. Total Tests - Teal -->
        <a href="results_history.php" class="stat-card teal">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">📊</span>
                    <div class="stat-number" id="statTotalTests"><?= number_format($total_tests) ?></div>
                    <div class="stat-label">Total Tests</div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 6. Test Catalog - Pink -->
        <a href="test_catalog.php" class="stat-card pink">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">📋</span>
                    <div class="stat-number" id="statTotalCatalog"><?= number_format($total_catalog) ?></div>
                    <div class="stat-label">Test Catalog</div>
                    <div class="stat-sub">Available tests</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 7. Completion Rate - Indigo -->
        <a href="completed_tests.php" class="stat-card indigo">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">📈</span>
                    <div class="stat-number" id="statCompletionRate"><?= $completion_rate ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                    <div class="stat-sub">Success rate</div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
        <!-- 8. Status Breakdown - Rose -->
        <a href="all_tests.php" class="stat-card rose">
            <div class="shine"></div>
            <div class="stat-content">
                <div>
                    <span class="stat-icon">📐</span>
                    <div class="stat-number" id="statStatusCount">
                        <?= $status_counts['pending'] + $status_counts['in_progress'] + $status_counts['completed'] ?>
                    </div>
                    <div class="stat-label">Active Tests</div>
                    <div class="stat-sub" id="statStatusSub">
                        P:<?= $status_counts['pending'] ?> | IP:<?= $status_counts['in_progress'] ?> | C:<?= $status_counts['completed'] ?>
                    </div>
                </div>
                <div class="stat-update"><span class="live-dot"></span> Live</div>
            </div>
            <span class="stat-arrow"><i class="fas fa-chevron-right"></i></span>
            <div class="dot-pattern">
                <span></span><span></span><span></span>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- MOST REQUESTED TESTS & RECENT TESTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Most Requested Tests -->
        <div class="card animate-fade-in-up lg:col-span-1">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-trophy title-purple mr-2"></i>
                    Most Requested Tests
                </h3>
            </div>
            <div id="mostRequested">
                <?php if (count($most_requested) > 0): ?>
                    <?php foreach ($most_requested as $index => $test): ?>
                        <div class="most-requested-item">
                            <span class="rank">#<?= $index + 1 ?></span>
                            <span class="test-name"><?= htmlspecialchars($test['test_name']) ?></span>
                            <span class="count"><?= $test['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-flask text-2xl block mb-2"></i>
                        <p>No tests completed yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Tests -->
        <div class="card animate-fade-in-up lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history title-blue mr-2"></i>
                    Recent Tests
                    <span class="text-sm font-normal text-gray-400">(Last 10)</span>
                </h3>
                <a href="all_tests.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="recentTableBody">
                        <?php if (count($recent_tests) > 0): ?>
                            <?php $counter = 1; foreach ($recent_tests as $test): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <span class="font-medium text-sm"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <div class="font-medium text-sm"><?= htmlspecialchars($test['patient_name'] ?? 'Unknown') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                        </span>
                                    </td>
                                    <td class="text-sm"><?= isset($test['created_at']) ? date('M d, Y', strtotime($test['created_at'])) : 'N/A' ?></td>
                                    <td>
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-flask text-3xl block mb-2"></i>
                                    <p>No laboratory tests yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
        <a href="pending_requests.php" class="quick-action">
            <span class="icon" style="color:#D97706;">⏳</span>
            <span class="label">Pending Tests</span>
        </a>
        
        <a href="in_progress_tests.php" class="quick-action">
            <span class="icon" style="color:#0B5ED7;">🔄</span>
            <span class="label">In Progress</span>
        </a>
        
        <a href="completed_tests.php" class="quick-action">
            <span class="icon" style="color:#059669;">✅</span>
            <span class="label">Completed</span>
        </a>
        
        <a href="test_catalog.php" class="quick-action">
            <span class="icon" style="color:#7C3AED;">📋</span>
            <span class="label">Test Catalog</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Laboratory Dashboard
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">● Live</span>
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
        }, 3500);
    }

    console.log('%c🧪 Braick - Laboratory Dashboard', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Pending: <?= $pending ?> | In Progress: <?= $in_progress ?> | Completed Today: <?= $completed_today ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🧪 Today Tests: <?= $today_tests ?> | Total Tests: <?= $total_tests ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Test Catalog: <?= $total_catalog ?> | Completion Rate: <?= $completion_rate ?>%', 'font-size:13px; color:#7C3AED;');
    console.log('%c🎨 Cards have fixed height (130px) with gradient backgrounds', 'font-size:13px; color:#D97706;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>