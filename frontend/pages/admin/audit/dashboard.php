<?php
// ================================================================
// FILE: frontend/pages/admin/audit/dashboard.php
// ADMIN - AUDIT DASHBOARD V8 - FIXED
// ✅ Patient Bills inajumuisha PREMIUM (b.total_amount)
// ✅ Other Bills HAINA PREMIUM (procedure + registration + equipment)
// ✅ 8 CARDS COMPACT SIZE
// ✅ BLUE THEME (#0B5ED7)
// ✅ No double counting
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_username = $_SESSION['username'] ?? 'admin';

$selected_branch_id = $_GET['branch'] ?? 'all';

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// BRANCH CONDITIONS
// ================================================================
$branch_cond = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_cond = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

$bi_branch_cond = "";
$bi_branch_params = [];
if ($selected_branch_id !== 'all') {
    $bi_branch_cond = " AND bi.branch_id = ?";
    $bi_branch_params[] = (int)$selected_branch_id;
}

$b_branch_cond = "";
$b_branch_params = [];
if ($selected_branch_id !== 'all') {
    $b_branch_cond = " AND b.branch_id = ?";
    $b_branch_params[] = (int)$selected_branch_id;
}

$o_branch_cond = "";
$o_branch_params = [];
if ($selected_branch_id !== 'all') {
    $o_branch_cond = " AND branch_id = ?";
    $o_branch_params[] = (int)$selected_branch_id;
}

// ================================================================
// STATS - 8 CARDS
// ================================================================
$stats = [
    'total_revenue' => 0,
    'prescription_revenue' => 0,
    'otc_revenue' => 0,
    'lab_revenue' => 0,
    'other_revenue' => 0,
    'consultation_revenue' => 0,
    'medication_revenue' => 0,
    'procedure_revenue' => 0,
    'registration_revenue' => 0,
    'equipment_revenue' => 0,
    'premium_revenue' => 0,
    'total_expenses' => 0,
    'profit' => 0,
    'total_bills' => 0,
    'prescription_count' => 0,
    'otc_count' => 0,
    'lab_count' => 0,
    'other_count' => 0,
    'consultation_count' => 0,
    'medication_count' => 0,
    'expenses_count' => 0,
    'today_revenue' => 0,
    'today_expenses' => 0,
    'today_profit' => 0,
    'total_patients' => 0,
    'today_patients' => 0,
    'total_medicines_sold' => 0,
    'total_doctors' => 0,
    'total_reception' => 0,
    'total_audit_logs' => 0,
    'today_audit_logs' => 0
];

try {
    
    // ============================================================
    // ✅ 1. PATIENT BILLS REVENUE (INCLUDES PREMIUM)
    // ============================================================
    $patient_bills_revenue = 0;
    $patient_bills_count = 0;
    
    $sql = "SELECT COALESCE(SUM(b.total_amount), 0) as total, 
                   COUNT(DISTINCT b.id) as count
            FROM bills b
            WHERE b.status = 'paid'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            $b_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($b_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($row['total'] ?? 0);
    $patient_bills_count = (int)($row['count'] ?? 0);
    
    // ============================================================
    // ✅ 2. CATEGORY BREAKDOWN (from bill_items)
    // ============================================================
    
    // 2a. MEDICATIONS
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count,
                   COALESCE(SUM(bi.quantity), 0) as qty
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'medication'
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['medication_revenue'] = (float)($row['total'] ?? 0);
    $stats['medication_count'] = (int)($row['count'] ?? 0);
    $stats['total_medicines_sold'] = (int)($row['qty'] ?? 0);
    
    // 2b. PRESCRIPTION REVENUE
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.reference_type = 'prescription'
            AND bi.item_type = 'medication'
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['prescription_revenue'] = (float)($row['total'] ?? 0);
    $stats['prescription_count'] = (int)($row['count'] ?? 0);
    
    // 2c. LAB TESTS
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'lab_test'
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['lab_revenue'] = (float)($row['total'] ?? 0);
    $stats['lab_count'] = (int)($row['count'] ?? 0);
    
    // 2d. CONSULTATION
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'consultation'
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['consultation_revenue'] = (float)($row['total'] ?? 0);
    $stats['consultation_count'] = (int)($row['count'] ?? 0);
    
    // 2e. PROCEDURES
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'procedure'
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $stats['procedure_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // 2f. REGISTRATION
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type = 'registration'
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $stats['registration_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // 2g. EQUIPMENT / TOOL / OTHER
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.item_type IN ('equipment', 'tool', 'other')
            AND b.patient_id IS NOT NULL
            $bi_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $stats['equipment_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // ============================================================
    // ✅ 3. PREMIUM REVENUE (Separate tracking only)
    // ============================================================
    $sql = "SELECT COALESCE(SUM(b.premium_amount), 0) as total,
                   COUNT(DISTINCT b.id) as count
            FROM bills b
            WHERE b.status = 'paid'
            AND b.patient_id IS NOT NULL
            AND b.premium_amount > 0
            $b_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($b_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['premium_revenue'] = (float)($row['total'] ?? 0);
    
    // ✅ OTHER BILLS = procedure + registration + equipment (NO PREMIUM)
    $stats['other_revenue'] = 
        $stats['procedure_revenue'] + 
        $stats['registration_revenue'] + 
        $stats['equipment_revenue'];
    $stats['other_count'] = $stats['consultation_count']; // placeholder
    
    // ============================================================
    // ✅ 4. OTC REVENUE
    // ============================================================
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales 
            WHERE payment_status = 'paid' $o_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($o_branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['otc_revenue'] = (float)($row['total'] ?? 0);
    $stats['otc_count'] = (int)($row['count'] ?? 0);
    
    // ============================================================
    // ✅ 5. TOTAL REVENUE
    // ============================================================
    $stats['total_revenue'] = $patient_bills_revenue + $stats['otc_revenue'];
    $stats['total_bills'] = $patient_bills_count;
    
    // ============================================================
    // ✅ 6. EXPENSES
    // ============================================================
    $sql = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count 
            FROM expenses 
            WHERE status = 'paid' $branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_expenses'] = (float)($row['total'] ?? 0);
    $stats['expenses_count'] = (int)($row['count'] ?? 0);
    
    // ============================================================
    // ✅ 7. PROFIT
    // ============================================================
    $stats['profit'] = $stats['total_revenue'] - $stats['total_expenses'];
    
    // ============================================================
    // ✅ 8. TODAY'S STATS
    // ============================================================
    $sql = "SELECT COALESCE(SUM(b.total_amount), 0) as total
            FROM bills b
            WHERE b.status = 'paid'
            AND DATE(b.updated_at) = CURDATE()
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            $b_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($b_branch_params);
    $today_bills = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales 
            WHERE payment_status = 'paid' AND DATE(updated_at) = CURDATE() $o_branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($o_branch_params);
    $today_otc = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stats['today_revenue'] = $today_bills + $today_otc;
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
            WHERE status = 'paid' AND DATE(payment_date) = CURDATE() $branch_cond";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['today_expenses'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stats['today_profit'] = $stats['today_revenue'] - $stats['today_expenses'];
    
    // ============================================================
    // ✅ 9. OTHER STATS
    // ============================================================
    $p_branch = $selected_branch_id !== 'all' ? " AND branch_id = ?" : "";
    
    $sql = "SELECT COUNT(*) as count FROM patients WHERE 1=1 $p_branch";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['total_patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $sql = "SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE() $p_branch";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['today_patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' $p_branch";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['total_doctors'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'reception' AND status = 'active' $p_branch";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['total_reception'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $al_branch = $selected_branch_id !== 'all' ? " AND branch_id = ?" : "";
    $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE 1=1 $al_branch";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['total_audit_logs'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE() $al_branch";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $stats['today_audit_logs'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
}

// ================================================================
// MONTHLY SALES (12 months)
// ================================================================
$monthly_sales = [];
$month_labels = [];
$monthly_bills = [];
$monthly_otc = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    
    $month_labels[] = $month_label;
    
    $sql = "SELECT COALESCE(SUM(b.total_amount), 0) as total
            FROM bills b
            WHERE b.status = 'paid'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            AND DATE_FORMAT(b.updated_at, '%Y-%m') = ? $b_branch_cond";
    $params_b = array_merge([$month], $b_branch_params);
    $stmt = $db->prepare($sql); $stmt->execute($params_b);
    $bills_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total
            FROM otc_sales
            WHERE payment_status = 'paid'
            AND DATE_FORMAT(updated_at, '%Y-%m') = ? $o_branch_cond";
    $params_o = array_merge([$month], $o_branch_params);
    $stmt = $db->prepare($sql); $stmt->execute($params_o);
    $otc_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $monthly_bills[] = $bills_total;
    $monthly_otc[] = $otc_total;
    $monthly_sales[] = $bills_total + $otc_total;
}

// ================================================================
// HOURLY SALES (Today)
// ================================================================
$hourly_sales = [];
for ($h = 0; $h < 24; $h++) {
    $hourly_sales[$h] = ['hour' => $h, 'total' => 0];
}

try {
    $sql = "SELECT HOUR(b.updated_at) as hour, 
            COALESCE(SUM(b.total_amount), 0) as total
            FROM bills b
            WHERE b.status = 'paid'
            AND DATE(b.updated_at) = CURDATE()
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            $b_branch_cond
            GROUP BY HOUR(b.updated_at)";
    $stmt = $db->prepare($sql); $stmt->execute($b_branch_params);
    $hourly_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hourly_bills as $row) {
        $hourly_sales[(int)$row['hour']]['total'] += (float)$row['total'];
    }
    
    $sql = "SELECT HOUR(updated_at) as hour, COALESCE(SUM(total_amount), 0) as total
            FROM otc_sales WHERE payment_status = 'paid' AND DATE(updated_at) = CURDATE() 
            $o_branch_cond
            GROUP BY HOUR(updated_at)";
    $stmt = $db->prepare($sql); $stmt->execute($o_branch_params);
    $hourly_otc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hourly_otc as $row) {
        $hourly_sales[(int)$row['hour']]['total'] += (float)$row['total'];
    }
} catch (Exception $e) {}

// ================================================================
// TOP MEDICINES
// ================================================================
$top_medicines = [];
try {
    $sql = "SELECT bi.item_name as medication_name, 
            SUM(bi.quantity) as total_qty, 
            COUNT(*) as times_sold, 
            COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total_revenue
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.item_type = 'medication' 
            AND b.status = 'paid'
            $bi_branch_cond
            GROUP BY bi.item_name 
            ORDER BY total_qty DESC 
            LIMIT 10";
    $stmt = $db->prepare($sql); $stmt->execute($bi_branch_params);
    $top_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// DOCTOR PERFORMANCE
// ================================================================
$doctor_performance = [];
try {
    $d_branch = $selected_branch_id !== 'all' ? " AND u.branch_id = ?" : "";
    $sql = "SELECT u.id, u.full_name as doctor_name, 
            COUNT(DISTINCT v.id) as total_visits,
            COUNT(DISTINCT p.id) as total_prescriptions
            FROM users u
            LEFT JOIN visits v ON v.doctor_id = u.id
            LEFT JOIN prescriptions p ON p.doctor_id = u.id
            WHERE u.role = 'doctor' AND u.status = 'active' $d_branch
            GROUP BY u.id, u.full_name 
            ORDER BY total_visits DESC 
            LIMIT 10";
    $stmt = $db->prepare($sql); $stmt->execute($branch_params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($doctors as $doc) {
        $d_rev_branch = $selected_branch_id !== 'all' ? " AND b.branch_id = ?" : "";
        $sql2 = "SELECT COALESCE(SUM(b.total_amount), 0) as total
                 FROM bills b
                 INNER JOIN visits v ON b.visit_id = v.id
                 WHERE v.doctor_id = ? 
                 AND b.status = 'paid' $d_rev_branch";
        $params2 = array_merge([$doc['id']], $branch_params);
        $stmt2 = $db->prepare($sql2); $stmt2->execute($params2);
        $doc['total_revenue'] = (float)($stmt2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $doctor_performance[] = $doc;
    }
} catch (Exception $e) {}

// ================================================================
// RECENT TRANSACTIONS
// ================================================================
$recent_transactions = [];
try {
    $sql = "
        (SELECT 
            'bill' as trans_type,
            b.id,
            b.bill_number as reference_number,
            b.total_amount,
            b.payment_method,
            b.status,
            b.updated_at,
            p.full_name as customer_name,
            u.full_name as received_by_name
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.status = 'paid' 
        AND b.patient_id IS NOT NULL 
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%' $b_branch_cond
        ORDER BY b.updated_at DESC LIMIT 5)
        
        UNION ALL
        
        (SELECT 
            'otc' as trans_type,
            o.id,
            o.sale_number as reference_number,
            o.total_amount,
            o.payment_method,
            o.payment_status as status,
            o.updated_at,
            COALESCE(o.customer_name, 'Walk-in') as customer_name,
            u2.full_name as received_by_name
        FROM otc_sales o
        LEFT JOIN users u2 ON o.sold_by = u2.id
        WHERE o.payment_status = 'paid' $o_branch_cond
        ORDER BY o.updated_at DESC LIMIT 5)
        
        ORDER BY updated_at DESC LIMIT 10
    ";
    
    $all_params = [];
    if ($selected_branch_id !== 'all') {
        $all_params = [(int)$selected_branch_id, (int)$selected_branch_id];
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($all_params);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Recent transactions error: " . $e->getMessage());
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
/* ================================================================
   BLUE THEME + FONTS
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --primary-soft: #DBEAFE;
    
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --primary-soft: #1E40AF;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: var(--font-primary);
}

html, body {
    font-family: var(--font-primary);
    -webkit-font-smoothing: antialiased;
}

.money-cell, .card-value, .rank-badge, .font-mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
}

/* PAGE HEADER */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}

.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
    position: relative;
    z-index: 1;
}

.page-header .role-badge {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.page-header .branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.1);
}

.btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.btn-header:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-2px);
}

.btn-back-admin {
    background: linear-gradient(135deg, #FCD34D, #F59E0B) !important;
    color: #78350F !important;
    font-weight: 800 !important;
    border: 2px solid rgba(255,255,255,0.3) !important;
}

/* 8 CARDS GRID */
.stats-grid-8 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 14px 16px;
    border: 2px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    min-height: 130px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    transition: height 0.3s ease;
}

.stat-card:hover::before { height: 5px; }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.stat-card .card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}

.stat-card:hover .card-icon {
    transform: scale(1.1) rotate(-5deg);
}

.stat-card .card-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 8px;
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.stat-card .card-label {
    font-size: 0.62rem;
    color: var(--text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-card .card-value {
    font-size: 1.25rem;
    font-weight: 900;
    color: var(--text-primary);
    line-height: 1.1;
    letter-spacing: -0.03em;
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex-wrap: wrap;
}

.stat-card .card-value .currency {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-secondary);
    font-family: var(--font-primary);
}

.stat-card .card-footer {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.stat-card .card-footer .highlight {
    color: var(--text-primary);
    font-weight: 800;
    font-family: var(--font-mono);
}

/* CARD COLORS */
.stat-card.revenue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.revenue:hover { border-color: #0B5ED7; }
.stat-card.revenue .card-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.revenue .card-badge { background: var(--primary-bg); color: var(--primary); }
.stat-card.revenue .card-value { color: var(--primary); }
[data-theme="dark"] .stat-card.revenue .card-value { color: #60A5FA; }

.stat-card.prescription::before { background: linear-gradient(90deg, #7C3AED, #A78BFA, #7C3AED); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.prescription:hover { border-color: #7C3AED; }
.stat-card.prescription .card-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.prescription .card-badge { background: var(--purple-bg); color: var(--purple); }
.stat-card.prescription .card-value { color: var(--purple); }

.stat-card.otc::before { background: linear-gradient(90deg, #0891B2, #06B6D4, #0891B2); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.otc:hover { border-color: #0891B2; }
.stat-card.otc .card-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.otc .card-badge { background: var(--cyan-bg); color: var(--cyan); }
.stat-card.otc .card-value { color: var(--cyan); }

.stat-card.lab::before { background: linear-gradient(90deg, #3B82F6, #93C5FD, #3B82F6); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.lab:hover { border-color: #3B82F6; }
.stat-card.lab .card-icon { background: linear-gradient(135deg, #3B82F6, #93C5FD); }
.stat-card.lab .card-badge { background: var(--primary-soft); color: var(--primary); }
.stat-card.lab .card-value { color: var(--primary); }

.stat-card.other::before { background: linear-gradient(90deg, #D97706, #FBBF24, #D97706); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.other:hover { border-color: #D97706; }
.stat-card.other .card-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-card.other .card-badge { background: var(--warning-bg); color: var(--warning); }
.stat-card.other .card-value { color: var(--warning); }

.stat-card.consultation::before { background: linear-gradient(90deg, #059669, #34D399, #059669); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.consultation:hover { border-color: #059669; }
.stat-card.consultation .card-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.consultation .card-badge { background: var(--success-bg); color: var(--success); }
.stat-card.consultation .card-value { color: var(--success); }

.stat-card.expenses::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.expenses:hover { border-color: #DC2626; }
.stat-card.expenses .card-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.expenses .card-badge { background: var(--danger-bg); color: var(--danger); }
.stat-card.expenses .card-value { color: var(--danger); }

.stat-card.profit {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card) 70%, rgba(11, 94, 215, 0.05) 100%);
}
.stat-card.profit::before { background: linear-gradient(90deg, #0B5ED7, #10B981, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit:hover { border-color: #0B5ED7; }
.stat-card.profit .card-icon { background: linear-gradient(135deg, #0B5ED7, #10B981); }
.stat-card.profit .card-badge { background: var(--primary-bg); color: var(--primary); }
.stat-card.profit .card-value { color: var(--primary); }
[data-theme="dark"] .stat-card.profit .card-value { color: #60A5FA; }

.stat-card.profit.loss .card-value { color: var(--danger); }
.stat-card.profit.loss::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit.loss .card-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.profit.loss .card-badge { background: var(--danger-bg); color: var(--danger); }

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.pulse-dot {
    display: inline-block;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
    margin-right: 3px;
    animation: pulseDot 1.5s infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* SECTION CARDS */
.section-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 18px 20px;
    border: 2px solid var(--border-color);
    margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.section-card:hover {
    box-shadow: var(--shadow-md);
    border-color: #0B5ED7;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 2px dashed var(--border-color);
    flex-wrap: wrap;
    gap: 8px;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i { color: var(--primary); font-size: 1rem; }

.filter-btn {
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 700;
    border: 2px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
}

.chart-container { position: relative; height: 260px; width: 100%; }

.chart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 18px;
    margin-bottom: 18px;
}

.data-table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 0.78rem;
}

.data-table thead th {
    text-align: left;
    padding: 9px 12px;
    font-weight: 800;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}

.data-table thead th:first-child { border-radius: 8px 0 0 0; }
.data-table thead th:last-child { border-radius: 0 8px 0 0; }

.data-table tbody td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}

.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

.money-cell {
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--success);
    text-align: right;
}

.money-cell .currency-prefix {
    font-size: 0.65rem;
    color: var(--text-secondary);
    margin-right: 2px;
    font-family: var(--font-primary);
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-weight: 800;
    font-size: 0.65rem;
    background: var(--primary-bg);
    color: var(--primary);
}

.rank-badge.gold { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; }
.rank-badge.silver { background: linear-gradient(135deg, #E5E7EB, #9CA3AF); color: #374151; }
.rank-badge.bronze { background: linear-gradient(135deg, #FBBF24, #D97706); color: #78350F; }

.progress-bar {
    width: 100%;
    height: 4px;
    background: var(--border-color);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 4px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0B5ED7, #3B82F6);
    border-radius: 2px;
    transition: width 0.8s ease;
}

.tx-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
}

.tx-type-badge.bill {
    background: #DBEAFE;
    color: #1E40AF;
    border: 1px solid #93C5FD;
}

.tx-type-badge.otc {
    background: #CFFAFE;
    color: #0E7490;
    border: 1px solid #67E8F9;
}

[data-theme="dark"] .tx-type-badge.bill {
    background: #1E3A8A;
    color: #93C5FD;
    border-color: #3B82F6;
}

[data-theme="dark"] .tx-type-badge.otc {
    background: #0E3A47;
    color: #67E8F9;
    border-color: #06B6D4;
}

.btn-action {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
}

.btn-action:hover { transform: translateY(-2px) scale(1.08); }

.btn-action.view { background: rgba(11, 94, 215, 0.15); color: #0B5ED7; }
.btn-action.view:hover { background: #0B5ED7; color: white; box-shadow: 0 4px 10px rgba(11, 94, 215, 0.4); }

@media (max-width: 1200px) {
    .stats-grid-8 { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
    .chart-grid { grid-template-columns: 1fr; }
    .stats-grid-8 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid-8 { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .stat-card .card-icon { width: 32px; height: 32px; font-size: 0.9rem; }
    .stat-card .card-value { font-size: 1.1rem; }
    .section-card { padding: 14px; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th,
    .data-table tbody td { padding: 7px 8px; }
}

@media (max-width: 480px) {
    .stats-grid-8 { grid-template-columns: 1fr; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shield-alt"></i>
                Audit Dashboard
                <span class="role-badge"><i class="fas fa-user-shield"></i> ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-chart-line"></i>
                Financial Overview
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> 
                    <?= $selected_branch_id === 'all' ? 'All Branches' : htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar-alt"></i> <?= date('d M Y') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-clock"></i> <span id="liveTime"><?= date('h:i:s A') ?></span>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
               class="btn-header btn-back-admin">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-chart-line"></i> Reports
            </a>
            <button onclick="window.location.reload()" class="btn-header">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- 8 CARDS -->
    <div class="stats-grid-8">

        <!-- CARD 1: TOTAL REVENUE -->
        <div class="stat-card revenue">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                <span class="card-badge"><span class="pulse-dot"></span>LIVE</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-coins"></i> Total Revenue</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['total_revenue'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-calendar-day"></i>
                Today: <span class="highlight"><?= $currency ?> <?= number_format($stats['today_revenue'], 0) ?></span>
            </div>
        </div>

        <!-- CARD 2: PATIENT BILLS (INCLUDES PREMIUM) -->
        <div class="stat-card revenue">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
                <span class="card-badge"><i class="fas fa-star"></i> +PREMIUM</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-file-invoice"></i> Patient Bills</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($patient_bills_revenue, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-check-circle"></i>
                Bills: <span class="highlight"><?= number_format($patient_bills_count) ?></span>
                <?php if ($stats['premium_revenue'] > 0): ?>
                    <span style="color:#F59E0B;">• ⭐ <?= number_format($stats['premium_revenue'], 0) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- CARD 3: PRESCRIPTION -->
        <div class="stat-card prescription">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-prescription"></i></div>
                <span class="card-badge"><i class="fas fa-pills"></i> Rx</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-prescription-bottle-medical"></i> Prescription</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['prescription_revenue'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list"></i>
                Items: <span class="highlight"><?= number_format($stats['prescription_count']) ?></span>
            </div>
        </div>

        <!-- CARD 4: OTC -->
        <div class="stat-card otc">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-store"></i></div>
                <span class="card-badge"><i class="fas fa-cash-register"></i> OTC</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-shopping-cart"></i> OTC Sale</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['otc_revenue'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-receipt"></i>
                Count: <span class="highlight"><?= number_format($stats['otc_count']) ?></span>
            </div>
        </div>

        <!-- CARD 5: LAB TESTS -->
        <div class="stat-card lab">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-flask"></i></div>
                <span class="card-badge"><i class="fas fa-vial"></i> LAB</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-microscope"></i> Lab Tests</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['lab_revenue'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-check-circle"></i>
                Tests: <span class="highlight"><?= number_format($stats['lab_count']) ?></span>
            </div>
        </div>

        <!-- CARD 6: CONSULTATION -->
        <div class="stat-card consultation">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-stethoscope"></i></div>
                <span class="card-badge"><i class="fas fa-user-md"></i> CONS</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-user-doctor"></i> Consultation</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['consultation_revenue'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-notes-medical"></i>
                Total: <span class="highlight"><?= number_format($stats['consultation_count']) ?></span>
            </div>
        </div>

        <!-- CARD 7: OTHER BILLS (NO PREMIUM - Proc + Reg + Equip) -->
        <div class="stat-card other">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <span class="card-badge"><i class="fas fa-folder-open"></i> OTHER</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-folder-open"></i> Other Bills</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['other_revenue'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list-ul"></i>
                Proc + Reg + Equip
            </div>
        </div>

        <!-- CARD 8: EXPENSES -->
        <div class="stat-card expenses">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-receipt"></i></div>
                <span class="card-badge"><i class="fas fa-arrow-down"></i> OUT</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-wallet"></i> Total Expenses</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($stats['total_expenses'], 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list"></i>
                Records: <span class="highlight"><?= number_format($stats['expenses_count']) ?></span>
            </div>
        </div>

        <!-- CARD 9: PROFIT -->
        <div class="stat-card profit <?= $stats['profit'] < 0 ? 'loss' : '' ?>">
            <div class="card-top">
                <div class="card-icon">
                    <i class="fas fa-<?= $stats['profit'] >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i>
                </div>
                <span class="card-badge">
                    <i class="fas fa-<?= $stats['profit'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                    <?= $stats['profit'] >= 0 ? 'PROFIT' : 'LOSS' ?>
                </span>
            </div>
            <div>
                <div class="card-label">
                    <i class="fas fa-balance-scale"></i>
                    <?= $stats['profit'] >= 0 ? 'Net Profit' : 'Net Loss' ?>
                </div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format(abs($stats['profit']), 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-percentage"></i>
                Margin: <span class="highlight">
                    <?= $stats['total_revenue'] > 0 ? round(($stats['profit'] / $stats['total_revenue']) * 100, 1) : 0 ?>%
                </span>
            </div>
        </div>

    </div>

    <!-- CHARTS -->
    <div class="chart-grid">
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Monthly Revenue (12 Months) — Bills + OTC
                </div>
                <span style="font-size:0.68rem;color:var(--text-secondary);background:var(--primary-bg);padding:4px 10px;border-radius:8px;font-weight:600;">
                    Total: <?= $currency ?> <?= number_format(array_sum($monthly_sales), 0) ?>
                </span>
            </div>
            <div class="chart-container">
                <canvas id="monthlySalesChart"></canvas>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-clock"></i>
                    Today's Hourly (Bills + OTC)
                </div>
            </div>
            <div class="chart-container">
                <canvas id="hourlySalesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- TOP MEDICINES + RECENT TRANSACTIONS -->
    <div class="chart-grid">
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-trophy"></i>
                    Top 10 Selling Medicines (Bill Items)
                </div>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="filter-btn">
                    <i class="fas fa-arrow-right"></i> All
                </a>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">Rank</th>
                            <th>Medicine</th>
                            <th style="text-align:right;">Qty</th>
                            <th style="text-align:right;">Times</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_medicines) > 0): ?>
                            <?php $rank = 1; foreach ($top_medicines as $med): 
                                $rank_class = '';
                                if ($rank == 1) $rank_class = 'gold';
                                elseif ($rank == 2) $rank_class = 'silver';
                                elseif ($rank == 3) $rank_class = 'bronze';
                            ?>
                                <tr>
                                    <td><span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span></td>
                                    <td>
                                        <div style="font-weight:700;font-size:0.75rem;"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width:<?= min(100, ($med['total_qty'] / max(1, $top_medicines[0]['total_qty'])) * 100) ?>%"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:right;font-weight:800;color:var(--primary);font-family:var(--font-mono);"><?= number_format($med['total_qty'] ?? 0) ?></td>
                                    <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $med['times_sold'] ?? 0 ?>×</td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($med['total_revenue'] ?? 0, 0) ?>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                    <i class="fas fa-chart-bar" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                                    No data
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Transactions
                </div>
                <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="filter-btn">
                    <i class="fas fa-arrow-right"></i> All
                </a>
            </div>
            <div style="overflow-x:auto;max-height:400px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th style="text-align:right;">Amount</th>
                            <th style="text-align:center;">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_transactions) > 0): ?>
                            <?php foreach ($recent_transactions as $txn): 
                                $is_otc = ($txn['trans_type'] ?? '') === 'otc';
                            ?>
                                <tr>
                                    <td>
                                        <span style="font-family:var(--font-mono);font-size:0.65rem;color:var(--primary);font-weight:700;background:var(--primary-bg);padding:2px 6px;border-radius:5px;display:inline-block;">
                                            <?= htmlspecialchars($txn['reference_number'] ?? 'N/A') ?>
                                        </span>
                                        <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;">
                                            <?= date('d M H:i', strtotime($txn['updated_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tx-type-badge <?= $is_otc ? 'otc' : 'bill' ?>">
                                            <i class="fas fa-<?= $is_otc ? 'store' : 'file-invoice' ?>"></i>
                                            <?= $is_otc ? 'OTC' : 'BILL' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;font-size:0.72rem;">
                                            <?= htmlspecialchars($txn['customer_name'] ?? 'Walk-in') ?>
                                        </div>
                                    </td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($txn['total_amount'] ?? 0, 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($is_otc): ?>
                                            <a href="view_otc.php?id=<?= $txn['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action view" title="View" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="view_bill.php?id=<?= $txn['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action view" title="View" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                    <i class="fas fa-receipt" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                                    No transactions
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DOCTOR PERFORMANCE + BREAKDOWN -->
    <div class="chart-grid">
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-user-md"></i>
                    Doctor Performance
                </div>
                <a href="employees.php?branch=<?= $selected_branch_id ?>" class="filter-btn">
                    <i class="fas fa-arrow-right"></i> All
                </a>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Doctor</th>
                            <th style="text-align:center;">Visits</th>
                            <th style="text-align:center;">Rx</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($doctor_performance) > 0): ?>
                            <?php $rank = 1; foreach ($doctor_performance as $doc): 
                                $rank_class = '';
                                if ($rank == 1) $rank_class = 'gold';
                                elseif ($rank == 2) $rank_class = 'silver';
                                elseif ($rank == 3) $rank_class = 'bronze';
                            ?>
                                <tr>
                                    <td><span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span></td>
                                    <td>
                                        <div style="font-weight:700;font-size:0.75rem;">Dr. <?= htmlspecialchars($doc['doctor_name'] ?? 'N/A') ?></div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width:<?= min(100, ($doc['total_visits'] / max(1, $doctor_performance[0]['total_visits'])) * 100) ?>%"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);"><?= number_format($doc['total_visits'] ?? 0) ?></td>
                                    <td style="text-align:center;font-weight:700;color:var(--purple);font-family:var(--font-mono);"><?= number_format($doc['total_prescriptions'] ?? 0) ?></td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format($doc['total_revenue'] ?? 0, 0) ?>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                    <i class="fas fa-user-md" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                                    No data
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-pie"></i>
                    Revenue Breakdown
                </div>
            </div>
            <div class="chart-container">
                <canvas id="breakdownChart"></canvas>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// LIVE TIME
function updateLiveTime() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var el = document.getElementById('liveTime');
    if (el) el.textContent = timeStr;
}
updateLiveTime();
setInterval(updateLiveTime, 1000);

// MONTHLY CHART
var monthlyLabels = <?= json_encode($month_labels) ?>;
var monthlyData = <?= json_encode($monthly_sales) ?>;

if (monthlyLabels.length === 0) {
    monthlyLabels = ['No Data'];
    monthlyData = [0];
}

var monthlyCtx = document.getElementById('monthlySalesChart');
if (monthlyCtx) {
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Revenue',
                data: monthlyData,
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(11, 94, 215, 0.8)';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, '#0A4CA8');
                    gradient.addColorStop(1, '#3B82F6');
                    return gradient;
                },
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 40
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0B5ED7',
                    titleColor: '#FFFFFF',
                    bodyColor: '#DBEAFE',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return '💰 <?= $currency ?> ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return (value/1000).toFixed(0) + 'K'; },
                        color: '#94A3B8',
                        font: { family: 'JetBrains Mono', size: 10, weight: '600' }
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.1)' }
                },
                x: {
                    ticks: {
                        color: '#94A3B8',
                        font: { family: 'Inter', size: 10, weight: '600' }
                    },
                    grid: { display: false }
                }
            }
        }
    });
}

// HOURLY CHART
var hourlyData = <?= json_encode(array_values($hourly_sales)) ?>;
var hourlyLabels = [];
var hourlyValues = [];

hourlyData.forEach(function(item) {
    hourlyLabels.push(String(item.hour).padStart(2, '0') + ':00');
    hourlyValues.push(parseFloat(item.total));
});

var hourlyCtx = document.getElementById('hourlySalesChart');
if (hourlyCtx) {
    new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: hourlyLabels,
            datasets: [{
                label: 'Revenue',
                data: hourlyValues,
                borderColor: '#0B5ED7',
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(11, 94, 215, 0.15)';
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(11, 94, 215, 0.4)');
                    gradient.addColorStop(1, 'rgba(11, 94, 215, 0.02)');
                    return gradient;
                },
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0B5ED7',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0B5ED7',
                    titleColor: '#FFFFFF',
                    bodyColor: '#DBEAFE',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return '💰 <?= $currency ?> ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return (value/1000).toFixed(0) + 'K'; },
                        color: '#94A3B8',
                        font: { family: 'JetBrains Mono', size: 9, weight: '600' }
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.1)' }
                },
                x: {
                    ticks: {
                        color: '#94A3B8',
                        font: { family: 'Inter', size: 9, weight: '600' },
                        maxRotation: 45
                    },
                    grid: { display: false }
                }
            }
        }
    });
}

// BREAKDOWN CHART
var breakdownCtx = document.getElementById('breakdownChart');
if (breakdownCtx) {
    var breakdownData = [
        <?= $stats['prescription_revenue'] ?>,
        <?= $stats['otc_revenue'] ?>,
        <?= $stats['lab_revenue'] ?>,
        <?= $stats['consultation_revenue'] ?>,
        <?= $stats['other_revenue'] ?>
    ];
    
    var breakdownLabels = ['Prescription', 'OTC Sale', 'Lab Tests', 'Consultation', 'Other'];
    
    var totalBreakdown = breakdownData.reduce((a, b) => a + b, 0);
    
    if (totalBreakdown === 0) {
        breakdownData = [1];
        breakdownLabels = ['No Data'];
    }
    
    new Chart(breakdownCtx, {
        type: 'doughnut',
        data: {
            labels: breakdownLabels,
            datasets: [{
                data: breakdownData,
                backgroundColor: ['#7C3AED', '#0891B2', '#3B82F6', '#059669', '#D97706'],
                borderWidth: 2,
                borderColor: '#FFFFFF',
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'Inter', size: 10, weight: '600' },
                        boxWidth: 10,
                        padding: 8,
                        color: '#64748B',
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: '#0B5ED7',
                    titleColor: '#FFFFFF',
                    bodyColor: '#DBEAFE',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            var value = context.parsed;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return context.label + ': <?= $currency ?> ' + value.toLocaleString() + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

console.log('%c👑 Audit Dashboard V8 - Other Bills (NO PREMIUM)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Patient Bills: b.total_amount (INCLUDES PREMIUM)', 'font-size:12px; color:#FCD34D; font-weight:bold;');
console.log('%c✅ Other Bills: Procedure + Registration + Equipment (NO PREMIUM)', 'font-size:12px; color:#34D399; font-weight:bold;');
console.log('%c💰 Total Revenue: <?= $currency ?> <?= number_format($stats['total_revenue'], 0) ?>', 'font-size:12px; color:#0B5ED7;');
console.log('%c📄 Patient Bills: <?= $currency ?> <?= number_format($patient_bills_revenue, 0) ?>', 'font-size:12px; color:#0B5ED7;');
console.log('%c⭐ Premium (in Patient Bills): <?= $currency ?> <?= number_format($stats['premium_revenue'], 0) ?>', 'font-size:12px; color:#F59E0B;');
console.log('%c📁 Other Bills (no premium): <?= $currency ?> <?= number_format($stats['other_revenue'], 0) ?>', 'font-size:12px; color:#D97706;');
console.log('%c💎 Profit: <?= $currency ?> <?= number_format($stats['profit'], 0) ?>', 'font-size:12px; color:#059669; font-weight:bold;');
</script>

</body>
</html>