<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// SUPER ADMIN DASHBOARD - KAMA REVENUE.PHP
// ✅ Prescriptions, Consultation, Lab Tests = PAID ONLY
// ✅ Cards 8: Revenue, Patient Bills, OTC, Prescriptions,
//             Consultation, Lab Tests, Expenses, Profit
// ✅ BLUE THEME (Revenue, Patient, OTC, Consultation, Stock, Equipment)
// ✅ RED THEME (Expenses, Expiry)
// ✅ GREEN THEME (Profit)
// ✅ FIXED: query() → prepare() + execute() kwa branch filter
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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
} else {
    $selected_branch_id = 'all';
}

// Branch filters
$branch_filter_b = "";
$branch_params_b = [];
if ($selected_branch_id !== 'all') {
    $branch_filter_b = " AND b.branch_id = ?";
    $branch_params_b[] = (int)$selected_branch_id;
}

$branch_filter = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

// ================================================================
// NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

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
// 3. PRESCRIPTION REVENUE - PAID ONLY
// ================================================================
$prescription_revenue = 0;
$prescription_count = 0;
try {
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
    
    // Fallback
    if ($prescription_revenue == 0) {
        $sql = "SELECT COALESCE(SUM(pi.total_price), 0) as total, COUNT(DISTINCT pi.id) as count 
                FROM prescription_items pi
                INNER JOIN prescriptions p ON pi.prescription_id = p.id
                WHERE p.status = 'dispensed'";
        $fb_params = [];
        if ($selected_branch_id !== 'all') {
            $sql .= " AND p.branch_id = ?";
            $fb_params[] = (int)$selected_branch_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($fb_params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $prescription_revenue = $data['total'] ?? 0;
        $prescription_count = $data['count'] ?? 0;
    }
} catch (Exception $e) {}

// ================================================================
// 4. CONSULTATION REVENUE - PAID ONLY
// ================================================================
$consultation_revenue = 0;
$consultation_count = 0;
try {
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
// 5. LAB TESTS REVENUE - PAID ONLY
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
// 6. MEDICATIONS REVENUE - PAID ONLY
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
// 7. PROCEDURES REVENUE - PAID ONLY
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
// 8. REGISTRATION REVENUE - PAID ONLY
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
// 9. TOTAL REVENUE
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
// 11. MEDICATION STOCK ✅ FIXED: prepare() + execute()
// ================================================================
$med_total_items = 0;
$med_total_quantity = 0;
$med_low_stock = 0;
$med_out_of_stock = 0;
try {
    $sql = "
        SELECT 
            COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as total_items,
            COALESCE(SUM(quantity), 0) as total_quantity,
            COUNT(DISTINCT CASE 
                WHEN quantity > 0 AND quantity <= reorder_level 
                AND status = 'active' 
                AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date >= CURDATE())
                THEN CONCAT(medication_name, '-', branch_id)
            END) as low_stock_items,
            COUNT(DISTINCT CASE 
                WHEN quantity <= 0 AND status = 'active'
                THEN CONCAT(medication_name, '-', branch_id)
            END) as out_of_stock_items
        FROM medications_inventory 
        WHERE status = 'active'" . $branch_filter;
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $med_total_items = $stock_data['total_items'] ?? 0;
    $med_total_quantity = $stock_data['total_quantity'] ?? 0;
    $med_low_stock = $stock_data['low_stock_items'] ?? 0;
    $med_out_of_stock = $stock_data['out_of_stock_items'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 12. MEDICATION EXPIRY ✅ FIXED
// ================================================================
$today_date = date('Y-m-d');
$med_expired_quantity = 0;
$med_expiring_quantity = 0;
$med_expired_items = 0;
$med_expiring_items = 0;
try {
    $sql = "
        SELECT 
            COALESCE(SUM(CASE 
                WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
                AND expiry_date < ? THEN quantity ELSE 0 END), 0) as expired_quantity,
            COALESCE(SUM(CASE 
                WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
                AND expiry_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY) 
                THEN quantity ELSE 0 END), 0) as expiring_soon_quantity,
            COUNT(DISTINCT CASE 
                WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
                AND expiry_date < ? THEN CONCAT(medication_name, '-', branch_id) END) as expired_items,
            COUNT(DISTINCT CASE 
                WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
                AND expiry_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY) 
                THEN CONCAT(medication_name, '-', branch_id) END) as expiring_soon_items
        FROM medications_inventory 
        WHERE status = 'active'" . $branch_filter;
    
    $expiry_params = [$today_date, $today_date, $today_date, $today_date, $today_date, $today_date];
    $expiry_params = array_merge($expiry_params, $branch_params);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($expiry_params);
    $expiry_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $med_expired_quantity = $expiry_data['expired_quantity'] ?? 0;
    $med_expiring_quantity = $expiry_data['expiring_soon_quantity'] ?? 0;
    $med_expired_items = $expiry_data['expired_items'] ?? 0;
    $med_expiring_items = $expiry_data['expiring_soon_items'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// 13. MEDICAL EQUIPMENT ✅ FIXED
// ================================================================
$equip_total_items = 0;
$equip_total_quantity = 0;
$equip_low_stock = 0;
$equip_out_of_stock = 0;
$equip_expired_items = 0;
$equip_expiring_items = 0;
try {
    $sql = "
        SELECT 
            COUNT(DISTINCT CONCAT(equipment_name, '-', branch_id)) as total_items,
            COALESCE(SUM(quantity), 0) as total_quantity,
            COUNT(DISTINCT CASE 
                WHEN quantity > 0 AND quantity <= reorder_level AND status = 'active' 
                AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date >= CURDATE())
                THEN CONCAT(equipment_name, '-', branch_id) END) as low_stock_items,
            COUNT(DISTINCT CASE 
                WHEN quantity <= 0 AND status = 'active'
                THEN CONCAT(equipment_name, '-', branch_id) END) as out_of_stock_items,
            COUNT(DISTINCT CASE 
                WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
                AND expiry_date < ? THEN CONCAT(equipment_name, '-', branch_id) END) as expired_items,
            COUNT(DISTINCT CASE 
                WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' 
                AND expiry_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY) 
                THEN CONCAT(equipment_name, '-', branch_id) END) as expiring_soon_items
        FROM medical_equipment 
        WHERE status = 'active'" . $branch_filter;
    
    $equip_params = [$today_date, $today_date, $today_date];
    $equip_params = array_merge($equip_params, $branch_params);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($equip_params);
    $equipment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $equip_total_items = $equipment_data['total_items'] ?? 0;
    $equip_total_quantity = $equipment_data['total_quantity'] ?? 0;
    $equip_low_stock = $equipment_data['low_stock_items'] ?? 0;
    $equip_out_of_stock = $equipment_data['out_of_stock_items'] ?? 0;
    $equip_expired_items = $equipment_data['expired_items'] ?? 0;
    $equip_expiring_items = $equipment_data['expiring_soon_items'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// CHART DATA (Last 7 days) ✅ FIXED
// ================================================================
$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $daily_total = 0;
    
    // Bills
    try {
        $sql = "SELECT COALESCE(SUM(b.paid_amount), 0) as total 
                FROM bills b
                WHERE DATE(b.created_at) = ? 
                AND b.status = 'paid'
                AND b.patient_id IS NOT NULL
                AND b.visit_id IS NOT NULL
                AND b.bill_number NOT LIKE 'BILL-OTC-%'";
        $params_b = [$date];
        if ($selected_branch_id !== 'all') {
            $sql .= " AND b.branch_id = ?";
            $params_b[] = (int)$selected_branch_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params_b);
        $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {}
    
    // OTC
    try {
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                FROM otc_sales 
                WHERE DATE(created_at) = ? 
                AND payment_status = 'paid'";
        $params = [$date];
        if ($selected_branch_id !== 'all') {
            $sql .= " AND branch_id = ?";
            $params[] = (int)$selected_branch_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {}
    
    $chart_values[] = (float)$daily_total;
}

// ================================================================
// BRANCHES + RECENT ACTIVITIES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$recent_activities = [];
try {
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [
        ['action' => 'System Started', 'details' => 'Super Admin logged in', 'created_at' => date('Y-m-d H:i:s')],
    ];
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
    --radius: 12px;
    --radius-lg: 16px;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
}

/* PAGE HEADER */
.page-header {
    background: var(--primary);
    border-radius: var(--radius-lg);
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 24px rgba(11, 94, 215, 0.2);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}

.page-header .page-subtitle strong { color: white; font-weight: 600; }

.page-header .header-badge {
    background: rgba(255,255,255,0.1);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 500;
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid rgba(255,255,255,0.1);
}

.page-header .btn-outline-light {
    background: rgba(255,255,255,0.1);
    color: white;
    border: 1px solid rgba(255,255,255,0.15);
    padding: 6px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.8rem;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.page-header .btn-outline-light:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    color: white;
}

/* STAT CARDS */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    position: relative;
    border-radius: var(--radius-lg);
    padding: 20px 22px;
    color: white;
    text-decoration: none;
    display: block;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: var(--shadow-sm);
    min-height: 120px;
    height: 100%;
    cursor: pointer;
    border: none;
}

.stat-card.card-revenue { background: #0B5ED7; }
.stat-card.card-revenue:hover { background: #0A4CA8; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(11,94,215,0.35); }

.stat-card.card-patient { background: #0B5ED7; }
.stat-card.card-patient:hover { background: #0A4CA8; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(11,94,215,0.35); }

.stat-card.card-otc { background: #0891B2; }
.stat-card.card-otc:hover { background: #0E7490; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(8,145,178,0.35); }

.stat-card.card-prescription { background: #7C3AED; }
.stat-card.card-prescription:hover { background: #5B21B6; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(124,58,237,0.35); }

.stat-card.card-consultation { background: #059669; }
.stat-card.card-consultation:hover { background: #047857; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(5,150,105,0.35); }

.stat-card.card-lab { background: #7C3AED; }
.stat-card.card-lab:hover { background: #5B21B6; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(124,58,237,0.35); }

.stat-card.card-stock { background: #0B5ED7; }
.stat-card.card-stock:hover { background: #0A4CA8; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(11,94,215,0.35); }

.stat-card.card-equipment { background: #0B5ED7; }
.stat-card.card-equipment:hover { background: #0A4CA8; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(11,94,215,0.35); }

.stat-card.card-expenses { background: #E11D48; }
.stat-card.card-expenses:hover { background: #BE123C; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(225,29,72,0.35); }

.stat-card.card-expiry { background: #DC2626; }
.stat-card.card-expiry:hover { background: #B91C1C; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(220,38,38,0.35); }

.stat-card.card-profit { background: #059669; }
.stat-card.card-profit:hover { background: #047857; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(5,150,105,0.35); }

.stat-card::before {
    content: '';
    position: absolute;
    top: -50%; right: -30%;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    pointer-events: none;
    transition: all 0.3s;
}

.stat-card::after {
    content: '';
    position: absolute;
    bottom: -40%; left: -20%;
    width: 150px; height: 150px;
    border-radius: 50%;
    background: rgba(255,255,255,0.02);
    pointer-events: none;
    transition: all 0.3s;
}

.stat-card:hover::before { transform: scale(1.2); right: -20%; }
.stat-card:hover::after { transform: scale(1.3); bottom: -30%; }
.stat-card:active { transform: scale(0.97); }

.stat-card .card-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.stat-card .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.stat-card .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    background: rgba(255,255,255,0.12);
    color: white;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
    transition: all 0.3s;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(-5deg);
    background: rgba(255,255,255,0.2);
}

.stat-card .stat-number {
    font-size: 1.7rem;
    font-weight: 700;
    color: white;
    line-height: 1.2;
    margin-top: 2px;
    letter-spacing: -0.02em;
}

.stat-card .stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.9);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.stat-card .stat-sub {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.8);
    margin-top: 2px;
}

.stat-card .stat-trend {
    font-size: 0.55rem;
    font-weight: 500;
    padding: 2px 10px;
    border-radius: 20px;
    background: rgba(255,255,255,0.1);
    color: white;
    display: inline-block;
    margin-top: 4px;
    backdrop-filter: blur(4px);
}

.stat-card .stat-badge-row {
    display: flex;
    gap: 4px;
    margin-top: 4px;
    flex-wrap: wrap;
}

.stat-card .stat-badge {
    font-size: 0.55rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 20px;
    background: rgba(255,255,255,0.1);
    color: white;
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.stat-card .stat-badge.danger { background: rgba(239, 68, 68, 0.25); color: #FCA5A5; }
.stat-card .stat-badge.warning { background: rgba(245, 158, 11, 0.25); color: #FCD34D; }
.stat-card .stat-badge.success { background: rgba(52, 211, 153, 0.2); color: #6EE7B7; }

.stat-card .stat-arrow {
    position: absolute;
    right: 16px;
    bottom: 16px;
    color: rgba(255,255,255,0.2);
    font-size: 0.75rem;
    transition: all 0.3s;
    z-index: 1;
}

.stat-card:hover .stat-arrow {
    transform: translateX(4px);
    color: rgba(255,255,255,0.6);
}

/* CARD */
.card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
}

.card:hover { box-shadow: var(--shadow-md); }

.card-header {
    padding: 12px 20px;
    background: var(--bg-body);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

[data-theme="dark"] .card-header { background: #0F172A; }

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-title i { margin-right: 8px; }
.title-blue { color: #0B5ED7; }
.title-green { color: #059669; }
.title-purple { color: #7C3AED; }

/* REVENUE BREAKDOWN TABLE */
.revenue-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.revenue-table thead { background: var(--bg-body); }
[data-theme="dark"] .revenue-table thead { background: #0F172A; }

.revenue-table th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid var(--border-color);
}

.revenue-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.revenue-table tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .revenue-table tr:hover td { background: rgba(30,58,95,0.3); }

.revenue-table tr.total-row td {
    border-top: 2px solid var(--border-color);
    font-weight: 700;
    font-size: 0.9rem;
}

.revenue-table .source-blue { color: #0B5ED7; }
.revenue-table .source-cyan { color: #0891B2; }
.revenue-table .source-purple { color: #7C3AED; }
.revenue-table .source-green { color: #059669; }
.revenue-table .source-red { color: #E11D48; }

/* RECENT ACTIVITIES */
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.3s;
}

.activity-item:hover { background: var(--primary-bg); }
[data-theme="dark"] .activity-item:hover { background: rgba(30, 58, 95, 0.3); }

.activity-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #0B5ED7;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-size: 0.55rem;
    margin-top: 2px;
}

.activity-content { flex: 1; min-width: 0; }

.activity-action {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.activity-details {
    font-size: 0.68rem;
    color: var(--text-secondary);
    margin: 2px 0 0 0;
}

.activity-time {
    font-size: 0.6rem;
    color: #94A3B8;
    margin: 2px 0 0 0;
}

.max-h-50 { max-height: 180px; overflow-y: auto; }

.footer {
    margin-top: 16px;
    padding: 12px 0;
    border-top: 1px solid var(--border-color);
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

.text-xs { font-size: 0.7rem; }
.text-gray-400 { color: var(--text-secondary); }
.text-blue-600 { color: #0B5ED7; }
.hover\:underline:hover { text-decoration: underline; }

@media (max-width: 1200px) {
    .stat-grid { grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .stat-card .stat-number { font-size: 1.4rem; }
}

@media (max-width: 992px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .stat-card { min-height: 110px; padding: 16px 18px; }
    .stat-card .stat-number { font-size: 1.3rem; }
    .stat-card .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
}

@media (max-width: 768px) {
    .stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { min-height: 95px; padding: 14px 16px; }
    .stat-card .stat-number { font-size: 1.1rem; }
    .stat-card .stat-label { font-size: 0.6rem; }
    .stat-card .stat-sub { font-size: 0.55rem; }
    .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.85rem; }
    .stat-card .stat-arrow { display: none; }
    .page-header { padding: 14px 18px; }
    .page-header .page-title { font-size: 1.1rem; }
}

@media (max-width: 480px) {
    .stat-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { min-height: 80px; padding: 10px 12px; border-radius: 12px; }
    .stat-card .stat-number { font-size: 0.95rem; }
    .stat-card .stat-label { font-size: 0.5rem; }
    .stat-card .stat-icon { width: 26px; height: 26px; font-size: 0.7rem; }
    .page-header { padding: 10px 14px; flex-direction: column; align-items: flex-start; }
}

@media print {
    .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
    .search-wrapper, .page-header .btn-outline-light,
    .footer, #sidebarToggle { display: none !important; }
    .main-content { margin: 0; padding: 20px; }
    .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .page-title, .page-subtitle { color: white !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
}
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home"></i> Super Admin Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                <span class="header-badge"><i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?></span>
                <span class="header-badge"><i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Revenue</span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-export"></i> Report
            </a>
            <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-chart-line"></i> Revenue
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- STAT CARDS GRID -->
    <div class="stat-grid">
        
        <!-- 1. TOTAL REVENUE -->
        <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="stat-card card-revenue">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Revenue</p>
                        <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                        <p class="stat-sub">Bills + OTC</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-arrow-up"></i> <?= number_format($total_transactions) ?> transactions</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 2. PATIENT BILLS -->
        <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="stat-card card-patient">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Patient Bills</p>
                        <p class="stat-number">TSh <?= number_format($patient_bills_revenue) ?></p>
                        <p class="stat-sub"><?= number_format($patient_bills_count) ?> paid bills</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-check-circle"></i> Paid only</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 3. OTC SALES -->
        <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="stat-card card-otc">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">OTC Sales</p>
                        <p class="stat-number">TSh <?= number_format($otc_revenue) ?></p>
                        <p class="stat-sub"><?= number_format($otc_count) ?> transactions</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-store"></i> Over the counter</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 4. PRESCRIPTIONS -->
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="stat-card card-prescription">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Prescriptions</p>
                        <p class="stat-number">TSh <?= number_format($prescription_revenue) ?></p>
                        <p class="stat-sub"><?= number_format($prescription_count) ?> dispensed</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-pills"></i> Paid bills only</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 5. CONSULTATION -->
        <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="stat-card card-consultation">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Consultation</p>
                        <p class="stat-number">TSh <?= number_format($consultation_revenue) ?></p>
                        <p class="stat-sub"><?= number_format($consultation_count) ?> consultations</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-check-circle"></i> Paid only</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 6. LAB TESTS -->
        <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="stat-card card-lab">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Lab Tests</p>
                        <p class="stat-number">TSh <?= number_format($lab_revenue) ?></p>
                        <p class="stat-sub"><?= number_format($lab_count) ?> tests</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-flask"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-check-circle"></i> Paid only</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 7. EXPENSES (RED) -->
        <a href="expenses.php?branch=<?= $selected_branch_id ?>" class="stat-card card-expenses">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Expenses</p>
                        <p class="stat-number">TSh <?= number_format($total_expenses) ?></p>
                        <p class="stat-sub"><?= number_format($expenses_count) ?> records</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-arrow-down"></i> All time</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 8. NET PROFIT (GREEN) -->
        <a href="profit.php?branch=<?= $selected_branch_id ?>" class="stat-card card-profit">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label"><?= $net_profit >= 0 ? '💰 Net Profit' : '📉 Net Loss' ?></p>
                        <p class="stat-number">TSh <?= number_format(abs($net_profit)) ?></p>
                        <p class="stat-sub"><?= $profit_percentage ?>% margin</p>
                    </div>
                    <div class="stat-icon"><i class="fas <?= $net_profit >= 0 ? 'fa-chart-line' : 'fa-exclamation-triangle' ?>"></i></div>
                </div>
                <div class="stat-trend">
                    <?php if ($net_profit >= 0): ?>
                        <i class="fas fa-arrow-up"></i> Revenue - Expenses
                    <?php else: ?>
                        <i class="fas fa-arrow-down"></i> Loss
                    <?php endif; ?>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
    </div>

    <!-- INVENTORY CARDS ROW -->
    <div class="stat-grid">
        
        <!-- 9. MEDICATION STOCK -->
        <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="stat-card card-stock">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Medication Stock</p>
                        <p class="stat-number"><?= number_format($med_total_items) ?></p>
                        <p class="stat-sub">📦 <?= number_format($med_total_quantity) ?> units</p>
                        <div class="stat-badge-row">
                            <span class="stat-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $med_low_stock ?> Low</span>
                            <span class="stat-badge danger"><i class="fas fa-times-circle"></i> <?= $med_out_of_stock ?> Out</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 10. MEDICATION EXPIRY (RED) -->
        <a href="inventory.php?filter=expired&branch=<?= $selected_branch_id ?>" class="stat-card card-expiry">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Medication Expiry</p>
                        <p class="stat-number"><?= number_format($med_expired_items + $med_expiring_items) ?></p>
                        <p class="stat-sub">📦 <?= number_format($med_expired_quantity + $med_expiring_quantity) ?> units</p>
                        <div class="stat-badge-row">
                            <span class="stat-badge danger"><i class="fas fa-skull"></i> <?= $med_expired_items ?> Expired</span>
                            <span class="stat-badge warning"><i class="fas fa-clock"></i> <?= $med_expiring_items ?> Soon</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 11. MEDICAL EQUIPMENT -->
        <a href="equipment_inventory.php?branch=<?= $selected_branch_id ?>" class="stat-card card-equipment">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Medical Equipment</p>
                        <p class="stat-number"><?= number_format($equip_total_items) ?></p>
                        <p class="stat-sub">📦 <?= number_format($equip_total_quantity) ?> units</p>
                        <div class="stat-badge-row">
                            <span class="stat-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $equip_low_stock ?> Low</span>
                            <span class="stat-badge danger"><i class="fas fa-times-circle"></i> <?= $equip_out_of_stock ?> Out</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-microscope"></i></div>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 12. EQUIPMENT EXPIRY -->
        <a href="equipment_inventory.php?filter=expired&branch=<?= $selected_branch_id ?>" class="stat-card card-expiry">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Equipment Expiry</p>
                        <p class="stat-number"><?= number_format($equip_expired_items + $equip_expiring_items) ?></p>
                        <p class="stat-sub">Total affected</p>
                        <div class="stat-badge-row">
                            <span class="stat-badge danger"><i class="fas fa-skull"></i> <?= $equip_expired_items ?> Expired</span>
                            <span class="stat-badge warning"><i class="fas fa-clock"></i> <?= $equip_expiring_items ?> Soon</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
    </div>

    <!-- CHART -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-line title-blue"></i> Revenue Overview (Last 7 Days)
                <span class="text-xs text-gray-400" style="margin-left:8px;font-weight:400;">TSh <?= number_format(array_sum($chart_values)) ?> total</span>
            </h3>
        </div>
        <div style="height: 180px; padding: 12px 16px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- REVENUE BREAKDOWN TABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-purple"></i> Revenue Breakdown by Source
            </h3>
            <span class="text-xs text-gray-400">Total: TSh <?= number_format($total_revenue, 0) ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="revenue-table">
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
                        <td><span class="source-blue">●</span> Patient Bills</td>
                        <td style="text-align:right;font-weight:600;color:#0B5ED7;">TSh <?= number_format($patient_bills_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($patient_bills_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($patient_bills_count) ?></td>
                    </tr>
                    <tr>
                        <td><span class="source-cyan">●</span> OTC Sales</td>
                        <td style="text-align:right;font-weight:600;color:#0891B2;">TSh <?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($otc_count) ?></td>
                    </tr>
                    <tr style="background:var(--primary-bg);">
                        <td><span class="source-purple">●</span> Prescriptions <span style="font-size:0.6rem;color:var(--text-secondary);">(from paid bills)</span></td>
                        <td style="text-align:right;font-weight:600;color:#7C3AED;">TSh <?= number_format($prescription_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($prescription_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($prescription_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span class="source-green">●</span> Consultation</td>
                        <td style="text-align:right;font-weight:500;color:#059669;">TSh <?= number_format($consultation_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($consultation_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($consultation_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span class="source-purple">●</span> Lab Tests</td>
                        <td style="text-align:right;font-weight:500;color:#7C3AED;">TSh <?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($lab_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#D97706;">●</span> Medications</td>
                        <td style="text-align:right;font-weight:500;color:#D97706;">TSh <?= number_format($medication_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($medication_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($medication_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#0D9488;">●</span> Procedures</td>
                        <td style="text-align:right;font-weight:500;color:#0D9488;">TSh <?= number_format($procedure_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($procedure_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($procedure_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:24px;"><span style="color:#64748B;">●</span> Registration</td>
                        <td style="text-align:right;font-weight:500;color:#64748B;">TSh <?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= $total_revenue > 0 ? round(($registration_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($registration_count) ?></td>
                    </tr>
                    <tr style="background:#FFE4E6;">
                        <td><span class="source-red">●</span> <strong>Total Expenses</strong></td>
                        <td style="text-align:right;font-weight:700;color:#E11D48;">- TSh <?= number_format($total_expenses, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($expenses_count) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td style="font-weight:700;">NET <?= $net_profit >= 0 ? 'PROFIT' : 'LOSS' ?></td>
                        <td style="text-align:right;font-weight:700;color:<?= $net_profit >= 0 ? '#059669' : '#E11D48' ?>;">TSh <?= number_format(abs($net_profit), 0) ?></td>
                        <td style="text-align:right;font-weight:700;color:<?= $net_profit >= 0 ? '#059669' : '#E11D48' ?>;"><?= $profit_percentage ?>%</td>
                        <td style="text-align:right;font-weight:700;color:var(--primary);"><?= number_format($total_transactions) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RECENT ACTIVITIES -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock title-green"></i> Recent Activities
            </h3>
            <a href="system_logs.php" class="text-xs text-blue-600 hover:underline" style="font-weight:500;">View All →</a>
        </div>
        <div class="max-h-50" style="padding: 8px 12px;">
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon"><i class="fas fa-circle"></i></div>
                    <div class="activity-content">
                        <p class="activity-action"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                        <p class="activity-details"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                        <p class="activity-time"><?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Super Admin Dashboard
            <span style="margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// SYNC DARK MODE
(function() {
    var htmlElement = document.documentElement;
    window.addEventListener('storage', function(e) {
        if (e.key === 'darkMode') {
            if (e.newValue === 'true') htmlElement.setAttribute('data-theme', 'dark');
            else htmlElement.removeAttribute('data-theme');
        }
    });
})();

setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// REVENUE CHART
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('revenueChart')?.getContext('2d');
    if (ctx && typeof Chart !== 'undefined') {
        var labels = <?= json_encode($chart_labels) ?>;
        var values = <?= json_encode($chart_values) ?>;
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (TSh)',
                    data: values,
                    borderColor: '#0B5ED7',
                    backgroundColor: 'rgba(11, 94, 215, 0.08)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#0B5ED7',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true,
                        labels: { font: { size: 9, weight: '600' }, boxWidth: 10, padding: 8, color: textColor }
                    },
                    tooltip: {
                        callbacks: { label: function(context) { return 'TSh ' + context.raw.toLocaleString(); } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(value) { return 'TSh ' + value.toLocaleString(); }, font: { size: 8 }, color: textColor },
                        grid: { color: gridColor }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 8 }, color: textColor } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }
});

console.log('%c🏥 Braick Dispensary - Super Admin Dashboard (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c💊 Prescriptions: TSh <?= number_format($prescription_revenue, 0) ?>', 'font-size:13px; color:#7C3AED;');
console.log('%c👨‍⚕️ Consultation: TSh <?= number_format($consultation_revenue, 0) ?>', 'font-size:13px; color:#059669;');
console.log('%c🧪 Lab Tests: TSh <?= number_format($lab_revenue, 0) ?>', 'font-size:13px; color:#7C3AED;');
console.log('%c✅ Only PAID items are counted', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>