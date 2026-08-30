<?php
// ================================================================
// FILE: frontend/pages/admin/revenue.php
// SUPER ADMIN - REVENUE REPORT PAGE
// BRAICK DISPENSARY - USING EXISTING DATABASE TABLES
// WITH HEADER, SIDEBAR, CLOCK & DATE
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($selected_branch_id !== 'all' && !is_numeric($selected_branch_id)) {
    $selected_branch_id = 'all';
}

$branch_name_display = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name_display = $branch_data['name'];
    }
}

// ================================================================
// GET UNREAD NOTIFICATIONS
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
// BRANCH FILTER FOR QUERIES
// ================================================================
$branch_filter = "";
$branch_params = [];

if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

// ================================================================
// GET REVENUE DATA - BILLS TABLE (ALL PAID BILLS)
// ================================================================
$total_revenue = 0;
$total_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count FROM bills WHERE status = 'paid'";
    if (!empty($branch_filter)) {
        $sql .= $branch_filter;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_revenue = $data['total'] ?? 0;
    $total_bills_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $total_revenue = 0;
    $total_bills_count = 0;
}

// ================================================================
// GET REVENUE DATA - CONSULTATION FEES (from visits)
// ================================================================
$consultation_revenue = 0;
$consultation_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(consultation_fee), 0) as total, COUNT(*) as count FROM visits WHERE status = 'completed'";
    if (!empty($branch_filter)) {
        $sql .= $branch_filter;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $consultation_revenue = $data['total'] ?? 0;
    $consultation_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $consultation_revenue = 0;
    $consultation_count = 0;
}

// ================================================================
// GET REVENUE DATA - LAB TESTS (from lab_tests with price)
// ================================================================
$lab_revenue = 0;
$lab_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(test_price), 0) as total, COUNT(*) as count FROM lab_tests WHERE status = 'completed'";
    if (!empty($branch_filter)) {
        $sql .= $branch_filter;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $lab_revenue = $data['total'] ?? 0;
    $lab_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $lab_revenue = 0;
    $lab_count = 0;
}

// ================================================================
// GET REVENUE DATA - MEDICATIONS (from bill_items)
// ================================================================
$medication_revenue = 0;
$medication_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.total_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication' AND b.status = 'paid'
    ";
    if (!empty($branch_filter)) {
        $sql .= " AND b.branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $medication_revenue = $data['total'] ?? 0;
    $medication_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $medication_revenue = 0;
    $medication_count = 0;
}

// ================================================================
// GET REVENUE DATA - PROCEDURES (from bill_items)
// ================================================================
$procedure_revenue = 0;
$procedure_count = 0;
try {
    $sql = "
        SELECT COALESCE(SUM(bi.total_price), 0) as total, COUNT(DISTINCT bi.id) as count 
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'procedure' AND b.status = 'paid'
    ";
    if (!empty($branch_filter)) {
        $sql .= " AND b.branch_id = ?";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $procedure_revenue = $data['total'] ?? 0;
    $procedure_count = $data['count'] ?? 0;
} catch (Exception $e) {
    $procedure_revenue = 0;
    $procedure_count = 0;
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_revenue_all = $total_revenue + $consultation_revenue + $lab_revenue + $medication_revenue + $procedure_revenue;
$total_transactions = $total_bills_count + $consultation_count + $lab_count + $medication_count + $procedure_count;

// ================================================================
// GET MONTHLY REVENUE DATA (Last 12 months)
// ================================================================
$monthly_labels = [];
$monthly_bills = [];
$monthly_consultation = [];
$monthly_lab = [];
$monthly_medication = [];
$monthly_procedure = [];
$monthly_total = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $monthly_labels[] = $month_label;
    
    // Bills
    $sql = "
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM bills 
        WHERE status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ";
    $params = [$month];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bills_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_bills[] = (float)$bills_total;
    
    // Consultation
    $sql = "
        SELECT COALESCE(SUM(consultation_fee), 0) as total 
        FROM visits 
        WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ";
    $params = [$month];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $consult_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_consultation[] = (float)$consult_total;
    
    // Lab Tests
    $sql = "
        SELECT COALESCE(SUM(test_price), 0) as total 
        FROM lab_tests 
        WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ";
    $params = [$month];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lab_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_lab[] = (float)$lab_total;
    
    // Medications (from bill_items)
    $sql = "
        SELECT COALESCE(SUM(bi.total_price), 0) as total 
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication' AND b.status = 'paid' AND DATE_FORMAT(b.created_at, '%Y-%m') = ?
    ";
    $params = [$month];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND b.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $med_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_medication[] = (float)$med_total;
    
    // Procedures (from bill_items)
    $sql = "
        SELECT COALESCE(SUM(bi.total_price), 0) as total 
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'procedure' AND b.status = 'paid' AND DATE_FORMAT(b.created_at, '%Y-%m') = ?
    ";
    $params = [$month];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND b.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $proc_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $monthly_procedure[] = (float)$proc_total;
    
    $monthly_total[] = (float)($bills_total + $consult_total + $lab_total + $med_total + $proc_total);
}

// ================================================================
// GET DAILY REVENUE (Last 30 days)
// ================================================================
$daily_labels = [];
$daily_values = [];
$daily_bills = [];
$daily_consultation = [];
$daily_lab = [];
$daily_medication = [];
$daily_procedure = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    
    // Bills
    $sql = "
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM bills 
        WHERE status = 'paid' AND DATE(created_at) = ?
    ";
    $params = [$date];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bills_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_bills[] = (float)$bills_total;
    
    // Consultation
    $sql = "
        SELECT COALESCE(SUM(consultation_fee), 0) as total 
        FROM visits 
        WHERE status = 'completed' AND DATE(created_at) = ?
    ";
    $params = [$date];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $consult_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_consultation[] = (float)$consult_total;
    
    // Lab Tests
    $sql = "
        SELECT COALESCE(SUM(test_price), 0) as total 
        FROM lab_tests 
        WHERE status = 'completed' AND DATE(created_at) = ?
    ";
    $params = [$date];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lab_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_lab[] = (float)$lab_total;
    
    // Medications
    $sql = "
        SELECT COALESCE(SUM(bi.total_price), 0) as total 
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication' AND b.status = 'paid' AND DATE(b.created_at) = ?
    ";
    $params = [$date];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND b.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $med_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_medication[] = (float)$med_total;
    
    // Procedures
    $sql = "
        SELECT COALESCE(SUM(bi.total_price), 0) as total 
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'procedure' AND b.status = 'paid' AND DATE(b.created_at) = ?
    ";
    $params = [$date];
    if ($selected_branch_id !== 'all') {
        $sql .= " AND b.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $proc_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $daily_procedure[] = (float)$proc_total;
    
    $daily_values[] = (float)($bills_total + $consult_total + $lab_total + $med_total + $proc_total);
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE SPECIFIC STYLES - REVENUE PAGE -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ROOT VARIABLES - BLUE THEME
       ================================================================ */
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #3B82F6;
        --primary-bg: #EFF6FF;
        --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
        
        --success: #059669;
        --success-dark: #047857;
        --success-light: #34D399;
        --success-bg: #D1FAE5;
        
        --danger: #DC2626;
        --danger-dark: #B91C1C;
        --danger-light: #F87171;
        --danger-bg: #FEE2E2;
        
        --warning: #D97706;
        --warning-bg: #FEF3C7;
        
        --purple: #7C3AED;
        --purple-bg: #EDE9FE;
        
        --teal: #0D9488;
        --teal-bg: #ECFDF5;
        
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
        
        --bg-body: #F0F4F8;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --radius: 12px;
        --radius-lg: 18px;
        --table-hover: #F8FAFC;
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --primary: #3B82F6;
        --primary-dark: #2563EB;
        --primary-light: #60A5FA;
        --primary-bg: #1E3A5F;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        --shadow-xl: 0 20px 40px rgba(0,0,0,0.5);
        --table-hover: #1E293B;
    }
    
    /* ================================================================
       MAIN CONTENT - Override shared header margin
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        transition: background 0.3s ease;
    }
    
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    
    /* ================================================================
       PAGE HEADER - BLUE THEME
       ================================================================ */
    .page-header {
        background: var(--primary-gradient);
        border-radius: var(--radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
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
    
    .page-header .role-badge-display {
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
    
    .page-header .header-badge {
        background: rgba(255,255,255,0.12);
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
    
    .page-header .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--radius);
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
    
    .page-header .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    /* ================================================================
       STATS CARDS - REVENUE CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: var(--radius);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 90px;
        text-decoration: none;
        cursor: default;
        border: none;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
        position: relative;
        z-index: 1;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        margin: 0;
        line-height: 1.2;
        position: relative;
        z-index: 1;
    }
    
    .stat-card .stat-sub {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.6);
        margin-top: 2px;
        position: relative;
        z-index: 1;
    }
    
    /* Card Colors */
    .stat-card.card-total { background: var(--primary-gradient); }
    .stat-card.card-consultation { background: #059669; }
    .stat-card.card-lab { background: #7C3AED; }
    .stat-card.card-medication { background: #D97706; }
    .stat-card.card-procedure { background: #0D9488; }
    
    /* ================================================================
       FILTER BAR
       ================================================================ */
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        align-items: center;
        background: var(--bg-card);
        padding: 16px 20px;
        border-radius: var(--radius);
        border: 2px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        transition: background 0.3s ease, border-color 0.3s ease;
    }
    
    .filter-bar .filter-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--primary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .filter-bar select {
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 8px 14px;
        font-size: 0.8rem;
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s;
        min-width: 200px;
    }
    
    .filter-bar select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }
    
    .filter-bar .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary-gradient);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-gradient-hover);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
    }
    
    /* ================================================================
       CHART CARDS
       ================================================================ */
    .chart-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .chart-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 2px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }
    
    .chart-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--primary-light);
    }
    
    .chart-card .chart-header {
        padding: 14px 20px;
        border-bottom: 2px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .chart-card .chart-header .chart-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .chart-card .chart-header .chart-title i {
        color: var(--primary);
    }
    
    .chart-card .chart-header .chart-total {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .chart-card .chart-body {
        padding: 16px 20px;
        height: 220px;
        position: relative;
    }
    
    /* ================================================================
       REVENUE BREAKDOWN TABLE
       ================================================================ */
    .table-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 2px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
    }
    
    .table-card .table-header {
        padding: 14px 20px;
        background: var(--primary-gradient);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .table-card .table-header .title {
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .table-card .table-header .title i {
        margin-right: 8px;
    }
    
    .table-card .table-header .count {
        color: rgba(255,255,255,0.8);
        font-size: 0.75rem;
    }
    
    .table-card table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    
    .table-card table thead {
        background: var(--bg-body);
    }
    
    .table-card table th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border-color);
        white-space: nowrap;
    }
    
    .table-card table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .table-card table tr:hover td {
        background: var(--table-hover);
    }
    
    .table-card table tr:last-child td {
        border-bottom: none;
    }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
        transition: border-color 0.3s ease, color 0.3s ease;
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .chart-grid { grid-template-columns: 1fr; }
    }
    
    @media (max-width: 768px) {
        .page-header { padding: 16px 18px; }
        .page-header .page-title { font-size: 1.3rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select { width: 100%; min-width: unset; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .stats-grid { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start !important; }
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
        .search-wrapper, .page-header .btn-outline-light,
        .footer, #sidebarToggle, .filter-bar { display: none !important; }
        .main-content { margin: 0; padding: 20px; }
        .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .page-title, .page-subtitle, .role-badge-display, .header-badge {
            color: white !important;
        }
        .chart-card, .table-card { break-inside: avoid; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue_all, 0) ?> Total Revenue
                </span>
                <span class="header-badge" style="background:rgba(96,165,250,0.2);border-color:rgba(96,165,250,0.3);color:#60A5FA;">
                    <i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Transactions
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="stat-card card-total">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue_all, 0) ?></p>
                <p class="stat-sub">All sources combined</p>
            </div>
        </div>
        <div class="stat-card card-consultation">
            <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
            <div>
                <p class="stat-label">Consultation</p>
                <p class="stat-value">TSh <?= number_format($consultation_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($consultation_count) ?> consultations</p>
            </div>
        </div>
        <div class="stat-card card-lab">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label">Lab Tests</p>
                <p class="stat-value">TSh <?= number_format($lab_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($lab_count) ?> tests</p>
            </div>
        </div>
        <div class="stat-card card-medication">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div>
                <p class="stat-label">Medications</p>
                <p class="stat-value">TSh <?= number_format($medication_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($medication_count) ?> items</p>
            </div>
        </div>
        <div class="stat-card card-procedure">
            <div class="stat-icon"><i class="fas fa-syringe"></i></div>
            <div>
                <p class="stat-label">Procedures</p>
                <p class="stat-value">TSh <?= number_format($procedure_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($procedure_count) ?> procedures</p>
            </div>
        </div>
        <div class="stat-card" style="background: #0891B2;">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div>
                <p class="stat-label">Total Bills</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($total_bills_count) ?> paid bills</p>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <select name="branch" onchange="this.form.submit()" class="flex-1 min-w-[200px]">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Apply Filter
            </button>
            
            <a href="revenue.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- CHARTS -->
    <div class="chart-grid animate-fade-in-up" style="animation-delay:0.15s;">
        
        <!-- Monthly Revenue Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="fas fa-calendar-alt"></i> Monthly Revenue
                </span>
                <span class="chart-total">Last 12 months</span>
            </div>
            <div class="chart-body">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <!-- Daily Revenue Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="fas fa-calendar-day"></i> Daily Revenue
                </span>
                <span class="chart-total">Last 30 days</span>
            </div>
            <div class="chart-body">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
    </div>

    <!-- Revenue Breakdown by Source -->
    <div class="chart-grid animate-fade-in-up" style="animation-delay:0.2s;">
        
        <!-- Revenue Breakdown Pie Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="fas fa-chart-pie"></i> Revenue Breakdown
                </span>
                <span class="chart-total">By source</span>
            </div>
            <div class="chart-body" style="height:250px;">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
        
        <!-- Revenue Summary -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="fas fa-list"></i> Revenue Summary
                </span>
                <span class="chart-total">Overview</span>
            </div>
            <div style="padding:16px 20px;">
                <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border-color);">
                            <th style="padding:6px 0;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;">Source</th>
                            <th style="padding:6px 0;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;">Revenue</th>
                            <th style="padding:6px 0;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;">%</th>
                            <th style="padding:6px 0;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;">Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 0;"><span style="color:#059669;">●</span> Consultation</td>
                            <td style="padding:8px 0;text-align:right;font-weight:600;color:#059669;">TSh <?= number_format($consultation_revenue, 0) ?></td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);">
                                <?= $total_revenue_all > 0 ? round(($consultation_revenue / $total_revenue_all) * 100, 1) : 0 ?>%
                            </td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);"><?= number_format($consultation_count) ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 0;"><span style="color:#7C3AED;">●</span> Lab Tests</td>
                            <td style="padding:8px 0;text-align:right;font-weight:600;color:#7C3AED;">TSh <?= number_format($lab_revenue, 0) ?></td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);">
                                <?= $total_revenue_all > 0 ? round(($lab_revenue / $total_revenue_all) * 100, 1) : 0 ?>%
                            </td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);"><?= number_format($lab_count) ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 0;"><span style="color:#D97706;">●</span> Medications</td>
                            <td style="padding:8px 0;text-align:right;font-weight:600;color:#D97706;">TSh <?= number_format($medication_revenue, 0) ?></td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);">
                                <?= $total_revenue_all > 0 ? round(($medication_revenue / $total_revenue_all) * 100, 1) : 0 ?>%
                            </td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);"><?= number_format($medication_count) ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 0;"><span style="color:#0D9488;">●</span> Procedures</td>
                            <td style="padding:8px 0;text-align:right;font-weight:600;color:#0D9488;">TSh <?= number_format($procedure_revenue, 0) ?></td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);">
                                <?= $total_revenue_all > 0 ? round(($procedure_revenue / $total_revenue_all) * 100, 1) : 0 ?>%
                            </td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);"><?= number_format($procedure_count) ?></td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:8px 0;"><span style="color:#0891B2;">●</span> Bills (Other)</td>
                            <td style="padding:8px 0;text-align:right;font-weight:600;color:#0891B2;">TSh <?= number_format($total_revenue, 0) ?></td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);">
                                <?= $total_revenue_all > 0 ? round(($total_revenue / $total_revenue_all) * 100, 1) : 0 ?>%
                            </td>
                            <td style="padding:8px 0;text-align:right;color:var(--text-secondary);"><?= number_format($total_bills_count) ?></td>
                        </tr>
                        <tr style="border-top:2px solid var(--border-color);">
                            <td style="padding:10px 0;font-weight:700;font-size:0.95rem;">TOTAL</td>
                            <td style="padding:10px 0;text-align:right;font-weight:700;font-size:0.95rem;color:var(--primary);">TSh <?= number_format($total_revenue_all, 0) ?></td>
                            <td style="padding:10px 0;text-align:right;font-weight:700;font-size:0.95rem;color:var(--primary);">100%</td>
                            <td style="padding:10px 0;text-align:right;font-weight:700;font-size:0.95rem;color:var(--primary);"><?= number_format($total_transactions) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Revenue Report
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT - CHARTS -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // ================================================================
    // DARK MODE - Sync with shared header
    // ================================================================
    (function() {
        var htmlElement = document.documentElement;
        var savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true') {
            htmlElement.setAttribute('data-theme', 'dark');
        } else {
            htmlElement.removeAttribute('data-theme');
        }
        
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                if (e.newValue === 'true') {
                    htmlElement.setAttribute('data-theme', 'dark');
                } else {
                    htmlElement.removeAttribute('data-theme');
                }
            }
        });
    })();

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
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
            weekday: 'short', 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true
        });
        
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH FUNCTION
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput ? searchInput.value.trim() : '';
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
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
    // CHARTS - REVENUE CHARTS (USING EXISTING DATABASE)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        
        // Monthly Chart
        var ctxMonthly = document.getElementById('monthlyChart')?.getContext('2d');
        if (ctxMonthly && typeof Chart !== 'undefined') {
            var monthlyLabels = <?= json_encode($monthly_labels) ?>;
            var monthlyBills = <?= json_encode($monthly_bills) ?>;
            var monthlyConsultation = <?= json_encode($monthly_consultation) ?>;
            var monthlyLab = <?= json_encode($monthly_lab) ?>;
            var monthlyMedication = <?= json_encode($monthly_medication) ?>;
            var monthlyProcedure = <?= json_encode($monthly_procedure) ?>;
            
            new Chart(ctxMonthly, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Consultation',
                            data: monthlyConsultation,
                            backgroundColor: '#059669',
                            borderRadius: 3,
                            barPercentage: 0.25
                        },
                        {
                            label: 'Lab Tests',
                            data: monthlyLab,
                            backgroundColor: '#7C3AED',
                            borderRadius: 3,
                            barPercentage: 0.25
                        },
                        {
                            label: 'Medications',
                            data: monthlyMedication,
                            backgroundColor: '#D97706',
                            borderRadius: 3,
                            barPercentage: 0.25
                        },
                        {
                            label: 'Procedures',
                            data: monthlyProcedure,
                            backgroundColor: '#0D9488',
                            borderRadius: 3,
                            barPercentage: 0.25
                        },
                        {
                            label: 'Bills',
                            data: monthlyBills,
                            backgroundColor: '#0891B2',
                            borderRadius: 3,
                            barPercentage: 0.25
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 8, weight: '600' },
                                boxWidth: 10,
                                padding: 6,
                                color: textColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                },
                                font: { size: 8 },
                                color: textColor
                            },
                            grid: { color: gridColor }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 8 }, color: textColor }
                        }
                    }
                }
            });
        }
        
        // Daily Chart
        var ctxDaily = document.getElementById('dailyChart')?.getContext('2d');
        if (ctxDaily && typeof Chart !== 'undefined') {
            var dailyLabels = <?= json_encode($daily_labels) ?>;
            var dailyBills = <?= json_encode($daily_bills) ?>;
            var dailyConsultation = <?= json_encode($daily_consultation) ?>;
            var dailyLab = <?= json_encode($daily_lab) ?>;
            var dailyMedication = <?= json_encode($daily_medication) ?>;
            var dailyProcedure = <?= json_encode($daily_procedure) ?>;
            
            new Chart(ctxDaily, {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [
                        {
                            label: 'Consultation',
                            data: dailyConsultation,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#059669',
                            borderWidth: 2
                        },
                        {
                            label: 'Lab Tests',
                            data: dailyLab,
                            borderColor: '#7C3AED',
                            backgroundColor: 'rgba(124, 58, 237, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#7C3AED',
                            borderWidth: 2
                        },
                        {
                            label: 'Medications',
                            data: dailyMedication,
                            borderColor: '#D97706',
                            backgroundColor: 'rgba(217, 119, 6, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#D97706',
                            borderWidth: 2
                        },
                        {
                            label: 'Procedures',
                            data: dailyProcedure,
                            borderColor: '#0D9488',
                            backgroundColor: 'rgba(13, 148, 136, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#0D9488',
                            borderWidth: 2
                        },
                        {
                            label: 'Bills',
                            data: dailyBills,
                            borderColor: '#0891B2',
                            backgroundColor: 'rgba(8, 145, 178, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 1.5,
                            pointBackgroundColor: '#0891B2',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 8, weight: '600' },
                                boxWidth: 10,
                                padding: 6,
                                color: textColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                },
                                font: { size: 8 },
                                color: textColor
                            },
                            grid: { color: gridColor }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 7 }, color: textColor, maxTicksLimit: 15 }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        }
        
        // Pie Chart
        var ctxPie = document.getElementById('pieChart')?.getContext('2d');
        if (ctxPie && typeof Chart !== 'undefined') {
            var consultationRev = <?= $consultation_revenue ?>;
            var labRev = <?= $lab_revenue ?>;
            var medicationRev = <?= $medication_revenue ?>;
            var procedureRev = <?= $procedure_revenue ?>;
            var billsRev = <?= $total_revenue ?>;
            
            new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ['Consultation', 'Lab Tests', 'Medications', 'Procedures', 'Bills'],
                    datasets: [{
                        data: [consultationRev, labRev, medicationRev, procedureRev, billsRev],
                        backgroundColor: ['#059669', '#7C3AED', '#D97706', '#0D9488', '#0891B2'],
                        borderWidth: 2,
                        borderColor: isDark ? '#1E293B' : '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 10, weight: '500' },
                                padding: 10,
                                color: textColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                    return context.label + ': TSh ' + context.raw.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c🏥 Braick Dispensary - Revenue Report', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue_all, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🩺 Consultation: TSh <?= number_format($consultation_revenue, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔬 Lab Tests: TSh <?= number_format($lab_revenue, 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💊 Medications: TSh <?= number_format($medication_revenue, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🛠️ Procedures: TSh <?= number_format($procedure_revenue, 0) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c📋 Bills: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0891B2;');
    console.log('%c✅ Using tables: bills, bill_items, visits, lab_tests', 'font-size:13px; color:#34D399;');
    console.log('%c🕐 Clock & Date: Active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>