<?php
// ================================================================
// FILE: frontend/pages/admin/audit/revenue.php
// ADMIN AUDIT - REVENUE REPORT (V29 - PRESCRIPTION = BILLS EXACT)
// ✅ Prescriptions = bill_items.total_price - discount (EXACT from paid bills)
// ✅ Breakdown Total = Patient Payments (rounded 50)
// ✅ Rounding to nearest 50 — numbers end in 50 or 00
// ✅ Premium & Discount from bills (each bill counted ONCE)
// ✅ Other Services = Premium - Discount (informational only)
// ✅ Calendar Day Filter (00:00 - 23:59)
// ✅ OTC Sales: created_at (siku husika)
// ✅ Timezone: Africa/Dar_es_Salaam + SET time_zone = '+03:00'
// ✅ Debug mode: ?debug=1
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'audit': header('Location: /dispensary_system/frontend/pages/audit/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
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

$selected_branch_id = $_GET['branch'] ?? 'all';

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$payments_date_col = 'received_at';
$otc_date_col = 'created_at';
$expenses_date_col = 'payment_date';

// ================================================================
// HELPER: Round value to nearest 50
// ================================================================
function roundTo50($value) {
    return round($value / 50) * 50;
}

// ================================================================
// HELPER: Round breakdown items to match target
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
// DELETE ACTIONS
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'delete_bill' && !empty($_POST['bill_id'])) {
        try {
            $bill_id = (int)$_POST['bill_id'];
            $stmt = $db->prepare("SELECT bill_number, total_amount FROM bills WHERE id = ?");
            $stmt->execute([$bill_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bill) {
                $db->prepare("DELETE FROM bill_items WHERE bill_id = ?")->execute([$bill_id]);
                $db->prepare("DELETE FROM payments WHERE bill_id = ?")->execute([$bill_id]);
                $db->prepare("DELETE FROM bills WHERE id = ?")->execute([$bill_id]);
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'delete_bill', ?, ?, NOW())")
                       ->execute([$user_id, $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id, "Deleted bill: " . ($bill['bill_number'] ?? 'N/A'), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } catch (Exception $e) {}
                $alert_message = "Bill deleted successfully!";
                $alert_type = 'success';
            }
        } catch (Exception $e) {
            $alert_message = "Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    if ($_POST['action'] === 'delete_otc' && !empty($_POST['sale_id'])) {
        try {
            $sale_id = (int)$_POST['sale_id'];
            $stmt = $db->prepare("SELECT sale_number, total_amount FROM otc_sales WHERE id = ?");
            $stmt->execute([$sale_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sale) {
                $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?")->execute([$sale_id]);
                $db->prepare("DELETE FROM otc_sales WHERE id = ?")->execute([$sale_id]);
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'delete_otc_sale', ?, ?, NOW())")
                       ->execute([$user_id, $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id, "Deleted OTC sale: " . ($sale['sale_number'] ?? 'N/A'), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } catch (Exception $e) {}
                $alert_message = "OTC Sale deleted successfully!";
                $alert_type = 'success';
            }
        } catch (Exception $e) {
            $alert_message = "Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    if ($_POST['action'] === 'delete_expense' && !empty($_POST['expense_id'])) {
        try {
            $expense_id = (int)$_POST['expense_id'];
            $stmt = $db->prepare("SELECT expense_number, amount FROM expenses WHERE id = ?");
            $stmt->execute([$expense_id]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($expense) {
                $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$expense_id]);
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'delete_expense', ?, ?, NOW())")
                       ->execute([$user_id, $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id, "Deleted expense: " . ($expense['expense_number'] ?? 'N/A'), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } catch (Exception $e) {}
                $alert_message = "Expense deleted successfully!";
                $alert_type = 'success';
            }
        } catch (Exception $e) {
            $alert_message = "Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    if ($_POST['action'] === 'delete_payment' && !empty($_POST['payment_id'])) {
        try {
            $payment_id = (int)$_POST['payment_id'];
            $stmt = $db->prepare("SELECT p.receipt_number, p.amount, p.bill_id, b.bill_number FROM payments p LEFT JOIN bills b ON p.bill_id = b.id WHERE p.id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($payment) {
                $bill_id = $payment['bill_id'];
                $db->prepare("DELETE FROM payments WHERE id = ?")->execute([$payment_id]);
                if ($bill_id) {
                    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE bill_id = ?");
                    $stmt->execute([$bill_id]);
                    $new_paid = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0);
                    $stmt = $db->prepare("SELECT total_amount FROM bills WHERE id = ?");
                    $stmt->execute([$bill_id]);
                    $bill_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0);
                    $new_balance = max(0, $bill_total - $new_paid);
                    $new_status = 'pending';
                    if ($new_balance <= 0 && $bill_total > 0) $new_status = 'paid';
                    elseif ($new_paid > 0 && $new_balance > 0) $new_status = 'partial';
                    $db->prepare("UPDATE bills SET paid_amount = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$new_paid, $new_balance, $new_status, $bill_id]);
                }
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'delete_payment', ?, ?, NOW())")
                       ->execute([$user_id, $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id, "Deleted payment: " . ($payment['receipt_number'] ?? 'N/A'), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } catch (Exception $e) {}
                $alert_message = "Payment deleted! Bill recalculated.";
                $alert_type = 'success';
            }
        } catch (Exception $e) {
            $alert_message = "Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// BRANCH
$branch_name_display = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
}

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FILTERS
$quick_filter = $_GET['quick'] ?? 'today';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_method = $_GET['payment_method'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$show_debug = isset($_GET['debug']) && $_GET['debug'] == '1';

// ================================================================
// CALENDAR DAY FILTER (00:00 - 23:59)
// ================================================================
$date_cond_payments = ""; $date_cond_otc = ""; $date_cond_exp = "";
$date_params = []; $date_label = "";

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
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) = CURDATE()";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) = CURDATE()";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) = CURDATE()";
        $date_label = "Today • " . date('d M Y');
}

// PAYMENT METHOD
$pay_cond_payments = ""; $pay_cond_otc = ""; $pay_params = [];
if ($payment_method !== 'all') {
    $pay_cond_payments = " AND p.payment_method = ?";
    $pay_cond_otc = " AND o.payment_method = ?";
    $pay_params = [$payment_method];
}

// BRANCH CONDITIONS
$branch_cond_p = ""; $branch_cond_o = ""; $branch_cond_e = "";
$branch_params_p = []; $branch_params_o = []; $branch_params_e = [];
if ($selected_branch_id !== 'all') {
    $branch_cond_p = " AND p.branch_id = ?";
    $branch_cond_o = " AND o.branch_id = ?";
    $branch_cond_e = " AND e.branch_id = ?";
    $branch_params_p = [(int)$selected_branch_id];
    $branch_params_o = [(int)$selected_branch_id];
    $branch_params_e = [(int)$selected_branch_id];
}

// ================================================================
// 1. PATIENT PAYMENTS (from payments.received_at)
// ================================================================
$patient_bills_revenue = 0; $patient_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total, COUNT(*) as count 
            FROM payments p 
            WHERE p.bill_id IS NOT NULL 
            $branch_cond_p $date_cond_payments $pay_cond_payments";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($data['total'] ?? 0);
    $patient_bills_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// ✅ Round patient payments
$patient_bills_revenue_rounded = roundTo50($patient_bills_revenue);

// ================================================================
// 2. OTC REVENUE (from otc_sales.created_at)
// ================================================================
$otc_revenue = 0; $otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales o 
            WHERE o.payment_status = 'paid' 
            $branch_cond_o $date_cond_otc $pay_cond_otc";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = (float)($data['total'] ?? 0);
    $otc_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// 3. PRESCRIPTION = BILL ITEMS EXACT (from paid bills)
// ================================================================
$prescription_revenue = 0; $prescription_count = 0;
try {
    $sql = "SELECT 
                COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.reference_type = 'prescription'
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
            AND b.patient_id IS NOT NULL
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
    $prescription_revenue_raw = (float)($data['total'] ?? 0);
    $prescription_count = (int)($data['count'] ?? 0);
    // ✅ Round to nearest 50
    $prescription_revenue = roundTo50($prescription_revenue_raw);
} catch (Exception $e) {}

// ================================================================
// 4. DISCOUNTS + PREMIUMS (from bills - each bill counted ONCE)
// ================================================================
$patient_discounts = 0; $patient_premiums = 0;
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

$otc_discounts = 0; $otc_premiums = 0;
try {
    $sql = "SELECT 
                COALESCE(SUM(o.discount_amount), 0) as total_discount,
                COALESCE(SUM(o.premium_amount), 0) as total_premium
            FROM otc_sales o
            WHERE o.payment_status = 'paid'
            $branch_cond_o $date_cond_otc $pay_cond_otc";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_discounts = (float)($data['total_discount'] ?? 0);
    $otc_premiums = (float)($data['total_premium'] ?? 0);
} catch (Exception $e) {}

$total_discounts = $patient_discounts + $otc_discounts;
$total_premiums = $patient_premiums + $otc_premiums;

// ================================================================
// ✅ V29: BREAKDOWN - Proportion from payments.amount
// Kisha round kila item kwa 50, adjust ili jumla = Patient Payments (rounded)
// ================================================================
$breakdown_types = ['consultation', 'lab_test', 'procedure', 'medication', 'registration', 'equipment'];
$breakdown_data = [];
foreach ($breakdown_types as $type) {
    $breakdown_data[$type] = ['revenue' => 0, 'count' => 0];
    try {
        $sql = "SELECT 
                    COALESCE(SUM(
                        (bi.total_price / bill_totals.items_total) * p.amount
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
                AND bill_totals.items_total > 0
                AND b.patient_id IS NOT NULL 
                AND b.visit_id IS NOT NULL 
                AND b.bill_number NOT LIKE 'BILL-OTC-%' 
                $branch_cond_p $date_cond_payments $pay_cond_payments";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$type], $branch_params_p, $date_params, $pay_params));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $breakdown_data[$type] = ['revenue' => (float)($data['total'] ?? 0), 'count' => (int)($data['count'] ?? 0)];
    } catch (Exception $e) {}
}

// ✅ RAW VALUES
$raw_breakdown = [
    'consultation' => $breakdown_data['consultation']['revenue'],
    'lab_test' => $breakdown_data['lab_test']['revenue'],
    'procedure' => $breakdown_data['procedure']['revenue'],
    'medication' => $breakdown_data['medication']['revenue'],
    'registration' => $breakdown_data['registration']['revenue'],
    'equipment' => $breakdown_data['equipment']['revenue'],
];

// ✅ Apply rounding to match patient_bills_revenue (rounded)
$target_total = $patient_bills_revenue_rounded;
$rounded_breakdown = roundBreakdownToMatch($raw_breakdown, $target_total, 50);

// ✅ OVERRIDE medication with EXACT prescription value (rounded)
$rounded_breakdown['medication'] = $prescription_revenue;

// ✅ Re-adjust other items to keep total = target
$current_sum = array_sum($rounded_breakdown);
$diff = $target_total - $current_sum;
if (abs($diff) >= 50) {
    // Distribute to the largest non-medication item
    $adjustable = $rounded_breakdown;
    unset($adjustable['medication']);
    if (!empty($adjustable)) {
        arsort($adjustable);
        $largest_key = array_key_first($adjustable);
        $rounded_breakdown[$largest_key] += $diff;
    }
} elseif (abs($diff) > 0.001) {
    $adjustable = $rounded_breakdown;
    unset($adjustable['medication']);
    if (!empty($adjustable)) {
        arsort($adjustable);
        $largest_key = array_key_first($adjustable);
        $rounded_breakdown[$largest_key] += $diff;
    }
}

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

// Breakdown Total (should = Patient Payments, rounded)
$breakdown_total = $consultation_revenue + $lab_revenue + $procedure_revenue 
                 + $medication_revenue + $registration_revenue + $equipment_revenue;

// Prescription = Medication (already set above)
$prescription_revenue = $medication_revenue;

// ================================================================
// 5. OTHER SERVICES (informational only - from bills, each bill once)
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
    $other_services_revenue_raw = (float)($data['total'] ?? 0);
    $other_services_revenue = roundTo50($other_services_revenue_raw);
    $other_services_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$total_revenue = $patient_bills_revenue_rounded + $otc_revenue;
$total_transactions = $patient_bills_count + $otc_count;

// ================================================================
// 6. EXPENSES
// ================================================================
$total_expenses = 0; $expenses_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(e.amount), 0) as total, COUNT(*) as count FROM expenses e WHERE e.status = 'paid' $branch_cond_e $date_cond_exp";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_e, $date_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = (float)($data['total'] ?? 0);
    $expenses_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// ================================================================
// TRANSACTIONS
// ================================================================
$transactions = [];
try {
    $search_cond_payments = ""; $search_cond_otc = "";
    $search_params_p = []; $search_params_o = [];
    if (!empty($search)) {
        $search_cond_payments = " AND (p.receipt_number LIKE ? OR b.bill_number LIKE ? OR pat.full_name LIKE ? OR u.full_name LIKE ?)";
        $search_params_p = ["%$search%", "%$search%", "%$search%", "%$search%"];
        $search_cond_otc = " AND (o.sale_number LIKE ? OR o.customer_name LIKE ? OR u2.full_name LIKE ? OR o.payment_method LIKE ?)";
        $search_params_o = ["%$search%", "%$search%", "%$search%", "%$search%"];
    }
    
    $union_sql = "
        SELECT 'payment' as transaction_type, p.id, p.receipt_number as reference_number, b.bill_number,
            p.amount, p.payment_method, 'paid' as status, p.received_at as transaction_date, p.received_at,
            COALESCE(pat.full_name, 'Walk-in') as customer_name,
            COALESCE(u.full_name, 'N/A') as received_by_name,
            COALESCE(u.role, 'user') as received_by_role,
            br.name as branch_name, pat.phone as customer_phone, b.id as bill_id,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            (SELECT GROUP_CONCAT(DISTINCT item_type ORDER BY item_type SEPARATOR ',') FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_types,
            (SELECT GROUP_CONCAT(CONCAT(item_name, ' (', quantity, ')') SEPARATOR '\n') FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as items_summary,
            b.total_amount as bill_total, b.paid_amount as bill_paid, b.balance as bill_balance,
            b.total_discount as bill_discount, b.premium_amount as bill_premium
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        LEFT JOIN branches br ON p.branch_id = br.id
        WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $branch_cond_p $date_cond_payments $pay_cond_payments $search_cond_payments
        
        UNION ALL
        
        SELECT 'otc' as transaction_type, o.id, o.sale_number as reference_number, o.sale_number as bill_number,
            o.total_amount as amount, o.payment_method, o.payment_status as status, o.{$otc_date_col} as transaction_date, o.{$otc_date_col} as received_at,
            COALESCE(o.customer_name, 'Walk-in') as customer_name,
            COALESCE(u2.full_name, 'N/A') as received_by_name,
            COALESCE(u2.role, 'user') as received_by_role,
            br2.name as branch_name, o.customer_phone, NULL as bill_id,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            'medication' as item_types,
            (SELECT GROUP_CONCAT(CONCAT(item_name, ' (', quantity, ')') SEPARATOR '\n') FROM otc_sale_items WHERE sale_id = o.id) as items_summary,
            o.total_amount as bill_total, o.total_amount as bill_paid, 0 as bill_balance,
            o.discount_amount as bill_discount, o.premium_amount as bill_premium
        FROM otc_sales o
        LEFT JOIN users u2 ON o.sold_by = u2.id
        LEFT JOIN branches br2 ON o.branch_id = br2.id
        WHERE o.payment_status = 'paid'
        $branch_cond_o $date_cond_otc $pay_cond_otc $search_cond_otc
        
        ORDER BY transaction_date DESC LIMIT 500
    ";
    
    $union_params = array_merge(
        $branch_params_p, $date_params, $pay_params, $search_params_p,
        $branch_params_o, $date_params, $pay_params, $search_params_o
    );
    
    $stmt = $db->prepare($union_sql);
    $stmt->execute($union_params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// EXPENSES LIST
$expenses_list = [];
try {
    $sql = "SELECT e.id, e.expense_number, e.category, e.description, e.amount, e.payment_method, e.payment_date,
                e.status, e.receipt_number, e.created_at, e.created_by,
                u.full_name as recorded_by_name, u.role as recorded_by_role, br.name as branch_name
            FROM expenses e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN branches br ON e.branch_id = br.id
            WHERE e.status = 'paid' $branch_cond_e $date_cond_exp
            ORDER BY e.payment_date DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_e, $date_params));
    $expenses_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// MONTHLY CHART
$monthly_labels = []; $monthly_patient = []; $monthly_otc = []; $monthly_expenses = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_labels[] = date('M Y', strtotime("-$i months"));
    $p_p = [$month]; $p_o = [$month]; $p_e = [$month];
    if ($selected_branch_id !== 'all') { $p_p[] = (int)$selected_branch_id; $p_o[] = (int)$selected_branch_id; $p_e[] = (int)$selected_branch_id; }
    
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total FROM payments p WHERE p.bill_id IS NOT NULL AND DATE_FORMAT(p.{$payments_date_col}, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_p);
    $monthly_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales WHERE payment_status = 'paid' AND DATE_FORMAT({$otc_date_col}, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_o);
    $monthly_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 'paid' AND DATE_FORMAT({$expenses_date_col}, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_e);
    $monthly_expenses[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

// DAILY CHART
$daily_labels = []; $daily_patient = []; $daily_otc = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    $p_p = [$date]; $p_o = [$date];
    if ($selected_branch_id !== 'all') { $p_p[] = (int)$selected_branch_id; $p_o[] = (int)$selected_branch_id; }
    
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total FROM payments p WHERE p.bill_id IS NOT NULL AND DATE(p.{$payments_date_col}) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND p.branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_p);
    $daily_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales WHERE payment_status = 'paid' AND DATE({$otc_date_col}) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_o);
    $daily_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revenue Report • Braick Admin Audit</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* ================================================================
   ROOT VARIABLES
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --success: #059669;
    --success-light: #34D399;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-light: #F87171;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-light: #FBBF24;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-light: #A78BFA;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-light: #22D3EE;
    --cyan-bg: #CFFAFE;
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
    --slate: #94A3B8;
    --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --border-strong: #CBD5E1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.05);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15), 0 10px 20px rgba(0,0,0,0.08);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
    --radius-full: 9999px;
    --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-base: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] {
    --bg-body: #0B1220;
    --bg-card: #111C33;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #1E2E4A;
    --border-strong: #2A3E5F;
    --primary-bg: #12294A;
    --success-bg: #0F2E22;
    --danger-bg: #3A1414;
    --warning-bg: #3A2A0F;
    --purple-bg: #2A1A4A;
    --cyan-bg: #0A2E3A;
    --teal-bg: #0A2E2A;
    --slate-bg: #1E2A3D;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    line-height: 1.5;
    min-height: 100vh;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: 
        radial-gradient(circle at 20% 0%, rgba(11, 94, 215, 0.04) 0%, transparent 50%),
        radial-gradient(circle at 80% 100%, rgba(124, 58, 237, 0.04) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}

.money-number, .stat-value, .money-cell, .font-mono {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.alert {
    padding: 14px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 0.85rem;
    animation: slideDown 0.4s ease;
    border-left: 4px solid;
    box-shadow: var(--shadow-sm);
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert.success { background: var(--success-bg); color: var(--success); border-left-color: var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left-color: var(--danger); }

.debug-panel {
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    border: 2px solid #F59E0B;
    border-radius: var(--radius-md);
    padding: 14px 18px;
    margin-bottom: 18px;
    font-family: var(--font-mono);
    font-size: 0.72rem;
    color: #78350F;
    box-shadow: var(--shadow-md);
}
.debug-panel strong { display: block; margin-bottom: 8px; font-size: 0.78rem; color: #B45309; letter-spacing: 0.03em; }
.debug-panel .debug-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dashed rgba(120, 53, 15, 0.2); }
.debug-panel .debug-row:last-child { border-bottom: none; }
.debug-panel .debug-row span:last-child { font-weight: 800; }

.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35);
    position: relative;
    overflow: hidden;
}
.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
    animation: floatBubble 8s ease-in-out infinite;
}
@keyframes floatBubble {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-20px, -20px) scale(1.1); }
}
.page-header .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    letter-spacing: -0.03em;
}
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; filter: drop-shadow(0 0 10px rgba(147, 197, 253, 0.6)); }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
    position: relative;
    z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 0.68rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    transition: all var(--transition-base);
}
.branch-tag:hover { background: rgba(255,255,255,0.25); transform: translateY(-1px); }
.branch-tag.filter-tag {
    background: linear-gradient(135deg, #10B981, #059669);
    border-color: rgba(255,255,255,0.25);
    font-weight: 800;
}
.branch-tag.disc-tag { background: linear-gradient(135deg, #F59E0B, #D97706); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.branch-tag.prem-tag { background: linear-gradient(135deg, #7C3AED, #A78BFA); border-color: rgba(255,255,255,0.25); font-weight: 800; }

.btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 10px 16px;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: 0.75rem;
    transition: all var(--transition-base);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
    cursor: pointer;
    font-family: var(--font-primary);
}
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

.filter-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 18px 20px;
    border: 1px solid var(--border-color);
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    transition: all var(--transition-base);
}
.filter-card:hover { box-shadow: var(--shadow-md); }
.filter-section { margin-bottom: 14px; }
.filter-section:last-child { margin-bottom: 0; }
.filter-section-title {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-secondary);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.quick-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.quick-btn {
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-weight: 700;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    font-family: var(--font-primary);
    white-space: nowrap;
}
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); box-shadow: 0 6px 14px rgba(11, 94, 215, 0.15); }
.quick-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: transparent;
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35);
}
.quick-btn.custom-active { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; border-color: transparent; }

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    align-items: end;
    margin-top: 12px;
    padding-top: 14px;
    border-top: 1.5px dashed var(--border-color);
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 5px;
}
.filter-group input, .filter-group select {
    padding: 9px 12px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.8rem;
    font-weight: 600;
    outline: none;
    transition: all var(--transition-base);
    height: 38px;
    font-family: var(--font-primary);
}
.filter-group input:focus, .filter-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12); }
.filter-btn-primary {
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    font-weight: 700;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
    height: 38px;
    font-family: var(--font-primary);
}
.filter-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4); }
.filter-btn-secondary {
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--text-secondary);
    border: 1.5px solid var(--border-color);
    font-weight: 700;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
    height: 38px;
    text-decoration: none;
    font-family: var(--font-primary);
}
.filter-btn-secondary:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-bg); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    border: 1px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    min-height: 155px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    transition: height var(--transition-base);
}
.stat-card:hover::before { height: 5px; }
.stat-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
    margin-bottom: 10px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.18);
    transition: transform var(--transition-base);
}
.stat-card:hover .stat-icon { transform: scale(1.15) rotate(-6deg); }
.stat-card .stat-label {
    font-size: 0.64rem;
    color: var(--text-secondary);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 6px;
}
.stat-card .stat-value {
    font-size: 1.35rem;
    font-weight: 900;
    color: var(--text-primary);
    line-height: 1.1;
    letter-spacing: -0.04em;
    display: flex;
    align-items: baseline;
    gap: 4px;
    flex-wrap: wrap;
}
.stat-card .stat-value .currency-symbol {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-secondary);
    font-family: var(--font-primary);
}
.stat-card .stat-sub {
    font-size: 0.62rem;
    color: var(--text-secondary);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed var(--border-color);
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
    flex-wrap: wrap;
}
.stat-card .stat-sub i { font-size: 0.58rem; }

.stat-card .stat-breakdown {
    display: flex;
    gap: 8px;
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px dashed var(--border-color);
    flex-wrap: wrap;
}
.stat-card .stat-breakdown .breakdown-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    font-weight: 800;
    font-family: var(--font-mono);
    padding: 3px 8px;
    border-radius: 6px;
    transition: all var(--transition-base);
    cursor: help;
}
.stat-card .stat-breakdown .breakdown-item.discount {
    background: var(--warning-bg);
    color: var(--warning);
    border: 1px solid rgba(217, 119, 6, 0.25);
}
.stat-card .stat-breakdown .breakdown-item.premium {
    background: var(--purple-bg);
    color: var(--purple);
    border: 1px solid rgba(124, 58, 237, 0.25);
}
.stat-card .stat-breakdown .breakdown-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.stat-card .stat-breakdown .breakdown-item i { font-size: 0.55rem; }

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.stat-card.revenue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.revenue:hover { box-shadow: 0 20px 40px rgba(11, 94, 215, 0.25); }
.stat-card.revenue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.revenue .stat-value .money-number { color: var(--primary); }

.stat-card.prescription::before { background: linear-gradient(90deg, #7C3AED, #A78BFA, #7C3AED); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.prescription:hover { box-shadow: 0 20px 40px rgba(124, 58, 237, 0.25); }
.stat-card.prescription .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.prescription .stat-value .money-number { color: var(--purple); }

.stat-card.otc::before { background: linear-gradient(90deg, #0891B2, #06B6D4, #0891B2); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.otc:hover { box-shadow: 0 20px 40px rgba(8, 145, 178, 0.25); }
.stat-card.otc .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.otc .stat-value .money-number { color: var(--cyan); }

.stat-card.consultation {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card) 70%, rgba(5, 150, 105, 0.06) 100%);
}
.stat-card.consultation::before { background: linear-gradient(90deg, #059669, #34D399, #0D9488, #7C3AED, #059669); background-size: 300% 100%; animation: shimmer 4s infinite linear; }
.stat-card.consultation:hover { box-shadow: 0 20px 40px rgba(5, 150, 105, 0.35); }
.stat-card.consultation .stat-icon { background: linear-gradient(135deg, #059669, #0D9488, #7C3AED); }
.stat-card.consultation .stat-value .money-number { color: var(--success); }
.stat-card.consultation .stat-sub span[title] {
    display: inline-block;
    padding: 2px 6px;
    background: var(--success-bg);
    color: var(--success);
    border-radius: 4px;
    font-weight: 800;
    font-size: 0.58rem;
    font-family: var(--font-mono);
    cursor: help;
    border: 1px solid rgba(5, 150, 105, 0.2);
}

.stat-card.lab::before { background: linear-gradient(90deg, #3B82F6, #93C5FD, #3B82F6); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.lab:hover { box-shadow: 0 20px 40px rgba(59, 130, 246, 0.25); }
.stat-card.lab .stat-icon { background: linear-gradient(135deg, #3B82F6, #93C5FD); }
.stat-card.lab .stat-value .money-number { color: var(--primary-light); }

.stat-card.expenses::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.expenses:hover { box-shadow: 0 20px 40px rgba(220, 38, 38, 0.25); }
.stat-card.expenses .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.expenses .stat-value .money-number { color: var(--danger); }

.stat-card.profit { background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card) 60%, rgba(11, 94, 215, 0.06) 100%); }
.stat-card.profit::before { background: linear-gradient(90deg, #0B5ED7, #10B981, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit:hover { box-shadow: 0 20px 40px rgba(11, 94, 215, 0.3); }
.stat-card.profit .stat-icon { background: linear-gradient(135deg, #0B5ED7, #10B981); }
.stat-card.profit .stat-value .money-number { color: var(--primary); }
.stat-card.profit.loss .stat-value .money-number { color: var(--danger); }
.stat-card.profit.loss::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit.loss .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }

.chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.chart-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all var(--transition-base);
}
.chart-card:hover { box-shadow: var(--shadow-lg); border-color: var(--primary); transform: translateY(-4px); }
.chart-card .chart-header {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    background: linear-gradient(135deg, var(--primary-bg), transparent);
}
.chart-card .chart-header .chart-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.chart-card .chart-header .chart-title i { color: var(--primary); font-size: 1rem; }
.chart-card .chart-body { padding: 18px; height: 280px; position: relative; }

.table-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
    transition: all var(--transition-base);
}
.table-card:hover { box-shadow: var(--shadow-md); }
.table-card .table-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    position: relative;
    overflow: hidden;
}
.table-card .table-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.table-card .table-header.profit { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.profit.loss { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.table-card .table-header .title {
    color: white;
    font-size: 0.88rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    z-index: 1;
}
.table-card .table-header .title i { color: #93C5FD; font-size: 1rem; }
.table-card .table-header .count {
    color: rgba(255,255,255,0.95);
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(255,255,255,0.18);
    padding: 5px 12px;
    border-radius: var(--radius-full);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    position: relative;
    z-index: 1;
}

.table-card.expenses-red { border-color: rgba(220, 38, 38, 0.3); }
.table-card.expenses-red:hover { border-color: #DC2626; box-shadow: 0 15px 40px rgba(220, 38, 38, 0.15); }
.table-card.expenses-red .table-header { background: linear-gradient(135deg, #DC2626, #B91C1C) !important; }
.table-card.expenses-red .table-header .title i { color: #FCA5A5 !important; }
.table-card.expenses-red .table-toolbar { background: var(--danger-bg) !important; border-bottom-color: rgba(220, 38, 38, 0.2) !important; }
.table-card.expenses-red .search-box input:focus { border-color: #DC2626; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.15); }
.table-card.expenses-red .search-count { background: #DC2626; }
.table-card.expenses-red .search-count.has-results { background: #059669; }
.table-card.expenses-red .search-count.no-results { background: #B91C1C; }
.table-card.expenses-red .scroll-btn:hover { background: #DC2626; border-color: #DC2626; }
.table-card.expenses-red .data-table thead th { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.table-card.expenses-red .data-table tbody tr:hover td { background: var(--danger-bg) !important; }
.table-card.expenses-red .data-table tbody tr.total-row td {
    border-top-color: #DC2626;
    background: var(--danger-bg) !important;
    color: #DC2626;
}

.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 18px;
    background: var(--primary-bg);
    border-bottom: 1px solid var(--border-color);
}
.table-toolbar-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 220px; }
.table-toolbar-right { display: flex; align-items: center; gap: 8px; }

.search-box { position: relative; flex: 1; max-width: 420px; }
.search-box input {
    width: 100%;
    padding: 9px 36px 9px 36px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.8rem;
    font-weight: 500;
    outline: none;
    transition: all var(--transition-base);
    height: 38px;
    font-family: var(--font-primary);
}
.search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12); }
.search-box .search-icon {
    position: absolute;
    left: 12px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 0.78rem;
    pointer-events: none;
}
.search-box .search-clear {
    position: absolute;
    right: 8px; top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.78rem;
    padding: 5px 8px;
    border-radius: 6px;
    display: none;
    transition: all var(--transition-fast);
}
.search-box .search-clear:hover { background: var(--border-color); color: var(--danger); }
.search-box.has-value .search-clear { display: block; }

.search-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: var(--radius-sm);
    font-size: 0.68rem;
    font-weight: 700;
    background: var(--primary);
    color: white;
    white-space: nowrap;
    height: 30px;
    transition: all var(--transition-base);
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.25);
}
.search-count.has-results { background: var(--success); box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3); }
.search-count.no-results { background: var(--danger); box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3); }

.scroll-btn {
    width: 34px;
    height: 34px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 700;
    transition: all var(--transition-base);
    flex-shrink: 0;
}
.scroll-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
}

.table-scroll-wrapper { overflow-x: auto; scroll-behavior: smooth; }
.table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
}

mark.search-highlight {
    background: #FEF08A;
    color: #713F12;
    padding: 1px 4px;
    border-radius: 4px;
    font-weight: 800;
}

.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th {
    text-align: left;
    padding: 11px 14px;
    font-weight: 800;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}
.data-table tbody tr { transition: background var(--transition-fast); }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.hidden-row { display: none !important; }
.data-table tbody tr.total-row td {
    border-top: 3px solid var(--primary);
    font-weight: 800;
    font-size: 0.88rem;
    background: var(--primary-bg) !important;
    color: var(--primary);
}

.data-table tbody tr.clinical-main {
    background: linear-gradient(90deg, rgba(5,150,105,0.08), transparent) !important;
    border-bottom: 1px solid rgba(5, 150, 105, 0.15);
}
.data-table tbody tr.clinical-main:hover td {
    background: rgba(5, 150, 105, 0.12) !important;
}
.data-table tbody tr.clinical-sub:hover td {
    background: rgba(11, 94, 215, 0.06) !important;
}

.data-table tbody tr.other-services {
    background: linear-gradient(90deg, rgba(148,163,184,0.08), transparent) !important;
}
.data-table tbody tr.other-services:hover td {
    background: rgba(148, 163, 184, 0.15) !important;
}
.data-table tbody tr.breakdown-total {
    background: var(--success-bg) !important;
    border-top: 2px solid var(--success);
    border-bottom: 2px solid var(--success);
}
.data-table tbody tr.breakdown-total td {
    color: var(--success) !important;
    font-weight: 900 !important;
}

.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.82rem;
    color: var(--success);
    text-align: right;
    letter-spacing: -0.02em;
    white-space: nowrap;
}
.money-cell .currency-prefix {
    font-size: 0.65rem;
    color: var(--text-secondary);
    margin-right: 3px;
    font-family: var(--font-primary);
    font-weight: 600;
}
.money-cell.red { color: #DC2626 !important; }
.money-cell.warning { color: #D97706 !important; }
.money-cell.purple { color: #7C3AED !important; }
.money-cell.slate { color: #94A3B8 !important; }

.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
    letter-spacing: 0.03em;
}
.type-badge.bill { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
.type-badge.otc { background: #CFFAFE; color: #0E7490; border: 1px solid #67E8F9; }
.type-badge.payment { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
    letter-spacing: 0.03em;
}
.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.partial { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.received-by { display: flex; align-items: center; gap: 8px; }
.received-by-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.68rem;
    flex-shrink: 0;
    text-transform: uppercase;
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.25);
}
.received-by-avatar.red { background: linear-gradient(135deg, #DC2626, #F87171) !important; }
.received-by-info { display: flex; flex-direction: column; gap: 2px; }
.received-by-name { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); }
.received-by-role { font-size: 0.55rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; }

.role-tag {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.5rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.role-tag.cashier { background: #FEF3C7; color: #D97706; }
.role-tag.reception { background: #DBEAFE; color: #1E40AF; }
.role-tag.pharmacy { background: #D1FAE5; color: #059669; }
.role-tag.admin { background: #FCE7F3; color: #BE185D; }
.role-tag.doctor { background: #EDE9FE; color: #7C3AED; }
.role-tag.laboratory { background: #CFFAFE; color: #0891B2; }
.role-tag.user { background: var(--border-color); color: var(--text-secondary); }

.payment-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.66rem;
    font-weight: 700;
    background: var(--primary-bg);
    color: var(--primary);
    white-space: nowrap;
}

.expense-ref-badge {
    font-size: 0.68rem;
    color: #DC2626;
    font-weight: 800;
    background: var(--danger-bg);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    font-family: var(--font-mono);
    border: 1px solid rgba(220, 38, 38, 0.2);
}
.expense-category-badge {
    font-size: 0.68rem;
    font-weight: 700;
    background: var(--warning-bg);
    color: var(--warning);
    padding: 4px 9px;
    border-radius: 6px;
    display: inline-block;
    border: 1px solid rgba(217, 119, 6, 0.2);
}

.action-buttons { display: flex; gap: 5px; justify-content: center; align-items: center; }
.btn-action {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    border: none;
    cursor: pointer;
    transition: all var(--transition-base);
    text-decoration: none;
}
.btn-action:hover { transform: translateY(-2px) scale(1.1); }
.btn-action.view { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
.btn-action.view:hover { background: #0B5ED7; color: white; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.4); }
.btn-action.edit { background: rgba(245, 158, 11, 0.12); color: #D97706; }
.btn-action.edit:hover { background: #F59E0B; color: white; box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4); }
.btn-action.delete { background: rgba(220, 38, 38, 0.12); color: #DC2626; }
.btn-action.delete:hover { background: #DC2626; color: white; box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4); }

.dot-indicator {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.item-details-cell { max-width: 240px; min-width: 170px; padding: 10px 12px !important; }
.item-list { display: flex; flex-direction: column; gap: 4px; max-height: 130px; overflow-y: auto; }
.item-list::-webkit-scrollbar { width: 4px; }
.item-list::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.item-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
.item-line {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    font-size: 0.7rem;
    line-height: 1.4;
    color: var(--text-primary);
    font-weight: 600;
    word-break: break-word;
}
.item-line .item-bullet { color: var(--primary); font-weight: 900; flex-shrink: 0; font-size: 0.72rem; line-height: 1.3; }
.item-line .item-name { flex: 1; word-break: break-word; }
.item-line .item-qty {
    font-family: var(--font-mono);
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--primary);
    background: var(--primary-bg);
    padding: 2px 6px;
    border-radius: 4px;
    flex-shrink: 0;
    white-space: nowrap;
}
.item-type-tag {
    display: inline-block;
    font-size: 0.52rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 4px;
    margin-top: 4px;
    background: var(--primary-bg);
    color: var(--primary);
    letter-spacing: 0.04em;
}
.item-type-tag.medication { background: #FEF3C7; color: #D97706; }
.item-type-tag.lab_test { background: #DBEAFE; color: #1E40AF; }
.item-type-tag.consultation { background: #D1FAE5; color: #059669; }
.item-type-tag.procedure { background: #CCFBF1; color: #0D9488; }
.item-type-tag.registration { background: #F1F5F9; color: #64748B; }
.item-type-tag.equipment { background: #EDE9FE; color: #7C3AED; }

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    max-width: 460px;
    width: 100%;
    padding: 32px;
    box-shadow: var(--shadow-xl);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
}
@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.85) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: var(--danger-bg);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 18px;
    animation: iconPulse 1.5s infinite;
}
@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 14px rgba(220, 38, 38, 0); }
}
.modal-title {
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 10px;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}
.modal-text {
    font-size: 0.88rem;
    color: var(--text-secondary);
    margin-bottom: 24px;
    line-height: 1.7;
}
.modal-text strong {
    color: var(--primary);
    background: var(--primary-bg);
    padding: 3px 10px;
    border-radius: 6px;
    font-family: var(--font-mono);
    font-weight: 700;
    display: inline-block;
    margin: 4px 0;
}
.modal-warning {
    color: var(--danger);
    font-weight: 700;
    display: block;
    margin-top: 8px;
    font-size: 0.8rem;
}
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.modal-btn {
    padding: 11px 26px;
    border-radius: var(--radius-md);
    font-weight: 700;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    transition: all var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-primary);
}
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: var(--border-strong); transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5); }

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: var(--text-secondary);
}
.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 14px;
    color: var(--primary);
}
.empty-state p { font-weight: 600; font-size: 0.9rem; }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) {
    .chart-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 18px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .stat-card { padding: 14px; min-height: 135px; }
    .stat-card .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
    .stat-card .stat-value { font-size: 1.15rem; }
    .data-table { font-size: 0.72rem; }
    .data-table thead th, .data-table tbody td { padding: 9px 10px; }
    .quick-btn { font-size: 0.68rem; padding: 7px 12px; }
    .table-toolbar { flex-direction: column; align-items: stretch; }
    .table-toolbar-right { justify-content: flex-end; }
    .item-details-cell { max-width: 200px; min-width: 150px; }
}
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }

@media print {
    .filter-card, .btn-header, .action-buttons, .table-toolbar,
    .scroll-btn, .search-box, .modal-overlay { display: none !important; }
    .page-header { background: white !important; color: black !important; }
    .page-header .page-title { color: black !important; }
    .stat-card { break-inside: avoid; }
    .chart-card { break-inside: avoid; }
}
</style>
</head>
<body>

<main class="main-content">

    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) {
                    el.style.transition = 'all 0.5s ease';
                    el.style.opacity = '0';
                    setTimeout(function() { el.remove(); }, 500);
                }
            }, 4000);
        </script>
    <?php endif; ?>

    <?php if ($show_debug): ?>
    <div class="debug-panel">
        <strong><i class="fas fa-bug"></i> DEBUG MODE — Calendar Day 00:00-23:59</strong>
        <div class="debug-row"><span>Filter:</span><span><?= htmlspecialchars($quick_filter) ?> • <?= htmlspecialchars($date_label) ?></span></div>
        <div class="debug-row"><span>Server Time:</span><span><?= date('Y-m-d H:i:s') ?></span></div>
        <div class="debug-row"><span>CURDATE():</span><span><?= date('Y-m-d') ?></span></div>
        <div class="debug-row"><span>Patient Payments (raw):</span><span><?= $currency ?> <?= number_format($patient_bills_revenue, 2) ?> (<?= $patient_bills_count ?>)</span></div>
        <div class="debug-row"><span>Patient Payments (rounded 50):</span><span><?= $currency ?> <?= number_format($patient_bills_revenue_rounded, 0) ?></span></div>
        <div class="debug-row"><span>Patient Discounts:</span><span><?= $currency ?> <?= number_format($patient_discounts, 2) ?></span></div>
        <div class="debug-row"><span>Patient Premiums:</span><span><?= $currency ?> <?= number_format($patient_premiums, 2) ?></span></div>
        <div class="debug-row"><span>OTC Revenue:</span><span><?= $currency ?> <?= number_format($otc_revenue, 2) ?> (<?= $otc_count ?>)</span></div>
        <div class="debug-row"><span>TOTAL REVENUE:</span><span><?= $currency ?> <?= number_format($total_revenue, 2) ?></span></div>
        <div class="debug-row"><span>Prescription (raw → rounded 50):</span><span><?= $currency ?> <?= number_format($prescription_revenue_raw ?? 0, 2) ?> → <?= $currency ?> <?= number_format($prescription_revenue, 0) ?></span></div>
        <div class="debug-row"><span>Clinical Services:</span><span><?= $currency ?> <?= number_format($clinical_services_revenue, 2) ?></span></div>
        <div class="debug-row"><span>Other Services (info):</span><span><?= $currency ?> <?= number_format($other_services_revenue, 2) ?></span></div>
        <div class="debug-row"><span>Breakdown Total:</span><span><?= $currency ?> <?= number_format($breakdown_total, 2) ?> (should = Patient Payments rounded)</span></div>
        <div class="debug-row"><span>Breakdown vs Payments:</span><span style="color:<?= abs($breakdown_total - $patient_bills_revenue_rounded) < 1 ? '#059669' : '#DC2626' ?>;">
            <?= abs($breakdown_total - $patient_bills_revenue_rounded) < 1 ? '✅ MATCH' : '❌ DIFF: ' . number_format($breakdown_total - $patient_bills_revenue_rounded, 2) ?>
        </span></div>
    </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($total_revenue, 0) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Payments
                </span>
                <?php if ($total_discounts > 0): ?>
                <span class="branch-tag disc-tag">
                    <i class="fas fa-tag"></i> Disc: <?= $currency ?> <?= number_format($total_discounts, 0) ?>
                </span>
                <?php endif; ?>
                <?php if ($total_premiums > 0): ?>
                <span class="branch-tag prem-tag">
                    <i class="fas fa-star"></i> Prem: <?= $currency ?> <?= number_format($total_premiums, 0) ?>
                </span>
                <?php endif; ?>
                <span class="branch-tag filter-tag">
                    <i class="fas fa-clock"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="?branch=<?= $selected_branch_id ?>&quick=<?= $quick_filter ?>&debug=<?= $show_debug ? '0' : '1' ?>" 
               class="btn-header" title="Toggle Debug">
                <i class="fas fa-bug"></i> <?= $show_debug ? 'Hide' : 'Debug' ?>
            </a>
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="/dispensary_system/frontend/pages/admin/audit/dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-bolt"></i> Quick Filters <span style="color:var(--success);font-weight:700;margin-left:6px;">(Calendar Day: 00:00 - 23:59)</span>
            </div>
            <div class="quick-filters">
                <a href="?branch=<?= $selected_branch_id ?>&quick=today" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=yesterday" class="quick-btn <?= $quick_filter === 'yesterday' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-minus"></i> Yesterday
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1d" class="quick-btn <?= $quick_filter === '1d' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> 24H
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1w" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i> 1W
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1m" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 1M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=3m" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 3M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=6m" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 6M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1y" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
                    <i class="fas fa-calendar"></i> 1Y
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=all" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-infinity"></i> All
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=custom&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="quick-btn <?= $quick_filter === 'custom' ? 'custom-active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Custom
                </a>
            </div>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
            
            <div class="filter-form">
                <?php if ($quick_filter === 'custom'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                <?php endif; ?>
                
                <div class="filter-group">
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
                
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="?branch=<?= $selected_branch_id ?>" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($total_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-info-circle"></i> Payments + OTC</div>
        </div>
        
        <div class="stat-card revenue">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-label">Patient Payments</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($patient_bills_revenue_rounded, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-receipt"></i> <?= number_format($patient_bills_count) ?> payments</div>
            <div class="stat-breakdown">
                <span class="breakdown-item discount" title="Total Discount on Patient Bills">
                    <i class="fas fa-tag"></i> Disc: <?= number_format($patient_discounts, 0) ?>
                </span>
                <span class="breakdown-item premium" title="Total Premium on Patient Bills">
                    <i class="fas fa-star"></i> Prem: <?= number_format($patient_premiums, 0) ?>
                </span>
            </div>
        </div>
        
        <div class="stat-card otc">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($otc_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-receipt"></i> <?= number_format($otc_count) ?> sales</div>
            <?php if ($otc_discounts > 0 || $otc_premiums > 0): ?>
            <div class="stat-breakdown">
                <span class="breakdown-item discount" title="OTC Discount">
                    <i class="fas fa-tag"></i> <?= number_format($otc_discounts, 0) ?>
                </span>
                <span class="breakdown-item premium" title="OTC Premium">
                    <i class="fas fa-star"></i> <?= number_format($otc_premiums, 0) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="stat-card prescription">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-label">Prescription</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($prescription_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-pills"></i> <?= number_format($prescription_count) ?> items</div>
        </div>
        
        <div class="stat-card consultation">
            <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
            <div class="stat-label">Clinical Services</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($clinical_services_revenue, 0) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-notes-medical"></i> 
                <span title="Consultation"><?= number_format($consultation_count) ?>c</span>
                <span style="color:var(--text-muted);">•</span>
                <span title="Procedures"><?= number_format($procedure_count) ?>p</span>
                <span style="color:var(--text-muted);">•</span>
                <span title="Equipment"><?= number_format($equipment_count) ?>e</span>
            </div>
        </div>
        
        <div class="stat-card lab">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div class="stat-label">Lab Tests</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($lab_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-microscope"></i> <?= number_format($lab_count) ?> tests</div>
        </div>
        
        <div class="stat-card expenses">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-label">Expenses</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($total_expenses, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-list"></i> <?= number_format($expenses_count) ?> paid</div>
        </div>
        
        <div class="stat-card profit <?= $net_profit < 0 ? 'loss' : '' ?>">
            <div class="stat-icon"><i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i></div>
            <div class="stat-label"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format(abs($net_profit), 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-percentage"></i> <?= $profit_percentage ?>% margin</div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title"><i class="fas fa-calendar-alt"></i> Monthly Revenue</span>
                <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:700;">Last 12 months</span>
            </div>
            <div class="chart-body"><canvas id="monthlyChart"></canvas></div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title"><i class="fas fa-calendar-day"></i> Daily Revenue</span>
                <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:700;">Last 30 days</span>
            </div>
            <div class="chart-body"><canvas id="dailyChart"></canvas></div>
        </div>
    </div>

    <!-- ALL TRANSACTIONS -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-list"></i> All Transactions (Payments + OTC)</span>
            <span class="count"><?= count($transactions) ?> records</span>
        </div>
        
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <div class="search-box" id="transSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="transSearch" 
                           placeholder="Search receipt #, bill #, customer, item..."
                           oninput="filterTable('transTable', this.value, 'transCount')"
                           autocomplete="off">
                    <button type="button" class="search-clear" onclick="clearSearch('transTable', 'transSearch', 'transCount')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="search-count" id="transCount">
                    <i class="fas fa-list"></i>
                    <span class="count-text"><?= count($transactions) ?> records</span>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" class="scroll-btn" onclick="scrollTable('transWrapper', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('transWrapper', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-wrapper" id="transWrapper">
            <table class="data-table" id="transTable" style="min-width:1700px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Receipt #</th>
                        <th>Bill #</th>
                        <th style="text-align:center;">Type</th>
                        <th>Customer / Patient</th>
                        <th style="text-align:center;">Items</th>
                        <th>Item Details</th>
                        <th>Payment</th>
                        <th>Received By</th>
                        <th style="text-align:center;">Status</th>
                        <th>Branch</th>
                        <th style="text-align:right;">Amount Paid</th>
                        <th>Date & Time</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php $row_num = 1; foreach ($transactions as $trans): 
                            $is_otc = ($trans['transaction_type'] ?? '') === 'otc';
                            $is_payment = ($trans['transaction_type'] ?? '') === 'payment';
                            
                            $role = strtolower($trans['received_by_role'] ?? 'user');
                            $name_parts = explode(' ', trim($trans['received_by_name'] ?? 'N/A'));
                            $initials = count($name_parts) >= 2 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1))
                                : strtoupper(substr($trans['received_by_name'] ?? 'NA', 0, 2));
                            
                            $item_types_str = $trans['item_types'] ?? '';
                            $items_summary = $trans['items_summary'] ?? '';
                            $item_count = (int)($trans['item_count'] ?? 0);
                            $item_lines = !empty($items_summary) ? explode("\n", $items_summary) : [];
                            
                            $display_date = $trans['transaction_date'] ?? $trans['received_at'];
                            $bill_total = (float)($trans['bill_total'] ?? 0);
                            $bill_balance = (float)($trans['bill_balance'] ?? 0);
                            $bill_discount = (float)($trans['bill_discount'] ?? 0);
                            $bill_premium = (float)($trans['bill_premium'] ?? 0);
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                                <td class="searchable-cell">
                                    <span style="font-size:0.68rem;color:var(--primary);font-weight:800;background:var(--primary-bg);padding:4px 8px;border-radius:5px;display:inline-block;font-family:var(--font-mono);">
                                        <?= htmlspecialchars($trans['reference_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="searchable-cell">
                                    <?php if (!empty($trans['bill_number']) && $trans['bill_number'] !== $trans['reference_number']): ?>
                                        <span style="font-size:0.62rem;color:var(--text-secondary);font-weight:700;font-family:var(--font-mono);">
                                            <?= htmlspecialchars($trans['bill_number']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.68rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <?php if ($is_otc): ?>
                                        <span class="type-badge otc"><i class="fas fa-shopping-cart"></i> OTC</span>
                                    <?php elseif ($is_payment): ?>
                                        <span class="type-badge payment"><i class="fas fa-money-bill-wave"></i> PAY</span>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable-cell">
                                    <div style="font-weight:600;font-size:0.75rem;"><?= htmlspecialchars($trans['customer_name'] ?? 'Walk-in') ?></div>
                                    <?php if (!empty($trans['customer_phone'])): ?>
                                        <div style="font-size:0.62rem;color:var(--text-secondary);">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($trans['customer_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <div style="font-weight:800;color:var(--primary);font-family:var(--font-mono);font-size:0.85rem;"><?= $item_count ?></div>
                                    <div style="font-size:0.55rem;font-weight:700;text-transform:uppercase;color:var(--text-secondary);">
                                        <?= $is_otc ? 'Meds' : 'Items' ?>
                                    </div>
                                </td>
                                <td class="searchable-cell item-details-cell">
                                    <?php if (count($item_lines) > 0): ?>
                                        <div class="item-list">
                                            <?php foreach ($item_lines as $line): 
                                                $line = trim($line);
                                                if (empty($line)) continue;
                                                $item_name = $line;
                                                $item_qty = '';
                                                if (preg_match('/^(.+?)\s*\((\d+)\)\s*$/', $line, $m)) {
                                                    $item_name = trim($m[1]);
                                                    $item_qty = $m[2];
                                                }
                                            ?>
                                                <div class="item-line">
                                                    <span class="item-bullet">•</span>
                                                    <span class="item-name"><?= htmlspecialchars($item_name) ?></span>
                                                    <?php if ($item_qty): ?>
                                                        <span class="item-qty">×<?= htmlspecialchars($item_qty) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (!empty($item_types_str)): 
                                            $types = array_map('trim', explode(',', $item_types_str));
                                        ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:5px;">
                                                <?php foreach ($types as $type): ?>
                                                    <span class="item-type-tag <?= htmlspecialchars($type) ?>">
                                                        <?= htmlspecialchars(str_replace('_', ' ', $type)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.68rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable-cell">
                                    <span class="payment-badge">
                                        <i class="fas fa-credit-card"></i>
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $trans['payment_method'] ?? 'Cash'))) ?>
                                    </span>
                                </td>
                                <td class="searchable-cell">
                                    <div class="received-by">
                                        <div class="received-by-avatar"><?= htmlspecialchars($initials) ?></div>
                                        <div class="received-by-info">
                                            <span class="received-by-name"><?= htmlspecialchars($trans['received_by_name'] ?? 'N/A') ?></span>
                                            <span class="received-by-role">
                                                <span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                    <?php if ($is_payment && $bill_balance > 0): ?>
                                        <div style="font-size:0.55rem;color:var(--danger);font-weight:700;margin-top:3px;">
                                            Bal: <?= number_format($bill_balance, 0) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable-cell">
                                    <span style="font-size:0.7rem;color:var(--text-secondary);">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($trans['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="money-cell">
                                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($trans['amount'] ?? 0, 0) ?>
                                    <?php if ($is_payment && $bill_total > 0 && $trans['amount'] < $bill_total): ?>
                                        <div style="font-size:0.55rem;color:var(--text-secondary);font-weight:600;margin-top:2px;">
                                            of <?= number_format($bill_total, 0) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($bill_discount > 0 || $bill_premium > 0): ?>
                                        <div style="font-size:0.55rem;margin-top:3px;display:flex;gap:4px;justify-content:flex-end;">
                                            <?php if ($bill_discount > 0): ?>
                                                <span style="color:var(--warning);font-weight:800;">-<?= number_format($bill_discount, 0) ?></span>
                                            <?php endif; ?>
                                            <?php if ($bill_premium > 0): ?>
                                                <span style="color:var(--purple);font-weight:800;">+<?= number_format($bill_premium, 0) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:0.68rem;font-weight:600;"><?= date('H:i', strtotime($display_date)) ?></div>
                                    <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('d M Y', strtotime($display_date)) ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($is_otc): ?>
                                            <a href="view_otc.php?id=<?= $trans['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view" title="View OTC Sale">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_otc.php?id=<?= $trans['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit" title="Edit OTC Sale">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn-action delete" title="Delete OTC Sale"
                                                    onclick="confirmDelete('otc', <?= $trans['id'] ?>, '<?= htmlspecialchars(addslashes($trans['reference_number'] ?? 'N/A')) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="view_payment.php?id=<?= $trans['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view" title="View Payment">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_payment.php?id=<?= $trans['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit" title="Edit Payment">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn-action delete" title="Delete Payment"
                                                    onclick="confirmDelete('payment', <?= $trans['id'] ?>, '<?= htmlspecialchars(addslashes($trans['reference_number'] ?? 'N/A')) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No transactions found for <?= htmlspecialchars($date_label) ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REVENUE BREAKDOWN -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-chart-pie"></i> Revenue Breakdown by Source</span>
            <span class="count">Total: <?= $currency ?> <?= number_format($total_revenue, 0) ?></span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
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
                        <td><span class="dot-indicator" style="background:#059669;"></span> Patient Payments</td>
                        <td class="money-cell"><span class="currency-prefix"><?= $currency ?></span><?= number_format($patient_bills_revenue_rounded, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($patient_bills_revenue_rounded / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($patient_bills_count) ?></td>
                    </tr>
                    <tr>
                        <td><span class="dot-indicator" style="background:#0891B2;"></span> OTC Sales</td>
                        <td class="money-cell"><span class="currency-prefix"><?= $currency ?></span><?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($otc_count) ?></td>
                    </tr>
                    
                    <tr style="background:var(--primary-bg);">
                        <td colspan="4" style="padding:10px 16px;font-weight:800;font-size:0.75rem;color:var(--primary);text-transform:uppercase;letter-spacing:0.05em;">
                            <i class="fas fa-info-circle"></i> Patient Payments Breakdown (rounded to nearest 50)
                        </td>
                    </tr>
                    
                    <tr class="clinical-main">
                        <td>
                            <span class="dot-indicator" style="background:#059669;"></span> 
                            <strong style="font-weight:800;">Clinical Services</strong>
                            <span style="font-size:0.6rem;color:var(--text-secondary);font-weight:500;display:block;margin-left:17px;margin-top:2px;">
                                Consultation + Procedures + Equipment
                            </span>
                        </td>
                        <td class="money-cell" style="color:#059669;font-weight:900;">
                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($clinical_services_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;color:#059669;font-weight:800;">
                            <?= $total_revenue > 0 ? round(($clinical_services_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:#059669;font-weight:800;">
                            <?= number_format($clinical_services_count) ?>
                        </td>
                    </tr>
                    
                    <tr class="clinical-sub">
                        <td style="padding-left:52px;font-size:0.72rem;color:var(--text-secondary);">
                            <i class="fas fa-caret-right" style="color:#059669;margin-right:6px;"></i> Consultation
                        </td>
                        <td class="money-cell" style="color:#059669;font-size:0.76rem;font-weight:700;">
                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($consultation_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;font-size:0.72rem;">
                            <?= $total_revenue > 0 ? round(($consultation_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;font-size:0.72rem;">
                            <?= number_format($consultation_count) ?>
                        </td>
                    </tr>
                    
                    <tr class="clinical-sub">
                        <td style="padding-left:52px;font-size:0.72rem;color:var(--text-secondary);">
                            <i class="fas fa-caret-right" style="color:#0D9488;margin-right:6px;"></i> Procedures
                        </td>
                        <td class="money-cell" style="color:#0D9488;font-size:0.76rem;font-weight:700;">
                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($procedure_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;font-size:0.72rem;">
                            <?= $total_revenue > 0 ? round(($procedure_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;font-size:0.72rem;">
                            <?= number_format($procedure_count) ?>
                        </td>
                    </tr>
                    
                    <tr class="clinical-sub">
                        <td style="padding-left:52px;font-size:0.72rem;color:var(--text-secondary);">
                            <i class="fas fa-caret-right" style="color:#7C3AED;margin-right:6px;"></i> Medical Equipment
                        </td>
                        <td class="money-cell" style="color:#7C3AED;font-size:0.76rem;font-weight:700;">
                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($equipment_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;font-size:0.72rem;">
                            <?= $total_revenue > 0 ? round(($equipment_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;font-size:0.72rem;">
                            <?= number_format($equipment_count) ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding-left:32px;"><span class="dot-indicator" style="background:#3B82F6;"></span> Lab Tests</td>
                        <td class="money-cell" style="color:#3B82F6;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($lab_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:32px;"><span class="dot-indicator" style="background:#D97706;"></span> Medications (= Prescriptions)</td>
                        <td class="money-cell" style="color:#D97706;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($medication_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($medication_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($medication_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:32px;"><span class="dot-indicator" style="background:#64748B;"></span> Registration</td>
                        <td class="money-cell" style="color:#64748B;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($registration_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($registration_count) ?></td>
                    </tr>
                    
                    <tr class="other-services">
                        <td style="padding-left:32px;">
                            <span class="dot-indicator" style="background:#94A3B8;"></span> 
                            <strong>Other Services</strong>
                            <span style="font-size:0.6rem;color:var(--text-secondary);font-weight:500;display:block;margin-left:17px;margin-top:2px;">
                                (Premium - Discount) — informational only
                            </span>
                        </td>
                        <td class="money-cell slate">
                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($other_services_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;">
                            <?= $total_revenue > 0 ? round(($other_services_revenue / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;">
                            <?= number_format($other_services_count) ?>
                        </td>
                    </tr>
                    
                    <tr class="breakdown-total">
                        <td>
                            <i class="fas fa-check-circle"></i> 
                            <strong>Breakdown Total</strong>
                            <span style="font-size:0.6rem;font-weight:500;display:block;margin-left:17px;margin-top:2px;">
                                (should equal Patient Payments)
                            </span>
                        </td>
                        <td class="money-cell" style="color:var(--success);font-weight:900;">
                            <span class="currency-prefix"><?= $currency ?></span><?= number_format($breakdown_total, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--success);font-weight:800;">
                            <?= $total_revenue > 0 ? round(($breakdown_total / $total_revenue) * 100, 1) : 0 ?>%
                        </td>
                        <td style="text-align:right;color:var(--success);font-weight:800;">
                            <?= number_format($patient_bills_count) ?> payments
                        </td>
                    </tr>
                    
                    <?php if ($total_discounts > 0): ?>
                    <tr style="background:var(--warning-bg);">
                        <td style="padding-left:20px;">
                            <i class="fas fa-tag" style="color:var(--warning);"></i> 
                            <strong style="color:var(--warning);">Total Discounts</strong>
                        </td>
                        <td class="money-cell warning">
                            <span class="currency-prefix"><?= $currency ?></span>-<?= number_format($total_discounts, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--warning);font-weight:700;">—</td>
                        <td style="text-align:right;color:var(--warning);font-weight:700;">—</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($total_premiums > 0): ?>
                    <tr style="background:var(--purple-bg);">
                        <td style="padding-left:20px;">
                            <i class="fas fa-star" style="color:var(--purple);"></i> 
                            <strong style="color:var(--purple);">Total Premiums</strong>
                        </td>
                        <td class="money-cell purple">
                            <span class="currency-prefix"><?= $currency ?></span>+<?= number_format($total_premiums, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--purple);font-weight:700;">—</td>
                        <td style="text-align:right;color:var(--purple);font-weight:700;">—</td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="total-row">
                        <td style="font-weight:800;font-size:0.88rem;"><i class="fas fa-calculator"></i> TOTAL REVENUE (Payments + OTC)</td>
                        <td style="text-align:right;font-weight:800;font-size:0.9rem;font-family:var(--font-mono);">
                            <?= $currency ?> <?= number_format($total_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;font-weight:800;font-size:0.88rem;">100%</td>
                        <td style="text-align:right;font-weight:800;font-size:0.88rem;"><?= number_format($total_transactions) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXPENSES TABLE -->
    <div class="table-card expenses-red">
        <div class="table-header">
            <span class="title">
                <i class="fas fa-receipt"></i> 
                All Expenses
                <span style="font-size:0.68rem;font-weight:600;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;margin-left:6px;">
                    <?= count($expenses_list) ?> records
                </span>
            </span>
            <span class="count">
                <i class="fas fa-money-bill-wave"></i>
                Total: <?= $currency ?> <?= number_format($total_expenses, 0) ?>
            </span>
        </div>
        
        <?php if (count($expenses_list) > 0): ?>
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <div class="search-box" id="expSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="expSearch" 
                           placeholder="Search expense #, category, recorded by..."
                           oninput="filterTable('expTable', this.value, 'expCount')"
                           autocomplete="off">
                    <button type="button" class="search-clear" onclick="clearSearch('expTable', 'expSearch', 'expCount')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="search-count" id="expCount">
                    <i class="fas fa-list"></i>
                    <span class="count-text"><?= count($expenses_list) ?> records</span>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" class="scroll-btn" onclick="scrollTable('expWrapper', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('expWrapper', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-wrapper" id="expWrapper">
            <table class="data-table" id="expTable" style="min-width:1300px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Expense #</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment</th>
                        <th style="text-align:center;">Status</th>
                        <th>Recorded By</th>
                        <th>Branch</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Date</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $exp_num = 1; foreach ($expenses_list as $exp): 
                        $role = strtolower($exp['recorded_by_role'] ?? 'user');
                        $name_parts = explode(' ', trim($exp['recorded_by_name'] ?? 'N/A'));
                        $initials = count($name_parts) >= 2 
                            ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1))
                            : strtoupper(substr($exp['recorded_by_name'] ?? 'NA', 0, 2));
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $exp_num++ ?></td>
                            <td class="searchable-cell">
                                <span class="expense-ref-badge"><?= htmlspecialchars($exp['expense_number'] ?? 'N/A') ?></span>
                            </td>
                            <td class="searchable-cell">
                                <span class="expense-category-badge">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($exp['category'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="searchable-cell" style="max-width:250px;">
                                <div style="font-size:0.75rem;font-weight:500;"><?= htmlspecialchars($exp['description'] ?? 'N/A') ?></div>
                            </td>
                            <td class="searchable-cell">
                                <span class="payment-badge">
                                    <i class="fas fa-credit-card"></i>
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $exp['payment_method'] ?? 'Cash'))) ?>
                                </span>
                            </td>
                            <td style="text-align:center;" class="searchable-cell">
                                <?php if ($exp['status'] === 'paid'): ?>
                                    <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                <?php else: ?>
                                    <span class="status-badge pending"><i class="fas fa-clock"></i> <?= strtoupper($exp['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="searchable-cell">
                                <div class="received-by">
                                    <div class="received-by-avatar red"><?= htmlspecialchars($initials) ?></div>
                                    <div class="received-by-info">
                                        <span class="received-by-name"><?= htmlspecialchars($exp['recorded_by_name'] ?? 'N/A') ?></span>
                                        <span class="received-by-role">
                                            <span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="searchable-cell">
                                <span style="font-size:0.7rem;color:var(--text-secondary);">
                                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="money-cell red">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($exp['amount'] ?? 0, 0) ?>
                            </td>
                            <td>
                                <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($exp['payment_date'])) ?></div>
                                <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($exp['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_expense.php?id=<?= $exp['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view" title="View Expense">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_expense.php?id=<?= $exp['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit" title="Edit Expense">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn-action delete" title="Delete Expense"
                                            onclick="confirmDelete('expense', <?= $exp['id'] ?>, '<?= htmlspecialchars(addslashes($exp['expense_number'] ?? 'N/A')) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="8" style="font-weight:800;font-size:0.88rem;text-align:right;color:#DC2626;">
                            <i class="fas fa-calculator"></i> TOTAL EXPENSES
                        </td>
                        <td style="text-align:right;font-weight:900;font-size:0.95rem;color:#DC2626;font-family:var(--font-mono);">
                            <?= $currency ?> <?= number_format($total_expenses, 0) ?>
                        </td>
                        <td colspan="2" style="text-align:center;font-weight:800;color:#DC2626;font-size:0.78rem;">
                            <?= number_format($expenses_count) ?> records
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <p>No expenses found for <?= htmlspecialchars($date_label) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROFIT SUMMARY -->
    <div class="table-card" style="border-color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
        <div class="table-header profit <?= $net_profit < 0 ? 'loss' : '' ?>">
            <span class="title"><i class="fas fa-chart-pie"></i> Profit Summary</span>
            <span class="count"><?= $profit_percentage ?>% Margin</span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td style="font-weight:600;font-size:0.88rem;padding:16px 20px;">
                            <i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:10px;"></i>
                            Total Revenue
                        </td>
                        <td style="text-align:right;font-weight:800;color:var(--primary);font-size:0.95rem;font-family:var(--font-mono);padding:16px 20px;">
                            <?= $currency ?> <?= number_format($total_revenue, 0) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;font-size:0.88rem;padding:16px 20px;">
                            <i class="fas fa-receipt" style="color:var(--danger);margin-right:10px;"></i>
                            Less: Total Expenses
                        </td>
                        <td style="text-align:right;font-weight:800;color:var(--danger);font-size:0.95rem;font-family:var(--font-mono);padding:16px 20px;">
                            - <?= $currency ?> <?= number_format($total_expenses, 0) ?>
                        </td>
                    </tr>
                    <tr class="total-row" style="border-top-color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;background:<?= $net_profit >= 0 ? 'var(--success-bg)' : 'var(--danger-bg)' ?> !important;">
                        <td style="font-weight:800;font-size:1.05rem;padding:18px 20px;color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                            <i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i>
                            <?= $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS' ?>
                        </td>
                        <td style="text-align:right;font-weight:900;font-size:1.15rem;color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-family:var(--font-mono);padding:18px 20px;">
                            <?= $currency ?> <?= number_format(abs($net_profit), 0) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title" id="deleteModalTitle">Delete Record?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong id="deleteRecordNumber">#</strong><br>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
            </span>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" id="deleteAction" value="">
            <input type="hidden" name="bill_id" id="deleteBillId" value="">
            <input type="hidden" name="sale_id" id="deleteSaleId" value="">
            <input type="hidden" name="expense_id" id="deleteExpenseId" value="">
            <input type="hidden" name="payment_id" id="deletePaymentId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// DELETE MODAL
// ================================================================
function confirmDelete(type, id, refNumber) {
    document.getElementById('deleteBillId').value = '';
    document.getElementById('deleteSaleId').value = '';
    document.getElementById('deleteExpenseId').value = '';
    document.getElementById('deletePaymentId').value = '';
    
    if (type === 'bill') {
        document.getElementById('deleteAction').value = 'delete_bill';
        document.getElementById('deleteBillId').value = id;
        document.getElementById('deleteModalTitle').textContent = 'Delete Bill?';
    } else if (type === 'otc') {
        document.getElementById('deleteAction').value = 'delete_otc';
        document.getElementById('deleteSaleId').value = id;
        document.getElementById('deleteModalTitle').textContent = 'Delete OTC Sale?';
    } else if (type === 'expense') {
        document.getElementById('deleteAction').value = 'delete_expense';
        document.getElementById('deleteExpenseId').value = id;
        document.getElementById('deleteModalTitle').textContent = 'Delete Expense?';
    } else if (type === 'payment') {
        document.getElementById('deleteAction').value = 'delete_payment';
        document.getElementById('deletePaymentId').value = id;
        document.getElementById('deleteModalTitle').textContent = 'Delete Payment?';
    }
    
    document.getElementById('deleteRecordNumber').textContent = refNumber;
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

// ================================================================
// TABLE FILTER + HIGHLIGHT
// ================================================================
function filterTable(tableId, searchTerm, countId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    var term = searchTerm.trim().toLowerCase();
    var visibleCount = 0;
    
    var searchBox = document.getElementById(tableId.replace('Table', 'SearchBox'));
    if (searchBox) searchBox.classList.toggle('has-value', term.length > 0);
    
    rows.forEach(function(row) {
        if (row.querySelector('td[colspan]')) return;
        var rowText = '';
        var cells = row.querySelectorAll('.searchable-cell');
        if (cells.length > 0) {
            cells.forEach(function(cell) { rowText += ' ' + cell.textContent; });
        } else {
            rowText = row.textContent;
        }
        rowText = rowText.toLowerCase();
        resetHighlights(row);
        
        if (term === '' || rowText.indexOf(term) !== -1) {
            row.classList.remove('hidden-row');
            visibleCount++;
            if (term !== '') highlightMatches(row, term);
        } else {
            row.classList.add('hidden-row');
        }
    });
    
    var countEl = document.getElementById(countId);
    if (countEl) {
        var countText = countEl.querySelector('.count-text');
        var icon = countEl.querySelector('i');
        if (term === '') {
            countEl.className = 'search-count';
            if (icon) icon.className = 'fas fa-list';
            if (countText) countText.textContent = visibleCount + ' records';
        } else if (visibleCount > 0) {
            countEl.className = 'search-count has-results';
            if (icon) icon.className = 'fas fa-check-circle';
            if (countText) countText.textContent = visibleCount + ' found';
        } else {
            countEl.className = 'search-count no-results';
            if (icon) icon.className = 'fas fa-times-circle';
            if (countText) countText.textContent = 'No results';
        }
    }
}

function highlightMatches(row, term) {
    var cells = row.querySelectorAll('.searchable-cell');
    cells.forEach(function(cell) {
        var html = cell.innerHTML;
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        var walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while (node = walker.nextNode()) textNodes.push(node);
        
        textNodes.forEach(function(textNode) {
            var text = textNode.nodeValue;
            var lowerText = text.toLowerCase();
            var lowerTerm = term.toLowerCase();
            if (lowerText.indexOf(lowerTerm) === -1) return;
            
            var fragments = [];
            var lastIndex = 0;
            var index;
            while ((index = lowerText.indexOf(lowerTerm, lastIndex)) !== -1) {
                if (index > lastIndex) fragments.push(document.createTextNode(text.substring(lastIndex, index)));
                var mark = document.createElement('mark');
                mark.className = 'search-highlight';
                mark.textContent = text.substring(index, index + term.length);
                fragments.push(mark);
                lastIndex = index + term.length;
            }
            if (lastIndex < text.length) fragments.push(document.createTextNode(text.substring(lastIndex)));
            if (fragments.length > 0) {
                var parent = textNode.parentNode;
                fragments.forEach(function(frag) { parent.insertBefore(frag, textNode); });
                parent.removeChild(textNode);
            }
        });
        cell.innerHTML = tempDiv.innerHTML;
    });
}

function resetHighlights(row) {
    var marks = row.querySelectorAll('mark.search-highlight');
    marks.forEach(function(mark) {
        var parent = mark.parentNode;
        parent.replaceChild(document.createTextNode(mark.textContent), mark);
        parent.normalize();
    });
}

function clearSearch(tableId, inputId, countId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.value = '';
        filterTable(tableId, '', countId);
        input.focus();
    }
}

function scrollTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    var amount = 400;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

// ================================================================
// CHARTS
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#94A3B8' : '#64748B';
    var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
    
    var ctxM = document.getElementById('monthlyChart')?.getContext('2d');
    if (ctxM && typeof Chart !== 'undefined') {
        var gradientPatient = ctxM.createLinearGradient(0, 0, 0, 280);
        gradientPatient.addColorStop(0, '#3B82F6');
        gradientPatient.addColorStop(1, '#0A4CA8');
        
        new Chart(ctxM, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthly_labels) ?>,
                datasets: [
                    { label: 'Patient Payments', data: <?= json_encode($monthly_patient) ?>, backgroundColor: gradientPatient, borderRadius: 6, barPercentage: 0.75, borderSkipped: false },
                    { label: 'OTC Sales', data: <?= json_encode($monthly_otc) ?>, backgroundColor: '#0891B2', borderRadius: 6, barPercentage: 0.75, borderSkipped: false },
                    { label: 'Expenses', data: <?= json_encode($monthly_expenses) ?>, backgroundColor: '#DC2626', borderRadius: 6, barPercentage: 0.75, borderSkipped: false }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Inter', size: 11, weight: '700' }, boxWidth: 12, padding: 12, color: textColor, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { 
                        backgroundColor: '#0B5ED7', titleColor: '#FFFFFF', bodyColor: '#DBEAFE', padding: 12, cornerRadius: 10,
                        titleFont: { family: 'Inter', size: 12, weight: '800' },
                        bodyFont: { family: 'JetBrains Mono', size: 11, weight: '600' },
                        callbacks: { label: function(c) { return c.dataset.label + ': <?= $currency ?> ' + c.raw.toLocaleString(); } }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return (v/1000).toFixed(0) + 'K'; }, font: { family: 'JetBrains Mono', size: 10, weight: '600' }, color: textColor }, grid: { color: gridColor, drawBorder: false } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10, weight: '600' }, color: textColor } }
                },
                animation: { duration: 1200, easing: 'easeOutQuart' }
            }
        });
    }
    
    var ctxD = document.getElementById('dailyChart')?.getContext('2d');
    if (ctxD && typeof Chart !== 'undefined') {
        var gradientPatientLine = ctxD.createLinearGradient(0, 0, 0, 280);
        gradientPatientLine.addColorStop(0, 'rgba(11, 94, 215, 0.3)');
        gradientPatientLine.addColorStop(1, 'rgba(11, 94, 215, 0.01)');
        
        var gradientOtcLine = ctxD.createLinearGradient(0, 0, 0, 280);
        gradientOtcLine.addColorStop(0, 'rgba(8, 145, 178, 0.3)');
        gradientOtcLine.addColorStop(1, 'rgba(8, 145, 178, 0.01)');
        
        new Chart(ctxD, {
            type: 'line',
            data: {
                labels: <?= json_encode($daily_labels) ?>,
                datasets: [
                    { label: 'Patient Payments', data: <?= json_encode($daily_patient) ?>, borderColor: '#0B5ED7', backgroundColor: gradientPatientLine, fill: true, tension: 0.4, pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: '#0B5ED7', pointBorderColor: '#FFFFFF', pointBorderWidth: 2, borderWidth: 2.5 },
                    { label: 'OTC Sales', data: <?= json_encode($daily_otc) ?>, borderColor: '#0891B2', backgroundColor: gradientOtcLine, fill: true, tension: 0.4, pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: '#0891B2', pointBorderColor: '#FFFFFF', pointBorderWidth: 2, borderWidth: 2.5 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Inter', size: 11, weight: '700' }, boxWidth: 12, padding: 12, color: textColor, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { 
                        backgroundColor: '#0B5ED7', titleColor: '#FFFFFF', bodyColor: '#DBEAFE', padding: 12, cornerRadius: 10,
                        titleFont: { family: 'Inter', size: 12, weight: '800' },
                        bodyFont: { family: 'JetBrains Mono', size: 11, weight: '600' },
                        callbacks: { label: function(c) { return c.dataset.label + ': <?= $currency ?> ' + c.raw.toLocaleString(); } }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return (v/1000).toFixed(0) + 'K'; }, font: { family: 'JetBrains Mono', size: 10, weight: '600' }, color: textColor }, grid: { color: gridColor, drawBorder: false } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 9, weight: '600' }, color: textColor, maxTicksLimit: 15 } }
                },
                interaction: { intersect: false, mode: 'index' },
                animation: { duration: 1400, easing: 'easeOutQuart' }
            }
        });
    }
});

// ================================================================
// CONSOLE BRANDING
// ================================================================
console.log('%c📊 Revenue Report V29 - ROUNDING 50 + PRESCRIPTION EXACT', 'font-size:18px; font-weight:bold; color:#0B5ED7; text-shadow: 0 0 10px rgba(11,94,215,0.5);');
console.log('%c✅ Breakdown uses payments.amount DIRECTLY', 'font-size:12px; color:#10B981; font-weight:bold;');
console.log('%c✅ Rounding to nearest 50 — numbers end in 50 or 00', 'font-size:12px; color:#10B981; font-weight:bold;');
console.log('%c✅ Prescriptions = bill_items.total_price EXACT (rounded 50)', 'font-size:12px; color:#10B981; font-weight:bold;');
console.log('%c✅ Premium = <?= $currency ?> <?= number_format($total_premiums, 0) ?> | Discount = <?= $currency ?> <?= number_format($total_discounts, 0) ?>', 'font-size:12px; color:#7C3AED; font-weight:bold;');
console.log('%c💰 Total Revenue: <?= $currency ?> <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#059669; font-weight:bold;');
console.log('%c📊 Patient Payments (rounded): <?= $currency ?> <?= number_format($patient_bills_revenue_rounded, 0) ?>', 'font-size:12px; color:#0B5ED7; font-weight:bold;');
console.log('%c💊 Prescriptions: <?= $currency ?> <?= number_format($prescription_revenue, 0) ?>', 'font-size:12px; color:#D97706; font-weight:bold;');
console.log('%c📋 Breakdown Total: <?= $currency ?> <?= number_format($breakdown_total, 0) ?>', 'font-size:12px; color:#10B981; font-weight:bold;');
console.log('%c<?= abs($breakdown_total - $patient_bills_revenue_rounded) < 1 ? "✅ Breakdown MATCHES Patient Payments!" : "❌ Breakdown DIFFERS by " . number_format($breakdown_total - $patient_bills_revenue_rounded, 2) ?>', 'font-size:12px; color:<?= abs($breakdown_total - $patient_bills_revenue_rounded) < 1 ? "#10B981" : "#DC2626" ?>; font-weight:bold;');
</script>

</body>
</html>