<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// BRAICK DISPENSARY - SUPER ADMIN DASHBOARD
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force session for direct access
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
require_once $root_path . 'backend/config/database.php';
require_once $root_path . 'backend/helpers/functions.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_condition = '';
$branch_name = 'All Branches';
$branch_location = '';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $branch_condition = " AND branch_id = $branch_id";
    
    $stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
        $branch_location = $branch_data['location'] ?? '';
    } else {
        $branch_name = 'Branch ' . $branch_id;
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48'%3E%3Crect width='48' height='48' fill='%230B5ED7' rx='12'/%3E%3Ctext x='24' y='32' text-anchor='middle' fill='white' font-size='20' font-weight='bold'%3EB%3C/text%3E%3C/svg%3E";
$avatar_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='38' height='38'%3E%3Crect width='38' height='38' fill='%230B5ED7' rx='50%25'/%3E%3Ctext x='19' y='25' text-anchor='middle' fill='white' font-size='18' font-weight='bold'%3EA%3C/text%3E%3C/svg%3E";

// ================================================================
// HELPER FUNCTION FOR BRANCH FILTER
// ================================================================
function getBranchFilter($branch_id, $table_alias = '') {
    if ($branch_id === 'all' || empty($branch_id)) {
        return '';
    }
    $prefix = $table_alias ? $table_alias . '.' : '';
    return " AND {$prefix}branch_id = " . (int)$branch_id;
}

// ================================================================
// FETCH STATISTICS - 8 CARDS
// ================================================================

$today = date('Y-m-d');

// CARD 1: Total Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients p WHERE 1=1 " . getBranchFilter($selected_branch_id, 'p'));
$stmt->execute();
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// CARD 2: Today's Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients p WHERE DATE(p.created_at) = ? " . getBranchFilter($selected_branch_id, 'p'));
$stmt->execute([$today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// CARD 3: Total Revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales ps WHERE payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'ps'));
$stmt->execute();
$pharmacy_revenue_total = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments pm WHERE payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'pm'));
$stmt->execute();
$payments_revenue_total = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

$total_revenue_all = $pharmacy_revenue_total + $payments_revenue_total;

// CARD 4: Today's Revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales ps WHERE DATE(ps.sale_date) = ? AND payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'ps'));
$stmt->execute([$today]);
$pharmacy_revenue_today = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments pm WHERE DATE(pm.payment_date) = ? AND payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'pm'));
$stmt->execute([$today]);
$payments_revenue_today = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

$total_revenue_today = $pharmacy_revenue_today + $payments_revenue_today;

// CARD 5: Prescription Sales
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions p WHERE 1=1 " . getBranchFilter($selected_branch_id, 'p'));
$stmt->execute();
$prescription_total = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(pi.quantity), 0) as total 
    FROM prescription_items pi
    LEFT JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE 1=1 " . getBranchFilter($selected_branch_id, 'p')
);
$stmt->execute();
$prescription_items_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(ps.total), 0) as revenue 
    FROM pharmacy_sales ps
    LEFT JOIN prescriptions p ON ps.prescription_id = p.id
    WHERE ps.payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'ps')
);
$stmt->execute();
$prescription_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// CARD 6: OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM pharmacy_sales ps 
    WHERE ps.sale_type = 'outdoor' 
    AND ps.payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'ps')
);
$stmt->execute();
$otc_total = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(si.quantity), 0) as total 
    FROM sale_items si
    LEFT JOIN pharmacy_sales ps ON si.sale_id = ps.id
    WHERE ps.sale_type = 'outdoor' 
    AND ps.payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'ps')
);
$stmt->execute();
$otc_items_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(total), 0) as revenue 
    FROM pharmacy_sales ps 
    WHERE ps.sale_type = 'outdoor' 
    AND ps.payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'ps')
);
$stmt->execute();
$otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// CARD 7: Total Doctors
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users u WHERE u.role = 'doctor' AND u.status = 'active' " . getBranchFilter($selected_branch_id, 'u'));
$stmt->execute();
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// CARD 8: Today's Appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments a WHERE DATE(a.appointment_date) = ? AND a.status IN ('scheduled', 'confirmed') " . getBranchFilter($selected_branch_id, 'a'));
$stmt->execute([$today]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// DOCTORS SUMMARY - Patients by Doctor
// ================================================================
$doctors_summary = [];
$stmt = $db->prepare("
    SELECT u.id, u.full_name, u.specialty, u.is_online, b.name as branch_name,
           COUNT(DISTINCT p.id) as total_patients,
           COUNT(DISTINCT CASE WHEN DATE(v.created_at) = ? THEN p.id END) as today_patients
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    LEFT JOIN visits v ON v.doctor_id = u.id
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE u.role = 'doctor' AND u.status = 'active'
    " . getBranchFilter($selected_branch_id, 'u') . "
    GROUP BY u.id
    ORDER BY total_patients DESC
");
$stmt->execute([$today]);
$doctors_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ALL PATIENTS WITH DOCTOR
// ================================================================
$all_patients = [];
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name, u.full_name as doctor_name, u.id as doctor_id,
           (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) as visit_count
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN visits v ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE 1=1 " . getBranchFilter($selected_branch_id, 'p') . "
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute();
$all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// UNASSIGNED PATIENTS
// ================================================================
$unassigned_patients = [];
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN visits v ON v.patient_id = p.id
    WHERE v.id IS NULL
    " . getBranchFilter($selected_branch_id, 'p') . "
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute();
$unassigned_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT PATIENTS
// ================================================================
$recent_patients = [];
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name, u.full_name as doctor_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN visits v ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE 1=1 " . getBranchFilter($selected_branch_id, 'p') . "
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT ACTIVITIES
// ================================================================
$recent_activities = [
    ['action' => 'New Patient Registered', 'details' => 'Patient: John Doe (ID: P-2024-001)', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['action' => 'Prescription Filled', 'details' => 'Patient: Mary Jane, Total: TSh 45,000', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ['action' => 'Lab Test Completed', 'details' => 'Patient: Peter Smith, Test: Blood Count', 'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))],
    ['action' => 'Doctor Online', 'details' => 'Dr. Sarah Mwamba came online', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
];

// Revenue Breakdown
$revenue_breakdown = [
    'Consultation Fees' => 0,
    'Laboratory Fees' => 0,
    'Prescription Sales' => $prescription_revenue,
    'OTC Sales' => $otc_revenue,
    'Other Income' => 0
];

// Get lab revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments pm WHERE payment_type = 'laboratory' AND payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'pm'));
$stmt->execute();
$lab_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
$revenue_breakdown['Laboratory Fees'] = $lab_revenue;

// Consultation revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments pm WHERE payment_type = 'consultation' AND payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'pm'));
$stmt->execute();
$consultation_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
$revenue_breakdown['Consultation Fees'] = $consultation_revenue;

// Other payments
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments pm WHERE payment_type = 'other' AND payment_status = 'paid' " . getBranchFilter($selected_branch_id, 'pm'));
$stmt->execute();
$other_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
$revenue_breakdown['Other Income'] = $other_revenue;

// ================================================================
// BRANCHES DATA
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: rgba(11, 94, 215, 0.10);
            --secondary: #0AA84F;
            --secondary-light: rgba(10, 168, 79, 0.10);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --radius: 18px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ===== SIDEBAR - BLUE WITH GREEN HOVER ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; background: var(--primary); 
            color: white;
            z-index: 50; overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-brand .branch-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 4px;
        }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { 
            font-size: 0.55rem; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            color: rgba(255,255,255,0.4); 
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
            color: rgba(255,255,255,0.75); 
            text-decoration: none;
            transition: var(--transition); 
            font-size: 0.85rem; 
            font-weight: 500;
            margin: 2px 0;
        }
        /* Hover effect - GREEN */
        .sidebar-link:hover { 
            background: rgba(10, 168, 79, 0.25); 
            color: #FFFFFF; 
        }
        /* Active link - GREEN */
        .sidebar-link.active { 
            background: #0AA84F; 
            color: white; 
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4); 
        }
        .sidebar-link.active i { color: white; }
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
        }
        .sidebar-link.active .badge { 
            background: rgba(255,255,255,0.25); 
            color: white; 
        }
        
        /* ===== TOP NAV ===== */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-card); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--bg-body); border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        .top-nav .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 280px; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        .top-nav .search-wrapper input::placeholder { color: var(--text-secondary); }
        .top-nav .search-wrapper .search-btn {
            background: var(--primary); color: white;
            border: none; padding: 8px 16px; border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: var(--transition);
        }
        .top-nav .search-wrapper .search-btn:hover { background: var(--primary-dark); }
        .top-nav .branch-selector {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            background: var(--bg-body);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            min-width: 180px;
            color: var(--text-primary);
            transition: var(--transition);
        }
        .top-nav .branch-selector:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
        .top-nav .datetime { font-size: 0.78rem; color: var(--text-secondary); font-weight: 500; }
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: var(--transition);
        }
        .top-nav .avatar:hover { border-color: var(--primary); }
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: var(--transition);
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: #EF4444;
            border-radius: 50%; border: 2px solid var(--bg-card);
        }
        .dark-toggle {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 6px 12px;
            cursor: pointer; font-size: 0.85rem;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex; align-items: center; gap: 6px;
        }
        .dark-toggle:hover { border-color: var(--primary); }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ===== SUMMARY CARDS - 8 CARDS (Blue & Green Only) ===== */
        .summary-card {
            border-radius: var(--radius);
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: default;
        }
        .summary-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        /* Hover effect - GREEN */
        .summary-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: #0AA84F;
        }
        /* Card 1,3,5,7 - Blue */
        .summary-card.blue { 
            background: rgba(11, 94, 215, 0.08); 
            border-left: 4px solid #0B5ED7;
        }
        .summary-card.blue::after { background: linear-gradient(90deg, #0B5ED7, #1E88E5); }
        .summary-card.blue .sc-icon { background: rgba(11, 94, 215, 0.15); color: #0B5ED7; }
        .summary-card.blue .sc-number { color: #0B5ED7; }
        
        /* Card 2,4,6,8 - Green */
        .summary-card.green { 
            background: rgba(10, 168, 79, 0.08); 
            border-left: 4px solid #0AA84F;
        }
        .summary-card.green::after { background: linear-gradient(90deg, #0AA84F, #34D399); }
        .summary-card.green .sc-icon { background: rgba(10, 168, 79, 0.15); color: #0AA84F; }
        .summary-card.green .sc-number { color: #0AA84F; }
        
        .summary-card .sc-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .summary-card .sc-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .summary-card .sc-label {
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .summary-card .sc-trend {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
        }
        .summary-card .sc-trend.up { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .summary-card .sc-trend.neutral { background: rgba(100, 116, 139, 0.12); color: #64748B; }
        .summary-card .sc-stats {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .summary-card .sc-stats span { font-weight: 600; }
        
        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .card-title i { color: var(--primary); }
        
        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 16px; border-radius: 10px;
            font-weight: 600; font-size: 0.78rem;
            transition: var(--transition); cursor: pointer;
            border: none; text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-secondary:hover { background: #08944A; transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ===== TABLES ===== */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .data-table th {
            text-align: left; padding: 8px 12px;
            font-weight: 600; color: var(--text-secondary);
            font-size: 0.6rem; text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        .data-table td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .data-table tr:hover td { background: var(--bg-body); }
        .data-table .table-actions { display: flex; gap: 8px; }
        
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.paid { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.pending { background: rgba(217, 119, 6, 0.12); color: #D97706; }
        .status-badge.active { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.assigned { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
        .status-badge.unassigned { background: rgba(239, 68, 68, 0.12); color: #EF4444; }
        
        /* ===== QUICK ACTION ===== */
        .quick-action {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 14px 10px;
            border-radius: var(--radius);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-primary);
            gap: 4px;
            text-align: center;
        }
        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        .quick-action .qa-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .quick-action .qa-label { font-size: 0.65rem; font-weight: 500; }
        
        /* ===== REVENUE BREAKDOWN ===== */
        .revenue-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 12px; border-radius: 10px; transition: var(--transition);
        }
        .revenue-item:hover { background: var(--bg-body); }
        .revenue-item .ri-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; font-size: 0.75rem;
        }
        
        /* ===== UNASSIGNED PATIENT CARD ===== */
        .unassigned-card {
            border-left: 4px solid #EF4444;
            animation: pulse-border 2s infinite;
        }
        @keyframes pulse-border {
            0%, 100% { border-left-color: #EF4444; }
            50% { border-left-color: #F87171; }
        }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper input { width: 160px; }
        }
        @media (max-width: 768px) {
            .summary-card .sc-number { font-size: 1.6rem; }
        }
        @media (max-width: 640px) {
            .top-nav .search-wrapper input { width: 100px; }
            .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 10px; }
            .summary-card .sc-number { font-size: 1.4rem; }
            .top-nav .dark-toggle span { display: none; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.18s; }
        .animate-fade-in-up:nth-child(7) { animation-delay: 0.21s; }
        .animate-fade-in-up:nth-child(8) { animation-delay: 0.24s; }
        
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--border-color); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px; border-radius: 12px;
            z-index: 999; max-width: 360px;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            display: flex; align-items: center; gap: 10px;
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR - BLUE WITH GREEN HOVER -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='<?= $logo_fallback ?>'">
            <div>
                <p class="font-bold text-base leading-tight">Braick Dispensary</p>
                <p class="text-xs opacity-80">Super Admin</p>
                <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?></span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="../doctor/dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-user-md"></i> Doctors</a>
        <a href="../reception/dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-headset"></i> Reception</a>
        <a href="../laboratory/dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-flask"></i> Laboratory</a>
        <a href="../pharmacy/dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-pills"></i> Pharmacy</a>
        <a href="../cashier/dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-cash-register"></i> Cashier</a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="users.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-users"></i> Users</a>
        <a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> Settings</a>
        <div class="nav-label mt-4">System</div>
        <a href="backups.php" class="sidebar-link"><i class="fas fa-database"></i> Backups</a>
        <a href="system_logs.php" class="sidebar-link"><i class="fas fa-history"></i> System Logs</a>
        <a href="<?= $root_path ?>logout.php" class="sidebar-link" style="margin-top:8px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines..." class="search-input">
            <button id="searchBtn" class="search-btn"><i class="fas fa-search"></i></button>
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
        <button id="darkModeToggle" class="dark-toggle">
            <i class="fas fa-moon" id="darkIcon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn" id="notifToggle">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-envelope text-lg"></i>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $avatar_fallback ?>'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-primary">Super Admin Dashboard</h1>
            <p class="text-sm text-secondary">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>! 
                <span class="inline-flex ml-2" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7; padding: 3px 14px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(11, 94, 215, 0.15);">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-export"></i> Generate Report</a>
            <button onclick="refreshData()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 SUMMARY CARDS - 2 ROWS (4 TOP, 4 BOTTOM) -->
    <!-- Blue & Green Only -->
    <!-- ================================================================ -->
    
    <!-- ROW 1: Cards 1-4 (Blue, Green, Blue, Green) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        
        <!-- Card 1: Total Patients - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Patients</p>
                    <p class="sc-number"><?= number_format($total_patients) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> Registered</span>
                </div>
                <div class="sc-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <!-- Card 2: Today's Patients - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Patients</p>
                    <p class="sc-number"><?= number_format($today_patients) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> New today</span>
                </div>
                <div class="sc-icon"><i class="fas fa-user-plus"></i></div>
            </div>
        </div>
        
        <!-- Card 3: Total Revenue - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Revenue</p>
                    <p class="sc-number">TSh <?= number_format($total_revenue_all) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> All time</span>
                </div>
                <div class="sc-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
        <!-- Card 4: Today's Revenue - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Revenue</p>
                    <p class="sc-number">TSh <?= number_format($total_revenue_today) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> Today</span>
                </div>
                <div class="sc-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>
        
    </div>
    
    <!-- ROW 2: Cards 5-8 (Blue, Green, Blue, Green) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <!-- Card 5: Prescription Sales - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Prescription Sales</p>
                    <p class="sc-number"><?= number_format($prescription_total) ?></p>
                    <div class="sc-stats">
                        <span><?= number_format($prescription_items_total) ?></span> items · TSh <?= number_format($prescription_revenue) ?>
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-prescription"></i></div>
            </div>
        </div>
        
        <!-- Card 6: OTC Sales - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">OTC Sales</p>
                    <p class="sc-number"><?= number_format($otc_total) ?></p>
                    <div class="sc-stats">
                        <span><?= number_format($otc_items_total) ?></span> items · TSh <?= number_format($otc_revenue) ?>
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        
        <!-- Card 7: Total Doctors - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Total Doctors</p>
                    <p class="sc-number"><?= number_format($total_doctors) ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> Active</span>
                </div>
                <div class="sc-icon"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        
        <!-- Card 8: Today's Appointments - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Appointments</p>
                    <p class="sc-number"><?= number_format($today_appointments) ?></p>
                    <span class="sc-trend neutral"><i class="fas fa-calendar-check"></i> Scheduled</span>
                </div>
                <div class="sc-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DOCTORS SUMMARY - Patients by Doctor -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-md mr-2"></i> Patients by Doctor</h3>
            <a href="doctors.php?branch=<?= $selected_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="overflow-x-auto max-h-60 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Specialty</th>
                        <th>Branch</th>
                        <th>Total Patients</th>
                        <th>Today's Patients</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($doctors_summary) > 0): ?>
                        <?php foreach ($doctors_summary as $doctor): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($doctor['full_name'] ?? 'Unknown') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></td>
                                <td class="font-semibold text-center"><?= $doctor['total_patients'] ?? 0 ?></td>
                                <td class="font-semibold text-center text-green-600"><?= $doctor['today_patients'] ?? 0 ?></td>
                                <td>
                                    <?php if (($doctor['is_online'] ?? 0) == 1): ?>
                                        <span class="status-badge paid"><i class="fas fa-circle text-green-500 mr-1"></i> Online</span>
                                    <?php else: ?>
                                        <span class="status-badge pending"><i class="fas fa-circle text-gray-400 mr-1"></i> Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../doctor/dashboard.php?branch=<?= $selected_branch_id ?>&doctor=<?= $doctor['id'] ?>" class="text-primary text-xs hover:underline">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-secondary text-sm py-3">No doctors found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- UNASSIGNED PATIENTS - WAGONJWA WASIO NA DOCTOR -->
    <!-- ================================================================ -->
    <?php if (count($unassigned_patients) > 0): ?>
    <div class="card mb-5 unassigned-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Unassigned Patients</h3>
            <a href="assign_doctor.php?branch=<?= $selected_branch_id ?>" class="text-xs text-primary font-medium">Assign Now →</a>
        </div>
        <div class="overflow-x-auto max-h-48 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Branch</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unassigned_patients as $patient): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                            <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                            <td class="text-xs"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></td>
                            <td class="text-xs"><?= date('M d, Y', strtotime($patient['created_at'])) ?></td>
                            <td>
                                <a href="assign_doctor.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-user-md"></i> Assign
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ALL PATIENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users mr-2"></i> All Patients</h3>
            <div class="flex gap-2">
                <a href="export_patients_excel.php?branch=<?= $selected_branch_id ?>" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="export_patients_pdf.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                <a href="patients.php?branch=<?= $selected_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
            </div>
        </div>
        <div class="overflow-x-auto max-h-72 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Assigned Doctor</th>
                        <th>Visits</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_patients) > 0): ?>
                        <?php foreach ($all_patients as $patient): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></td>
                                <td class="text-xs">
                                    <?php if (!empty($patient['doctor_name'])): ?>
                                        <span class="text-green-600 font-medium"><?= htmlspecialchars($patient['doctor_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-red-500">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge paid"><?= $patient['visit_count'] ?? 0 ?></span></td>
                                <td>
                                    <?php if (!empty($patient['doctor_name'])): ?>
                                        <span class="status-badge assigned">Assigned</span>
                                    <?php else: ?>
                                        <span class="status-badge unassigned">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-primary text-xs hover:underline">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="assign_doctor.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-secondary text-xs hover:underline">
                                            <i class="fas fa-user-md"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-secondary text-sm py-3">No patients found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REVENUE BREAKDOWN -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i> Revenue Breakdown</h3>
            <span class="text-xs text-secondary">Total: TSh <?= number_format($total_revenue_all) ?></span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
            <?php foreach ($revenue_breakdown as $name => $amount): ?>
                <div class="revenue-item">
                    <div class="flex items-center gap-2">
                        <div class="ri-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                            <i class="fas fa-circle"></i>
                        </div>
                        <span class="text-xs font-medium"><?= $name ?></span>
                    </div>
                    <span class="font-semibold text-sm">TSh <?= number_format($amount) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 gap-3 mb-5">
        <a href="add_branch.php" class="quick-action">
            <div class="qa-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;"><i class="fas fa-store"></i></div>
            <span class="qa-label">Add Branch</span>
        </a>
        <a href="add_user.php?branch=<?= $selected_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-user-plus"></i></div>
            <span class="qa-label">Add User</span>
        </a>
        <a href="add_doctor.php?branch=<?= $selected_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;"><i class="fas fa-user-md"></i></div>
            <span class="qa-label">Register Doctor</span>
        </a>
        <a href="add_medicine.php?branch=<?= $selected_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-pills"></i></div>
            <span class="qa-label">Add Medicine</span>
        </a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;"><i class="fas fa-chart-bar"></i></div>
            <span class="qa-label">Generate Report</span>
        </a>
        <a href="backups.php" class="quick-action">
            <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-database"></i></div>
            <span class="qa-label">Backup</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-injured mr-2"></i> Recent Patients</h3>
            <a href="patients.php?branch=<?= $selected_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="overflow-x-auto max-h-60 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Patient ID</th><th>Name</th><th>Branch</th><th>Assigned Doctor</th><th>Registered</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (count($recent_patients) > 0): ?>
                        <?php foreach ($recent_patients as $patient): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></td>
                                <td class="text-xs">
                                    <?php if (!empty($patient['doctor_name'])): ?>
                                        <span class="text-green-600"><?= htmlspecialchars($patient['doctor_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-red-500">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($patient['created_at'])) ?></td>
                                <td><a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-primary text-xs hover:underline">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-secondary text-sm py-3">No patients found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock mr-2"></i> Recent Activity</h3>
            <a href="system_logs.php" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
            <?php foreach ($recent_activities as $activity): ?>
                <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-body transition">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                        <i class="fas fa-circle text-[8px]"></i>
                    </div>
                    <div>
                        <p class="font-medium text-sm text-primary"><?= htmlspecialchars($activity['action']) ?></p>
                        <p class="text-xs text-secondary"><?= htmlspecialchars($activity['details']) ?></p>
                        <p class="text-[10px] text-secondary mt-0.5"><?= time_ago($activity['created_at']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i> Quick Reports</h3>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="reports.php?type=daily&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-calendar-day"></i> Daily</a>
            <a href="reports.php?type=weekly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-calendar-week"></i> Weekly</a>
            <a href="reports.php?type=monthly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-calendar-alt"></i> Monthly</a>
            <a href="reports.php?type=revenue&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-money-bill-wave"></i> Revenue</a>
            <a href="reports.php?type=medicine&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-pills"></i> Medicine</a>
            <a href="reports.php?type=laboratory&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-flask"></i> Laboratory</a>
            <div class="flex-1"></div>
            <button onclick="downloadPDF()" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
            <button onclick="exportExcel()" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</button>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p class="font-medium text-sm">Braick Dispensary Management System</p>
        <p class="text-xs">Super Admin Dashboard v2.0 &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const darkToggle = document.getElementById('darkModeToggle');
    const darkIcon = document.getElementById('darkIcon');
    const darkText = document.getElementById('darkText');
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');
    const refreshBtn = document.getElementById('refreshBtn');
    const branchSelector = document.getElementById('branchSelector');

    // Sidebar Toggle
    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // Dark Mode
    let isDark = false;
    darkToggle?.addEventListener('click', () => {
        isDark = !isDark;
        const html = document.getElementById('htmlRoot');
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
        } else {
            html.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
        }
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        isDark = true;
        document.getElementById('htmlRoot').setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }

    // Search
    function performSearch() {
        const query = searchInput.value.trim();
        if (query.length > 0) {
            showToast('Search', 'Searching for: "' + query + '"', 'info');
            const branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    // Branch Switcher
    function switchBranch(branchId) {
        const url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // Refresh
    function refreshData() {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<span class="spinner"></span> Refreshing...';
            refreshBtn.disabled = true;
        }
        setTimeout(() => { location.reload(); }, 800);
    }

    // Toast
    function showToast(title, message, type = 'info') {
        const existing = document.querySelector('.toast-custom');
        if (existing) existing.remove();
        const colors = {
            info: { bg: '#0B5ED7', icon: 'fa-info-circle' },
            success: { bg: '#0AA84F', icon: 'fa-check-circle' },
            error: { bg: '#EF4444', icon: 'fa-exclamation-circle' },
            warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' }
        };
        const style = colors[type] || colors.info;
        const toast = document.createElement('div');
        toast.className = 'toast-custom';
        toast.style.cssText = `
            background: ${style.bg}; color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        `;
        toast.innerHTML = `
            <i class="fas ${style.icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    // Download & Export
    function downloadPDF() {
        showToast('Downloading PDF', 'Generating PDF report...', 'info');
        const branch = '<?= $selected_branch_id ?>';
        window.location.href = 'reports.php?export=pdf&branch=' + branch;
    }
    function exportExcel() {
        showToast('Exporting Excel', 'Preparing Excel export...', 'info');
        const branch = '<?= $selected_branch_id ?>';
        window.location.href = 'reports.php?export=excel&branch=' + branch;
    }

    // DateTime
    function updateDateTime() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Welcome
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            showToast('Welcome', 'Super Admin Dashboard loaded successfully', 'success');
        }, 300);
    });

    console.log('%c🏥 Braick Dispensary - Super Admin Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0AA84F;');
</script>

</body>
</html>