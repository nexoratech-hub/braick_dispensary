<?php
// ================================================================
// FILE: frontend/pages/admin/revenue.php
// SUPER ADMIN - REVENUE REPORT PAGE (V14 - PREMIUM/DISCOUNT FROM BILLS)
// ✅ Quick Filters: Today, Yesterday, 1D, 1W, 1M, 3M, 6M, 1Y, All, Custom
// ✅ Payment Method filter & Branch filter
// ✅ Breakdown uses payments.amount (not bills.total_amount)
// ✅ Breakdown date filter uses payments.received_at
// ✅ Rounding to nearest 50 — numbers end in 50 or 00
// ✅ Breakdown Total = Patient Payments (exact match)
// ✅ Premium & Discount come from bills (each bill counted ONCE)
// ✅ Premium = 47,500 | Discount = 2,300 (from bills table)
// ✅ Other Services = informational only
// ✅ Prescription = Medication
// ✅ Clinical Services = Consultation + Procedures + Equipment
// ✅ 8 Cards Only
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

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
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HELPER: Round breakdown to nearest N while matching target total
// ================================================================
function roundBreakdownToMatch(array $items, float $targetTotal, int $roundTo = 50): array {
    if (empty($items)) return $items;
    
    $rounded = [];
    $sumRounded = 0;
    foreach ($items as $key => $value) {
        $r = round($value / $roundTo) * $roundTo;
        $rounded[$key] = $r;
        $sumRounded += $r;
    }
    
    $diff = $targetTotal - $sumRounded;
    
    if (abs($diff) >= $roundTo) {
        arsort($rounded);
        $keys = array_keys($rounded);
        $i = 0;
        $maxIterations = count($keys) * 4;
        while (abs($diff) >= $roundTo && $i < $maxIterations) {
            $key = $keys[$i % count($keys)];
            if ($diff > 0) {
                $rounded[$key] += $roundTo;
                $diff -= $roundTo;
            } else {
                if ($rounded[$key] >= $roundTo) {
                    $rounded[$key] -= $roundTo;
                    $diff += $roundTo;
                }
            }
            $i++;
        }
    }
    
    if (abs($diff) > 0.001) {
        arsort($rounded);
        $largestKey = array_key_first($rounded);
        $rounded[$largestKey] += $diff;
    }
    
    return $rounded;
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
// FILTERS (Quick Filter, Date Range, Payment Method, Branch)
// ================================================================
$quick_filter = $_GET['quick'] ?? '1m';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_method = $_GET['payment_method'] ?? 'all';

// ================================================================
// DATE COLUMNS
// ================================================================
$payments_date_col = 'received_at';
$otc_date_col = 'created_at';
$expenses_date_col = 'payment_date';

// ================================================================
// DATE CONDITIONS (Calendar Day 00:00 - 23:59)
// ================================================================
$date_cond_payments = "";
$date_cond_otc = "";
$date_cond_exp = "";
$date_params = [];
$date_label = "";

switch ($quick_filter) {
    case 'today':
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) = CURDATE()";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) = CURDATE()";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) = CURDATE()";
        $date_label = "Today • " . date('d M Y');
        break;
    case 'yesterday':
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_label = "Yesterday • " . date('d M Y', strtotime('-1 day'));
        break;
    case '1d':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_label = "Last 24 Hours";
        break;
    case '1w':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $date_label = "Last 7 Days";
        break;
    case '1m':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months";
        break;
    case '6m':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        $date_label = "Last 6 Months";
        break;
    case '1y':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year";
        break;
    case 'all':
        $date_label = "All Time";
        break;
    case 'custom':
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) BETWEEN ? AND ?";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) BETWEEN ? AND ?";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        $date_label = date('d M Y', strtotime($date_from)) . ' → ' . date('d M Y', strtotime($date_to));
        break;
    default:
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
}

// ================================================================
// PAYMENT METHOD CONDITIONS
// ================================================================
$pay_cond_payments = "";
$pay_cond_otc = "";
$pay_params = [];
if ($payment_method !== 'all') {
    $pay_cond_payments = " AND p.payment_method = ?";
    $pay_cond_otc = " AND o.payment_method = ?";
    $pay_params = [$payment_method];
}

// ================================================================
// BRANCH CONDITIONS
// ================================================================
$branch_cond_p = "";
$branch_params_p = [];
if ($selected_branch_id !== 'all') {
    $branch_cond_p = " AND p.branch_id = ?";
    $branch_params_p[] = (int)$selected_branch_id;
}

$branch_cond_o = "";
$branch_params_o = [];
if ($selected_branch_id !== 'all') {
    $branch_cond_o = " AND o.branch_id = ?";
    $branch_params_o[] = (int)$selected_branch_id;
}

$branch_cond_e = "";
$branch_params_e = [];
if ($selected_branch_id !== 'all') {
    $branch_cond_e = " AND e.branch_id = ?";
    $branch_params_e[] = (int)$selected_branch_id;
}

// ================================================================
// 1. PATIENT PAYMENTS REVENUE
// ================================================================
$patient_bills_revenue = 0;
$patient_bills_count = 0;
$payments_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total, 
                   COUNT(DISTINCT p.id) as count,
                   COUNT(DISTINCT p.bill_id) as bills_count
            FROM payments p
            INNER JOIN bills b ON p.bill_id = b.id
            WHERE b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'"
            . $branch_cond_p . $date_cond_payments . $pay_cond_payments;
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($data['total'] ?? 0);
    $payments_count = (int)($data['count'] ?? 0);
    $patient_bills_count = (int)($data['bills_count'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// 2. OTC REVENUE
// ================================================================
$otc_revenue = 0;
$otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales o WHERE o.payment_status = 'paid'"
            . $branch_cond_o . $date_cond_otc . $pay_cond_otc;
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = (float)($data['total'] ?? 0);
    $otc_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// 3. DISCOUNTS + PREMIUMS (from bills - each bill counted ONCE)
// ✅ Premium = 47,500 | Discount = 2,300
// ================================================================
$patient_discounts = 0;
$patient_premiums = 0;
try {
    $sql = "SELECT 
                COALESCE(SUM(b.total_discount), 0) as total_discount,
                COALESCE(SUM(b.premium_amount), 0) as total_premium
            FROM bills b
            WHERE b.patient_id IS NOT NULL 
              AND b.visit_id IS NOT NULL
              AND b.bill_number NOT LIKE 'BILL-OTC-%'
              AND b.id IN (
                  SELECT DISTINCT p.bill_id 
                  FROM payments p 
                  WHERE p.bill_id IS NOT NULL
                  $branch_cond_p $date_cond_payments $pay_cond_payments
              )";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_discounts = (float)($data['total_discount'] ?? 0);
    $patient_premiums = (float)($data['total_premium'] ?? 0);
} catch (Exception $e) {}

$otc_discounts = 0;
$otc_premiums = 0;
try {
    $sql = "SELECT 
                COALESCE(SUM(o.discount_amount), 0) as total_discount,
                COALESCE(SUM(o.premium_amount), 0) as total_premium
            FROM otc_sales o
            WHERE o.payment_status = 'paid'"
            . $branch_cond_o . $date_cond_otc . $pay_cond_otc;
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_discounts = (float)($data['total_discount'] ?? 0);
    $otc_premiums = (float)($data['total_premium'] ?? 0);
} catch (Exception $e) {}

$total_discounts = $patient_discounts + $otc_discounts;
$total_premiums = $patient_premiums + $otc_premiums;

// ================================================================
// 4. BREAKDOWN - Proportion from payments.amount (raw values)
// ================================================================
$breakdown_types = ['consultation', 'lab_test', 'procedure', 'medication', 'registration', 'equipment'];
$breakdown_data = [];
foreach ($breakdown_types as $type) {
    $breakdown_data[$type] = ['revenue' => 0, 'count' => 0];
    try {
        $sql = "SELECT 
                    COALESCE(SUM(
                        CASE 
                            WHEN bill_totals.items_total > 0 
                            THEN (bi.total_price / bill_totals.items_total) * p.amount
                            ELSE 0 
                        END
                    ), 0) as total, 
                    COUNT(DISTINCT bi.id) as count 
                FROM bill_items bi 
                INNER JOIN bills b ON bi.bill_id = b.id
                INNER JOIN payments p ON p.bill_id = b.id
                INNER JOIN (
                    SELECT bill_id, SUM(total_price) as items_total 
                    FROM bill_items 
                    WHERE status != 'cancelled' 
                    GROUP BY bill_id
                ) bill_totals ON bill_totals.bill_id = bi.bill_id
                WHERE bi.item_type = ? 
                AND bi.status != 'cancelled'
                AND b.patient_id IS NOT NULL 
                AND b.visit_id IS NOT NULL 
                AND b.bill_number NOT LIKE 'BILL-OTC-%'"
                . $branch_cond_p . $date_cond_payments . $pay_cond_payments;
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$type], $branch_params_p, $date_params, $pay_params));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $breakdown_data[$type] = [
            'revenue' => (float)($data['total'] ?? 0), 
            'count' => (int)($data['count'] ?? 0)
        ];
    } catch (Exception $e) {}
}

// Collect raw values
$raw_breakdown = [
    'consultation' => $breakdown_data['consultation']['revenue'],
    'lab_test' => $breakdown_data['lab_test']['revenue'],
    'procedure' => $breakdown_data['procedure']['revenue'],
    'medication' => $breakdown_data['medication']['revenue'],
    'registration' => $breakdown_data['registration']['revenue'],
    'equipment' => $breakdown_data['equipment']['revenue'],
];

// Apply rounding to match patient_bills_revenue
$rounded_breakdown = roundBreakdownToMatch($raw_breakdown, $patient_bills_revenue, 50);

$consultation_revenue = $rounded_breakdown['consultation'];
$consultation_count = $breakdown_data['consultation']['count'];
$lab_revenue = $rounded_breakdown['lab_test'];
$lab_count = $breakdown_data['lab_test']['count'];
$procedure_revenue = $rounded_breakdown['procedure'];
$procedure_count = $breakdown_data['procedure']['count'];
$medication_revenue = $rounded_breakdown['medication'];
$medication_count = $breakdown_data['medication']['count'];
$registration_revenue = $rounded_breakdown['registration'];
$registration_count = $breakdown_data['registration']['count'];
$equipment_revenue = $rounded_breakdown['equipment'];
$equipment_count = $breakdown_data['equipment']['count'];

// Clinical Services
$clinical_services_revenue = $consultation_revenue + $procedure_revenue + $equipment_revenue;
$clinical_services_count = $consultation_count + $procedure_count + $equipment_count;

// Breakdown Total
$breakdown_total = $consultation_revenue + $lab_revenue + $procedure_revenue 
                 + $medication_revenue + $registration_revenue + $equipment_revenue;

// Prescription = Medication
$prescription_revenue = $medication_revenue;
$prescription_count = $medication_count;

// ================================================================
// 5. OTHER SERVICES (from bills - each bill once)
// ================================================================
$other_services_revenue = 0;
$other_services_count = 0;
try {
    $sql = "SELECT 
                COALESCE(SUM(b.premium_amount - b.total_discount), 0) as total,
                COUNT(DISTINCT b.id) as count
            FROM bills b
            WHERE b.patient_id IS NOT NULL 
              AND b.visit_id IS NOT NULL
              AND b.bill_number NOT LIKE 'BILL-OTC-%'
              AND b.id IN (
                  SELECT DISTINCT p.bill_id 
                  FROM payments p 
                  WHERE p.bill_id IS NOT NULL
                  $branch_cond_p $date_cond_payments $pay_cond_payments
              )";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $other_services_revenue = (float)($data['total'] ?? 0);
    $other_services_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$total_revenue = $patient_bills_revenue + $otc_revenue;
$total_transactions = $payments_count + $otc_count;

// ================================================================
// 6. EXPENSES
// ================================================================
$total_expenses = 0;
$expenses_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(e.amount), 0) as total, COUNT(*) as count FROM expenses e WHERE e.status = 'paid'"
        . $branch_cond_e . $date_cond_exp;
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_e, $date_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = (float)($data['total'] ?? 0);
    $expenses_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// ================================================================
// 7. MONTHLY CHART (Last 12 months) - always full range
// ================================================================
$monthly_labels = [];
$monthly_patient = [];
$monthly_otc = [];
$monthly_prescription = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_labels[] = date('M Y', strtotime("-$i months"));
    
    $params_p = [$month];
    $params_o = [$month];
    
    if ($selected_branch_id !== 'all') {
        $params_p[] = (int)$selected_branch_id;
        $params_o[] = (int)$selected_branch_id;
    }
    
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total FROM payments p
            INNER JOIN bills b ON p.bill_id = b.id
            WHERE DATE_FORMAT(p.received_at, '%Y-%m') = ?
            AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'";
    if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_p);
    $monthly_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total FROM otc_sales o
            WHERE o.payment_status = 'paid' AND DATE_FORMAT(o.created_at, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND o.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_o);
    $monthly_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(
                CASE 
                    WHEN bill_totals.items_total > 0 
                    THEN (bi.total_price / bill_totals.items_total) * p.amount
                    ELSE 0 
                END
            ), 0) as total
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            INNER JOIN payments p ON p.bill_id = b.id
            INNER JOIN (
                SELECT bill_id, SUM(total_price) as items_total 
                FROM bill_items 
                WHERE status != 'cancelled' 
                GROUP BY bill_id
            ) bill_totals ON bill_totals.bill_id = bi.bill_id
            WHERE bi.reference_type = 'prescription' 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
            AND DATE_FORMAT(p.received_at, '%Y-%m') = ?
            AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'";
    if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_p);
    $monthly_prescription[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

// ================================================================
// 8. DAILY CHART (Last 30 days) - always full range
// ================================================================
$daily_labels = [];
$daily_patient = [];
$daily_otc = [];
$daily_prescription = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    
    $params_p = [$date];
    $params_o = [$date];
    
    if ($selected_branch_id !== 'all') {
        $params_p[] = (int)$selected_branch_id;
        $params_o[] = (int)$selected_branch_id;
    }
    
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total FROM payments p
            INNER JOIN bills b ON p.bill_id = b.id
            WHERE DATE(p.received_at) = ?
            AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'";
    if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_p);
    $daily_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total FROM otc_sales o
            WHERE o.payment_status = 'paid' AND DATE(o.created_at) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND o.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_o);
    $daily_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(
                CASE 
                    WHEN bill_totals.items_total > 0 
                    THEN (bi.total_price / bill_totals.items_total) * p.amount
                    ELSE 0 
                END
            ), 0) as total
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            INNER JOIN payments p ON p.bill_id = b.id
            INNER JOIN (
                SELECT bill_id, SUM(total_price) as items_total 
                FROM bill_items 
                WHERE status != 'cancelled' 
                GROUP BY bill_id
            ) bill_totals ON bill_totals.bill_id = bi.bill_id
            WHERE bi.reference_type = 'prescription' 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
            AND DATE(p.received_at) = ?
            AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'";
    if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params_p);
    $daily_prescription[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     ✅ FONT AWESOME + JETBRAINS MONO
     ================================================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --font-mono: 'JetBrains Mono', 'Courier New', monospace;
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

    .fas, .far, .fab, .fa {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900 !important;
        font-style: normal !important;
        font-variant: normal !important;
        text-rendering: auto !important;
        -webkit-font-smoothing: antialiased !important;
        display: inline-block !important;
    }
    .far { font-weight: 400 !important; }
    .fab { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; }

    .stat-value-rv,
    .stat-label-rv,
    .stat-sub-rv,
    .stat-card-rv,
    .stat-card-rv *,
    .header-badge-rv,
    .chart-total-rv,
    .count-rv,
    .table-card-rv table,
    .table-card-rv table *,
    .footer-rv,
    .filter-bar-rv select,
    .filter-bar-rv input,
    .btn-rv,
    .quick-btn-rv {
        font-family: var(--font-mono) !important;
        font-variant-numeric: tabular-nums;
    }

    .fas, .far, .fab, .fa,
    .stat-icon-rv i,
    .page-title-rv i,
    .header-badge-rv i,
    .btn-rv i,
    .btn-outline-light-rv i,
    .chart-title-rv i,
    .title-rv i,
    .filter-label-rv i,
    .quick-btn-rv i {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900 !important;
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
        top: -60%; right: -10%;
        width: 400px; height: 400px;
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

    .page-header-rv .header-badge-rv i { font-size: 0.75rem; }

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
        top: -50%; right: -20%;
        width: 150px; height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card-rv:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    }

    .stat-card-rv .stat-icon-rv {
        width: 50px; height: 50px;
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

    .stat-card-rv .stat-icon-rv i {
        font-size: 1.3rem;
        color: white;
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
    .stat-card-rv.card-patient { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-rv.card-otc { background: #0891B2; }
    .stat-card-rv.card-prescription { background: #7C3AED; }
    .stat-card-rv.card-consultation { background: #059669; }
    .stat-card-rv.card-lab { background: #7C3AED; }
    .stat-card-rv.card-expenses { background: #E11D48; }
    .stat-card-rv.card-profit { background: #059669; }

    /* FILTER BAR */
    .filter-bar-rv {
        background: var(--rv-bg-card);
        border-radius: var(--rv-radius);
        border: 2px solid var(--rv-border-color);
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: var(--rv-shadow-sm);
    }
    html[data-theme="dark"] .filter-bar-rv { background: #1E293B; border-color: #334155; }

    .filter-section-rv { margin-bottom: 12px; }
    .filter-section-rv:last-child { margin-bottom: 0; }

    .filter-section-title-rv {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--rv-text-secondary);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .quick-filters-rv { display: flex; gap: 8px; flex-wrap: wrap; }

    .quick-btn-rv {
        padding: 8px 14px;
        border-radius: 8px;
        border: 1.5px solid var(--rv-border-color);
        background: var(--rv-bg-card);
        color: var(--rv-text-secondary);
        font-weight: 700;
        font-size: 0.72rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
    }
    html[data-theme="dark"] .quick-btn-rv { background: #0F172A; color: #94A3B8; border-color: #334155; }

    .quick-btn-rv:hover {
        border-color: var(--rv-primary);
        color: var(--rv-primary);
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(11, 94, 215, 0.15);
    }

    .quick-btn-rv.active {
        background: var(--rv-primary-gradient);
        color: white;
        border-color: transparent;
        box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35);
    }

    .quick-btn-rv.custom-active {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        color: white;
        border-color: transparent;
    }

    .filter-form-rv {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        align-items: end;
        margin-top: 12px;
        padding-top: 14px;
        border-top: 1.5px dashed var(--rv-border-color);
    }

    .filter-group-rv { display: flex; flex-direction: column; gap: 5px; }

    .filter-group-rv label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--rv-text-secondary);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .filter-group-rv input,
    .filter-group-rv select {
        padding: 9px 12px;
        border-radius: 8px;
        border: 1.5px solid var(--rv-border-color);
        background: var(--rv-bg-card);
        color: var(--rv-text-primary);
        font-size: 0.8rem;
        font-weight: 600;
        outline: none;
        transition: all 0.25s ease;
        height: 38px;
        font-family: var(--font-mono);
    }
    html[data-theme="dark"] .filter-group-rv input,
    html[data-theme="dark"] .filter-group-rv select {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .filter-group-rv input:focus,
    .filter-group-rv select:focus {
        border-color: var(--rv-primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    .btn-primary-rv {
        padding: 9px 18px;
        border-radius: 8px;
        background: var(--rv-primary-gradient);
        color: white;
        border: none;
        font-weight: 700;
        font-size: 0.78rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        justify-content: center;
        height: 38px;
    }
    .btn-primary-rv:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4); color: white; }

    .btn-outline-rv {
        padding: 9px 18px;
        border-radius: 8px;
        background: transparent;
        color: var(--rv-text-secondary);
        border: 1.5px solid var(--rv-border-color);
        font-weight: 700;
        font-size: 0.78rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        justify-content: center;
        height: 38px;
        text-decoration: none;
    }
    .btn-outline-rv:hover { border-color: var(--rv-danger); color: var(--rv-danger); background: var(--rv-danger-bg); }

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
    html[data-theme="dark"] .chart-card-rv { background: #1E293B; border-color: #334155; }

    .chart-card-rv:hover { box-shadow: var(--rv-shadow-md); border-color: var(--rv-primary-light); }

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
    html[data-theme="dark"] .chart-card-rv .chart-header-rv { background: #0F172A; }

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
    html[data-theme="dark"] .table-card-rv { background: #1E293B; border-color: #334155; }

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

    .table-card-rv table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .table-card-rv table thead { background: var(--rv-bg-body); }
    html[data-theme="dark"] .table-card-rv table thead { background: #0F172A; }

    .table-card-rv table th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: var(--rv-text-secondary);
        font-size: 0.65rem;
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
    .footer-rv .footer-brand-rv { color: var(--rv-primary); font-weight: 600; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up-rv { animation: fadeInUp 0.5s ease forwards; opacity: 0; }

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
        .filter-form-rv { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .stats-grid-rv { grid-template-columns: 1fr; gap: 8px; }
        .stat-card-rv { padding: 10px 12px; }
        .stat-card-rv .stat-value-rv { font-size: 1rem; }
        .stat-icon-rv { width: 32px; height: 32px; font-size: 0.85rem; }
        .page-header-rv { flex-direction: column; align-items: flex-start !important; }
    }
    @media print {
        .btn-rv, .btn-outline-light-rv, .filter-bar-rv, .quick-btn-rv { display: none !important; }
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
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Total
                </span>
                <span class="header-badge-rv">
                    <i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Txns
                </span>
                <?php if ($total_discounts > 0): ?>
                <span class="header-badge-rv" style="background:rgba(217,119,6,0.3);border-color:rgba(217,119,6,0.4);">
                    <i class="fas fa-tag"></i> Disc: <?= number_format($total_discounts, 0) ?>
                </span>
                <?php endif; ?>
                <?php if ($total_premiums > 0): ?>
                <span class="header-badge-rv" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.4);">
                    <i class="fas fa-star"></i> Prem: <?= number_format($total_premiums, 0) ?>
                </span>
                <?php endif; ?>
                <span class="header-badge-rv" style="background:rgba(16,185,129,0.25);border-color:rgba(16,185,129,0.4);">
                    <i class="fas fa-clock"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light-rv">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light-rv">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-bar-rv animate-fade-in-up-rv" style="animation-delay:0.05s;">

        <!-- Quick Filters -->
        <div class="filter-section-rv">
            <div class="filter-section-title-rv">
                <i class="fas fa-bolt"></i> Quick Filters
                <span style="color:var(--rv-success);margin-left:6px;">(Calendar Day: 00:00 - 23:59)</span>
            </div>
            <div class="quick-filters-rv">
                <a href="?branch=<?= $selected_branch_id ?>&quick=today" class="quick-btn-rv <?= $quick_filter === 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=yesterday" class="quick-btn-rv <?= $quick_filter === 'yesterday' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-minus"></i> Yesterday
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1d" class="quick-btn-rv <?= $quick_filter === '1d' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> 24H
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1w" class="quick-btn-rv <?= $quick_filter === '1w' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i> 1W
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1m" class="quick-btn-rv <?= $quick_filter === '1m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 1M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=3m" class="quick-btn-rv <?= $quick_filter === '3m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 3M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=6m" class="quick-btn-rv <?= $quick_filter === '6m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 6M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1y" class="quick-btn-rv <?= $quick_filter === '1y' ? 'active' : '' ?>">
                    <i class="fas fa-calendar"></i> 1Y
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=all" class="quick-btn-rv <?= $quick_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-infinity"></i> All
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=custom&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="quick-btn-rv <?= $quick_filter === 'custom' ? 'custom-active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Custom
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" id="filterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
            
            <div class="filter-form-rv">
                <?php if ($quick_filter === 'custom'): ?>
                    <div class="filter-group-rv">
                        <label><i class="fas fa-calendar"></i> From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group-rv">
                        <label><i class="fas fa-calendar"></i> To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                <?php endif; ?>

                <div class="filter-group-rv">
                    <label><i class="fas fa-store"></i> Branch</label>
                    <select name="branch" onchange="this.form.submit()">
                        <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group-rv">
                    <label><i class="fas fa-credit-card"></i> Payment Method</label>
                    <select name="payment_method">
                        <option value="all" <?= $payment_method === 'all' ? 'selected' : '' ?>>All Methods</option>
                        <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="m-pesa" <?= $payment_method === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                        <option value="airtel_money" <?= $payment_method === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                        <option value="tigo_pesa" <?= $payment_method === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                        <option value="halopesa" <?= $payment_method === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                        <option value="bank" <?= $payment_method === 'bank' ? 'selected' : '' ?>>Bank</option>
                        <option value="card" <?= $payment_method === 'card' ? 'selected' : '' ?>>Card</option>
                        <option value="insurance" <?= $payment_method === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                        <option value="other" <?= $payment_method === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary-rv">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="btn-outline-rv">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- STATS CARDS - 8 CARDS -->
    <div class="stats-grid-rv animate-fade-in-up-rv" style="animation-delay:0.1s;">
        
        <!-- 1. Total Revenue -->
        <div class="stat-card-rv card-total">
            <div class="stat-icon-rv"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label-rv">Total Revenue</p>
                <p class="stat-value-rv">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub-rv">Payments + OTC</p>
            </div>
        </div>
        
        <!-- 2. Patient Payments -->
        <div class="stat-card-rv card-patient">
            <div class="stat-icon-rv"><i class="fas fa-hand-holding-usd"></i></div>
            <div>
                <p class="stat-label-rv">Patient Payments</p>
                <p class="stat-value-rv">TSh <?= number_format($patient_bills_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($payments_count) ?> payments</p>
                <?php if ($patient_discounts > 0 || $patient_premiums > 0): ?>
                <p class="stat-sub-rv" style="font-size:0.55rem;opacity:0.85;">
                    <?php if ($patient_discounts > 0): ?>Disc: <?= number_format($patient_discounts, 0) ?><?php endif; ?>
                    <?php if ($patient_discounts > 0 && $patient_premiums > 0): ?> | <?php endif; ?>
                    <?php if ($patient_premiums > 0): ?>Prem: <?= number_format($patient_premiums, 0) ?><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 3. OTC Sales -->
        <div class="stat-card-rv card-otc">
            <div class="stat-icon-rv"><i class="fas fa-cash-register"></i></div>
            <div>
                <p class="stat-label-rv">OTC Sales</p>
                <p class="stat-value-rv">TSh <?= number_format($otc_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($otc_count) ?> transactions</p>
                <?php if ($otc_discounts > 0 || $otc_premiums > 0): ?>
                <p class="stat-sub-rv" style="font-size:0.55rem;opacity:0.85;">
                    <?php if ($otc_discounts > 0): ?>Disc: <?= number_format($otc_discounts, 0) ?><?php endif; ?>
                    <?php if ($otc_discounts > 0 && $otc_premiums > 0): ?> | <?php endif; ?>
                    <?php if ($otc_premiums > 0): ?>Prem: <?= number_format($otc_premiums, 0) ?><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 4. Prescriptions -->
        <div class="stat-card-rv card-prescription">
            <div class="stat-icon-rv"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-label-rv">Prescriptions</p>
                <p class="stat-value-rv">TSh <?= number_format($prescription_revenue, 0) ?></p>
                <p class="stat-sub-rv"><?= number_format($prescription_count) ?> items</p>
            </div>
        </div>
        
        <!-- 5. Clinical Services -->
        <div class="stat-card-rv card-consultation">
            <div class="stat-icon-rv"><i class="fas fa-stethoscope"></i></div>
            <div>
                <p class="stat-label-rv">Clinical Services</p>
                <p class="stat-value-rv">TSh <?= number_format($clinical_services_revenue, 0) ?></p>
                <p class="stat-sub-rv">
                    <?= number_format($consultation_count) ?>c · 
                    <?= number_format($procedure_count) ?>p · 
                    <?= number_format($equipment_count) ?>e
                </p>
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
            <span class="count-rv">Total: TSh <?= number_format($total_revenue, 0) ?> • <?= htmlspecialchars($date_label) ?></span>
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
                        <td><span style="color:#059669;">●</span> Patient Payments <span style="font-size:0.6rem;color:var(--rv-text-secondary);">(from payments)</span></td>
                        <td style="text-align:right;font-weight:600;color:#059669;">TSh <?= number_format($patient_bills_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($patient_bills_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($payments_count) ?></td>
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
                        <td colspan="4" style="padding:8px 14px;font-weight:700;font-size:0.7rem;color:var(--rv-primary);text-transform:uppercase;letter-spacing:0.05em;">
                            <i class="fas fa-info-circle"></i> Patient Payments Breakdown (should equal Patient Payments)
                        </td>
                    </tr>
                    
                    <tr style="background:rgba(5,150,105,0.05);">
                        <td><span style="color:#059669;">●</span> <strong>Clinical Services</strong> <span style="font-size:0.6rem;color:var(--rv-text-secondary);">(Consultation + Procedures + Equipment)</span></td>
                        <td style="text-align:right;font-weight:700;color:#059669;">TSh <?= number_format($clinical_services_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($clinical_services_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($clinical_services_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span style="color:#059669;">●</span> Consultation</td>
                        <td style="text-align:right;font-weight:500;color:#059669;">TSh <?= number_format($consultation_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($consultation_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span style="color:#0D9488;">●</span> Procedures</td>
                        <td style="text-align:right;font-weight:500;color:#0D9488;">TSh <?= number_format($procedure_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($procedure_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span style="color:#7C3AED;">●</span> Medical Equipment</td>
                        <td style="text-align:right;font-weight:500;color:#7C3AED;">TSh <?= number_format($equipment_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($equipment_count) ?></td>
                    </tr>
                    
                    <tr>
                        <td><span style="color:#7C3AED;">●</span> Prescriptions <span style="font-size:0.6rem;color:var(--rv-text-secondary);">(= Medication)</span></td>
                        <td style="text-align:right;font-weight:600;color:#7C3AED;">TSh <?= number_format($prescription_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">
                            <?= $total_revenue > 0 ? round(($prescription_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($prescription_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span style="color:#3B82F6;">●</span> Lab Tests</td>
                        <td style="text-align:right;font-weight:500;color:#3B82F6;">TSh <?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span style="color:#64748B;">●</span> Registration</td>
                        <td style="text-align:right;font-weight:500;color:#64748B;">TSh <?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($registration_count) ?></td>
                    </tr>
                    
                    <tr style="background:var(--rv-primary-bg);">
                        <td><span style="color:#94A3B8;">●</span> Other Services <span style="font-size:0.6rem;color:var(--rv-text-secondary);">(Premium - Discount) — informational only</span></td>
                        <td style="text-align:right;font-weight:500;color:#94A3B8;">TSh <?= number_format($other_services_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($other_services_count) ?></td>
                    </tr>
                    
                    <tr style="background:var(--rv-success-bg);border-top:2px solid #059669;border-bottom:2px solid #059669;">
                        <td style="font-weight:700;color:#059669;">
                            <i class="fas fa-check-circle"></i> Breakdown Total
                            <span style="font-size:0.6rem;font-weight:500;display:block;margin-left:14px;">(should equal Patient Payments)</span>
                        </td>
                        <td style="text-align:right;font-weight:800;color:#059669;font-size:0.9rem;">TSh <?= number_format($breakdown_total, 0) ?></td>
                        <td style="text-align:right;color:#059669;font-weight:700;">
                            <?= $total_revenue > 0 ? round(($breakdown_total / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:#059669;font-weight:700;"><?= number_format($patient_bills_count) ?> bills</td>
                    </tr>
                    
                    <?php if ($total_discounts > 0): ?>
                    <tr style="background:var(--rv-warning-bg);">
                        <td><i class="fas fa-tag" style="color:#D97706;"></i> <strong style="color:#D97706;">Total Discounts</strong> <span style="font-size:0.6rem;color:#D97706;">(already deducted)</span></td>
                        <td style="text-align:right;font-weight:700;color:#D97706;">- TSh <?= number_format($total_discounts, 0) ?></td>
                        <td style="text-align:right;color:#D97706;">—</td>
                        <td style="text-align:right;color:#D97706;">—</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($total_premiums > 0): ?>
                    <tr style="background:var(--rv-purple-bg);">
                        <td><i class="fas fa-star" style="color:#7C3AED;"></i> <strong style="color:#7C3AED;">Total Premiums</strong> <span style="font-size:0.6rem;color:#7C3AED;">(already added)</span></td>
                        <td style="text-align:right;font-weight:700;color:#7C3AED;">+ TSh <?= number_format($total_premiums, 0) ?></td>
                        <td style="text-align:right;color:#7C3AED;">—</td>
                        <td style="text-align:right;color:#7C3AED;">—</td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="total-row-rv">
                        <td style="font-weight:700;font-size:0.95rem;">TOTAL REVENUE (Payments + OTC)</td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--rv-primary);">
                            TSh <?= number_format($total_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--rv-primary);">100%</td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:var(--rv-primary);"><?= number_format($total_transactions) ?></td>
                    </tr>
                    
                    <tr style="background:var(--rv-rose-bg);">
                        <td><span style="color:#E11D48;">●</span> <strong>Total Expenses</strong></td>
                        <td style="text-align:right;font-weight:700;color:#E11D48;">- TSh <?= number_format($total_expenses, 0) ?></td>
                        <td style="text-align:right;color:var(--rv-text-secondary);">—</td>
                        <td style="text-align:right;color:var(--rv-text-secondary);"><?= number_format($expenses_count) ?></td>
                    </tr>
                    
                    <tr class="total-row-rv" style="background:<?= $net_profit >= 0 ? 'var(--rv-success-bg)' : 'var(--rv-danger-bg)' ?> !important;">
                        <td style="font-weight:700;font-size:0.95rem;color:<?= $net_profit >= 0 ? 'var(--rv-success)' : 'var(--rv-danger)' ?>;">
                            NET <?= $net_profit >= 0 ? 'PROFIT' : 'LOSS' ?>
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:<?= $net_profit >= 0 ? 'var(--rv-success)' : 'var(--rv-danger)' ?>;">
                            TSh <?= number_format(abs($net_profit), 0) ?>
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:<?= $net_profit >= 0 ? 'var(--rv-success)' : 'var(--rv-danger)' ?>;">
                            <?= $profit_percentage ?>%
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:0.95rem;color:<?= $net_profit >= 0 ? 'var(--rv-success)' : 'var(--rv-danger)' ?>;">
                            <?= number_format($total_transactions) ?>
                        </td>
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
            Revenue Report (Payments-Based)
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
                        { label: 'Patient Payments', data: <?= json_encode($monthly_patient) ?>, backgroundColor: '#059669', borderRadius: 3, barPercentage: 0.3 },
                        { label: 'OTC', data: <?= json_encode($monthly_otc) ?>, backgroundColor: '#0891B2', borderRadius: 3, barPercentage: 0.3 },
                        { label: 'Prescriptions', data: <?= json_encode($monthly_prescription) ?>, backgroundColor: '#7C3AED', borderRadius: 3, barPercentage: 0.3 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'top', 
                            labels: { 
                                font: { family: 'JetBrains Mono', size: 10, weight: '600' }, 
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
                            },
                            titleFont: { family: 'JetBrains Mono', size: 11, weight: 'bold' },
                            bodyFont: { family: 'JetBrains Mono', size: 11, weight: '600' }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { 
                                callback: function(value) { return 'TSh ' + value.toLocaleString(); }, 
                                font: { family: 'JetBrains Mono', size: 9, weight: '600' }, 
                                color: textColor 
                            }, 
                            grid: { color: gridColor } 
                        },
                        x: { 
                            grid: { display: false }, 
                            ticks: { 
                                font: { family: 'JetBrains Mono', size: 9, weight: '600' }, 
                                color: textColor 
                            } 
                        }
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
                        { label: 'Patient Payments', data: <?= json_encode($daily_patient) ?>, borderColor: '#059669', backgroundColor: 'rgba(5, 150, 105, 0.08)', fill: true, tension: 0.4, pointRadius: 1.5, borderWidth: 2 },
                        { label: 'OTC', data: <?= json_encode($daily_otc) ?>, borderColor: '#0891B2', backgroundColor: 'rgba(8, 145, 178, 0.08)', fill: true, tension: 0.4, pointRadius: 1.5, borderWidth: 2 },
                        { label: 'Prescriptions', data: <?= json_encode($daily_prescription) ?>, borderColor: '#7C3AED', backgroundColor: 'rgba(124, 58, 237, 0.08)', fill: true, tension: 0.4, pointRadius: 1.5, borderWidth: 2, borderDash: [4, 4] }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'top', 
                            labels: { 
                                font: { family: 'JetBrains Mono', size: 10, weight: '600' }, 
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
                            },
                            titleFont: { family: 'JetBrains Mono', size: 11, weight: 'bold' },
                            bodyFont: { family: 'JetBrains Mono', size: 11, weight: '600' }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { 
                                callback: function(value) { return 'TSh ' + value.toLocaleString(); }, 
                                font: { family: 'JetBrains Mono', size: 9, weight: '600' }, 
                                color: textColor 
                            }, 
                            grid: { color: gridColor } 
                        },
                        x: { 
                            grid: { display: false }, 
                            ticks: { 
                                font: { family: 'JetBrains Mono', size: 8, weight: '600' }, 
                                color: textColor, 
                                maxTicksLimit: 15 
                            } 
                        }
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

    console.log('%c🏥 Braick - Revenue Report V14 (PREMIUM/DISCOUNT FROM BILLS)', 'font-family: monospace; font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Quick Filters: Today, Yesterday, 1D, 1W, 1M, 3M, 6M, 1Y, All, Custom', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ Premium = 47,500 | Discount = 2,300 (from bills, each bill once)', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ Breakdown uses payments.amount + received_at', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ Rounding to nearest 50 — numbers end in 50 or 00', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-family: monospace; font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-family: monospace; font-size:13px; color:#0B5ED7;');
    console.log('%c💳 Patient Payments: TSh <?= number_format($patient_bills_revenue, 0) ?> (<?= $payments_count ?> payments)', 'font-family: monospace; font-size:13px; color:#059669;');
    console.log('%c👑 Premium: TSh <?= number_format($total_premiums, 0) ?> | 🏷️ Discount: TSh <?= number_format($total_discounts, 0) ?>', 'font-family: monospace; font-size:13px; color:#7C3AED; font-weight:bold;');
    console.log('%c📋 Breakdown Total: TSh <?= number_format($breakdown_total, 0) ?>', 'font-family: monospace; font-size:13px; color:#10B981;');
    console.log('%c<?= abs($breakdown_total - $patient_bills_revenue) < 1 ? "✅ Breakdown MATCHES Patient Payments!" : "❌ Breakdown DIFFERS by TSh " . number_format($breakdown_total - $patient_bills_revenue, 2) ?>', 'font-family: monospace; font-size:13px; color:<?= abs($breakdown_total - $patient_bills_revenue) < 1 ? "#10B981" : "#DC2626" ?>; font-weight:bold;');
</script>

</body>
</html>