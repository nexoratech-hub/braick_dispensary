<?php
// ================================================================
// FILE: frontend/pages/admin/revenue.php
// SUPER ADMIN - REVENUE REPORT PAGE
// BRAICK DISPENSARY - BLUE THEME
// ✅ FIXED: Consultation & Prescriptions show ONLY paid
// ✅ FIXED: Lab Test uses total_price - discount_amount
// ✅ REMOVED: Medication Card (8 Cards Only)
// ✅ Revenue = Patient Bills + OTC ONLY (no double count)
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
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// BRANCH FILTER PARAMS
// ================================================================
$branch_filter = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

$branch_filter_b = "";
$branch_params_b = [];
if ($selected_branch_id !== 'all') {
    $branch_filter_b = " AND b.branch_id = ?";
    $branch_params_b[] = (int)$selected_branch_id;
}

// ================================================================
// 1. PATIENT BILLS REVENUE
// ================================================================
$patient_bills_revenue = 0;
$patient_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(b.paid_amount), 0) as total, COUNT(*) as count 
            FROM bills b
            WHERE b.status = 'paid'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = $data['total'] ?? 0;
    $patient_bills_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 2. OTC REVENUE
// ================================================================
$otc_revenue = 0;
$otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales WHERE payment_status = 'paid'" . $branch_filter;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = $data['total'] ?? 0;
    $otc_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 3. PRESCRIPTION REVENUE - ✅ FIXED: Only from PAID bills
// ================================================================
$prescription_revenue = 0;
$prescription_count = 0;
try {
    // ✅ Prescriptions from PAID bills (medication items with reference_type = prescription)
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.reference_type = 'prescription'
            AND bi.item_type = 'medication'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $prescription_revenue = $data['total'] ?? 0;
    $prescription_count = $data['count'] ?? 0;
    
    // Fallback: dispensed prescriptions
    if ($prescription_revenue == 0) {
        $sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total, 
                       COUNT(DISTINCT pi.id) as count 
                FROM prescription_items pi
                INNER JOIN prescriptions p ON pi.prescription_id = p.id
                WHERE p.status = 'dispensed'";
        if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($selected_branch_id !== 'all' ? [(int)$selected_branch_id] : []);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $prescription_revenue = $data['total'] ?? 0;
        $prescription_count = $data['count'] ?? 0;
    }
} catch (Exception $e) {}

// ================================================================
// 4. CONSULTATION - ✅ FIXED: Only from PAID bills
// ================================================================
$consultation_revenue = 0;
$consultation_count = 0;
try {
    // ✅ Consultation from PAID bills only
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'consultation'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $consultation_revenue = $data['total'] ?? 0;
    $consultation_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 5. LAB TESTS - ✅ FIXED: Only from PAID bills
// ================================================================
$lab_revenue = 0;
$lab_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'lab_test'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $lab_revenue = $data['total'] ?? 0;
    $lab_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 6. PROCEDURES - Only from PAID bills
// ================================================================
$procedure_revenue = 0;
$procedure_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'procedure'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $procedure_revenue = $data['total'] ?? 0;
    $procedure_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 7. MEDICATIONS - Only from PAID bills (for table display)
// ================================================================
$medication_revenue = 0;
$medication_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'medication'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $medication_revenue = $data['total'] ?? 0;
    $medication_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 8. REGISTRATION - Only from PAID bills
// ================================================================
$registration_revenue = 0;
$registration_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'registration'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'" . $branch_filter_b;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params_b);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $registration_revenue = $data['total'] ?? 0;
    $registration_count = $data['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 9. TOTAL REVENUE = Patient Bills + OTC
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue;
$total_transactions = $patient_bills_count + $otc_count;

// ================================================================
// 10. EXPENSES
// ================================================================
$total_expenses = 0;
$expenses_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM expenses WHERE status = 'paid'" . $branch_filter;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = $data['total'] ?? 0;
    $expenses_count = $data['count'] ?? 0;
} catch (Exception $e) {}

$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// ================================================================
// 11. MONTHLY REVENUE (Last 12 months)
// ================================================================
$monthly_labels = [];
$monthly_patient = [];
$monthly_otc = [];
$monthly_prescription = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_labels[] = date('M Y', strtotime("-$i months"));
    
    $params_b = [$month];
    $params = [$month];
    
    if ($selected_branch_id !== 'all') {
        $params_b[] = (int)$selected_branch_id;
        $params[] = (int)$selected_branch_id;
    }
    
    // Patient Bills
    $sql = "SELECT COALESCE(SUM(b.paid_amount), 0) as total FROM bills b
            WHERE b.status = 'paid' AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%' AND DATE_FORMAT(b.created_at, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND b.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_b);
    $monthly_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // OTC
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales
            WHERE payment_status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $monthly_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Prescriptions (from PAID bills)
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid' AND bi.reference_type = 'prescription' 
            AND bi.item_type = 'medication' AND DATE_FORMAT(b.created_at, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND b.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_b);
    $monthly_prescription[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

// ================================================================
// 12. DAILY REVENUE (Last 30 days)
// ================================================================
$daily_labels = [];
$daily_patient = [];
$daily_otc = [];
$daily_prescription = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    
    $params_b = [$date];
    $params = [$date];
    
    if ($selected_branch_id !== 'all') {
        $params_b[] = (int)$selected_branch_id;
        $params[] = (int)$selected_branch_id;
    }
    
    $sql = "SELECT COALESCE(SUM(b.paid_amount), 0) as total FROM bills b
            WHERE b.status = 'paid' AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%' AND DATE(b.created_at) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND b.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_b);
    $daily_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales
            WHERE payment_status = 'paid' AND DATE(created_at) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $daily_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid' AND bi.reference_type = 'prescription' 
            AND bi.item_type = 'medication' AND DATE(b.created_at) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND b.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_b);
    $daily_prescription[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- PAGE-SPECIFIC CSS -->
<style>
    :root {
        --rv-primary: #0B5ED7;
        --rv-primary-dark: #0A4CA8;
        --rv-primary-light: #3B82F6;
        --rv-primary-bg: #EFF6FF;
        --rv-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --rv-primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
        --rv-success: #059669;
        --rv-success-bg: #D1FAE5;
        --rv-danger: #DC2626;
        --rv-danger-bg: #FEE2E2;
        --rv-warning: #D97706;
        --rv-warning-bg: #FEF3C7;
        --rv-purple: #7C3AED;
        --rv-purple-bg: #EDE9FE;
        --rv-teal: #0D9488;
        --rv-cyan: #0891B2;
        --rv-rose: #E11D48;
        --rv-rose-bg: #FFE4E6;
        --rv-bg-body: #F0F4F8;
        --rv-bg-card: #FFFFFF;
        --rv-text-primary: #1E293B;
        --rv-text-secondary: #64748B;
        --rv-border-color: #E2E8F0;
        --rv-radius: 12px;
        --rv-radius-lg: 18px;
        --rv-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --rv-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --rv-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --rv-table-hover: #F8FAFC;
    }

    [data-theme="dark"] {
        --rv-bg-body: #0F172A;
        --rv-bg-card: #1E293B;
        --rv-text-primary: #F1F5F9;
        --rv-text-secondary: #94A3B8;
        --rv-border-color: #334155;
        --rv-primary: #3B82F6;
        --rv-primary-dark: #2563EB;
        --rv-primary-light: #60A5FA;
        --rv-primary-bg: #1E3A5F;
        --rv-table-hover: #1E293B;
    }

    html[data-theme="dark"] body { background: #0F172A !important; }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    .page-header-rv {
        background: var(--rv-primary-gradient);
        border-radius: var(--rv-radius-lg);
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

    .page-header-rv::before {
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

    .page-header-rv .page-title-rv {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-rv .page-title-rv i { font-size: 2rem; opacity: 0.9; }

    .page-header-rv .page-subtitle-rv {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-rv .page-subtitle-rv strong { color: white; font-weight: 600; }

    .page-header-rv .role-badge-display-rv {
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

    .page-header-rv .header-badge-rv {
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

    .page-header-rv .btn-outline-light-rv {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--rv-radius);
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
        cursor: pointer;
    }

    .page-header-rv .btn-outline-light-rv:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* STATS CARDS - 8 CARDS */
    .stats-grid-rv {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .stat-card-rv {
        border-radius: var(--rv-radius);
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
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .stat-card-rv::before {
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

    .stat-card-rv:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    }

    .stat-card-rv .stat-icon-rv {
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

    .stat-card-rv .stat-label-rv {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .stat-card-rv .stat-value-rv {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        margin: 0;
        line-height: 1.2;
        position: relative;
        z-index: 1;
    }

    .stat-card-rv .stat-sub-rv {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.6);
        margin-top: 2px;
        position: relative;
        z-index: 1;
    }

    .stat-card-rv.card-total { background: var(--rv-primary-gradient); }
    .stat-card-rv.card-patient { background: #0B5ED7; }
    .stat-card-rv.card-otc { background: #0891B2; }
    .stat-card-rv.card-prescription { background: #7C3AED; }
    .stat-card-rv.card-consultation { background: #059669; }
    .stat-card-rv.card-lab { background: #7C3AED; }
    .stat-card-rv.card-expenses { background: #E11D48; }
    .stat-card-rv.card-profit { background: #059669; }

    /* FILTER BAR */
    .filter-bar-rv {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        align-items: center;
        background: var(--rv-bg-card);
        padding: 16px 20px;
        border-radius: var(--rv-radius);
        border: 2px solid var(--rv-border-color);
        box-shadow: var(--rv-shadow-sm);
    }

    .filter-bar-rv .filter-label-rv {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--rv-primary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-bar-rv select {
        background: var(--rv-bg-body);
        border: 2px solid var(--rv-border-color);
        border-radius: var(--rv-radius);
        padding: 8px 14px;
        font-size: 0.8rem;
        color: var(--rv-text-primary);
        outline: none;
        transition: all 0.3s;
        min-width: 200px;
        font-family: inherit;
        cursor: pointer;
    }

    .filter-bar-rv select:focus {
        border-color: var(--rv-primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .btn-rv {
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
        font-family: inherit;
    }

    .btn-primary-rv { background: var(--rv-primary-gradient); color: white; }
    .btn-primary-rv:hover {
        background: var(--rv-primary-gradient-hover);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-outline-rv {
        background: transparent;
        color: var(--rv-text-secondary);
        border: 2px solid var(--rv-border-color);
    }
    .btn-outline-rv:hover {
        background: var(--rv-bg-body);
        border-color: var(--rv-primary);
        color: var(--rv-primary);
    }

    /* CHART CARDS */
    .chart-grid-rv {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .chart-card-rv {
        background: var(--rv-bg-card);
        border-radius: var(--rv-radius-lg);
        border: 2px solid var(--rv-border-color);
        overflow: hidden;
        box-shadow: var(--rv-shadow-sm);
        transition: all 0.3s ease;
    }

    .chart-card-rv:hover {
        box-shadow: var(--rv-shadow-md);
        border-color: var(--rv-primary-light);
    }

    .chart-card-rv .chart-header-rv {
        padding: 14px 20px;
        border-bottom: 2px solid var(--rv-border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        background: var(--rv-bg-body);
    }

    [data-theme="dark"] .chart-card-rv .chart-header-rv { background: #0F172A; }

    .chart-card-rv .chart-header-rv .chart-title-rv {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--rv-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-card-rv .chart-header-rv .chart-title-rv i { color: var(--rv-primary); }

    .chart-card-rv .chart-header-rv .chart-total-rv {
        font-size: 0.75rem;
        color: var(--rv-text-secondary);
        font-weight: 500;
    }

    .chart-card-rv .chart-body-rv {
        padding: 16px 20px;
        height: 220px;
        position: relative;
    }

    /* TABLE CARD */
    .table-card-rv {
        background: var(--rv-bg-card);
        border-radius: var(--rv-radius-lg);
        border: 2px solid var(--rv-border-color);
        overflow: hidden;
        box-shadow: var(--rv-shadow-sm);
        margin-bottom: 24px;
    }

    .table-card-rv .table-header-rv {
        padding: 14px 20px;
        background: var(--rv-primary-gradient);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .table-card-rv .table-header-rv .title-rv {
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .table-card-rv .table-header-rv .title-rv i { margin-right: 8px; }

    .table-card-rv .table-header-rv .count-rv {
        color: rgba(255,255,255,0.8);
        font-size: 0.75rem;
    }

    .table-card-rv table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }

    .table-card-rv table thead { background: var(--rv-bg-body); }
    [data-theme="dark"] .table-card-rv table thead { background: #0F172A; }

    .table-card-rv table th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: var(--rv-text-secondary);
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--rv-border-color);
        white-space: nowrap;
    }

    .table-card-rv table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--rv-border-color);
        color: var(--rv-text-primary);
        vertical-align: middle;
    }

    .table-card-rv table tr:hover td { background: var(--rv-table-hover); }
    .table-card-rv table tr:last-child td { border-bottom: none; }

    .table-card-rv table tr.total-row-rv td {
        border-top: 2px solid var(--rv-border-color);
        font-weight: 700;
        font-size: 0.95rem;
    }

    .footer-rv {
        padding: 14px 0;
        border-top: 2px solid var(--rv-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--rv-text-secondary);
    }

    .footer-rv .footer-brand-rv {
        color: var(--rv-primary);
        font-weight: 600;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-rv {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    @media (max-width: 1024px) {
        .stats-grid-rv { grid-template-columns: repeat(2, 1fr); }
        .chart-grid-rv { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .page-header-rv { padding: 16px 18px; }
        .page-header-rv .page-title-rv { font-size: 1.3rem; }
        .stats-grid-rv { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card-rv { padding: 14px 16px; }
        .stat-card-rv .stat-value-rv { font-size: 1.2rem; }
        .stat-icon-rv { width: 40px; height: 40px; font-size: 1rem; }
        .filter-bar-rv { flex-direction: column; align-items: stretch; }
        .filter-bar-rv select { width: 100%; min-width: unset; }
    }

    @media (max-width: 480px) {
        .stats-grid-rv { grid-template-columns: 1fr; gap: 8px; }
        .stat-card-rv { padding: 10px 12px; }
        .stat-card-rv .stat-value-rv { font-size: 1rem; }
        .stat-icon-rv { width: 32px; height: 32px; font-size: 0.85rem; }
        .page-header-rv { flex-direction: column; align-items: flex-start !important; }
    }

    @media print {
        .btn-rv, .btn-outline-light-rv, .filter-bar-rv { display: none !important; }
        .stat-card-rv { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header-rv {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .chart-card-rv, .table-card-rv { break-inside: avoid; }
    }
</style>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-rv animate-fade-in-up-rv">
        <div>
            <h1 class="page-title-rv">
                <i class="fas fa-chart-line"></i>
                Revenue Report
                <span class="role-badge-display-rv">ADMIN</span>
            </h1>
            <p class="page-subtitle-rv">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                <span class="header-badge-rv">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Total Revenue
                </span>
                <span class="header-badge-rv">
                    <i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Transactions
                </span>
                <span class="header-badge-rv">
                    <i class="fas fa-prescription"></i> Presc: TSh <?= number_format($prescription_revenue, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light-rv">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn-outline-light-rv">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- STATS CARDS - 8 CARDS -->
    <div class="stats-grid-rv animate-fade-in-up-rv" style="animation-delay:0.05s;">
        
        <!-- 1. Total Revenue -->
        <div class="stat-card-rv card-total">
            <div class="stat-icon-rv"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label-rv">Total Revenue</p>
                <p class="stat-value-rv">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub-rv">Bills + OTC</p>
            </div>
        </div>
        
        <!-- 2. Patient Bills -->
        <div class="stat-card-rv card-patient">
            <div class="stat-icon-rv"><i class="fas fa-file-invoice"></i></div>
            <div>
                <p class="stat-label-rv">Patient Bills</p>
                <p class="stat-value-rv">TSh <?= number_format($patient_bills_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($patient_bills_count) ?> paid bills</p>
            </div>
        </div>
        
        <!-- 3. OTC Sales -->
        <div class="stat-card-rv card-otc">
            <div class="stat-icon-rv"><i class="fas fa-cash-register"></i></div>
            <div>
                <p class="stat-label-rv">OTC Sales</p>
                <p class="stat-value-rv">TSh <?= number_format($otc_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($otc_count) ?> transactions</p>
            </div>
        </div>
        
        <!-- 4. Prescriptions -->
        <div class="stat-card-rv card-prescription">
            <div class="stat-icon-rv"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-label-rv">Prescriptions</p>
                <p class="stat-value-rv">TSh <?= number_format($prescription_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($prescription_count) ?> dispensed</p>
            </div>
        </div>
        
        <!-- 5. Consultation -->
        <div class="stat-card-rv card-consultation">
            <div class="stat-icon-rv"><i class="fas fa-stethoscope"></i></div>
            <div>
                <p class="stat-label-rv">Consultation</p>
                <p class="stat-value-rv">TSh <?= number_format($consultation_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($consultation_count) ?> consultations</p>
            </div>
        </div>
        
        <!-- 6. Lab Tests -->
        <div class="stat-card-rv card-lab">
            <div class="stat-icon-rv"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label-rv">Lab Tests</p>
                <p class="stat-value-rv">TSh <?= number_format($lab_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($lab_count) ?> tests</p>
            </div>
        </div>
        
        <!-- 7. Total Expenses -->
        <div class="stat-card-rv card-expenses">
            <div class="stat-icon-rv"><i class="fas fa-receipt"></i></div>
            <div>
                <p class="stat-label-rv">Total Expenses</p>
                <p class="stat-value-rv">TSh <?= number_format($total_expenses, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($expenses_count) ?> records</p>
            </div>
        </div>
        
        <!-- 8. Net Profit -->
        <div class="stat-card-rv card-profit">
            <div class="stat-icon-rv"><i class="fas fa-chart-line"></i></div>
            <div>
                <p class="stat-label-rv"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></p>
                <p class="stat-value-rv">TSh <?= number_format(abs($net_profit), 0) ?></p>
                <p class="stat-sub-rv"><?= $profit_percentage ?>% margin</p>
            </div>
        </div>
        
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar-rv animate-fade-in-up-rv" style="animation-delay:0.1s;">
        <span class="filter-label-rv"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;width:100%;">
            <select name="branch" onchange="this.form.submit()" style="flex:1;min-width:200px;">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-rv btn-primary-rv">
                <i class="fas fa-search"></i> Apply Filter
            </button>
            
            <a href="revenue.php" class="btn-rv btn-outline-rv">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- CHARTS -->
    <div class="chart-grid-rv animate-fade-in-up-rv" style="animation-delay:0.15s;">
        
        <div class="chart-card-rv">
            <div class="chart-header-rv">
                <span class="chart-title-rv">
                    <i class="fas fa-calendar-alt"></i> Monthly Revenue
                </span>
                <span class="chart-total-rv">Last 12 months</span>
            </div>
            <div class="chart-body-rv">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <div class="chart-card-rv">
            <div class="chart-header-rv">
                <span class="chart-title-rv">
                    <i class="fas fa-calendar-day"></i> Daily Revenue
                </span>
                <span class="chart-total-rv">Last 30 days</span>
            </div>
            <div class="chart-body-rv">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
    </div>

    <!-- REVENUE BREAKDOWN TABLE -->
    <div class="table-card-rv animate-fade-in-up-rv" style="animation-delay:0.2s;">
        <div class="table-header-rv">
            <span class="title-rv"><i class="fas fa-list"></i> Revenue Breakdown by Source</span>
            <span class="count-rv">Total: TSh <?= number_format($total_revenue, 0) ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">% of Total</th>
                        <th style="text-align:right;">Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span style="color:#0B5ED7;">●</span> Patient Bills</td>
                        <td style="text-align:right;font-weight:600;color:#0B5ED7;">TSh <?= number_format($patient_bills_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($patient_bills_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($patient_bills_count) ?></td>
                    </tr>
                    <tr>
                        <td><span style="color:#0891B2;">●</span> OTC Sales</td>
                        <td style="text-align:right;font-weight:600;color:#0891B2;">TSh <?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($otc_count) ?></td>
                    </tr>
                    <tr style="background:var(--rv-primary-bg);">
                        <td><span style="color:#7C3AED;">●</span> Prescriptions <span style="font-size:0.6rem;color:var(--rv-text-secondary);">(from paid bills)</span></td>
                        <td style="text-align:right;font-weight:600;color:#7C3AED;">TSh <?= number_format($prescription_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($prescription_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($prescription_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#059669;">●</span> Consultation</td>
                        <td style="text-align:right;font-weight:500;color:#059669;">TSh <?= number_format($consultation_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($consultation_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($consultation_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#7C3AED;">●</span> Lab Tests</td>
                        <td style="text-align:right;font-weight:500;color:#7C3AED;">TSh <?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($lab_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#D97706;">●</span> Medications</td>
                        <td style="text-align:right;font-weight:500;color:#D97706;">TSh <?= number_format($medication_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($medication_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($medication_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#0D9488;">●</span> Procedures</td>
                        <td style="text-align:right;font-weight:500;color:#0D9488;">TSh <?= number_format($procedure_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($procedure_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($procedure_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#64748B;">●</span> Registration</td>
                        <td style="text-align:right;font-weight:500;color:#64748B;">TSh <?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($registration_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($registration_count) ?></td>
                    </tr>
                    <!-- EXPENSES ROW -->
                    <tr style="background:var(--rv-rose-bg);">
                        <td><span style="color:#E11D48;">●</span> <strong>Total Expenses</strong></td>
                        <td style="text-align:right;font-weight:700;color:#E11D48;">- TSh <?= number_format($total_expenses, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($expenses_count) ?></td>
                    </tr>
                    <tr class="total-row-rv">
                        <td style="font-weight:700;font-size:0.95rem;">NET <?= $net_profit >= 0 ? 'PROFIT' : 'LOSS' ?></td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:<?= $net_profit >= 0 ? 'var(--rv-success)' : 'var(--rv-danger)' ?>;">
                            TSh <?= number_format(abs($net_profit), 0) ?>
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:<?= $net_profit >= 0 ? 'var(--rv-success)' : 'var(--rv-danger)' ?>;">
                            <?= $profit_percentage ?>%
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--rv-primary);"><?= number_format($total_transactions) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-rv">
        <p>
            <span class="footer-brand-rv">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Revenue Report
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        
        // Monthly Chart
        var ctxMonthly = document.getElementById('monthlyChart')?.getContext('2d');
        if (ctxMonthly && typeof Chart !== 'undefined') {
            new Chart(ctxMonthly, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($monthly_labels) ?>,
                    datasets: [
                        { label: 'Patient Bills', data: <?= json_encode($monthly_patient) ?>, backgroundColor: '#0B5ED7', borderRadius: 3, barPercentage: 0.3 },
                        { label: 'OTC', data: <?= json_encode($monthly_otc) ?>, backgroundColor: '#0891B2', borderRadius: 3, barPercentage: 0.3 },
                        { label: 'Prescriptions', data: <?= json_encode($monthly_prescription) ?>, backgroundColor: '#7C3AED', borderRadius: 3, barPercentage: 0.3 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 8, weight: '600' }, boxWidth: 10, padding: 6, color: textColor } },
                        tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': TSh ' + context.raw.toLocaleString(); } } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: function(value) { return 'TSh ' + value.toLocaleString(); }, font: { size: 8 }, color: textColor }, grid: { color: gridColor } },
                        x: { grid: { display: false }, ticks: { font: { size: 8 }, color: textColor } }
                    }
                }
            });
        }
        
        // Daily Chart
        var ctxDaily = document.getElementById('dailyChart')?.getContext('2d');
        if (ctxDaily && typeof Chart !== 'undefined') {
            new Chart(ctxDaily, {
                type: 'line',
                data: {
                    labels: <?= json_encode($daily_labels) ?>,
                    datasets: [
                        { label: 'Patient Bills', data: <?= json_encode($daily_patient) ?>, borderColor: '#0B5ED7', backgroundColor: 'rgba(11, 94, 215, 0.08)', fill: true, tension: 0.4, pointRadius: 1.5, borderWidth: 2 },
                        { label: 'OTC', data: <?= json_encode($daily_otc) ?>, borderColor: '#0891B2', backgroundColor: 'rgba(8, 145, 178, 0.08)', fill: true, tension: 0.4, pointRadius: 1.5, borderWidth: 2 },
                        { label: 'Prescriptions', data: <?= json_encode($daily_prescription) ?>, borderColor: '#7C3AED', backgroundColor: 'rgba(124, 58, 237, 0.08)', fill: true, tension: 0.4, pointRadius: 1.5, borderWidth: 2, borderDash: [4, 4] }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 8, weight: '600' }, boxWidth: 10, padding: 6, color: textColor } },
                        tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': TSh ' + context.raw.toLocaleString(); } } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: function(value) { return 'TSh ' + value.toLocaleString(); }, font: { size: 8 }, color: textColor }, grid: { color: gridColor } },
                        x: { grid: { display: false }, ticks: { font: { size: 7 }, color: textColor, maxTicksLimit: 15 } }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        }
    });

    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c🏥 Braick - Revenue Report (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🧪 Lab Tests: TSh <?= number_format($lab_revenue, 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c👨‍⚕️ Consultation: TSh <?= number_format($consultation_revenue, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Prescriptions: TSh <?= number_format($prescription_revenue, 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Only PAID items are counted', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>