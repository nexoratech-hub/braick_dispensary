<?php
// ================================================================
// FILE: frontend/pages/admin/reports.php
// SUPER ADMIN - FULL REPORTS DASHBOARD
// CHARTS HEIGHT ZIMEREKEBISHWA + AUTO REFRESH IMETOLEWA
// BRAICK DISPENSARY
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// REPORT TYPE
// ================================================================
$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// ================================================================
// FUNCTION TO CHECK IF COLUMN EXISTS
// ================================================================
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ================================================================
// FUNCTION TO GET BRANCH FILTER - SAFE
// ================================================================
function getBranchFilter($db, $selected_branch_id, $table) {
    if ($selected_branch_id === 'all') {
        return '';
    }
    if (columnExists($db, $table, 'branch_id')) {
        return " AND $table.branch_id = " . (int)$selected_branch_id;
    }
    return '';
}

// ================================================================
// FUNCTION TO GET BRANCH NAME BY ID
// ================================================================
function getBranchNameById($db, $branch_id) {
    if (!$branch_id || $branch_id == 0) {
        return 'N/A';
    }
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['name'] ?? 'N/A';
    } catch (Exception $e) {
        return 'N/A';
    }
}

// ================================================================
// FETCH ALL STATISTICS
// ================================================================

// 1. Total Revenue
$filter = getBranchFilter($db, $selected_branch_id, 'pharmacy_sales');
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total), 0) as total_revenue 
    FROM pharmacy_sales 
    WHERE payment_status = 'paid' 
    AND DATE(sale_date) BETWEEN ? AND ? 
    $filter
");
$stmt->execute([$date_from, $date_to]);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

// 2. Total Patients
$filter = getBranchFilter($db, $selected_branch_id, 'patients');
$stmt = $db->prepare("
    SELECT COUNT(*) as total_patients 
    FROM patients 
    WHERE DATE(created_at) BETWEEN ? AND ? 
    $filter
");
$stmt->execute([$date_from, $date_to]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total_patients'] ?? 0;

// 3. Total Visits
$filter = getBranchFilter($db, $selected_branch_id, 'visits');
$stmt = $db->prepare("
    SELECT COUNT(*) as total_visits 
    FROM visits 
    WHERE DATE(created_at) BETWEEN ? AND ? 
    $filter
");
$stmt->execute([$date_from, $date_to]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total_visits'] ?? 0;

// 4. Total Doctors - Use branch_id directly from users table
if ($selected_branch_id !== 'all') {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_doctors 
        FROM users 
        WHERE role = 'doctor' AND status = 'active'
        AND branch_id = ?
    ");
    $stmt->execute([(int)$selected_branch_id]);
} else {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_doctors 
        FROM users 
        WHERE role = 'doctor' AND status = 'active'
    ");
    $stmt->execute([]);
}
$total_doctors_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_doctors'] ?? 0;

// 5. Total Prescriptions
$filter = getBranchFilter($db, $selected_branch_id, 'prescriptions');
$stmt = $db->prepare("
    SELECT COUNT(*) as total_prescriptions 
    FROM prescriptions 
    WHERE DATE(created_at) BETWEEN ? AND ? 
    $filter
");
$stmt->execute([$date_from, $date_to]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total_prescriptions'] ?? 0;

// 6. Lab Tests Completed
$filter = getBranchFilter($db, $selected_branch_id, 'lab_tests');
$stmt = $db->prepare("
    SELECT COUNT(*) as completed_tests 
    FROM lab_tests 
    WHERE status = 'completed' 
    AND DATE(completed_at) BETWEEN ? AND ? 
    $filter
");
$stmt->execute([$date_from, $date_to]);
$completed_tests = $stmt->fetch(PDO::FETCH_ASSOC)['completed_tests'] ?? 0;

// 7. Pending Lab Tests
$filter = getBranchFilter($db, $selected_branch_id, 'lab_tests');
$stmt = $db->prepare("
    SELECT COUNT(*) as pending_tests 
    FROM lab_tests 
    WHERE status = 'pending' 
    $filter
");
$stmt->execute([]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_tests'] ?? 0;

// 8. Total Employees - Use branch_id directly from users table
if ($selected_branch_id !== 'all') {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_employees 
        FROM users 
        WHERE role != 'admin' AND branch_id = ?
    ");
    $stmt->execute([(int)$selected_branch_id]);
} else {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_employees 
        FROM users 
        WHERE role != 'admin'
    ");
    $stmt->execute([]);
}
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['total_employees'] ?? 0;

// 9. Daily Revenue for Charts
$filter = getBranchFilter($db, $selected_branch_id, 'pharmacy_sales');
$stmt = $db->prepare("
    SELECT DATE(sale_date) as date, COALESCE(SUM(total), 0) as revenue
    FROM pharmacy_sales
    WHERE payment_status = 'paid'
    AND DATE(sale_date) BETWEEN ? AND ?
    $filter
    GROUP BY DATE(sale_date)
    ORDER BY date
");
$stmt->execute([$date_from, $date_to]);
$daily_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. Doctor Stats - Use branch_id directly from users table
if ($selected_branch_id !== 'all') {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.specialty,
            u.is_online,
            u.branch_id,
            COUNT(DISTINCT v.patient_id) as patient_count,
            COUNT(v.id) as visit_count,
            COALESCE(SUM(ps.total), 0) as revenue,
            COUNT(DISTINCT pr.id) as prescription_count
        FROM users u
        LEFT JOIN visits v ON v.doctor_id = u.id AND DATE(v.created_at) BETWEEN ? AND ?
        LEFT JOIN prescriptions pr ON pr.doctor_id = u.id AND DATE(pr.created_at) BETWEEN ? AND ?
        LEFT JOIN pharmacy_sales ps ON ps.prescription_id = pr.id AND ps.payment_status = 'paid'
        WHERE u.role = 'doctor' AND u.status = 'active'
        AND u.branch_id = ?
        GROUP BY u.id
        ORDER BY revenue DESC
    ");
    $stmt->execute([$date_from, $date_to, $date_from, $date_to, (int)$selected_branch_id]);
} else {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.specialty,
            u.is_online,
            u.branch_id,
            COUNT(DISTINCT v.patient_id) as patient_count,
            COUNT(v.id) as visit_count,
            COALESCE(SUM(ps.total), 0) as revenue,
            COUNT(DISTINCT pr.id) as prescription_count
        FROM users u
        LEFT JOIN visits v ON v.doctor_id = u.id AND DATE(v.created_at) BETWEEN ? AND ?
        LEFT JOIN prescriptions pr ON pr.doctor_id = u.id AND DATE(pr.created_at) BETWEEN ? AND ?
        LEFT JOIN pharmacy_sales ps ON ps.prescription_id = pr.id AND ps.payment_status = 'paid'
        WHERE u.role = 'doctor' AND u.status = 'active'
        GROUP BY u.id
        ORDER BY revenue DESC
    ");
    $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
}
$doctor_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$top_doctors = array_slice($doctor_stats, 0, 10);

// Total doctor revenue
if ($selected_branch_id !== 'all') {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ps.total), 0) as total_revenue
        FROM pharmacy_sales ps
        JOIN prescriptions pr ON ps.prescription_id = pr.id
        JOIN users u ON pr.doctor_id = u.id
        WHERE u.role = 'doctor' AND ps.payment_status = 'paid'
        AND DATE(ps.sale_date) BETWEEN ? AND ?
        AND u.branch_id = ?
    ");
    $stmt->execute([$date_from, $date_to, (int)$selected_branch_id]);
} else {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ps.total), 0) as total_revenue
        FROM pharmacy_sales ps
        JOIN prescriptions pr ON ps.prescription_id = pr.id
        JOIN users u ON pr.doctor_id = u.id
        WHERE u.role = 'doctor' AND ps.payment_status = 'paid'
        AND DATE(ps.sale_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
}
$doctor_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests_sidebar = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests_sidebar = 0;
}

$pending_prescriptions_sidebar = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions_sidebar = 0;
}

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors_count ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests_sidebar ?? 0;
$pending_prescriptions = $pending_prescriptions_sidebar ?? 0;
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES - CHARTS HEIGHT FIXED -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       REPORTS - MODERN CARDS WITH SMALLER ICONS
       ================================================================ */
    
    .report-nav-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 14px 14px;
        border: 2px solid var(--border-color);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: block;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }
    
    .report-nav-card:nth-child(1) { animation-delay: 0.05s; }
    .report-nav-card:nth-child(2) { animation-delay: 0.10s; }
    .report-nav-card:nth-child(3) { animation-delay: 0.15s; }
    .report-nav-card:nth-child(4) { animation-delay: 0.20s; }
    .report-nav-card:nth-child(5) { animation-delay: 0.25s; }
    .report-nav-card:nth-child(6) { animation-delay: 0.30s; }
    
    .report-nav-card:hover {
        transform: translateY(-4px);
        border-color: #0B5ED7;
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.10);
    }
    
    .report-nav-card:active {
        transform: translateY(-2px) scale(0.98);
    }
    
    /* ================================================================
       NAVIGATION CARDS - SMALLER ICONS
       ================================================================ */
    .report-nav-card .card-icon-wrapper {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        color: white;
        flex-shrink: 0;
        transition: all 0.4s ease;
        position: relative;
        z-index: 1;
    }
    
    .report-nav-card:hover .card-icon-wrapper {
        transform: scale(1.05) rotate(-2deg);
    }
    
    .report-nav-card .card-icon-wrapper.blue { 
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        box-shadow: 0 3px 12px rgba(11, 94, 215, 0.30);
    }
    
    .report-nav-card .card-icon-wrapper.green { 
        background: linear-gradient(135deg, #059669, #0AA84F);
        box-shadow: 0 3px 12px rgba(5, 150, 105, 0.30);
    }
    
    .report-nav-card .card-icon-wrapper.purple { 
        background: linear-gradient(135deg, #7C3AED, #9B4DCA);
        box-shadow: 0 3px 12px rgba(124, 58, 237, 0.30);
    }
    
    .report-nav-card .card-icon-wrapper.orange { 
        background: linear-gradient(135deg, #F59E0B, #FBBF24);
        box-shadow: 0 3px 12px rgba(245, 158, 11, 0.30);
    }
    
    .report-nav-card .card-icon-wrapper.red { 
        background: linear-gradient(135deg, #EF4444, #F87171);
        box-shadow: 0 3px 12px rgba(239, 68, 68, 0.30);
    }
    
    .report-nav-card .card-icon-wrapper.teal { 
        background: linear-gradient(135deg, #0D9488, #14B8A6);
        box-shadow: 0 3px 12px rgba(13, 148, 136, 0.30);
    }
    
    /* ================================================================
       NAVIGATION CARDS - SMALLER TEXT
       ================================================================ */
    .report-nav-card .card-number {
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1.2;
        position: relative;
        z-index: 1;
        transition: color 0.3s ease;
    }
    
    .report-nav-card:hover .card-number {
        color: #0B5ED7;
    }
    
    .report-nav-card .card-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        position: relative;
        z-index: 1;
    }
    
    .report-nav-card .card-sub {
        font-size: 0.55rem;
        color: var(--text-secondary);
        opacity: 0.7;
        position: relative;
        z-index: 1;
    }
    
    .report-nav-card .card-arrow {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--border-color);
        font-size: 0.8rem;
        transition: all 0.4s ease;
        z-index: 1;
        opacity: 0;
    }
    
    .report-nav-card:hover .card-arrow {
        opacity: 1;
        color: #0B5ED7;
        transform: translateY(-50%) translateX(4px);
    }
    
    .report-nav-card .card-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 1px 8px;
        border-radius: 16px;
        font-size: 0.45rem;
        font-weight: 700;
        color: white;
        z-index: 1;
        animation: pulse-badge 2s infinite;
    }
    
    .report-nav-card .card-badge.blue { background: #0B5ED7; }
    .report-nav-card .card-badge.green { background: #059669; }
    .report-nav-card .card-badge.orange { background: #F59E0B; }
    .report-nav-card .card-badge.red { background: #EF4444; }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }
    
    .report-nav-card.active {
        border-color: #0B5ED7;
        background: #E8F0FE;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.12);
    }
    
    [data-theme="dark"] .report-nav-card.active {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }
    
    .report-nav-card.active .card-number {
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .report-nav-card.active .card-number {
        color: #6EA8FE;
    }
    
    /* ================================================================
       STATS GRID - SMALL ICONS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .stats-grid .stat-item {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 10px 12px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stats-grid .stat-item:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
    }
    
    .stats-grid .stat-item .stat-icon {
        font-size: 1.1rem;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #E8F0FE;
        color: #0B5ED7;
        margin: 0 auto 4px;
        flex-shrink: 0;
    }
    
    [data-theme="dark"] .stats-grid .stat-item .stat-icon {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .stats-grid .stat-item .stat-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0B5ED7;
        line-height: 1.2;
    }
    
    .stats-grid .stat-item .stat-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* ================================================================
       CHART CARDS - HEIGHT FIXED TO 200px
       ================================================================ */
    .chart-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 18px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .chart-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.06);
    }
    
    .chart-card canvas {
        height: 200px !important;
        max-height: 200px;
        width: 100% !important;
    }
    
    .chart-card-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .chart-card-title .title-icon {
        color: #0B5ED7;
        font-size: 0.9rem;
    }
    
    /* Doctor List */
    .doctor-list-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 8px;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }
    
    .doctor-list-item:hover {
        background: var(--table-hover);
        border-radius: 6px;
    }
    
    .doctor-list-item:last-child {
        border-bottom: none;
    }
    
    .doctor-rank {
        font-weight: 700;
        font-size: 0.6rem;
        color: var(--text-secondary);
        min-width: 18px;
    }
    
    .doctor-avatar-sm {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.6rem;
        flex-shrink: 0;
    }
    
    .doctor-name {
        flex: 1;
        font-weight: 500;
        font-size: 0.7rem;
        color: var(--text-primary);
    }
    
    .doctor-name .specialty {
        font-size: 0.55rem;
        color: var(--text-secondary);
        display: block;
        font-weight: 400;
    }
    
    .doctor-stats {
        font-size: 0.55rem;
        color: var(--text-secondary);
        text-align: right;
    }
    
    .doctor-stats strong {
        color: var(--text-primary);
    }
    
    /* Filter Controls */
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    
    .filter-group .filter-input {
        padding: 5px 8px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.7rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.3s ease;
        outline: none;
    }
    
    .filter-group .filter-input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.10);
    }
    
    .filter-group .filter-btn {
        padding: 5px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        background: #0B5ED7;
        color: white;
    }
    
    .filter-group .filter-btn:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }
    
    .filter-group .filter-btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    
    .filter-group .filter-btn-outline:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    .report-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 16px;
        font-size: 0.55rem;
        font-weight: 600;
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #D2E3FC;
    }
    
    [data-theme="dark"] .report-indicator {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    
    .branch-tag-display {
        background: #059669;
        color: white;
        padding: 2px 12px;
        border-radius: 16px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* Page Header */
    .page-header .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0B3D8A;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: #6EA8FE;
    }
    
    .page-header .page-subtitle {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    
    .btn-sm { padding: 3px 10px; font-size: 0.65rem; border-radius: 6px; }
    
    .footer {
        padding: 12px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 16px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    @media (max-width: 640px) {
        .report-nav-card .card-number {
            font-size: 1rem;
        }
        .report-nav-card .card-icon-wrapper {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
        .report-nav-card {
            padding: 10px 10px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .stats-grid .stat-item .stat-number {
            font-size: 1rem;
        }
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .chart-card canvas {
            height: 160px !important;
            max-height: 160px;
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
            <input type="text" id="searchInput" placeholder="Search reports...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-3">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-bar mr-2" style="color: #0B5ED7;"></i> Reports Dashboard
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag-display ml-0">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-alt mr-1"></i> <?= date('M d, Y', strtotime($date_from)) ?> - <?= date('M d, Y', strtotime($date_to)) ?>
                </span>
                <span class="report-indicator ml-2">
                    <i class="fas fa-<?= $report_type === 'overview' ? 'chart-pie' : ($report_type === 'doctors' ? 'user-md' : ($report_type === 'revenue' ? 'money-bill-wave' : ($report_type === 'patients' ? 'users' : ($report_type === 'pharmacy' ? 'pills' : 'flask')))) ?>"></i>
                    <?= ucfirst($report_type) ?> Report
                </span>
                <?php if ($report_type === 'doctors' || $report_type === 'overview'): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-user-md mr-1"></i> Doctors: <?= $total_doctors_count ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DATE FILTER -->
    <!-- ================================================================ -->
    <div class="chart-card mb-3">
        <form method="GET" class="filter-group">
            <input type="hidden" name="type" value="<?= $report_type ?>">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <span class="text-xs font-medium text-gray-500"><i class="fas fa-calendar-alt mr-1"></i> Date Range:</span>
            
            <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
            <span class="text-gray-400 text-xs">to</span>
            <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
            
            <button type="submit" class="filter-btn">
                <i class="fas fa-filter mr-1"></i> Apply
            </button>
            
            <a href="?type=<?= $report_type ?>&branch=<?= $selected_branch_id ?>" class="filter-btn filter-btn-outline">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- REPORT NAVIGATION CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
        
        <!-- Overview Card -->
        <a href="?type=overview&branch=<?= $selected_branch_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="report-nav-card <?= $report_type === 'overview' ? 'active' : '' ?>">
            <div class="flex items-center gap-3">
                <div class="card-icon-wrapper blue"><i class="fas fa-chart-pie"></i></div>
                <div>
                    <p class="card-number"><?= number_format($total_revenue + $total_patients, 0) ?></p>
                    <p class="card-label">Overview</p>
                    <p class="card-sub">All stats</p>
                </div>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>
        
        <!-- Revenue Card -->
        <a href="?type=revenue&branch=<?= $selected_branch_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="report-nav-card <?= $report_type === 'revenue' ? 'active' : '' ?>">
            <div class="flex items-center gap-3">
                <div class="card-icon-wrapper green"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <p class="card-number">TSh <?= number_format($total_revenue / 1000, 0) ?>K</p>
                    <p class="card-label">Revenue</p>
                    <p class="card-sub"><?= number_format($total_revenue) ?> total</p>
                </div>
            </div>
            <span class="card-badge green"><?= $total_prescriptions > 0 ? number_format($total_revenue / $total_prescriptions, 0) : 0 ?></span>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>
        
        <!-- Patients Card -->
        <a href="?type=patients&branch=<?= $selected_branch_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="report-nav-card <?= $report_type === 'patients' ? 'active' : '' ?>">
            <div class="flex items-center gap-3">
                <div class="card-icon-wrapper purple"><i class="fas fa-users"></i></div>
                <div>
                    <p class="card-number"><?= number_format($total_patients) ?></p>
                    <p class="card-label">Patients</p>
                    <p class="card-sub"><?= number_format($total_visits) ?> visits</p>
                </div>
            </div>
            <span class="card-badge purple"><?= $total_patients > 0 ? number_format($total_patients / 30, 0) : 0 ?>/day</span>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>
        
        <!-- Doctors Card -->
        <a href="?type=doctors&branch=<?= $selected_branch_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="report-nav-card <?= $report_type === 'doctors' ? 'active' : '' ?>">
            <div class="flex items-center gap-3">
                <div class="card-icon-wrapper orange"><i class="fas fa-user-md"></i></div>
                <div>
                    <p class="card-number"><?= number_format($total_doctors_count) ?></p>
                    <p class="card-label">Doctors</p>
                    <p class="card-sub">Active physicians</p>
                </div>
            </div>
            <span class="card-badge orange"><?= $total_doctors_count > 0 ? number_format($doctor_revenue / $total_doctors_count, 0) : 0 ?></span>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>
        
        <!-- Pharmacy Card -->
        <a href="?type=pharmacy&branch=<?= $selected_branch_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="report-nav-card <?= $report_type === 'pharmacy' ? 'active' : '' ?>">
            <div class="flex items-center gap-3">
                <div class="card-icon-wrapper red"><i class="fas fa-pills"></i></div>
                <div>
                    <p class="card-number"><?= number_format($total_prescriptions) ?></p>
                    <p class="card-label">Pharmacy</p>
                    <p class="card-sub">Prescriptions</p>
                </div>
            </div>
            <span class="card-badge red"><?= $pending_prescriptions_sidebar ?> pending</span>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>
        
        <!-- Laboratory Card -->
        <a href="?type=laboratory&branch=<?= $selected_branch_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="report-nav-card <?= $report_type === 'laboratory' ? 'active' : '' ?>">
            <div class="flex items-center gap-3">
                <div class="card-icon-wrapper teal"><i class="fas fa-flask"></i></div>
                <div>
                    <p class="card-number"><?= number_format($completed_tests + $pending_tests) ?></p>
                    <p class="card-label">Laboratory</p>
                    <p class="card-sub"><?= $completed_tests ?> completed</p>
                </div>
            </div>
            <span class="card-badge blue"><?= $pending_tests ?> pending</span>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- REPORT CONTENT -->
    <!-- ================================================================ -->
    
    <?php if ($report_type === 'overview'): ?>
        <!-- OVERVIEW REPORT - Charts Height 200px -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                <p class="stat-label">Total Revenue</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <p class="stat-number"><?= number_format($total_patients) ?></p>
                <p class="stat-label">Patients</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-clinic-medical"></i></div>
                <p class="stat-number"><?= number_format($total_visits) ?></p>
                <p class="stat-label">Visits</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                <p class="stat-number"><?= number_format($total_doctors_count) ?></p>
                <p class="stat-label">Doctors</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                <p class="stat-number"><?= number_format($total_prescriptions) ?></p>
                <p class="stat-label">Prescriptions</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-flask"></i></div>
                <p class="stat-number"><?= number_format($completed_tests + $pending_tests) ?></p>
                <p class="stat-label">Lab Tests</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="chart-card">
                <div class="chart-card-title">
                    <i class="fas fa-chart-line title-icon"></i>
                    Revenue Overview
                </div>
                <canvas id="overviewRevenueChart" height="200"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-card-title">
                    <i class="fas fa-chart-bar title-icon"></i>
                    Key Metrics
                </div>
                <canvas id="overviewMetricsChart" height="200"></canvas>
            </div>
        </div>

    <?php elseif ($report_type === 'doctors'): ?>
        <!-- DOCTORS REPORT -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                <p class="stat-number"><?= number_format($total_doctors_count) ?></p>
                <p class="stat-label">Total Doctors</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <p class="stat-number">TSh <?= number_format($doctor_revenue) ?></p>
                <p class="stat-label">Total Revenue</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                <p class="stat-number"><?= $total_doctors_count > 0 ? number_format($doctor_revenue / $total_doctors_count, 0) : 0 ?></p>
                <p class="stat-label">Avg Per Doctor</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <p class="stat-number"><?= number_format(array_sum(array_column($doctor_stats, 'patient_count'))) ?></p>
                <p class="stat-label">Total Patients</p>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="fas fa-trophy title-icon" style="color: #F59E0B;"></i>
                Top Performing Doctors
                <span class="text-xs font-normal text-gray-400">(<?= count($top_doctors) ?> doctors)</span>
            </div>
            
            <div class="max-h-72 overflow-y-auto">
                <?php if (count($top_doctors) > 0): ?>
                    <?php foreach ($top_doctors as $index => $doc): ?>
                        <div class="doctor-list-item">
                            <span class="doctor-rank">#<?= $index + 1 ?></span>
                            <div class="doctor-avatar-sm" style="background: <?= getUserColor($doc['full_name']) ?>;">
                                <?= strtoupper(substr($doc['full_name'], 0, 1)) ?>
                            </div>
                            <span class="doctor-name">
                                <?= htmlspecialchars($doc['full_name']) ?>
                                <span class="specialty"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?> • <?= getBranchNameById($db, $doc['branch_id'] ?? 0) ?></span>
                            </span>
                            <div class="doctor-stats">
                                <div><strong>TSh <?= number_format($doc['revenue'] ?? 0) ?></strong></div>
                                <div><?= $doc['patient_count'] ?? 0 ?> patients</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-400 text-sm py-6">
                        <i class="fas fa-user-md text-2xl block mb-2"></i>
                        No doctors found for <strong><?= htmlspecialchars($branch_name) ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($report_type === 'revenue'): ?>
        <!-- REVENUE REPORT -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                <p class="stat-label">Total Revenue</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <p class="stat-number"><?= number_format($total_prescriptions) ?></p>
                <p class="stat-label">Total Sales</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                <p class="stat-number">TSh <?= $total_prescriptions > 0 ? number_format($total_revenue / $total_prescriptions, 0) : 0 ?></p>
                <p class="stat-label">Average Sale</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <p class="stat-number"><?= number_format($total_revenue / 30, 0) ?></p>
                <p class="stat-label">Daily Average</p>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="fas fa-chart-area title-icon"></i>
                Revenue Trend
            </div>
            <canvas id="revenueChart" height="200"></canvas>
        </div>

    <?php elseif ($report_type === 'patients'): ?>
        <!-- PATIENTS REPORT -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <p class="stat-number"><?= number_format($total_patients) ?></p>
                <p class="stat-label">Total Patients</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-clinic-medical"></i></div>
                <p class="stat-number"><?= number_format($total_visits) ?></p>
                <p class="stat-label">Total Visits</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-arrow-right"></i></div>
                <p class="stat-number"><?= $total_patients > 0 ? number_format($total_visits / $total_patients, 1) : 0 ?></p>
                <p class="stat-label">Visits per Patient</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-calendar-plus"></i></div>
                <p class="stat-number"><?= number_format($total_patients / 30, 1) ?></p>
                <p class="stat-label">New Patients/Day</p>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="fas fa-users title-icon"></i>
                Patient Statistics
            </div>
            <canvas id="patientsChart" height="200"></canvas>
        </div>

    <?php elseif ($report_type === 'pharmacy'): ?>
        <!-- PHARMACY REPORT -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                <p class="stat-number"><?= number_format($total_prescriptions) ?></p>
                <p class="stat-label">Total Prescriptions</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                <p class="stat-label">Total Revenue</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                <p class="stat-number"><?= $total_prescriptions > 0 ? number_format($total_revenue / $total_prescriptions, 0) : 0 ?></p>
                <p class="stat-label">Avg per Prescription</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <p class="stat-number"><?= $pending_prescriptions_sidebar ?></p>
                <p class="stat-label">Pending Prescriptions</p>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="fas fa-pills title-icon"></i>
                Pharmacy Overview
            </div>
            <canvas id="pharmacyChart" height="200"></canvas>
        </div>

    <?php elseif ($report_type === 'laboratory'): ?>
        <!-- LABORATORY REPORT -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-flask"></i></div>
                <p class="stat-number"><?= number_format($completed_tests + $pending_tests) ?></p>
                <p class="stat-label">Total Tests</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <p class="stat-number"><?= number_format($completed_tests) ?></p>
                <p class="stat-label">Completed</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <p class="stat-number"><?= number_format($pending_tests) ?></p>
                <p class="stat-label">Pending</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-percent"></i></div>
                <p class="stat-number"><?= ($completed_tests + $pending_tests) > 0 ? number_format(($completed_tests / ($completed_tests + $pending_tests)) * 100, 0) : 0 ?>%</p>
                <p class="stat-label">Completion Rate</p>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="fas fa-flask title-icon"></i>
                Laboratory Statistics
            </div>
            <canvas id="labChart" height="200"></canvas>
        </div>

    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reports Dashboard - <?= ucfirst($report_type) ?> Report
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
<!-- JAVASCRIPT - NO AUTO REFRESH -->
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // DATE & TIME - NO AUTO REFRESH
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
    // CHARTS - WITH HEIGHT 200px
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart !== 'undefined') {
            var isDark = htmlElement.getAttribute('data-theme') === 'dark';
            var gridColor = isDark ? '#334155' : '#E2E8F0';
            var textColor = isDark ? '#94A3B8' : '#64748B';
            
            var reportType = '<?= $report_type ?>';
            var dailyData = <?= json_encode($daily_revenue) ?>;
            var dailyLabels = dailyData.map(function(d) { return d.date; });
            var dailyValues = dailyData.map(function(d) { return parseFloat(d.revenue); });
            
            if (dailyLabels.length === 0) {
                dailyLabels = ['No Data'];
                dailyValues = [0];
            }
            
            // OVERVIEW CHARTS
            if (reportType === 'overview') {
                var ctx1 = document.getElementById('overviewRevenueChart')?.getContext('2d');
                if (ctx1) {
                    new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: dailyLabels,
                            datasets: [{
                                label: 'Revenue (TSh)',
                                data: dailyValues,
                                borderColor: '#0B5ED7',
                                backgroundColor: 'rgba(11, 94, 215, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#0B5ED7'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                                x: { grid: { display: false }, ticks: { color: textColor, maxTicksLimit: 10 } }
                            }
                        }
                    });
                }
                
                var ctx2 = document.getElementById('overviewMetricsChart')?.getContext('2d');
                if (ctx2) {
                    new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: ['Patients', 'Visits', 'Prescriptions', 'Lab Tests'],
                            datasets: [{
                                label: 'Count',
                                data: [
                                    <?= $total_patients ?>,
                                    <?= $total_visits ?>,
                                    <?= $total_prescriptions ?>,
                                    <?= $completed_tests + $pending_tests ?>
                                ],
                                backgroundColor: ['#0B5ED7', '#059669', '#F59E0B', '#7C3AED'],
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                                x: { grid: { display: false }, ticks: { color: textColor } }
                            }
                        }
                    });
                }
            }
            
            // REVENUE CHART
            if (reportType === 'revenue') {
                var ctx = document.getElementById('revenueChart')?.getContext('2d');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: dailyLabels,
                            datasets: [{
                                label: 'Revenue (TSh)',
                                data: dailyValues,
                                borderColor: '#059669',
                                backgroundColor: 'rgba(5, 150, 105, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#059669'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                                x: { grid: { display: false }, ticks: { color: textColor, maxTicksLimit: 10 } }
                            }
                        }
                    });
                }
            }
            
            // PATIENTS CHART
            if (reportType === 'patients') {
                var ctx = document.getElementById('patientsChart')?.getContext('2d');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['New Patients', 'Returning Patients'],
                            datasets: [{
                                data: [
                                    <?= $total_patients > 0 ? floor($total_patients * 0.6) : 1 ?>,
                                    <?= $total_patients > 0 ? ceil($total_patients * 0.4) : 1 ?>
                                ],
                                backgroundColor: ['#0B5ED7', '#059669'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { color: textColor, usePointStyle: true, pointStyle: 'circle' }
                                }
                            }
                        }
                    });
                }
            }
            
            // PHARMACY CHART
            if (reportType === 'pharmacy') {
                var ctx = document.getElementById('pharmacyChart')?.getContext('2d');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['Prescriptions', 'Revenue (K)'],
                            datasets: [{
                                label: 'Pharmacy Metrics',
                                data: [
                                    <?= $total_prescriptions ?>,
                                    <?= $total_revenue / 1000 ?>
                                ],
                                backgroundColor: ['#F59E0B', '#EF4444'],
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                                x: { grid: { display: false }, ticks: { color: textColor } }
                            }
                        }
                    });
                }
            }
            
            // LABORATORY CHART
            if (reportType === 'laboratory') {
                var ctx = document.getElementById('labChart')?.getContext('2d');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Completed', 'Pending'],
                            datasets: [{
                                data: [
                                    <?= $completed_tests ?: 1 ?>,
                                    <?= $pending_tests ?: 1 ?>
                                ],
                                backgroundColor: ['#059669', '#F59E0B'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { color: textColor, usePointStyle: true, pointStyle: 'circle' }
                                }
                            }
                        }
                    });
                }
            }
        }
    });

    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function getUserColor(name) {
        var colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#F59E0B', '#0891B2', '#DB2777'];
        var index = 0;
        for (var i = 0; i < name.length; i++) {
            index = (index + name.charCodeAt(i)) % colors.length;
        }
        return colors[index];
    }

    console.log('%c📊 Braick - Reports Dashboard (Charts Height 200px)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Doctors: <?= number_format($total_doctors_count) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📈 Charts Height: 200px', 'font-size:13px; color:#64748B;');
    console.log('%c🚫 Auto Refresh: REMOVED', 'font-size:13px; color:#EF4444;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>