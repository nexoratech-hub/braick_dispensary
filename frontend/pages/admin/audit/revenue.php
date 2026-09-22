<?php
// ================================================================
// FILE: frontend/pages/admin/audit/revenue.php
// ADMIN AUDIT - REVENUE REPORT (V59 - FIX PENDING BILLS)
// ================================================================
// ✅ V59: FIX - Pending bills zinaonekana bila kuhitaji payments
// ✅ V59: Pending filter inafanya kazi (bills zenye status pending)
// ✅ V59: All filter inaonyesha pending + paid + partial
// ✅ V58: Quick Filters (Today...Custom) JUU kabla ya Cards
// ✅ V58: Patients Filter (All, Partial, Pending, Paid) CHINI ya Cards
// ✅ V57: Live Search + Highlight ya njano
// ✅ V56: Toggle button kwa kila patient
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

function roundTo50($value) {
    return round($value / 50) * 50;
}

// ✅ V57: Highlight function
function highlightSearchTerm($text, $search) {
    if (empty($search) || $text === null || $text === '') {
        return htmlspecialchars($text ?? '');
    }
    $escaped = htmlspecialchars($text);
    $searchEscaped = preg_quote($search, '/');
    return preg_replace(
        '/(' . $searchEscaped . ')/iu',
        '<mark class="search-highlight">$1</mark>',
        $escaped
    );
}

// DELETE ACTIONS
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'delete_visit' && !empty($_POST['visit_id'])) {
        try {
            $visit_id = (int)$_POST['visit_id'];
            $stmt = $db->prepare("SELECT visit_number FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($visit) {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $bill_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($bill_ids)) {
                        $ph = implode(',', array_fill(0, count($bill_ids), '?'));
                        $db->prepare("DELETE FROM payments WHERE bill_id IN ($ph)")->execute($bill_ids);
                        $db->prepare("DELETE FROM bill_items WHERE bill_id IN ($ph)")->execute($bill_ids);
                        $db->prepare("DELETE FROM bills WHERE id IN ($ph)")->execute($bill_ids);
                    }

                    $stmt = $db->prepare("SELECT id FROM prescriptions WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $presc_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($presc_ids)) {
                        $ph = implode(',', array_fill(0, count($presc_ids), '?'));
                        $db->prepare("DELETE FROM prescription_items WHERE prescription_id IN ($ph)")->execute($presc_ids);
                        $db->prepare("DELETE FROM prescriptions WHERE id IN ($ph)")->execute($presc_ids);
                    }

                    $db->prepare("DELETE FROM lab_tests WHERE visit_id = ?")->execute([$visit_id]);
                    $db->prepare("DELETE FROM procedures WHERE visit_id = ?")->execute([$visit_id]);
                    $db->prepare("DELETE FROM visits WHERE id = ?")->execute([$visit_id]);

                    $db->commit();

                    try {
                        $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'delete_visit', ?, ?, NOW())")
                           ->execute([$user_id, $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id, "Deleted visit: " . ($visit['visit_number'] ?? 'N/A'), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } catch (Exception $e) {}

                    $alert_message = "✅ Visit <strong>" . htmlspecialchars($visit['visit_number'] ?? 'N/A') . "</strong> deleted successfully!";
                    $alert_type = 'success';
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $alert_message = "❌ Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }

    if ($_POST['action'] === 'delete_otc' && !empty($_POST['sale_id'])) {
        try {
            $sale_id = (int)$_POST['sale_id'];
            $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?")->execute([$sale_id]);
            $db->prepare("DELETE FROM otc_sales WHERE id = ?")->execute([$sale_id]);
            $alert_message = "✅ OTC Sale deleted successfully!";
            $alert_type = 'success';
        } catch (Exception $e) {
            $alert_message = "❌ Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    if ($_POST['action'] === 'delete_expense' && !empty($_POST['expense_id'])) {
        try {
            $expense_id = (int)$_POST['expense_id'];
            $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$expense_id]);
            $alert_message = "✅ Expense deleted successfully!";
            $alert_type = 'success';
        } catch (Exception $e) {
            $alert_message = "❌ Error: " . $e->getMessage();
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
$quick_filter = $_GET['quick'] ?? 'all';
$patient_filter = $_GET['patient_filter'] ?? 'all';

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = date('Y-m-d');

if (strtotime($date_from) > strtotime($date_to)) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

$payment_method = $_GET['payment_method'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$date_cond_payments = ""; $date_cond_otc = ""; $date_cond_exp = "";
$date_cond_bills = "";
$date_params = []; $date_params_bills = []; $date_label = "";

switch ($quick_filter) {
    case 'today':
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) = CURDATE()";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) = CURDATE()";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) = CURDATE()";
        $date_cond_bills = " AND DATE(b.created_at) = CURDATE()";
        $date_label = "Today • " . date('d M Y');
        break;
    case 'yesterday':
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_cond_bills = " AND DATE(b.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_label = "Yesterday • " . date('d M Y', strtotime('-1 day'));
        break;
    case '1d':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_cond_bills = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $date_label = "Last 24 Hours";
        break;
    case '1w':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $date_cond_bills = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 7 Days";
        break;
    case '1m':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $date_cond_bills = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $date_cond_bills = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months";
        break;
    case '6m':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        $date_cond_bills = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_label = "Last 6 Months";
        break;
    case '1y':
        $date_cond_payments = " AND p.{$payments_date_col} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_otc = " AND o.{$otc_date_col} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_exp = " AND e.{$expenses_date_col} >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $date_cond_bills = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year";
        break;
    case 'all':
        $date_label = "All Time";
        break;
    case 'custom':
        $date_cond_payments = " AND DATE(p.{$payments_date_col}) BETWEEN ? AND ?";
        $date_cond_otc = " AND DATE(o.{$otc_date_col}) BETWEEN ? AND ?";
        $date_cond_exp = " AND DATE(e.{$expenses_date_col}) BETWEEN ? AND ?";
        $date_cond_bills = " AND DATE(b.created_at) BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        $date_params_bills = [$date_from, $date_to];
        $date_label = date('d M Y', strtotime($date_from)) . ' → ' . date('d M Y', strtotime($date_to));
        break;
    default:
        $date_label = "All Time";
}

$pay_cond_payments = ""; $pay_cond_otc = ""; $pay_params = [];
if ($payment_method !== 'all') {
    $pay_cond_payments = " AND p.payment_method = ?";
    $pay_cond_otc = " AND o.payment_method = ?";
    $pay_params = [$payment_method];
}

$branch_cond_p = ""; $branch_cond_o = ""; $branch_cond_e = ""; $branch_cond_bi = ""; $branch_cond_b = "";
$branch_params_p = []; $branch_params_o = []; $branch_params_e = []; $branch_params_bi = []; $branch_params_b = [];
if ($selected_branch_id !== 'all') {
    $branch_cond_p = " AND p.branch_id = ?";
    $branch_cond_o = " AND o.branch_id = ?";
    $branch_cond_e = " AND e.branch_id = ?";
    $branch_cond_bi = " AND bi.branch_id = ?";
    $branch_cond_b = " AND b.branch_id = ?";
    $branch_params_p = [(int)$selected_branch_id];
    $branch_params_o = [(int)$selected_branch_id];
    $branch_params_e = [(int)$selected_branch_id];
    $branch_params_bi = [(int)$selected_branch_id];
    $branch_params_b = [(int)$selected_branch_id];
}

// PATIENT PAYMENTS
$patient_bills_revenue = 0; $patient_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total, COUNT(*) as count 
            FROM payments p
            INNER JOIN bills b ON p.bill_id = b.id
            WHERE p.bill_id IS NOT NULL 
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            $branch_cond_p $date_cond_payments $pay_cond_payments";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($data['total'] ?? 0);
    $patient_bills_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// OTC REVENUE
$otc_revenue = 0; $otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales o WHERE o.payment_status IN ('paid', 'partial') 
            $branch_cond_o $date_cond_otc $pay_cond_otc";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = (float)($data['total'] ?? 0);
    $otc_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// PENDING
$pending_bills_amount = 0; $pending_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(b.total_amount - COALESCE(b.paid_amount, 0)), 0) as total, COUNT(*) as count 
            FROM bills b
            WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%' AND b.status = 'pending'
            " . ($selected_branch_id !== 'all' ? " AND b.branch_id = ?" : "");
    $stmt = $db->prepare($sql);
    $stmt->execute($selected_branch_id !== 'all' ? [(int)$selected_branch_id] : []);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_bills_amount = (float)($data['total'] ?? 0);
    $pending_bills_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$partial_remaining = 0; $partial_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(b.total_amount - COALESCE(b.paid_amount, 0)), 0) as total, COUNT(*) as count 
            FROM bills b
            WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%' AND b.status = 'partial'
            " . ($selected_branch_id !== 'all' ? " AND b.branch_id = ?" : "");
    $stmt = $db->prepare($sql);
    $stmt->execute($selected_branch_id !== 'all' ? [(int)$selected_branch_id] : []);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_remaining = (float)($data['total'] ?? 0);
    $partial_bills_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$pending_otc_amount = 0; $pending_otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales o WHERE o.payment_status = 'pending' 
            " . ($selected_branch_id !== 'all' ? " AND o.branch_id = ?" : "");
    $stmt = $db->prepare($sql);
    $stmt->execute($selected_branch_id !== 'all' ? [(int)$selected_branch_id] : []);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_otc_amount = (float)($data['total'] ?? 0);
    $pending_otc_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$partial_otc_amount = 0; $partial_otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales o WHERE o.payment_status = 'partial' 
            " . ($selected_branch_id !== 'all' ? " AND o.branch_id = ?" : "");
    $stmt = $db->prepare($sql);
    $stmt->execute($selected_branch_id !== 'all' ? [(int)$selected_branch_id] : []);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_otc_amount = (float)($data['total'] ?? 0);
    $partial_otc_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$total_pending = $pending_bills_amount + $partial_remaining + $pending_otc_amount + $partial_otc_amount;

// BREAKDOWN
$breakdown_types = ['consultation', 'lab_test', 'procedure', 'medication', 'registration', 'equipment'];
$breakdown_data = [];
foreach ($breakdown_types as $type) {
    $breakdown_data[$type] = ['gross' => 0, 'discount' => 0, 'count' => 0];
    try {
        $sql = "SELECT 
                    COALESCE(SUM(bi.total_price), 0) as gross,
                    COALESCE(SUM(bi.discount_amount), 0) as discount,
                    COUNT(DISTINCT bi.id) as count 
                FROM bill_items bi 
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE bi.item_type = ? 
                AND bi.status != 'cancelled'
                AND b.patient_id IS NOT NULL 
                AND b.visit_id IS NOT NULL 
                AND b.bill_number NOT LIKE 'BILL-OTC-%' 
                AND b.status IN ('paid', 'partial')
                AND b.id IN (
                    SELECT DISTINCT p.bill_id 
                    FROM payments p 
                    INNER JOIN bills b2 ON p.bill_id = b2.id
                    WHERE p.bill_id IS NOT NULL
                    AND b2.patient_id IS NOT NULL
                    AND b2.visit_id IS NOT NULL
                    AND b2.bill_number NOT LIKE 'BILL-OTC-%'
                    $branch_cond_p $date_cond_payments $pay_cond_payments
                )
                $branch_cond_bi";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$type], $branch_params_p, $date_params, $pay_params, $branch_params_bi));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $breakdown_data[$type] = [
            'gross' => (float)($data['gross'] ?? 0),
            'discount' => (float)($data['discount'] ?? 0),
            'count' => (int)($data['count'] ?? 0)
        ];
    } catch (Exception $e) {}
}

// DISCOUNTS + PREMIUMS
$patient_discounts = 0; $patient_premiums = 0;
$pharmacy_discounts = 0; $cashier_discounts = 0;
$pharmacy_premiums = 0; $cashier_premiums = 0;
try {
    $sql = "SELECT 
                COALESCE(SUM(b.pharmacy_discount), 0) as pharmacy_discount,
                COALESCE(SUM(b.cashier_discount), 0) as cashier_discount,
                COALESCE(SUM(b.pharmacy_discount + b.cashier_discount), 0) as total_discount,
                COALESCE(SUM(b.pharmacy_premium), 0) as pharmacy_premium,
                COALESCE(SUM(b.cashier_premium), 0) as cashier_premium,
                COALESCE(SUM(b.pharmacy_premium + b.cashier_premium), 0) as total_premium
            FROM bills b
            WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
              AND b.bill_number NOT LIKE 'BILL-OTC-%' AND b.status IN ('paid', 'partial')
              AND b.id IN (SELECT DISTINCT p.bill_id FROM payments p 
                  INNER JOIN bills b2 ON p.bill_id = b2.id
                  WHERE p.bill_id IS NOT NULL
                  AND b2.patient_id IS NOT NULL
                  AND b2.visit_id IS NOT NULL
                  AND b2.bill_number NOT LIKE 'BILL-OTC-%'
                  $branch_cond_p $date_cond_payments $pay_cond_payments)";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $pharmacy_discounts = (float)($data['pharmacy_discount'] ?? 0);
    $cashier_discounts = (float)($data['cashier_discount'] ?? 0);
    $patient_discounts = (float)($data['total_discount'] ?? 0);
    $pharmacy_premiums = (float)($data['pharmacy_premium'] ?? 0);
    $cashier_premiums = (float)($data['cashier_premium'] ?? 0);
    $patient_premiums = (float)($data['total_premium'] ?? 0);
} catch (Exception $e) {}

$otc_discounts = 0; $otc_premiums = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.discount_amount), 0) as total_discount,
                COALESCE(SUM(o.premium_amount), 0) as total_premium
            FROM otc_sales o WHERE o.payment_status IN ('paid', 'partial')
            $branch_cond_o $date_cond_otc $pay_cond_otc";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_discounts = (float)($data['total_discount'] ?? 0);
    $otc_premiums = (float)($data['total_premium'] ?? 0);
} catch (Exception $e) {}

$total_discounts = $patient_discounts + $otc_discounts;
$total_premiums = $patient_premiums + $otc_premiums;

// CATEGORIES
$consultation_revenue = $breakdown_data['consultation']['gross'];
$consultation_count = $breakdown_data['consultation']['count'];
$lab_revenue = $breakdown_data['lab_test']['gross'];
$lab_count = $breakdown_data['lab_test']['count'];
$procedure_revenue = $breakdown_data['procedure']['gross'];
$procedure_count = $breakdown_data['procedure']['count'];
$medication_revenue = $breakdown_data['medication']['gross'];
$medication_count = $breakdown_data['medication']['count'];
$registration_revenue = $breakdown_data['registration']['gross'];
$registration_count = $breakdown_data['registration']['count'];
$equipment_revenue = $breakdown_data['equipment']['gross'];
$equipment_count = $breakdown_data['equipment']['count'];

$prescription_gross = $medication_revenue;
$prescription_revenue = $prescription_gross;
$prescription_count = $medication_count;

$clinical_services_revenue = $consultation_revenue + $procedure_revenue + $equipment_revenue;
$clinical_services_count = $consultation_count + $procedure_count + $equipment_count;

$breakdown_total = $consultation_revenue + $lab_revenue + $procedure_revenue 
                 + $prescription_gross + $registration_revenue + $equipment_revenue;

$total_revenue = $patient_bills_revenue + $otc_revenue;
$total_transactions = $patient_bills_count + $otc_count;

// EXPENSES
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

// ROUND
$patient_bills_revenue_rounded = roundTo50($patient_bills_revenue);
$otc_revenue_rounded = roundTo50($otc_revenue);
$total_revenue = roundTo50($total_revenue);
$total_expenses = roundTo50($total_expenses);
$net_profit = roundTo50($net_profit);

$patient_discounts = roundTo50($patient_discounts);
$patient_premiums = roundTo50($patient_premiums);
$pharmacy_discounts = roundTo50($pharmacy_discounts);
$cashier_discounts = roundTo50($cashier_discounts);
$pharmacy_premiums = roundTo50($pharmacy_premiums);
$cashier_premiums = roundTo50($cashier_premiums);
$total_discounts = roundTo50($total_discounts);
$total_premiums = roundTo50($total_premiums);

$consultation_revenue = roundTo50($consultation_revenue);
$lab_revenue = roundTo50($lab_revenue);
$procedure_revenue = roundTo50($procedure_revenue);
$prescription_revenue = roundTo50($prescription_revenue);
$prescription_gross = roundTo50($prescription_gross);
$medication_revenue = roundTo50($medication_revenue);
$registration_revenue = roundTo50($registration_revenue);
$equipment_revenue = roundTo50($equipment_revenue);
$clinical_services_revenue = roundTo50($clinical_services_revenue);
$breakdown_total = roundTo50($breakdown_total);

$pending_bills_amount = roundTo50($pending_bills_amount);
$partial_remaining = roundTo50($partial_remaining);
$pending_otc_amount = roundTo50($pending_otc_amount);
$partial_otc_amount = roundTo50($partial_otc_amount);
$total_pending = roundTo50($total_pending);

// OTC SALES LIST
$otc_sales_list = [];
try {
    $search_otc = "";
    $search_otc_params = [];
    if (!empty($search)) {
        $search_otc = " AND (o.sale_number LIKE ? OR o.customer_name LIKE ? OR u.full_name LIKE ?)";
        $search_otc_params = ["%$search%", "%$search%", "%$search%"];
    }

    $sql = "SELECT o.id as sale_id, o.sale_number, o.customer_name, o.customer_phone,
                o.subtotal, o.discount_amount, o.premium_amount, o.premium_note, o.total_amount,
                o.payment_method, o.payment_status, o.sold_by, o.branch_id, o.notes,
                o.created_at, o.updated_at, o.bill_id,
                u.full_name as sold_by_name, u.role as sold_by_role, br.name as branch_name,
                (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
                (SELECT GROUP_CONCAT(CONCAT(item_name, '|||', quantity, '|||', unit_price, '|||', total_price) SEPARATOR '###') 
                 FROM otc_sale_items WHERE sale_id = o.id) as items_raw
            FROM otc_sales o
            LEFT JOIN users u ON o.sold_by = u.id
            LEFT JOIN branches br ON o.branch_id = br.id
            WHERE o.payment_status IN ('paid', 'partial')
            $branch_cond_o $date_cond_otc $pay_cond_otc $search_otc
            ORDER BY o.{$otc_date_col} DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params, $search_otc_params));
    $otc_sales_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("OTC sales list error: " . $e->getMessage());
}

// ================================================================
// ✅ V59 FIX: PATIENT-GROUPED BILLS - PENDING BILLS ZINAONEKANA
// ================================================================
// TATIZO LA V58: Query ilikuwa na `IN (SELECT ... FROM payments)` 
// ambayo ilifuta pending bills (hazina payments).
//
// SULUHISHO: Kwa 'pending' na 'all' filter, HATUTUMII payments condition.
// Kwa 'paid'/'partial', tunatumia payments condition.
// ================================================================
$patient_groups = [];

try {
    $search_patient = "";
    $search_patient_params = [];
    if (!empty($search)) {
        $search_patient = " AND (
            pat.full_name LIKE ? 
            OR pat.patient_id LIKE ? 
            OR pat.phone LIKE ? 
            OR b.bill_number LIKE ?
            OR v.visit_number LIKE ?
        )";
        $search_patient_params = [
            "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"
        ];
    }

    // ✅ Patient filter condition
    $patient_filter_cond = "";
    if ($patient_filter === 'partial') {
        $patient_filter_cond = " AND b.status = 'partial'";
    } elseif ($patient_filter === 'pending') {
        $patient_filter_cond = " AND b.status = 'pending'";
    } elseif ($patient_filter === 'paid') {
        $patient_filter_cond = " AND b.status = 'paid'";
    }
    // 'all' - hakuna cond, inaonyesha zote

    // ✅ Payment requirement - TUNAPUNGUKA KWA PENDING/ALL
    // Kwa paid/partial: inahitaji payment (ndio zilizolipwa)
    // Kwa pending: HAITAJI payment (hazijalipwa bado)
    // Kwa all: HAITAJI payment (tunataka pia pending zionekane)
    $payment_require_cond = "";
    $extra_params_payment = [];
    
    if ($patient_filter === 'paid' || $patient_filter === 'partial') {
        // Kwa paid/partial — inahitaji payment kweli
        $payment_require_cond = " AND b.id IN (SELECT DISTINCT p.bill_id FROM payments p 
            INNER JOIN bills b2 ON p.bill_id = b2.id
            WHERE p.bill_id IS NOT NULL 
            AND b2.patient_id IS NOT NULL
            AND b2.visit_id IS NOT NULL
            AND b2.bill_number NOT LIKE 'BILL-OTC-%'
            $branch_cond_p $date_cond_payments $pay_cond_payments)";
        
        // ✅ Tunahitaji params za payment kwa filter hii
        $extra_params_payment = array_merge($branch_params_p, $date_params, $pay_params);
    } else {
        // Kwa 'pending' na 'all' — HAITAJI payment
        // Pending bills hazina payments, hivyo lazima zionekane
        $payment_require_cond = "";
        $extra_params_payment = [];
    }

    $sql_bills = "SELECT b.id as bill_id, b.bill_number, b.patient_id as patient_db_id, b.visit_id,
                    b.subtotal, b.pharmacy_discount, b.cashier_discount, b.total_discount,
                    b.pharmacy_premium, b.cashier_premium, b.premium_amount,
                    b.total_amount, b.paid_amount, b.balance, b.status as bill_status,
                    b.payment_method, b.created_at as bill_created_at,
                    pat.full_name as patient_name, pat.patient_id as patient_code,
                    pat.phone as patient_phone, pat.date_of_birth, pat.gender,
                    v.visit_number, v.visit_type, v.visit_date, v.status as visit_status,
                    v.diagnosis, v.disease_code, v.symptoms, v.complaint,
                    v.consultation_fee, v.visit_total,
                    u_doctor.full_name as doctor_name,
                    u_reception.full_name as receptionist_name
                FROM bills b
                LEFT JOIN patients pat ON b.patient_id = pat.id
                LEFT JOIN visits v ON b.visit_id = v.id
                LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
                LEFT JOIN users u_reception ON v.receptionist_id = u_reception.id
                WHERE b.patient_id IS NOT NULL 
                AND b.visit_id IS NOT NULL
                AND b.bill_number NOT LIKE 'BILL-OTC-%' 
                AND b.status IN ('paid', 'partial', 'pending')
                $branch_cond_b
                $date_cond_bills
                $patient_filter_cond
                $search_patient
                $payment_require_cond
                ORDER BY pat.full_name ASC, v.visit_date DESC, b.created_at DESC 
                LIMIT 200";

    $stmt = $db->prepare($sql_bills);
    
    // ✅ Params: 
    // [branch, dates, search] + (payment params ikiwa paid/partial)
    $final_params = array_merge(
        $branch_params_b, 
        $date_params_bills, 
        $search_patient_params
    );
    
    if (!empty($extra_params_payment)) {
        $final_params = array_merge($final_params, $extra_params_payment);
    }
    
    $stmt->execute($final_params);
    $bills_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bills_data as $bill) {
        $patient_key = $bill['patient_db_id'];
        $visit_key = $bill['visit_id'];

        if (!isset($patient_groups[$patient_key])) {
            $patient_groups[$patient_key] = [
                'patient_db_id' => $bill['patient_db_id'],
                'patient_name' => $bill['patient_name'],
                'patient_code' => $bill['patient_code'],
                'patient_phone' => $bill['patient_phone'],
                'date_of_birth' => $bill['date_of_birth'],
                'gender' => $bill['gender'],
                'visits' => [],
                'total_paid' => 0,
                'total_billed' => 0,
                'total_balance' => 0,
                'has_partial' => false,
                'has_pending' => false,
                'has_paid' => false
            ];
        }

        $patient_groups[$patient_key]['total_paid'] += (float)$bill['paid_amount'];
        $patient_groups[$patient_key]['total_billed'] += (float)$bill['total_amount'];
        $patient_groups[$patient_key]['total_balance'] += (float)$bill['balance'];
        
        if ($bill['bill_status'] === 'partial') $patient_groups[$patient_key]['has_partial'] = true;
        if ($bill['bill_status'] === 'pending') $patient_groups[$patient_key]['has_pending'] = true;
        if ($bill['bill_status'] === 'paid') $patient_groups[$patient_key]['has_paid'] = true;

        if (!isset($patient_groups[$patient_key]['visits'][$visit_key])) {
            $patient_groups[$patient_key]['visits'][$visit_key] = [
                'visit_id' => $visit_key,
                'visit_number' => $bill['visit_number'],
                'visit_type' => $bill['visit_type'],
                'visit_date' => $bill['visit_date'],
                'visit_status' => $bill['visit_status'],
                'diagnosis' => $bill['diagnosis'],
                'disease_code' => $bill['disease_code'],
                'symptoms' => $bill['symptoms'],
                'complaint' => $bill['complaint'],
                'consultation_fee' => $bill['consultation_fee'],
                'doctor_name' => $bill['doctor_name'] ?? 'N/A',
                'receptionist_name' => $bill['receptionist_name'] ?? 'N/A',
                'bills' => [],
                'items' => [],
                'visit_total' => 0,
                'visit_paid' => 0,
                'visit_balance' => 0,
                'pharmacy_discount' => 0,
                'cashier_discount' => 0,
                'pharmacy_premium' => 0,
                'cashier_premium' => 0,
                'paid_items_count' => 0,
                'paid_items_total' => 0,
                'pending_items_count' => 0,
                'pending_items_total' => 0,
                'items_by_category' => [
                    'consultation' => [], 'lab_test' => [], 'medication' => [],
                    'procedure' => [], 'equipment' => [], 'registration' => [], 'other' => []
                ]
            ];
        }

        $patient_groups[$patient_key]['visits'][$visit_key]['visit_total'] += (float)$bill['total_amount'];
        $patient_groups[$patient_key]['visits'][$visit_key]['visit_paid'] += (float)$bill['paid_amount'];
        $patient_groups[$patient_key]['visits'][$visit_key]['visit_balance'] += (float)$bill['balance'];
        $patient_groups[$patient_key]['visits'][$visit_key]['pharmacy_discount'] += (float)($bill['pharmacy_discount'] ?? 0);
        $patient_groups[$patient_key]['visits'][$visit_key]['cashier_discount'] += (float)($bill['cashier_discount'] ?? 0);
        $patient_groups[$patient_key]['visits'][$visit_key]['pharmacy_premium'] += (float)($bill['pharmacy_premium'] ?? 0);
        $patient_groups[$patient_key]['visits'][$visit_key]['cashier_premium'] += (float)($bill['cashier_premium'] ?? 0);
        
        $patient_groups[$patient_key]['visits'][$visit_key]['bills'][] = $bill;
    }

    $all_bill_ids = array_column($bills_data, 'bill_id');
    if (!empty($all_bill_ids)) {
        $placeholders = implode(',', array_fill(0, count($all_bill_ids), '?'));
        $sql_items = "SELECT bi.id as item_id, bi.bill_id, bi.item_type, bi.item_name, bi.item_code,
                        bi.description, bi.quantity, bi.unit_price, bi.total_price,
                        bi.discount_amount, bi.final_price, bi.status as item_status
                    FROM bill_items bi
                    WHERE bi.bill_id IN ($placeholders) AND bi.status != 'cancelled'
                    ORDER BY bi.bill_id ASC,
                        FIELD(bi.item_type, 'consultation', 'lab_test', 'medication', 'procedure', 'equipment', 'registration', 'other'),
                        bi.id ASC";

        $stmt = $db->prepare($sql_items);
        $stmt->execute($all_bill_ids);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bill_to_visit = [];
        foreach ($bills_data as $bill) {
            $bill_to_visit[$bill['bill_id']] = [
                'patient_db_id' => $bill['patient_db_id'],
                'visit_id' => $bill['visit_id']
            ];
        }

        foreach ($items_data as $item) {
            $bill_id = $item['bill_id'];
            if (!isset($bill_to_visit[$bill_id])) continue;

            $patient_key = $bill_to_visit[$bill_id]['patient_db_id'];
            $visit_key = $bill_to_visit[$bill_id]['visit_id'];

            if (!isset($patient_groups[$patient_key]['visits'][$visit_key])) continue;

            $item_type = $item['item_type'] ?? 'other';
            if (!isset($patient_groups[$patient_key]['visits'][$visit_key]['items_by_category'][$item_type])) {
                $item_type = 'other';
            }

            $patient_groups[$patient_key]['visits'][$visit_key]['items_by_category'][$item_type][] = $item;
            $patient_groups[$patient_key]['visits'][$visit_key]['items'][] = $item;

            $item_status = strtolower($item['item_status'] ?? 'pending');
            $item_amount = (float)($item['total_price'] ?? 0);
            $item_discount = (float)($item['discount_amount'] ?? 0);
            $item_final = $item_amount - $item_discount;
            
            if ($item_status === 'paid') {
                $patient_groups[$patient_key]['visits'][$visit_key]['paid_items_count']++;
                $patient_groups[$patient_key]['visits'][$visit_key]['paid_items_total'] += $item_final;
            } else {
                $patient_groups[$patient_key]['visits'][$visit_key]['pending_items_count']++;
                $patient_groups[$patient_key]['visits'][$visit_key]['pending_items_total'] += $item_final;
            }
        }
    }

    foreach ($patient_groups as $patient_key => &$patient) {
        $patient['visits'] = array_values($patient['visits']);
    }
    unset($patient);

    $patient_groups = array_values($patient_groups);

} catch (Exception $e) {
    error_log("Patient grouped bills error: " . $e->getMessage());
}

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
<title>Revenue Report V59 • Braick Admin Audit</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7; --primary-dark: #0A4CA8; --primary-light: #3B82F6; --primary-bg: #E8F0FE; --primary-soft: #DBEAFE;
    --success: #059669; --success-light: #34D399; --success-bg: #D1FAE5;
    --danger: #DC2626; --danger-light: #F87171; --danger-bg: #FEE2E2;
    --warning: #D97706; --warning-light: #FBBF24; --warning-bg: #FEF3C7;
    --purple: #7C3AED; --purple-light: #A78BFA; --purple-bg: #EDE9FE;
    --cyan: #0891B2; --cyan-light: #22D3EE; --cyan-bg: #CFFAFE;
    --teal: #0D9488; --teal-bg: #CCFBF1;
    --slate: #94A3B8; --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9; --bg-card: #FFFFFF;
    --text-primary: #1E293B; --text-secondary: #64748B; --text-muted: #94A3B8;
    --border-color: #E2E8F0; --border-strong: #CBD5E1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-xl: 20px; --radius-full: 9999px;
}
[data-theme="dark"] {
    --bg-body: #0B1220; --bg-card: #111C33;
    --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
    --border-color: #1E2E4A; --border-strong: #2A3E5F;
    --primary-bg: #12294A; --success-bg: #0F2E22; --danger-bg: #3A1414;
    --warning-bg: #3A2A0F; --purple-bg: #2A1A4A; --cyan-bg: #0A2E3A;
    --teal-bg: #0A2E2A; --slate-bg: #1E2A3D;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); -webkit-font-smoothing: antialiased; line-height: 1.5; min-height: 100vh; animation: fadeIn 0.3s ease-in; }
@keyframes fadeIn { from { opacity: 0.85; } to { opacity: 1; } }
.money-number, .stat-value, .money-cell, .font-mono, .card-value { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

mark.search-highlight {
    background: linear-gradient(135deg, #FEF08A, #FDE047);
    color: #78350F;
    font-weight: 900;
    padding: 1px 4px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(250, 204, 21, 0.4);
    animation: highlightPulse 0.6s ease-out;
    border: 1px solid rgba(250, 204, 21, 0.6);
}
@keyframes highlightPulse {
    0% { background: #FDE047; transform: scale(1.15); box-shadow: 0 0 12px rgba(250, 204, 21, 0.8); }
    100% { background: linear-gradient(135deg, #FEF08A, #FDE047); transform: scale(1); }
}
.live-search-active {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 6px;
    background: linear-gradient(135deg, #FDE047, #FACC15);
    color: #78350F; font-size: 0.6rem; font-weight: 900;
    text-transform: uppercase; animation: livePulse 1.5s infinite; margin-left: 8px;
}
@keyframes livePulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
.search-input-wrapper.live-typing input { border-color: #FACC15; box-shadow: 0 0 0 4px rgba(250, 204, 21, 0.2); }
.patient-group-card[style*="display: none"] { display: none !important; }
.patient-group-card { transition: opacity 0.2s ease, transform 0.2s ease; }

.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.85rem; animation: slideDown 0.4s ease; border-left: 4px solid; box-shadow: var(--shadow-sm); }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left-color: var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left-color: var(--danger); }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); border-radius: var(--radius-lg); padding: 24px 28px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.5rem; font-weight: 900; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; position: relative; z-index: 1; }
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.9); font-size: 0.8rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.68rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); }
.branch-tag.filter-tag { background: linear-gradient(135deg, #10B981, #059669); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.branch-tag.live-tag { background: linear-gradient(135deg, #FDE047, #FACC15); color: #78350F; font-weight: 900; }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 10px 16px; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.75rem; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(10px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

.quick-filters-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 18px 22px;
    border: 2px solid var(--primary);
    margin-bottom: 20px;
    box-shadow: 0 6px 24px rgba(11, 94, 215, 0.15);
    position: relative;
    overflow: hidden;
}
.quick-filters-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0B5ED7, #3B82F6, #7C3AED, #3B82F6, #0B5ED7);
    background-size: 200% 100%;
    animation: shimmer 3s infinite linear;
}
.quick-filters-card .qfc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 2px dashed var(--border-color);
}
.quick-filters-card .qfc-title {
    font-size: 0.95rem;
    font-weight: 900;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.quick-filters-card .qfc-title .qfc-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}
.quick-filters-card .qfc-count {
    font-size: 0.7rem;
    font-weight: 800;
    background: var(--primary-bg);
    color: var(--primary);
    padding: 5px 12px;
    border-radius: 20px;
    border: 1.5px solid rgba(11, 94, 215, 0.25);
}
.quick-filters-card .filter-section-title {
    font-size: 0.75rem;
    font-weight: 900;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    margin-bottom: 8px;
}
.quick-filters-card .filter-section-title i { color: var(--primary); font-size: 0.85rem; }
.quick-filters { 
    display: flex; 
    gap: 10px; 
    flex-wrap: wrap; 
    padding: 14px 16px;
    background: linear-gradient(135deg, rgba(11, 94, 215, 0.04), rgba(124, 58, 237, 0.02));
    border-radius: 14px;
    border: 1.5px dashed var(--border-color);
}
.quick-btn { 
    padding: 10px 18px; 
    border-radius: 25px; 
    border: 2px solid var(--border-color); 
    background: var(--bg-card); 
    color: var(--text-secondary); 
    font-weight: 800; 
    font-size: 0.72rem; 
    cursor: pointer; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    display: inline-flex; 
    align-items: center; 
    gap: 7px; 
    text-decoration: none; 
    white-space: nowrap;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.quick-btn::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}
.quick-btn:hover::before { left: 100%; }
.quick-btn:hover { 
    border-color: var(--primary); 
    color: var(--primary); 
    transform: translateY(-3px); 
    box-shadow: 0 8px 20px rgba(11, 94, 215, 0.2);
}
.quick-btn.active { 
    background: linear-gradient(135deg, var(--primary), var(--primary-dark), var(--purple)); 
    color: white; 
    border-color: transparent; 
    box-shadow: 0 8px 24px rgba(11, 94, 215, 0.4), 0 0 0 3px rgba(11, 94, 215, 0.15);
    transform: translateY(-2px);
}
.quick-btn.active i { animation: pulseIcon 1.5s infinite; }
@keyframes pulseIcon { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }

.custom-date-row {
    padding: 12px 16px;
    background: var(--bg-body);
    border-radius: 10px;
    border: 2px solid var(--primary);
    margin-top: 14px;
    display: grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.auto-filter-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border-radius: 10px;
    background: linear-gradient(135deg, #059669, #047857);
    color: white; font-weight: 800; font-size: 0.78rem;
    height: 44px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
    white-space: nowrap;
}
.auto-filter-badge i { animation: pulseIcon 1.5s infinite; }

.stats-grid-8 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
.stat-card { background: var(--bg-card); border-radius: 14px; padding: 14px 16px; border: 2px solid var(--border-color); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: var(--shadow-sm); position: relative; overflow: hidden; cursor: pointer; min-height: 130px; display: flex; flex-direction: column; justify-content: space-between; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; transition: height 0.3s ease; }
.stat-card:hover::before { height: 5px; }
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.stat-card .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.stat-card .card-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.15); transition: transform 0.3s ease; }
.stat-card:hover .card-icon { transform: scale(1.1) rotate(-5deg); }
.stat-card .card-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 8px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
.stat-card .card-label { font-size: 0.62rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
.stat-card .card-value { font-size: 1.25rem; font-weight: 900; color: var(--text-primary); line-height: 1.1; letter-spacing: -0.03em; display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap; }
.stat-card .card-value .currency { font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); font-family: var(--font-primary); }
.stat-card .card-footer { display: flex; align-items: center; gap: 4px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border-color); font-size: 0.6rem; color: var(--text-secondary); font-weight: 600; flex-wrap: wrap; }
.stat-card .card-footer .highlight { color: var(--text-primary); font-weight: 800; font-family: var(--font-mono); }
.stat-card .card-footer .discount-badge { display: inline-flex; align-items: center; gap: 2px; padding: 1px 6px; border-radius: 6px; font-size: 0.55rem; font-weight: 800; font-family: var(--font-mono); background: var(--warning-bg); color: var(--warning); border: 1px solid rgba(217, 119, 6, 0.25); }
.stat-card .card-footer .premium-badge { display: inline-flex; align-items: center; gap: 2px; padding: 1px 6px; border-radius: 6px; font-size: 0.55rem; font-weight: 800; font-family: var(--font-mono); background: var(--purple-bg); color: var(--purple); border: 1px solid rgba(124, 58, 237, 0.25); }

.stat-card.revenue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.revenue:hover { border-color: #0B5ED7; }
.stat-card.revenue .card-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.revenue .card-badge { background: var(--primary-bg); color: var(--primary); }
.stat-card.revenue .card-value { color: var(--primary); }
.stat-card.payments::before { background: linear-gradient(90deg, #059669, #34D399, #059669); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.payments:hover { border-color: #059669; }
.stat-card.payments .card-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.payments .card-badge { background: var(--success-bg); color: var(--success); }
.stat-card.payments .card-value { color: var(--success); }
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
.stat-card.profit { background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card) 70%, rgba(11, 94, 215, 0.05) 100%); }
.stat-card.profit::before { background: linear-gradient(90deg, #0B5ED7, #10B981, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit:hover { border-color: #0B5ED7; }
.stat-card.profit .card-icon { background: linear-gradient(135deg, #0B5ED7, #10B981); }
.stat-card.profit .card-badge { background: var(--primary-bg); color: var(--primary); }
.stat-card.profit .card-value { color: var(--primary); }
.stat-card.profit.loss .card-value { color: var(--danger); }
.stat-card.profit.loss::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit.loss .card-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.profit.loss .card-badge { background: var(--danger-bg); color: var(--danger); }

@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.pulse-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: currentColor; margin-right: 3px; animation: pulseDot 1.5s infinite; }
@keyframes pulseDot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.pending-partial-card { background: var(--bg-card); border-radius: 16px; padding: 20px 24px; border: 2px solid var(--warning); margin-bottom: 20px; box-shadow: 0 6px 24px rgba(217, 119, 6, 0.15); position: relative; overflow: hidden; transition: all 0.35s ease; }
.pending-partial-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #D97706, #F59E0B, #FBBF24, #F59E0B, #D97706); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.pending-partial-card:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.25); border-color: #F59E0B; transform: translateY(-2px); }
.pending-partial-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px dashed var(--border-color); flex-wrap: wrap; gap: 10px; }
.pending-partial-title { display: flex; align-items: center; gap: 10px; font-size: 1rem; font-weight: 900; color: var(--text-primary); }
.pending-partial-title .title-icon { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #D97706, #F59E0B); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); flex-shrink: 0; }
.pending-partial-total { display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #FEF3C7, #FDE68A); padding: 8px 16px; border-radius: 10px; border: 1.5px solid rgba(217, 119, 6, 0.3); box-shadow: 0 2px 8px rgba(217, 119, 6, 0.12); }
.pending-partial-total .total-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #92400E; }
.pending-partial-total .total-value { font-family: var(--font-mono); font-size: 1.1rem; font-weight: 900; color: #92400E; }
.pending-partial-total .total-currency { font-size: 0.7rem; font-weight: 700; color: #B45309; font-family: var(--font-primary); }
.pending-partial-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.pending-partial-item { background: var(--bg-body); border-radius: 12px; padding: 14px 16px; border: 2px solid var(--border-color); display: flex; flex-direction: column; gap: 8px; transition: all 0.3s ease; position: relative; overflow: hidden; }
.pending-partial-item::before { content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 4px; }
.pending-partial-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.pending-partial-item .pp-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.pending-partial-item .pp-icon { width: 32px; height: 32px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; color: white; box-shadow: 0 3px 8px rgba(0,0,0,0.15); flex-shrink: 0; }
.pending-partial-item .pp-count-badge { font-family: var(--font-mono); font-size: 0.6rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; white-space: nowrap; }
.pending-partial-item .pp-label { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); }
.pending-partial-item .pp-value { font-family: var(--font-mono); font-size: 1.05rem; font-weight: 900; letter-spacing: -0.02em; line-height: 1.1; display: flex; align-items: baseline; gap: 4px; }
.pending-partial-item .pp-value .pp-currency { font-size: 0.65rem; font-weight: 700; color: var(--text-secondary); font-family: var(--font-primary); }
.pending-partial-item .pp-sub { font-size: 0.55rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 4px; }
.pending-partial-item.bills-pending::before { background: linear-gradient(180deg, #DC2626, #F87171); }
.pending-partial-item.bills-pending { border-color: rgba(220, 38, 38, 0.25); }
.pending-partial-item.bills-pending .pp-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.pending-partial-item.bills-pending .pp-count-badge { background: var(--danger-bg); color: var(--danger); }
.pending-partial-item.bills-pending .pp-value { color: #DC2626; }
.pending-partial-item.bills-partial::before { background: linear-gradient(180deg, #D97706, #FBBF24); }
.pending-partial-item.bills-partial { border-color: rgba(217, 119, 6, 0.25); }
.pending-partial-item.bills-partial .pp-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.pending-partial-item.bills-partial .pp-count-badge { background: var(--warning-bg); color: var(--warning); }
.pending-partial-item.bills-partial .pp-value { color: #D97706; }
.pending-partial-item.otc-pending::before { background: linear-gradient(180deg, #7C3AED, #A78BFA); }
.pending-partial-item.otc-pending { border-color: rgba(124, 58, 237, 0.25); }
.pending-partial-item.otc-pending .pp-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.pending-partial-item.otc-pending .pp-count-badge { background: var(--purple-bg); color: var(--purple); }
.pending-partial-item.otc-pending .pp-value { color: #7C3AED; }
.pending-partial-item.otc-partial::before { background: linear-gradient(180deg, #0891B2, #22D3EE); }
.pending-partial-item.otc-partial { border-color: rgba(8, 145, 178, 0.25); }
.pending-partial-item.otc-partial .pp-icon { background: linear-gradient(135deg, #0891B2, #22D3EE); }
.pending-partial-item.otc-partial .pp-count-badge { background: var(--cyan-bg); color: var(--cyan); }
.pending-partial-item.otc-partial .pp-value { color: #0891B2; }

.discount-premium-card { background: var(--bg-card); border-radius: 14px; padding: 18px 20px; border: 2px solid var(--border-color); margin-bottom: 20px; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
.discount-premium-card:hover { box-shadow: var(--shadow-md); border-color: #0B5ED7; }
.discount-premium-card .dp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px dashed var(--border-color); flex-wrap: wrap; gap: 8px; }
.discount-premium-card .dp-title { font-size: 0.95rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.dp-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; }
.dp-item { background: var(--bg-body); border-radius: 10px; padding: 12px 14px; border: 2px solid var(--border-color); transition: all 0.3s ease; position: relative; overflow: hidden; }
.dp-item::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.dp-item:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.dp-item .dp-label { font-size: 0.55rem; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
.dp-item .dp-value { font-size: 1rem; font-weight: 900; font-family: var(--font-mono); line-height: 1.1; }
.dp-item .dp-sub { font-size: 0.55rem; color: var(--text-secondary); margin-top: 4px; font-weight: 600; }
.dp-item.pharmacy-disc::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
.dp-item.pharmacy-disc .dp-value { color: #D97706; }
.dp-item.cashier-disc::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.dp-item.cashier-disc .dp-value { color: #7C3AED; }
.dp-item.total-disc::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.dp-item.total-disc .dp-value { color: #DC2626; }
.dp-item.pharmacy-prem::before { background: linear-gradient(90deg, #059669, #34D399); }
.dp-item.pharmacy-prem .dp-value { color: #059669; }
.dp-item.cashier-prem::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.dp-item.cashier-prem .dp-value { color: #0891B2; }
.dp-item.total-prem::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.dp-item.total-prem .dp-value { color: #0B5ED7; }

.patient-filter-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 20px 22px;
    border: 2px solid var(--primary);
    margin-bottom: 20px;
    box-shadow: 0 6px 24px rgba(11, 94, 215, 0.15);
    position: relative;
    overflow: hidden;
}
.patient-filter-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0B5ED7, #3B82F6, #7C3AED, #3B82F6, #0B5ED7);
    background-size: 200% 100%;
    animation: shimmer 3s infinite linear;
}
.patient-filter-card .pfc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px dashed var(--border-color);
}
.patient-filter-card .pfc-title {
    font-size: 0.95rem;
    font-weight: 900;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.patient-filter-card .pfc-title .pfc-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}
.patient-filter-card .pfc-count {
    font-size: 0.7rem;
    font-weight: 800;
    background: var(--primary-bg);
    color: var(--primary);
    padding: 5px 12px;
    border-radius: 20px;
    border: 1.5px solid rgba(11, 94, 215, 0.25);
}

.patient-filter-section {
    background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
    border-radius: var(--radius-md);
    padding: 14px 18px;
    margin-bottom: 14px;
    border: 2px dashed var(--border-color);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
[data-theme="dark"] .patient-filter-section {
    background: linear-gradient(135deg, #1E2A3D, #12294A);
}
.patient-filter-label {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}
.patient-filter-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1;
}
.patient-filter-btn {
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-secondary);
    transition: all 0.25s;
    white-space: nowrap;
    cursor: pointer;
}
.patient-filter-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.patient-filter-btn.active { color: white; border-color: transparent; box-shadow: 0 6px 16px rgba(0,0,0,0.15); }
.patient-filter-btn.all.active { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
.patient-filter-btn.partial.active { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.patient-filter-btn.pending.active { background: linear-gradient(135deg, #D97706, #B45309); }
.patient-filter-btn.paid.active { background: linear-gradient(135deg, #059669, #047857); }
.patient-filter-btn.partial:hover { border-color: #7C3AED; color: #7C3AED; }
.patient-filter-btn.pending:hover { border-color: #D97706; color: #D97706; }
.patient-filter-btn.paid:hover { border-color: #059669; color: #059669; }
.patient-filter-btn.all:hover { border-color: #0B5ED7; color: #0B5ED7; }
.patient-filter-btn.active:hover { color: white !important; }
.patient-filter-btn .filter-count {
    background: rgba(255,255,255,0.25);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: 900;
    font-family: var(--font-mono);
}
.patient-filter-btn:not(.active) .filter-count {
    background: var(--bg-body);
    color: var(--text-primary);
}

.search-input-wrapper { position: relative; width: 100%; }
.search-input-wrapper input {
    width: 100%;
    padding: 12px 16px 12px 42px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.25s;
}
.search-input-wrapper input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15); }
.search-input-wrapper .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--primary); font-size: 0.95rem; pointer-events: none; }
.search-input-wrapper .clear-search {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: var(--danger-bg); color: var(--danger);
    border: none; width: 24px; height: 24px; border-radius: 50%;
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.7rem; text-decoration: none;
}

.patient-group-card { background: var(--bg-card); border-radius: var(--radius-xl); border: 3px solid var(--primary); overflow: hidden; box-shadow: 0 8px 30px rgba(11, 94, 215, 0.15); margin-bottom: 36px; position: relative; transition: all 0.35s ease; }
.patient-group-card::before { content: ''; position: absolute; inset: -8px; border-radius: calc(var(--radius-xl) + 8px); background: linear-gradient(135deg, rgba(11, 94, 215, 0.12), rgba(124, 58, 237, 0.08)); z-index: -1; pointer-events: none; }
.patient-group-card:hover { border-color: var(--primary-light); box-shadow: 0 12px 40px rgba(11, 94, 215, 0.25); transform: translateY(-2px); }
.patient-group-card.has-pending { border-color: #DC2626; }
.patient-group-card.has-pending::before { background: linear-gradient(135deg, rgba(220, 38, 38, 0.15), rgba(185, 28, 28, 0.08)); }

.patient-info-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); padding: 18px 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; position: relative; overflow: hidden; cursor: pointer; }
.patient-info-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%; pointer-events: none; z-index: 0; }
.patient-info-header > * { position: relative; z-index: 1; }
.patient-number-badge { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); border: 1.5px solid rgba(255, 255, 255, 0.45); color: white; padding: 6px 14px; border-radius: 999px; font-size: 0.68rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); align-self: flex-start; flex-shrink: 0; white-space: nowrap; }
.patient-identity { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; flex: 1; min-width: 250px; }
.patient-avatar { width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 3px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; color: white; flex-shrink: 0; text-transform: uppercase; }
.patient-details { display: flex; flex-direction: column; gap: 4px; }
.patient-name { color: white; font-size: 1.25rem; font-weight: 900; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.patient-meta { color: rgba(255,255,255,0.85); font-size: 0.72rem; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.patient-meta span { display: inline-flex; align-items: center; gap: 5px; }
.patient-summary-badges { display: flex; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; align-self: flex-start; }
.summary-badge { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.25); border-radius: var(--radius-md); padding: 8px 14px; display: flex; flex-direction: column; gap: 2px; min-width: 110px; }
.summary-badge .badge-label { color: rgba(255,255,255,0.7); font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
.summary-badge .badge-value { color: white; font-size: 0.95rem; font-weight: 900; font-family: var(--font-mono); }
.summary-badge.visits-count .badge-value { color: #93C5FD; }
.summary-badge.billed .badge-value { color: #FBBF24; }
.summary-badge.paid .badge-value { color: #6EE7B7; }
.summary-badge.balance .badge-value { color: #FCA5A5; }

.patient-toggle-btn { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.25); backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.4); color: white; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.3s ease; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15); align-self: flex-start; }
.patient-toggle-btn:hover { background: rgba(255,255,255,0.4); transform: scale(1.1); border-color: rgba(255,255,255,0.7); }
.patient-toggle-btn.rotated { transform: rotate(180deg); }
.patient-toggle-btn.rotated:hover { transform: rotate(180deg) scale(1.1); }

.patient-body-container { max-height: 0; overflow: hidden; transition: max-height 0.5s ease-in-out, padding 0.3s ease; }
.patient-body-container.open { max-height: 50000px; padding: 18px 24px 20px; }

.partial-badge { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; padding: 3px 10px; border-radius: 12px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 8px rgba(245,158,11,0.5); border: 1px solid rgba(255,255,255,0.3); }
.pending-badge { background: linear-gradient(135deg, #F87171, #DC2626); color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 8px rgba(220,38,38,0.5); border: 1px solid rgba(255,255,255,0.3); }

.patient-footer { background: linear-gradient(135deg, rgba(11, 94, 215, 0.08), rgba(124, 58, 237, 0.05)); padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 2px dashed var(--primary); }
.patient-footer .footer-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.patient-footer .footer-end-label { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 900; color: var(--primary); text-transform: uppercase; }
.patient-footer .footer-visit-count { font-size: 0.62rem; color: var(--text-secondary); font-weight: 600; padding: 3px 8px; background: var(--bg-card); border-radius: 6px; border: 1px solid var(--border-color); }
.patient-footer .footer-right { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
.patient-footer .footer-stat { display: inline-flex; align-items: center; gap: 5px; font-size: 0.62rem; font-weight: 700; color: var(--text-secondary); background: var(--bg-card); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-color); }
.patient-footer .footer-stat strong { font-family: var(--font-mono); color: var(--text-primary); font-weight: 900; }
.patient-footer .footer-stat.success strong { color: var(--success); }
.patient-footer .footer-stat.danger strong { color: var(--danger); }

.visit-section { border-top: 2px solid var(--border-color); padding: 0; }
.visit-section:first-of-type { border-top: none; }

.visit-header-row1 { background: linear-gradient(135deg, rgba(11, 94, 215, 0.08), rgba(124, 58, 237, 0.05)); padding: 14px 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-color); }
.visit-info-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.visit-number-badge { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; padding: 6px 14px; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 900; font-family: var(--font-mono); display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.visit-status-badge { padding: 5px 12px; border-radius: var(--radius-full); font-size: 0.62rem; font-weight: 800; text-transform: uppercase; border: 1px solid; }
.visit-status-badge.completed { background: var(--success-bg); color: var(--success); border-color: var(--success); }
.visit-status-badge.pending { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
.visit-status-badge.with_doctor { background: var(--cyan-bg); color: var(--cyan); border-color: var(--cyan); }
.visit-status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
.visit-date-badge { font-size: 0.72rem; color: var(--text-secondary); font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
.visit-info-right { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.visit-staff-chip { display: inline-flex; align-items: center; gap: 6px; background: var(--bg-card); padding: 6px 12px; border-radius: var(--radius-sm); border: 1.5px solid var(--border-color); font-size: 0.72rem; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.visit-staff-chip i { color: var(--primary); font-size: 0.72rem; }
.visit-staff-chip .staff-label { color: var(--text-secondary); font-weight: 600; font-size: 0.65rem; text-transform: uppercase; }
.visit-staff-chip .staff-name { color: var(--text-primary); font-weight: 800; }

.visit-payment-summary { background: var(--bg-card); padding: 12px 20px; display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; border-bottom: 1px solid var(--border-color); }
.vps-item { background: var(--bg-body); border-radius: var(--radius-md); padding: 10px 14px; border: 2px solid var(--border-color); display: flex; flex-direction: column; gap: 3px; position: relative; overflow: hidden; transition: all 0.25s ease; }
.vps-item::before { content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 4px; }
.vps-item:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
.vps-item .vps-label { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; }
.vps-item .vps-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; letter-spacing: -0.02em; display: flex; align-items: baseline; gap: 4px; }
.vps-item .vps-value .vps-currency { font-size: 0.62rem; font-weight: 700; color: var(--text-secondary); font-family: var(--font-primary); }
.vps-item .vps-sub { font-size: 0.55rem; font-weight: 600; color: var(--text-muted); }
.vps-item.paid-items::before { background: linear-gradient(180deg, #059669, #34D399); }
.vps-item.paid-items { border-color: rgba(5, 150, 105, 0.3); background: linear-gradient(135deg, #ECFDF5, #D1FAE5); }
.vps-item.paid-items .vps-label i { color: #059669; }
.vps-item.paid-items .vps-value { color: #059669; }
.vps-item.pending-items::before { background: linear-gradient(180deg, #DC2626, #F87171); }
.vps-item.pending-items { border-color: rgba(220, 38, 38, 0.3); background: linear-gradient(135deg, #FEF2F2, #FEE2E2); }
.vps-item.pending-items .vps-label i { color: #DC2626; }
.vps-item.pending-items .vps-value { color: #DC2626; }
.vps-item.visit-paid::before { background: linear-gradient(180deg, #0891B2, #22D3EE); }
.vps-item.visit-paid { border-color: rgba(8, 145, 178, 0.3); background: linear-gradient(135deg, #ECFEFF, #CFFAFE); }
.vps-item.visit-paid .vps-label i { color: #0891B2; }
.vps-item.visit-paid .vps-value { color: #0891B2; }
.vps-item.visit-balance::before { background: linear-gradient(180deg, #D97706, #FBBF24); }
.vps-item.visit-balance { border-color: rgba(217, 119, 6, 0.3); background: linear-gradient(135deg, #FFFBEB, #FEF3C7); }
.vps-item.visit-balance .vps-label i { color: #D97706; }
.vps-item.visit-balance .vps-value { color: #D97706; }

[data-theme="dark"] .vps-item.paid-items { background: linear-gradient(135deg, #0F2E22, #1A3D2E); }
[data-theme="dark"] .vps-item.pending-items { background: linear-gradient(135deg, #3A1414, #4A1A1A); }
[data-theme="dark"] .vps-item.visit-paid { background: linear-gradient(135deg, #0A2E3A, #10404F); }
[data-theme="dark"] .vps-item.visit-balance { background: linear-gradient(135deg, #3A2A0F, #4A3A12); }

.visit-header-row2 { background: var(--bg-card); padding: 14px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; border-bottom: 1px solid var(--border-color); }
.visit-dp-card { background: var(--bg-body); border-radius: var(--radius-md); padding: 10px 14px; display: flex; flex-direction: column; gap: 3px; border: 1.5px solid var(--border-color); transition: all 0.25s; min-height: 70px; justify-content: center; }
.visit-dp-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
.visit-dp-card .dp-label { font-size: 0.55rem; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 4px; }
.visit-dp-card .dp-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; }
.visit-dp-card.pharmacy-disc { border-color: rgba(217, 119, 6, 0.3); background: linear-gradient(135deg, #FFFBEB, #FEF3C7); }
.visit-dp-card.pharmacy-disc .dp-label i, .visit-dp-card.pharmacy-disc .dp-value { color: #B45309; }
.visit-dp-card.cashier-disc { border-color: rgba(217, 119, 6, 0.4); background: linear-gradient(135deg, #FEF3C7, #FDE68A); }
.visit-dp-card.cashier-disc .dp-label i, .visit-dp-card.cashier-disc .dp-value { color: #92400E; }
.visit-dp-card.pharmacy-prem { border-color: rgba(124, 58, 237, 0.3); background: linear-gradient(135deg, #F5F3FF, #EDE9FE); }
.visit-dp-card.pharmacy-prem .dp-label i, .visit-dp-card.pharmacy-prem .dp-value { color: #6D28D9; }
.visit-dp-card.cashier-prem { border-color: rgba(168, 85, 247, 0.4); background: linear-gradient(135deg, #EDE9FE, #DDD6FE); }
.visit-dp-card.cashier-prem .dp-label i, .visit-dp-card.cashier-prem .dp-value { color: #7C3AED; }
.visit-dp-card.visit-total { background: linear-gradient(135deg, #059669, #047857); border-color: rgba(5, 150, 105, 0.4); }
.visit-dp-card.visit-total .dp-label, .visit-dp-card.visit-total .dp-label i { color: rgba(255,255,255,0.9); }
.visit-dp-card.visit-total .dp-value { color: white; font-size: 1.05rem; }

.visit-header-row3 { background: var(--bg-card); padding: 14px 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
.visit-diagnosis-box { display: flex; align-items: flex-start; gap: 10px; background: linear-gradient(135deg, rgba(220, 38, 38, 0.06), rgba(220, 38, 38, 0.02)); border-left: 4px solid var(--danger); padding: 10px 14px; border-radius: var(--radius-sm); flex: 1; min-width: 250px; }
.visit-diagnosis-box i { color: var(--danger); font-size: 1rem; margin-top: 2px; flex-shrink: 0; }
.visit-diagnosis-box .diagnosis-content { display: flex; flex-direction: column; gap: 3px; flex: 1; }
.visit-diagnosis-box .diagnosis-label { font-size: 0.58rem; font-weight: 800; text-transform: uppercase; color: var(--danger); }
.visit-diagnosis-box .diagnosis-text { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.visit-diagnosis-box .diagnosis-code { font-size: 0.62rem; font-weight: 700; color: var(--danger); background: rgba(220, 38, 38, 0.1); padding: 2px 8px; border-radius: 4px; font-family: var(--font-mono); display: inline-block; align-self: flex-start; margin-top: 2px; }
.visit-no-diagnosis { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); font-style: italic; font-weight: 600; }

.visit-action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.visit-action-btn { padding: 8px 16px; border-radius: var(--radius-sm); font-weight: 800; font-size: 0.72rem; border: none; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; text-transform: uppercase; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
.visit-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
.visit-action-btn.view { background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; }
.visit-action-btn.delete { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }

.visit-items-container { padding: 0; background: var(--bg-body); }
.items-table-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: var(--primary-bg); border-bottom: 1px solid var(--border-color); gap: 12px; flex-wrap: wrap; }
.items-table-header .items-title { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em; }
.items-scroll-buttons { display: flex; gap: 6px; }
.scroll-btn { width: 30px; height: 30px; border-radius: var(--radius-sm); border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 700; transition: all 0.25s; }
.scroll-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

.table-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
.table-card .table-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.table-card .table-header.green { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.table-card .table-header.profit { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.profit.loss { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.table-card .table-header .title { color: white; font-size: 0.88rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.table-card .table-header .count { color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.18); padding: 5px 12px; border-radius: var(--radius-full); }
.table-card .table-header .header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.table-card .table-header .header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.header-scroll-buttons { display: flex; gap: 6px; }
.header-scroll-btn { width: 32px; height: 32px; border-radius: var(--radius-sm); border: 1.5px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.2); color: white; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; transition: all 0.25s; }
.header-scroll-btn:hover { background: rgba(255,255,255,0.4); transform: translateY(-2px); }

.table-scroll-wrapper { overflow-x: auto; background: var(--bg-card); }
.table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 10px; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th { text-align: left; padding: 11px 14px; font-weight: 800; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.08em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; font-weight: 500; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr.total-row td { border-top: 3px solid var(--primary); font-weight: 800; font-size: 0.88rem; background: var(--primary-bg) !important; color: var(--primary); }
.data-table tbody tr.item-paid td { background: linear-gradient(90deg, rgba(5, 150, 105, 0.04), transparent) !important; }
.data-table tbody tr.item-paid:hover td { background: linear-gradient(90deg, rgba(5, 150, 105, 0.12), rgba(5, 150, 105, 0.05)) !important; }
.data-table tbody tr.item-pending td { background: linear-gradient(90deg, rgba(217, 119, 6, 0.04), transparent) !important; }
.data-table tbody tr.item-pending:hover td { background: linear-gradient(90deg, rgba(217, 119, 6, 0.12), rgba(217, 119, 6, 0.05)) !important; }

.money-cell { font-family: var(--font-mono); font-weight: 800; font-size: 0.82rem; color: var(--success); text-align: right; white-space: nowrap; }
.money-cell .currency-prefix { font-size: 0.65rem; color: var(--text-secondary); margin-right: 3px; font-weight: 600; }
.money-cell.warning { color: #D97706 !important; }
.money-cell.purple { color: #7C3AED !important; }
.money-cell.red { color: #DC2626 !important; }
.money-cell.slate { color: #94A3B8 !important; }
.money-cell.cyan { color: #0891B2 !important; }

.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: var(--radius-full); font-size: 0.62rem; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.received-by { display: flex; align-items: center; gap: 8px; }
.received-by-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.68rem; flex-shrink: 0; }
.received-by-avatar.red { background: linear-gradient(135deg, #DC2626, #F87171) !important; }
.received-by-avatar.cyan { background: linear-gradient(135deg, #0891B2, #22D3EE) !important; }
.received-by-info { display: flex; flex-direction: column; gap: 2px; }
.received-by-name { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); }
.received-by-role { font-size: 0.55rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; }

.role-tag { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.5rem; font-weight: 800; text-transform: uppercase; }
.role-tag.cashier { background: #FEF3C7; color: #D97706; }
.role-tag.reception { background: #DBEAFE; color: #1E40AF; }
.role-tag.pharmacy { background: #D1FAE5; color: #059669; }
.role-tag.admin { background: #FCE7F3; color: #BE185D; }
.role-tag.doctor { background: #EDE9FE; color: #7C3AED; }
.role-tag.laboratory { background: #CFFAFE; color: #0891B2; }
.role-tag.user { background: var(--border-color); color: var(--text-secondary); }

.payment-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.66rem; font-weight: 700; background: var(--primary-bg); color: var(--primary); white-space: nowrap; }
.expense-ref-badge { font-size: 0.68rem; color: #DC2626; font-weight: 800; background: var(--danger-bg); padding: 4px 8px; border-radius: 6px; display: inline-block; font-family: var(--font-mono); }
.expense-category-badge { font-size: 0.68rem; font-weight: 700; background: var(--warning-bg); color: var(--warning); padding: 4px 9px; border-radius: 6px; display: inline-block; }

.action-buttons { display: flex; gap: 5px; justify-content: center; align-items: center; }
.btn-action { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; border: none; cursor: pointer; transition: all 0.25s; text-decoration: none; }
.btn-action:hover { transform: translateY(-2px) scale(1.1); }
.btn-action.view { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
.btn-action.view:hover { background: #0B5ED7; color: white; }
.btn-action.delete { background: rgba(220, 38, 38, 0.12); color: #DC2626; }
.btn-action.delete:hover { background: #DC2626; color: white; }

.category-group-row td { background: linear-gradient(135deg, rgba(11, 94, 215, 0.06), rgba(124, 58, 237, 0.04)) !important; padding: 10px 16px !important; font-weight: 900; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--primary); border-bottom: 2px solid var(--primary) !important; }
.category-group-row td .category-icon { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; margin-right: 8px; font-size: 0.7rem; color: white; vertical-align: middle; }
.category-group-row.consultation td .category-icon { background: linear-gradient(135deg, #059669, #34D399); }
.category-group-row.lab_test td .category-icon { background: linear-gradient(135deg, #3B82F6, #93C5FD); }
.category-group-row.medication td .category-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.category-group-row.procedure td .category-icon { background: linear-gradient(135deg, #0D9488, #22D3EE); }
.category-group-row.equipment td .category-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.category-group-row.registration td .category-icon { background: linear-gradient(135deg, #64748B, #94A3B8); }
.category-group-row.other td .category-icon { background: linear-gradient(135deg, #94A3B8, #CBD5E1); }
.category-group-row td .category-total { float: right; font-family: var(--font-mono); font-size: 0.82rem; color: var(--success); background: var(--success-bg); padding: 3px 10px; border-radius: 6px; font-weight: 900; }
.category-group-row td .category-breakdown { float: right; display: inline-flex; gap: 6px; align-items: center; margin-right: 12px; }
.category-group-row td .category-breakdown .cb-paid { font-size: 0.62rem; font-weight: 800; color: #059669; background: #D1FAE5; padding: 2px 8px; border-radius: 6px; font-family: var(--font-mono); border: 1px solid rgba(5, 150, 105, 0.3); }
.category-group-row td .category-breakdown .cb-pending { font-size: 0.62rem; font-weight: 800; color: #DC2626; background: #FEE2E2; padding: 2px 8px; border-radius: 6px; font-family: var(--font-mono); border: 1px solid rgba(220, 38, 38, 0.3); }

.dot-indicator { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--bg-card); border-radius: var(--radius-xl); max-width: 500px; width: 100%; padding: 32px; box-shadow: var(--shadow-xl); animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); text-align: center; }
@keyframes modalPop { 0% { opacity: 0; transform: scale(0.85) translateY(20px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
.modal-icon { width: 72px; height: 72px; border-radius: 50%; background: var(--danger-bg); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 18px; }
.modal-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 10px; color: var(--text-primary); }
.modal-text { font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.7; }
.modal-text strong { color: var(--primary); background: var(--primary-bg); padding: 3px 10px; border-radius: 6px; font-family: var(--font-mono); font-weight: 700; display: inline-block; margin: 4px 0; }
.modal-warning { color: var(--danger); font-weight: 700; display: block; margin-top: 8px; font-size: 0.8rem; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.modal-btn { padding: 11px 26px; border-radius: var(--radius-md); font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 8px; }
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: var(--border-strong); transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5); }

.empty-state { padding: 60px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 14px; color: var(--primary); }
.empty-state p { font-weight: 600; font-size: 0.9rem; }

@media (max-width: 1400px) { .dp-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1200px) { .stats-grid-8 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) {
    .stats-grid-8 { grid-template-columns: repeat(2, 1fr); }
    .dp-grid { grid-template-columns: repeat(2, 1fr); }
    .pending-partial-grid { grid-template-columns: repeat(2, 1fr); }
    .visit-payment-summary { grid-template-columns: repeat(2, 1fr); }
    .patient-info-header { flex-direction: column; align-items: stretch; }
    .patient-number-badge { align-self: flex-start; }
    .patient-summary-badges { align-self: stretch; }
    .custom-date-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
    .page-header { padding: 18px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    .stats-grid-8 { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .stat-card .card-value { font-size: 1.1rem; }
    .dp-grid { grid-template-columns: 1fr 1fr; }
    .pending-partial-grid { grid-template-columns: 1fr 1fr; }
    .visit-payment-summary { grid-template-columns: 1fr 1fr; padding: 10px 14px; }
    .discount-premium-card { padding: 14px; }
    .data-table { font-size: 0.72rem; }
    .visit-header-row1 { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .visit-header-row2 { grid-template-columns: repeat(2, 1fr); padding: 12px 14px; }
    .visit-header-row3 { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .visit-action-buttons { width: 100%; }
    .visit-action-btn { flex: 1; justify-content: center; }
    .patient-number-badge { font-size: 0.6rem; padding: 4px 10px; }
    .patient-footer { padding: 12px 16px; flex-direction: column; align-items: flex-start; }
    .patient-footer .footer-right { width: 100%; }
    .custom-date-row { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .stats-grid-8 { grid-template-columns: 1fr; }
    .dp-grid { grid-template-columns: 1fr; }
    .pending-partial-grid { grid-template-columns: 1fr; }
    .visit-payment-summary { grid-template-columns: 1fr; }
    .visit-header-row2 { grid-template-columns: 1fr; }
}
@media print {
    .quick-filters-card, .patient-filter-card, .btn-header, .visit-action-buttons, .action-buttons, .modal-overlay, .header-scroll-buttons { display: none !important; }
    .page-header { background: white !important; color: black !important; }
    .stat-card { break-inside: avoid; }
    .patient-group-card { break-inside: avoid; }
}
</style>
</head>
<body>

<main class="main-content">

    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= $alert_message ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report V59
                <span class="branch-tag" style="background:rgba(255,255,255,0.25);"><i class="fas fa-shield-alt"></i> ADMIN</span>
                <span class="branch-tag live-tag"><i class="fas fa-check-circle"></i> PENDING FIXED</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                <span class="branch-tag"><i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($total_revenue, 0) ?></span>
                <span class="branch-tag"><i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Payments</span>
                <span class="branch-tag filter-tag"><i class="fas fa-clock"></i> <?= htmlspecialchars($date_label) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header"><i class="fas fa-print"></i> Print</button>
            <a href="/dispensary_system/frontend/pages/admin/audit/dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ✅ QUICK FILTERS CARD - JUU KABLA YA CARDS -->
    <div class="quick-filters-card">
        <div class="qfc-header">
            <div class="qfc-title">
                <span class="qfc-icon"><i class="fas fa-bolt"></i></span>
                Quick Date Filters
                <span class="live-search-active"><i class="fas fa-filter"></i> FILTER</span>
            </div>
            <span class="qfc-count">
                <i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($date_label) ?>
            </span>
        </div>

        <div class="filter-section-title">
            <i class="fas fa-clock"></i> Chagua Kipindi
        </div>
        <div class="quick-filters">
            <a href="?branch=<?= $selected_branch_id ?>&quick=today&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=yesterday&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === 'yesterday' ? 'active' : '' ?>"><i class="fas fa-calendar-minus"></i> Yesterday</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=1d&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === '1d' ? 'active' : '' ?>"><i class="fas fa-clock"></i> 1D</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=1w&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 1W</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=1m&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=3m&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=6m&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 6M</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=1y&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> 1Y</a>
            <a href="?branch=<?= $selected_branch_id ?>&quick=all&patient_filter=<?= $patient_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?>" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>"><i class="fas fa-infinity"></i> All</a>
            <a href="#" onclick="toggleCustomFilter(); return false;" class="quick-btn <?= $quick_filter === 'custom' ? 'active' : '' ?>" id="customFilterBtn">
                <i class="fas fa-calendar-check"></i> Custom
            </a>
        </div>

        <!-- CUSTOM DATE ROW -->
        <div class="custom-date-row" id="customFilterSection" style="display: <?= $quick_filter === 'custom' ? 'grid' : 'none' ?>;">
            <form method="GET" action="" style="display: contents;">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="hidden" name="quick" value="custom">
                <input type="hidden" name="patient_filter" value="<?= htmlspecialchars($patient_filter) ?>">
                <?php if ($payment_method !== 'all'): ?>
                    <input type="hidden" name="payment_method" value="<?= htmlspecialchars($payment_method) ?>">
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                
                <div>
                    <label style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);display:block;margin-bottom:4px;text-transform:uppercase;">📅 From</label>
                    <input type="date" name="date_from" id="customDateFrom" value="<?= htmlspecialchars($date_from) ?>" max="<?= date('Y-m-d') ?>" required
                           style="width:100%;padding:9px 12px;border-radius:8px;border:2px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.82rem;font-weight:600;font-family:var(--font-mono);">
                </div>
                <div>
                    <label style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);display:block;margin-bottom:4px;text-transform:uppercase;">📅 To</label>
                    <input type="date" name="date_to" id="customDateTo" value="<?= htmlspecialchars($date_to) ?>" max="<?= date('Y-m-d') ?>" required
                           style="width:100%;padding:9px 12px;border-radius:8px;border:2px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.82rem;font-weight:600;font-family:var(--font-mono);">
                </div>
                <div class="auto-filter-badge">
                    <i class="fas fa-bolt"></i> Auto-Apply
                </div>
                <button type="button" class="quick-btn" onclick="resetCustomFilter();" style="height: 44px; padding: 0 20px;">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </form>
        </div>
    </div>

    <!-- 8 CARDS -->
    <div class="stats-grid-8">
        <div class="stat-card revenue">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                <span class="card-badge"><span class="pulse-dot"></span>LIVE</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-coins"></i> Total Revenue</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($total_revenue, 0) ?></div>
            </div>
            <div class="card-footer"><i class="fas fa-info-circle"></i> Payments + OTC</div>
        </div>

        <div class="stat-card payments">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
                <span class="card-badge"><i class="fas fa-star"></i> +PREM</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-hand-holding-usd"></i> Patient Payments</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($patient_bills_revenue_rounded, 0) ?></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-receipt"></i>
                Payments: <span class="highlight"><?= number_format($patient_bills_count) ?></span>
                <?php if ($patient_discounts > 0): ?>
                    <span class="discount-badge"><i class="fas fa-tag"></i> <?= number_format($patient_discounts, 0) ?></span>
                <?php endif; ?>
                <?php if ($patient_premiums > 0): ?>
                    <span class="premium-badge"><i class="fas fa-star"></i> <?= number_format($patient_premiums, 0) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card prescription">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-prescription"></i></div>
                <span class="card-badge"><i class="fas fa-pills"></i> Rx GROSS</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-prescription-bottle-medical"></i> Prescription (Gross)</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($prescription_gross, 0) ?></div>
            </div>
            <div class="card-footer"><i class="fas fa-list"></i> Items: <span class="highlight"><?= number_format($prescription_count) ?></span></div>
        </div>

        <div class="stat-card otc">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-cash-register"></i></div>
                <span class="card-badge"><i class="fas fa-store"></i> OTC</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-shopping-cart"></i> OTC Sales</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($otc_revenue, 0) ?></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-receipt"></i>
                Sales: <span class="highlight"><?= number_format($otc_count) ?></span>
            </div>
        </div>

        <div class="stat-card consultation">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-stethoscope"></i></div>
                <span class="card-badge"><i class="fas fa-user-md"></i> CLINICAL</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-notes-medical"></i> Clinical Services</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($clinical_services_revenue, 0) ?></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list"></i>
                Cons: <span class="highlight"><?= number_format($consultation_count) ?></span> •
                Proc: <span class="highlight"><?= number_format($procedure_count) ?></span> •
                Equip: <span class="highlight"><?= number_format($equipment_count) ?></span>
            </div>
        </div>

        <div class="stat-card lab">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-flask"></i></div>
                <span class="card-badge"><i class="fas fa-vial"></i> LAB</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-microscope"></i> Lab Tests</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($lab_revenue, 0) ?></div>
            </div>
            <div class="card-footer"><i class="fas fa-check-circle"></i> Tests: <span class="highlight"><?= number_format($lab_count) ?></span></div>
        </div>

        <div class="stat-card expenses">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-receipt"></i></div>
                <span class="card-badge"><i class="fas fa-arrow-down"></i> OUT</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-wallet"></i> Total Expenses</div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format($total_expenses, 0) ?></div>
            </div>
            <div class="card-footer"><i class="fas fa-list"></i> Records: <span class="highlight"><?= number_format($expenses_count) ?></span></div>
        </div>

        <div class="stat-card profit <?= $net_profit < 0 ? 'loss' : '' ?>">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i></div>
                <span class="card-badge"><i class="fas fa-<?= $net_profit >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> <?= $net_profit >= 0 ? 'PROFIT' : 'LOSS' ?></span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-balance-scale"></i> <?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
                <div class="card-value"><span class="currency"><?= $currency ?></span><?= number_format(abs($net_profit), 0) ?></div>
            </div>
            <div class="card-footer"><i class="fas fa-percentage"></i> Margin: <span class="highlight"><?= $profit_percentage ?>%</span></div>
        </div>
    </div>

    <!-- PENDING & PARTIAL CARD -->
    <div class="pending-partial-card">
        <div class="pending-partial-header">
            <div class="pending-partial-title">
                <span class="title-icon"><i class="fas fa-hourglass-half"></i></span>
                Pending & Partial Overview
                <span style="font-size:0.65rem;background:var(--warning-bg);color:var(--warning);padding:3px 10px;border-radius:6px;font-weight:800;border:1px solid rgba(217,119,6,0.25);">DUE</span>
            </div>
            <div class="pending-partial-total">
                <span class="total-label"><i class="fas fa-calculator"></i> Total Pending & Partial</span>
                <span class="total-currency"><?= $currency ?></span>
                <span class="total-value"><?= number_format($total_pending, 0) ?></span>
            </div>
        </div>
        <div class="pending-partial-grid">
            <div class="pending-partial-item bills-pending">
                <div class="pp-top">
                    <span class="pp-icon"><i class="fas fa-file-invoice"></i></span>
                    <span class="pp-count-badge"><i class="fas fa-hashtag"></i> <?= number_format($pending_bills_count) ?> Bills</span>
                </div>
                <div class="pp-label">Bills Pending</div>
                <div class="pp-value"><span class="pp-currency"><?= $currency ?></span><?= number_format($pending_bills_amount, 0) ?></div>
                <div class="pp-sub"><i class="fas fa-info-circle"></i> Hazijalipwa kabisa</div>
            </div>
            <div class="pending-partial-item bills-partial">
                <div class="pp-top">
                    <span class="pp-icon"><i class="fas fa-hourglass-half"></i></span>
                    <span class="pp-count-badge"><i class="fas fa-hashtag"></i> <?= number_format($partial_bills_count) ?> Bills</span>
                </div>
                <div class="pp-label">Partial Remaining</div>
                <div class="pp-value"><span class="pp-currency"><?= $currency ?></span><?= number_format($partial_remaining, 0) ?></div>
                <div class="pp-sub"><i class="fas fa-info-circle"></i> Mabaki ya bills zilizolipwa kiasi</div>
            </div>
            <div class="pending-partial-item otc-pending">
                <div class="pp-top">
                    <span class="pp-icon"><i class="fas fa-cash-register"></i></span>
                    <span class="pp-count-badge"><i class="fas fa-hashtag"></i> <?= number_format($pending_otc_count) ?> Sales</span>
                </div>
                <div class="pp-label">OTC Pending</div>
                <div class="pp-value"><span class="pp-currency"><?= $currency ?></span><?= number_format($pending_otc_amount, 0) ?></div>
                <div class="pp-sub"><i class="fas fa-info-circle"></i> OTC sales hazijalipwa</div>
            </div>
            <div class="pending-partial-item otc-partial">
                <div class="pp-top">
                    <span class="pp-icon"><i class="fas fa-hourglass-start"></i></span>
                    <span class="pp-count-badge"><i class="fas fa-hashtag"></i> <?= number_format($partial_otc_count) ?> Sales</span>
                </div>
                <div class="pp-label">OTC Partial</div>
                <div class="pp-value"><span class="pp-currency"><?= $currency ?></span><?= number_format($partial_otc_amount, 0) ?></div>
                <div class="pp-sub"><i class="fas fa-info-circle"></i> OTC sales zilizolipwa kiasi</div>
            </div>
        </div>
    </div>

    <!-- DISCOUNT & PREMIUM CARD -->
    <div class="discount-premium-card">
        <div class="dp-header">
            <div class="dp-title">
                <i class="fas fa-tags"></i>
                Discount & Premium Breakdown
                <span style="font-size:0.65rem;background:var(--primary-bg);color:var(--primary);padding:2px 8px;border-radius:6px;font-weight:700;">Total: <?= $currency ?> <?= number_format($total_discounts + $total_premiums, 0) ?></span>
            </div>
            <div style="font-size:0.65rem;color:var(--text-secondary);font-weight:600;">
                <i class="fas fa-info-circle"></i> Pharmacy + Cashier
            </div>
        </div>
        <div class="dp-grid">
            <div class="dp-item pharmacy-disc">
                <div class="dp-label"><i class="fas fa-prescription-bottle-medical"></i> Pharmacy Discount</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($pharmacy_discounts, 0) ?></div>
            </div>
            <div class="dp-item cashier-disc">
                <div class="dp-label"><i class="fas fa-cash-register"></i> Cashier Discount</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($cashier_discounts, 0) ?></div>
            </div>
            <div class="dp-item total-disc">
                <div class="dp-label"><i class="fas fa-tag"></i> Total Discount</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($total_discounts, 0) ?></div>
            </div>
            <div class="dp-item pharmacy-prem">
                <div class="dp-label"><i class="fas fa-prescription-bottle-medical"></i> Pharmacy Premium</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($pharmacy_premiums, 0) ?></div>
            </div>
            <div class="dp-item cashier-prem">
                <div class="dp-label"><i class="fas fa-cash-register"></i> Cashier Premium</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($cashier_premiums, 0) ?></div>
            </div>
            <div class="dp-item total-prem">
                <div class="dp-label"><i class="fas fa-star"></i> Total Premium</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($total_premiums, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- ✅ PATIENT FILTER CARD - CHINI YA CARDS ZOTE -->
    <div class="patient-filter-card">
        <div class="pfc-header">
            <div class="pfc-title">
                <span class="pfc-icon"><i class="fas fa-users"></i></span>
                Patients Filter & Search
                <span class="live-search-active"><i class="fas fa-bolt"></i> LIVE</span>
            </div>
            <span class="pfc-count">
                <i class="fas fa-users"></i> <?= count($patient_groups) ?> Patient<?= count($patient_groups) != 1 ? 's' : '' ?> Found
            </span>
        </div>

        <!-- PATIENT FILTER BUTTONS -->
        <div class="patient-filter-section">
            <div class="patient-filter-label">
                <i class="fas fa-filter"></i> Patients Filter:
            </div>
            <div class="patient-filter-buttons">
                <a href="?branch=<?= $selected_branch_id ?>&quick=<?= $quick_filter ?>&patient_filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?><?= $quick_filter === 'custom' ? '&date_from=' . $date_from . '&date_to=' . $date_to : '' ?>" 
                   class="patient-filter-btn all <?= $patient_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> All
                    <span class="filter-count"><?= count($patient_groups) ?></span>
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=<?= $quick_filter ?>&patient_filter=partial<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?><?= $quick_filter === 'custom' ? '&date_from=' . $date_from . '&date_to=' . $date_to : '' ?>" 
                   class="patient-filter-btn partial <?= $patient_filter === 'partial' ? 'active' : '' ?>">
                    <i class="fas fa-hourglass-half"></i> Partial
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=<?= $quick_filter ?>&patient_filter=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?><?= $quick_filter === 'custom' ? '&date_from=' . $date_from . '&date_to=' . $date_to : '' ?>" 
                   class="patient-filter-btn pending <?= $patient_filter === 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=<?= $quick_filter ?>&patient_filter=paid<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $payment_method !== 'all' ? '&payment_method=' . urlencode($payment_method) : '' ?><?= $quick_filter === 'custom' ? '&date_from=' . $date_from . '&date_to=' . $date_to : '' ?>" 
                   class="patient-filter-btn paid <?= $patient_filter === 'paid' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Paid
                </a>
            </div>
        </div>

        <!-- SEARCH FORM -->
        <form method="GET" id="searchFilterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
            <input type="hidden" name="patient_filter" value="<?= htmlspecialchars($patient_filter) ?>">
            <?php if ($quick_filter === 'custom'): ?>
                <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            <?php endif; ?>
            
            <div style="display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:end;flex-wrap:wrap;">
                <div class="search-input-wrapper" id="liveSearchWrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" id="liveSearchInput" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="🔍 Type to search live: Name, Phone, Patient ID, Bill No, Visit No..." 
                           autocomplete="off">
                    <a href="#" id="liveClearBtn" class="clear-search" title="Clear Search" style="<?= empty($search) ? 'display:none;' : '' ?>">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
                
                <div>
                    <select name="payment_method" id="paymentMethodSelect" style="padding:10px 14px;border-radius:10px;border:2px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.82rem;font-weight:600;height:44px;">
                        <option value="all" <?= $payment_method === 'all' ? 'selected' : '' ?>>💳 All Methods</option>
                        <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>💵 Cash</option>
                        <option value="m-pesa" <?= $payment_method === 'm-pesa' ? 'selected' : '' ?>>📱 M-Pesa</option>
                        <option value="airtel_money" <?= $payment_method === 'airtel_money' ? 'selected' : '' ?>>📱 Airtel Money</option>
                        <option value="tigo_pesa" <?= $payment_method === 'tigo_pesa' ? 'selected' : '' ?>>📱 Tigo Pesa</option>
                        <option value="halopesa" <?= $payment_method === 'halopesa' ? 'selected' : '' ?>>📱 Halopesa</option>
                        <option value="bank" <?= $payment_method === 'bank' ? 'selected' : '' ?>>🏦 Bank</option>
                        <option value="card" <?= $payment_method === 'card' ? 'selected' : '' ?>>💳 Card</option>
                        <option value="insurance" <?= $payment_method === 'insurance' ? 'selected' : '' ?>>🏥 Insurance</option>
                        <option value="other" <?= $payment_method === 'other' ? 'selected' : '' ?>>📋 Other</option>
                    </select>
                </div>
                
                <div class="auto-filter-badge">
                    <i class="fas fa-bolt"></i> Auto-Filter
                </div>
            </div>
            
            <div style="margin-top:10px;padding:8px 14px;background:var(--primary-bg);border-radius:8px;border-left:4px solid var(--primary);font-size:0.72rem;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px;">
                <i class="fas fa-info-circle"></i>
                <span>Results: <strong id="liveResultsCount"><?= count($patient_groups) ?> patients • <?= count($otc_sales_list) ?> OTC sales</strong></span>
                <?php if (!empty($search)): ?>
                <span style="margin-left:auto;background:var(--primary);color:white;padding:2px 10px;border-radius:10px;font-size:0.65rem;">
                    Searching: "<?= htmlspecialchars($search) ?>"
                </span>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- PATIENT-GROUPED SECTIONS -->
    <?php if (count($patient_groups) > 0): ?>
        <?php foreach ($patient_groups as $patient_index => $patient): 
            $patient_initials = '';
            $name_parts = explode(' ', trim($patient['patient_name'] ?? 'N/A'));
            if (count($name_parts) >= 2) {
                $patient_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
            } else {
                $patient_initials = strtoupper(substr($patient['patient_name'] ?? 'NA', 0, 2));
            }
            $visit_count = count($patient['visits']);
            
            $card_class = '';
            $status_badge_html = '';
            if ($patient['has_pending']) {
                $card_class = 'has-pending';
                $status_badge_html = '<span class="pending-badge"><i class="fas fa-clock"></i> PENDING</span>';
            } elseif ($patient['has_partial']) {
                $card_class = 'has-partial';
                $status_badge_html = '<span class="partial-badge"><i class="fas fa-hourglass-half"></i> PARTIAL</span>';
            }
        ?>
        <div class="patient-group-card <?= $card_class ?>" id="patientCard_<?= $patient_index ?>">
            
            <div class="patient-info-header" onclick="togglePatientCard(<?= $patient_index ?>)">
                
                <div class="patient-number-badge">
                    <i class="fas fa-user-tag"></i>
                    PATIENT #<?= $patient_index + 1 ?> / <?= count($patient_groups) ?>
                </div>
                
                <div class="patient-identity">
                    <div class="patient-avatar"><?= htmlspecialchars($patient_initials) ?></div>
                    <div class="patient-details">
                        <div class="patient-name">
                            <i class="fas fa-user-circle" style="color:#93C5FD;"></i>
                            <?= highlightSearchTerm($patient['patient_name'] ?? 'N/A', $search) ?>
                            <?= $status_badge_html ?>
                        </div>
                        <div class="patient-meta">
                            <?php if (!empty($patient['patient_code'])): ?>
                                <span><i class="fas fa-id-card"></i> <?= highlightSearchTerm($patient['patient_code'], $search) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($patient['patient_phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?= highlightSearchTerm($patient['patient_phone'], $search) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($patient['gender'])): ?>
                                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars(ucfirst($patient['gender'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="patient-summary-badges">
                    <div class="summary-badge visits-count">
                        <span class="badge-label">Visits</span>
                        <span class="badge-value"><?= $visit_count ?></span>
                    </div>
                    <div class="summary-badge billed">
                        <span class="badge-label">Total Billed</span>
                        <span class="badge-value"><?= $currency ?> <?= number_format($patient['total_billed'], 0) ?></span>
                    </div>
                    <div class="summary-badge paid">
                        <span class="badge-label">Total Paid</span>
                        <span class="badge-value"><?= $currency ?> <?= number_format($patient['total_paid'], 0) ?></span>
                    </div>
                    <div class="summary-badge balance">
                        <span class="badge-label">Balance</span>
                        <span class="badge-value"><?= $currency ?> <?= number_format($patient['total_balance'], 0) ?></span>
                    </div>
                </div>
                
                <button type="button" class="patient-toggle-btn" id="toggleBtn_<?= $patient_index ?>" 
                        onclick="event.stopPropagation(); togglePatientCard(<?= $patient_index ?>);" 
                        title="Fungua / Funga">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <div class="patient-body-container" id="patientBody_<?= $patient_index ?>">
                
            <?php foreach ($patient['visits'] as $visit_index => $visit): 
                $visit_status = strtolower($visit['visit_status'] ?? 'pending');
                $visit_total_rounded = roundTo50($visit['visit_total']);
                
                $v_pharm_disc = (float)($visit['pharmacy_discount'] ?? 0);
                $v_cash_disc = (float)($visit['cashier_discount'] ?? 0);
                $v_pharm_prem = (float)($visit['pharmacy_premium'] ?? 0);
                $v_cash_prem = (float)($visit['cashier_premium'] ?? 0);
                
                $v_paid_count = (int)($visit['paid_items_count'] ?? 0);
                $v_paid_total = (float)($visit['paid_items_total'] ?? 0);
                $v_pending_count = (int)($visit['pending_items_count'] ?? 0);
                $v_pending_total = (float)($visit['pending_items_total'] ?? 0);
                $v_visit_paid = (float)($visit['visit_paid'] ?? 0);
                $v_visit_balance = (float)($visit['visit_balance'] ?? 0);
            ?>
            <div class="visit-section">
                
                <div class="visit-header-row1">
                    <div class="visit-info-left">
                        <span class="visit-number-badge">
                            <i class="fas fa-notes-medical"></i> <?= highlightSearchTerm($visit['visit_number'] ?? 'N/A', $search) ?>
                        </span>
                        <span class="visit-status-badge <?= $visit_status ?>">
                            <i class="fas fa-<?= $visit_status === 'completed' ? 'check-circle' : ($visit_status === 'cancelled' ? 'times-circle' : 'clock') ?>"></i>
                            <?= strtoupper($visit_status) ?>
                        </span>
                        <span class="visit-date-badge">
                            <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($visit['visit_date'] ?? 'now')) ?>
                        </span>
                    </div>
                    <div class="visit-info-right">
                        <span class="visit-staff-chip">
                            <i class="fas fa-user-md"></i>
                            <span class="staff-label">Doctor:</span>
                            <span class="staff-name"><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span>
                        </span>
                        <span class="visit-staff-chip">
                            <i class="fas fa-user-tie"></i>
                            <span class="staff-label">Reception:</span>
                            <span class="staff-name"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
                        </span>
                    </div>
                </div>

                <div class="visit-payment-summary">
                    <div class="vps-item paid-items">
                        <div class="vps-label"><i class="fas fa-check-circle"></i> Items Paid</div>
                        <div class="vps-value"><?= $v_paid_count ?><span class="vps-currency">items</span></div>
                        <div class="vps-sub"><i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format(roundTo50($v_paid_total), 0) ?></div>
                    </div>
                    <div class="vps-item pending-items">
                        <div class="vps-label"><i class="fas fa-clock"></i> Items Pending</div>
                        <div class="vps-value"><?= $v_pending_count ?><span class="vps-currency">items</span></div>
                        <div class="vps-sub"><i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format(roundTo50($v_pending_total), 0) ?></div>
                    </div>
                    <div class="vps-item visit-paid">
                        <div class="vps-label"><i class="fas fa-hand-holding-usd"></i> Visit Paid</div>
                        <div class="vps-value"><span class="vps-currency"><?= $currency ?></span><?= number_format(roundTo50($v_visit_paid), 0) ?></div>
                        <div class="vps-sub"><i class="fas fa-info-circle"></i> <?= number_format($v_paid_count + $v_pending_count) ?> total items</div>
                    </div>
                    <div class="vps-item visit-balance">
                        <div class="vps-label"><i class="fas fa-balance-scale"></i> Balance</div>
                        <div class="vps-value"><span class="vps-currency"><?= $currency ?></span><?= number_format(roundTo50($v_visit_balance), 0) ?></div>
                        <div class="vps-sub"><i class="fas fa-info-circle"></i> <?= $v_visit_balance <= 0 ? 'Fully Paid ✅' : 'Inabaki kulipwa' ?></div>
                    </div>
                </div>

                <div class="visit-header-row2">
                    <div class="visit-dp-card pharmacy-disc">
                        <div class="dp-label"><i class="fas fa-prescription-bottle-medical"></i> Pharmacy Discount</div>
                        <div class="dp-value"><?= $currency ?> <?= number_format($v_pharm_disc, 0) ?></div>
                    </div>
                    <div class="visit-dp-card cashier-disc">
                        <div class="dp-label"><i class="fas fa-cash-register"></i> Cashier Discount</div>
                        <div class="dp-value"><?= $currency ?> <?= number_format($v_cash_disc, 0) ?></div>
                    </div>
                    <div class="visit-dp-card pharmacy-prem">
                        <div class="dp-label"><i class="fas fa-star"></i> Pharmacy Premium</div>
                        <div class="dp-value"><?= $currency ?> <?= number_format($v_pharm_prem, 0) ?></div>
                    </div>
                    <div class="visit-dp-card cashier-prem">
                        <div class="dp-label"><i class="fas fa-crown"></i> Cashier Premium</div>
                        <div class="dp-value"><?= $currency ?> <?= number_format($v_cash_prem, 0) ?></div>
                    </div>
                    <div class="visit-dp-card visit-total">
                        <div class="dp-label"><i class="fas fa-calculator"></i> Visit Total</div>
                        <div class="dp-value"><?= $currency ?> <?= number_format($visit_total_rounded, 0) ?></div>
                    </div>
                </div>

                <div class="visit-header-row3">
                    <div class="visit-diagnosis-box">
                        <?php if (!empty($visit['diagnosis'])): ?>
                            <i class="fas fa-stethoscope"></i>
                            <div class="diagnosis-content">
                                <span class="diagnosis-label">Diagnosis</span>
                                <div class="diagnosis-text"><?= htmlspecialchars($visit['diagnosis']) ?></div>
                                <?php if (!empty($visit['disease_code'])): ?>
                                    <span class="diagnosis-code"><i class="fas fa-barcode"></i> <?= htmlspecialchars($visit['disease_code']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="visit-no-diagnosis"><i class="fas fa-info-circle"></i> No diagnosis recorded</div>
                        <?php endif; ?>
                    </div>
                    <div class="visit-action-buttons">
                        <a href="view_visit.php?id=<?= (int)$visit['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="visit-action-btn view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button type="button" class="visit-action-btn delete" onclick="confirmDeleteVisit(<?= (int)$visit['visit_id'] ?>, '<?= htmlspecialchars(addslashes($visit['visit_number'] ?? 'N/A')) ?>')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <div class="visit-items-container">
                    <div class="items-table-header">
                        <span class="items-title">
                            <i class="fas fa-list-ul"></i> 
                            Bill Items (<?= count($visit['items']) ?>)
                            <span style="color:var(--success);font-weight:800;font-size:0.68rem;background:var(--success-bg);padding:2px 8px;border-radius:6px;margin-left:6px;">✅ <?= $v_paid_count ?> Paid</span>
                            <span style="color:var(--danger);font-weight:800;font-size:0.68rem;background:var(--danger-bg);padding:2px 8px;border-radius:6px;margin-left:4px;">⏳ <?= $v_pending_count ?> Pending</span>
                        </span>
                        <div class="items-scroll-buttons">
                            <button type="button" class="scroll-btn" onclick="scrollItemsTable('itemsWrapper_<?= $patient_index ?>_<?= $visit_index ?>', 'left')"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" class="scroll-btn" onclick="scrollItemsTable('itemsWrapper_<?= $patient_index ?>_<?= $visit_index ?>', 'right')"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="table-scroll-wrapper" id="itemsWrapper_<?= $patient_index ?>_<?= $visit_index ?>">
                        <table class="data-table" style="min-width:900px;">
                            <thead>
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:right;">Unit Price</th>
                                    <th style="text-align:right;">Total</th>
                                    <th style="text-align:right;">Discount</th>
                                    <th style="text-align:right;">Final Price</th>
                                    <th style="text-align:center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $item_counter = 1;
                                $categories_order = [
                                    'consultation' => ['label' => 'Consultation', 'icon' => 'fa-stethoscope', 'class' => 'consultation'],
                                    'lab_test' => ['label' => 'Lab Tests', 'icon' => 'fa-flask', 'class' => 'lab_test'],
                                    'medication' => ['label' => 'Medications / Prescriptions', 'icon' => 'fa-pills', 'class' => 'medication'],
                                    'procedure' => ['label' => 'Procedures', 'icon' => 'fa-procedures', 'class' => 'procedure'],
                                    'equipment' => ['label' => 'Medical Equipment', 'icon' => 'fa-tools', 'class' => 'equipment'],
                                    'registration' => ['label' => 'Registration', 'icon' => 'fa-user-plus', 'class' => 'registration'],
                                    'other' => ['label' => 'Other Items', 'icon' => 'fa-box', 'class' => 'other']
                                ];

                                $has_any_items = false;
                                foreach ($categories_order as $cat_key => $cat_info):
                                    $cat_items = $visit['items_by_category'][$cat_key] ?? [];
                                    if (empty($cat_items)) continue;
                                    $has_any_items = true;
                                    
                                    $cat_paid_count = 0;
                                    $cat_pending_count = 0;
                                    $cat_total = 0;
                                    foreach ($cat_items as $ci) {
                                        $ci_status = strtolower($ci['item_status'] ?? 'pending');
                                        $ci_amount = (float)($ci['total_price'] ?? 0) - (float)($ci['discount_amount'] ?? 0);
                                        $cat_total += $ci_amount;
                                        if ($ci_status === 'paid') $cat_paid_count++;
                                        else $cat_pending_count++;
                                    }
                                ?>
                                    <tr class="category-group-row <?= $cat_info['class'] ?>">
                                        <td colspan="9">
                                            <span class="category-icon"><i class="fas <?= $cat_info['icon'] ?>"></i></span>
                                            <?= htmlspecialchars($cat_info['label']) ?> (<?= count($cat_items) ?> items)
                                            
                                            <span class="category-breakdown">
                                                <?php if ($cat_paid_count > 0): ?>
                                                    <span class="cb-paid">✅ <?= $cat_paid_count ?> Paid</span>
                                                <?php endif; ?>
                                                <?php if ($cat_pending_count > 0): ?>
                                                    <span class="cb-pending">⏳ <?= $cat_pending_count ?> Pending</span>
                                                <?php endif; ?>
                                            </span>
                                            
                                            <span class="category-total"><?= $currency ?> <?= number_format(roundTo50($cat_total), 0) ?></span>
                                        </td>
                                    </tr>
                                    <?php foreach ($cat_items as $item): 
                                        $item_status = strtolower($item['item_status'] ?? 'pending');
                                        $item_status_class = 'pending';
                                        $item_status_icon = 'fa-clock';
                                        $row_class = 'item-pending';
                                        switch ($item_status) {
                                            case 'paid': 
                                                $item_status_class = 'paid'; 
                                                $item_status_icon = 'fa-check-circle'; 
                                                $row_class = 'item-paid';
                                                break;
                                            case 'pending': 
                                                $item_status_class = 'pending'; 
                                                $item_status_icon = 'fa-clock'; 
                                                $row_class = 'item-pending';
                                                break;
                                            case 'cancelled': 
                                                $item_status_class = 'cancelled'; 
                                                $item_status_icon = 'fa-times-circle'; 
                                                $row_class = '';
                                                break;
                                        }
                                    ?>
                                        <tr class="<?= $row_class ?>">
                                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_counter++ ?></td>
                                            <td><div style="font-weight:700;font-size:0.78rem;"><?= highlightSearchTerm($item['item_name'] ?? 'N/A', $search) ?></div></td>
                                            <td>
                                                <span style="font-size:0.6rem;background:var(--bg-body);padding:2px 8px;border-radius:5px;font-weight:700;text-transform:uppercase;">
                                                    <?= htmlspecialchars($item['item_type'] ?? 'item') ?>
                                                </span>
                                            </td>
                                            <td style="text-align:center;">
                                                <span style="font-family:var(--font-mono);font-weight:800;font-size:0.78rem;background:var(--primary-bg);color:var(--primary);padding:3px 8px;border-radius:5px;">
                                                    <?= (int)($item['quantity'] ?? 1) ?>
                                                </span>
                                            </td>
                                            <td class="money-cell" style="font-size:0.75rem;">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['unit_price'] ?? 0), 0) ?>
                                            </td>
                                            <td class="money-cell" style="font-size:0.75rem;">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['total_price'] ?? 0), 0) ?>
                                            </td>
                                            <td class="money-cell warning" style="font-size:0.75rem;">
                                                <?php if ((float)($item['discount_amount'] ?? 0) > 0): ?>
                                                    -<span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['discount_amount'] ?? 0), 0) ?>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="money-cell" style="font-size:0.78rem;font-weight:900;">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['total_price'] ?? 0) - (float)($item['discount_amount'] ?? 0), 0) ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <span class="status-badge <?= $item_status_class ?>">
                                                    <i class="fas <?= $item_status_icon ?>"></i> <?= strtoupper($item_status) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>

                                <?php if (!$has_any_items): ?>
                                    <tr>
                                        <td colspan="9">
                                            <div class="empty-state" style="padding:30px 20px;">
                                                <i class="fas fa-inbox" style="font-size:2rem;"></i>
                                                <p style="font-size:0.8rem;">No items for this visit</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="total-row">
                                    <td colspan="7" style="text-align:right;font-weight:900;font-size:0.82rem;color:var(--primary);">
                                        <i class="fas fa-calculator"></i> VISIT TOTAL
                                    </td>
                                    <td style="text-align:right;font-weight:900;font-size:0.95rem;color:var(--primary);font-family:var(--font-mono);">
                                        <?= $currency ?> <?= number_format($visit_total_rounded, 0) ?>
                                    </td>
                                    <td style="text-align:center;font-weight:800;font-size:0.68rem;">
                                        <span style="color:var(--success);">✅ <?= $v_paid_count ?></span> / <span style="color:var(--danger);">⏳ <?= $v_pending_count ?></span>
                                    </td>
                                </tr>
                                <tr style="background:linear-gradient(135deg, rgba(5,150,105,0.1), rgba(5,150,105,0.05));">
                                    <td colspan="7" style="text-align:right;font-weight:800;font-size:0.75rem;color:var(--success);">
                                        <i class="fas fa-check-circle"></i> PAID TOTAL
                                    </td>
                                    <td style="text-align:right;font-weight:900;font-size:0.85rem;color:var(--success);font-family:var(--font-mono);">
                                        <?= $currency ?> <?= number_format(roundTo50($v_visit_paid), 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge paid" style="font-size:0.55rem;"><i class="fas fa-check"></i> PAID</span>
                                    </td>
                                </tr>
                                <?php if ($v_visit_balance > 0): ?>
                                <tr style="background:linear-gradient(135deg, rgba(220,38,38,0.1), rgba(220,38,38,0.05));">
                                    <td colspan="7" style="text-align:right;font-weight:800;font-size:0.75rem;color:var(--danger);">
                                        <i class="fas fa-exclamation-circle"></i> PENDING BALANCE
                                    </td>
                                    <td style="text-align:right;font-weight:900;font-size:0.85rem;color:var(--danger);font-family:var(--font-mono);">
                                        <?= $currency ?> <?= number_format(roundTo50($v_visit_balance), 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge pending" style="font-size:0.55rem;"><i class="fas fa-clock"></i> DUE</span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            </div>

            <div class="patient-footer">
                <div class="footer-left">
                    <span class="footer-end-label">
                        <i class="fas fa-user-check"></i>
                        END OF <?= htmlspecialchars(strtoupper($patient['patient_name'] ?? 'N/A')) ?>
                    </span>
                    <span class="footer-visit-count">
                        <i class="fas fa-notes-medical"></i>
                        <?= $visit_count ?> Visit<?= $visit_count != 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="footer-right">
                    <span class="footer-stat">
                        <i class="fas fa-file-invoice" style="color:var(--primary);"></i>
                        Billed: <strong><?= $currency ?> <?= number_format($patient['total_billed'], 0) ?></strong>
                    </span>
                    <span class="footer-stat success">
                        <i class="fas fa-check-circle" style="color:var(--success);"></i>
                        Paid: <strong><?= $currency ?> <?= number_format($patient['total_paid'], 0) ?></strong>
                    </span>
                    <?php if ($patient['total_balance'] > 0): ?>
                    <span class="footer-stat danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Balance: <strong><?= $currency ?> <?= number_format($patient['total_balance'], 0) ?></strong>
                    </span>
                    <?php else: ?>
                    <span class="footer-stat success">
                        <i class="fas fa-check-double"></i> Fully Paid
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="table-card">
            <div class="table-header green">
                <span class="title"><i class="fas fa-file-invoice"></i> All Patient Bills</span>
                <span class="count">0 bills</span>
            </div>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No patient bills found <?= $patient_filter !== 'all' ? 'for filter: ' . strtoupper($patient_filter) : 'for ' . htmlspecialchars($date_label) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- OTC SALES TABLE -->
    <div class="table-card">
        <div class="table-header cyan">
            <div class="header-left">
                <span class="title"><i class="fas fa-cash-register"></i> OTC Sales (Over-The-Counter)</span>
                <span class="count"><i class="fas fa-list"></i> <span id="otcCountDisplay"><?= count($otc_sales_list) ?></span> sales • Total: <?= $currency ?> <?= number_format($otc_revenue, 0) ?></span>
            </div>
            <div class="header-right">
                <div class="header-scroll-buttons">
                    <button type="button" class="header-scroll-btn" onclick="scrollTable('otcWrapper', 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="header-scroll-btn" onclick="scrollTable('otcWrapper', 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
        <div class="table-scroll-wrapper" id="otcWrapper">
            <table class="data-table" style="min-width:1700px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th>Bill #</th>
                        <th>Item Name</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">Premium</th>
                        <th style="text-align:right;">Grand Total</th>
                        <th>Payment</th>
                        <th>Sold By</th>
                        <th style="text-align:center;">Status</th>
                        <th>Branch</th>
                        <th>Date & Time</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="otcTableBody">
                    <?php if (count($otc_sales_list) > 0): ?>
                        <?php $otc_num = 1; foreach ($otc_sales_list as $otc): 
                            $role = strtolower($otc['sold_by_role'] ?? 'user');
                            $name_parts = explode(' ', trim($otc['sold_by_name'] ?? 'N/A'));
                            $initials = count($name_parts) >= 2 ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) : strtoupper(substr($otc['sold_by_name'] ?? 'NA', 0, 2));
                            
                            $items_raw = $otc['items_raw'] ?? '';
                            $item_lines = !empty($items_raw) ? explode('###', $items_raw) : [];
                            $parsed_items = [];
                            foreach ($item_lines as $line) {
                                $parts = explode('|||', $line);
                                if (count($parts) >= 4) {
                                    $parsed_items[] = [
                                        'name' => $parts[0],
                                        'qty' => (int)$parts[1],
                                        'unit_price' => (float)$parts[2],
                                        'total_price' => (float)$parts[3],
                                    ];
                                }
                            }
                            $item_count = count($parsed_items);
                            $row_count = max(1, $item_count);
                            $bill_number = !empty($otc['bill_id']) ? 'BILL-OTC-' . $otc['bill_id'] : '—';
                            $otc_status = strtolower($otc['payment_status'] ?? 'paid');
                        ?>
                            
                            <?php if ($item_count > 0): ?>
                                <?php $first = true; foreach ($parsed_items as $idx => $pi): ?>
                                    <tr class="<?= $first ? 'otc-sale-row' : 'otc-item-row' ?>">
                                        <?php if ($first): ?>
                                            <td rowspan="<?= $row_count ?>" style="text-align:center;font-weight:800;color:var(--cyan);background:var(--cyan-bg);border-right:2px solid var(--cyan);vertical-align:middle;"><?= $otc_num++ ?></td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;border-right:1px solid var(--border-color);"><span style="font-size:0.68rem;color:var(--cyan);font-weight:800;background:var(--cyan-bg);padding:4px 8px;border-radius:5px;display:inline-block;font-family:var(--font-mono);"><?= highlightSearchTerm($otc['sale_number'] ?? 'N/A', $search) ?></span></td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;border-right:1px solid var(--border-color);">
                                                <div style="font-weight:600;font-size:0.75rem;"><?= highlightSearchTerm($otc['customer_name'] ?? 'Walk-in Customer', $search) ?></div>
                                                <?php if (!empty($otc['customer_phone'])): ?>
                                                    <div style="font-size:0.62rem;color:var(--text-secondary);"><i class="fas fa-phone"></i> <?= highlightSearchTerm($otc['customer_phone'], $search) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;border-right:1px solid var(--border-color);"><span style="font-size:0.62rem;color:var(--text-secondary);font-weight:700;font-family:var(--font-mono);"><?= htmlspecialchars($bill_number) ?></span></td>
                                        <?php endif; ?>
                                        
                                        <td class="otc-item-row"><div class="otc-item-name" style="font-weight:700;"><i class="fas fa-capsules" style="color:var(--cyan);"></i> <?= highlightSearchTerm($pi['name'], $search) ?></div></td>
                                        <td style="text-align:center;"><span style="font-family:var(--font-mono);font-weight:800;font-size:0.78rem;background:var(--cyan-bg);color:var(--cyan);padding:3px 8px;border-radius:5px;"><?= $pi['qty'] ?></span></td>
                                        <td class="money-cell cyan" style="font-size:0.75rem;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($pi['unit_price'], 0) ?></td>
                                        <td class="money-cell" style="font-size:0.75rem;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($pi['total_price'], 0) ?></td>
                                        
                                        <?php if ($first): ?>
                                            <td rowspan="<?= $row_count ?>" class="money-cell warning" style="font-size:0.75rem;vertical-align:middle;border-left:2px solid var(--border-color);">
                                                <?php if ((float)($otc['discount_amount'] ?? 0) > 0): ?>-<span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($otc['discount_amount'] ?? 0), 0) ?><?php else: ?><span style="color:var(--text-secondary);">—</span><?php endif; ?>
                                            </td>
                                            <td rowspan="<?= $row_count ?>" class="money-cell purple" style="font-size:0.75rem;vertical-align:middle;">
                                                <?php if ((float)($otc['premium_amount'] ?? 0) > 0): ?>+<span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($otc['premium_amount'] ?? 0), 0) ?><?php else: ?><span style="color:var(--text-secondary);">—</span><?php endif; ?>
                                            </td>
                                            <td rowspan="<?= $row_count ?>" class="money-cell" style="font-size:0.85rem;font-weight:900;vertical-align:middle;background:var(--cyan-bg);border-left:2px solid var(--cyan);"><span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($otc['total_amount'] ?? 0), 0) ?></td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;border-left:1px solid var(--border-color);"><span class="payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $otc['payment_method'] ?? 'Cash'))) ?></span></td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;border-left:1px solid var(--border-color);">
                                                <div class="received-by">
                                                    <div class="received-by-avatar cyan"><?= htmlspecialchars($initials) ?></div>
                                                    <div class="received-by-info">
                                                        <span class="received-by-name"><?= htmlspecialchars($otc['sold_by_name'] ?? 'N/A') ?></span>
                                                        <span class="received-by-role"><span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td rowspan="<?= $row_count ?>" style="text-align:center;vertical-align:middle;">
                                                <?php if ($otc_status === 'paid'): ?><span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                                <?php elseif ($otc_status === 'partial'): ?><span class="status-badge pending"><i class="fas fa-hourglass-half"></i> PARTIAL</span>
                                                <?php else: ?><span class="status-badge pending"><i class="fas fa-clock"></i> <?= strtoupper($otc_status) ?></span><?php endif; ?>
                                            </td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;"><span style="font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($otc['branch_name'] ?? 'N/A') ?></span></td>
                                            <td rowspan="<?= $row_count ?>" style="vertical-align:middle;">
                                                <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                                <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                            </td>
                                            <td rowspan="<?= $row_count ?>" style="text-align:center;vertical-align:middle;">
                                                <div class="action-buttons">
                                                    <a href="view_otc.php?id=<?= (int)$otc['sale_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view" title="View OTC Sale"><i class="fas fa-eye"></i></a>
                                                    <button type="button" class="btn-action delete" title="Delete OTC Sale" onclick="confirmDeleteOtc(<?= (int)$otc['sale_id'] ?>, '<?= htmlspecialchars(addslashes($otc['sale_number'] ?? 'N/A')) ?>')"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="otc-sale-row">
                                    <td style="text-align:center;font-weight:800;color:var(--cyan);"><?= $otc_num++ ?></td>
                                    <td><span style="font-size:0.68rem;color:var(--cyan);font-weight:800;background:var(--cyan-bg);padding:4px 8px;border-radius:5px;font-family:var(--font-mono);"><?= highlightSearchTerm($otc['sale_number'] ?? 'N/A', $search) ?></span></td>
                                    <td><?= highlightSearchTerm($otc['customer_name'] ?? 'Walk-in Customer', $search) ?></td>
                                    <td><span style="font-size:0.62rem;color:var(--text-secondary);font-family:var(--font-mono);"><?= htmlspecialchars($bill_number) ?></span></td>
                                    <td colspan="4" style="text-align:center;color:var(--text-secondary);font-style:italic;">No items recorded</td>
                                    <td class="money-cell warning"><?= (float)($otc['discount_amount'] ?? 0) > 0 ? '-' . $currency . ' ' . number_format((float)$otc['discount_amount'], 0) : '—' ?></td>
                                    <td class="money-cell purple"><?= (float)($otc['premium_amount'] ?? 0) > 0 ? '+' . $currency . ' ' . number_format((float)$otc['premium_amount'], 0) : '—' ?></td>
                                    <td class="money-cell" style="font-weight:900;background:var(--cyan-bg);"><?= $currency ?> <?= number_format((float)($otc['total_amount'] ?? 0), 0) ?></td>
                                    <td><span class="payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $otc['payment_method'] ?? 'Cash'))) ?></span></td>
                                    <td>
                                        <div class="received-by">
                                            <div class="received-by-avatar cyan"><?= htmlspecialchars($initials) ?></div>
                                            <div class="received-by-info">
                                                <span class="received-by-name"><?= htmlspecialchars($otc['sold_by_name'] ?? 'N/A') ?></span>
                                                <span class="received-by-role"><span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($otc_status === 'paid'): ?><span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                        <?php elseif ($otc_status === 'partial'): ?><span class="status-badge pending"><i class="fas fa-hourglass-half"></i> PARTIAL</span>
                                        <?php else: ?><span class="status-badge pending"><i class="fas fa-clock"></i> <?= strtoupper($otc_status) ?></span><?php endif; ?>
                                    </td>
                                    <td><span style="font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($otc['branch_name'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                        <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_otc.php?id=<?= (int)$otc['sale_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i></a>
                                            <button type="button" class="btn-action delete" onclick="confirmDeleteOtc(<?= (int)$otc['sale_id'] ?>, '<?= htmlspecialchars(addslashes($otc['sale_number'] ?? 'N/A')) ?>')"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="17"><div class="empty-state"><i class="fas fa-cash-register"></i><p>No OTC sales found for <?= htmlspecialchars($date_label) ?></p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REVENUE BREAKDOWN TABLE -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-chart-pie"></i> Revenue Breakdown by Source</span>
            <span class="count">Total: <?= $currency ?> <?= number_format($total_revenue, 0) ?></span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Source</th><th style="text-align:right;">Revenue</th><th style="text-align:right;">% of Total</th><th style="text-align:right;">Transactions</th></tr>
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
                        <td class="money-cell cyan"><span class="currency-prefix"><?= $currency ?></span><?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($otc_count) ?></td>
                    </tr>
                    <tr style="background:var(--primary-bg);">
                        <td colspan="4" style="padding:10px 16px;font-weight:800;font-size:0.75rem;color:var(--primary);text-transform:uppercase;"><i class="fas fa-info-circle"></i> Patient Payments Breakdown (GROSS)</td>
                    </tr>
                    <tr>
                        <td><span class="dot-indicator" style="background:#7C3AED;"></span> Prescriptions (GROSS)</td>
                        <td class="money-cell purple"><span class="currency-prefix"><?= $currency ?></span><?= number_format($prescription_gross, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($medication_count) ?></td>
                    </tr>
                    <tr>
                        <td><span class="dot-indicator" style="background:#059669;"></span> <strong>Clinical Services</strong> (Cons + Proc + Equip)</td>
                        <td class="money-cell" style="color:#059669;font-weight:900;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($clinical_services_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($clinical_services_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:48px;font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-caret-right" style="color:#059669;"></i> Consultation</td>
                        <td class="money-cell" style="color:#059669;"><?= number_format($consultation_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($consultation_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:48px;font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-caret-right" style="color:#0D9488;"></i> Procedures</td>
                        <td class="money-cell" style="color:#0D9488;"><?= number_format($procedure_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($procedure_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:48px;font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-caret-right" style="color:#7C3AED;"></i> Equipment</td>
                        <td class="money-cell" style="color:#7C3AED;"><?= number_format($equipment_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($equipment_count) ?></td>
                    </tr>
                    <tr>
                        <td><span class="dot-indicator" style="background:#3B82F6;"></span> Lab Tests</td>
                        <td class="money-cell" style="color:#3B82F6;"><?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td><span class="dot-indicator" style="background:#64748B;"></span> Registration</td>
                        <td class="money-cell slate"><?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);">—</td>
                        <td style="text-align:right;color:var(--text-secondary);"><?= number_format($registration_count) ?></td>
                    </tr>
                    <tr style="background:var(--success-bg);border-top:2px solid var(--success);">
                        <td style="font-weight:800;color:var(--success);"><i class="fas fa-check-circle"></i> Breakdown Total (GROSS)</td>
                        <td class="money-cell" style="color:var(--success);font-weight:900;"><?= number_format($breakdown_total, 0) ?></td>
                        <td style="text-align:right;color:var(--success);font-weight:800;">—</td>
                        <td style="text-align:right;color:var(--success);font-weight:800;"><?= number_format($patient_bills_count) ?> payments</td>
                    </tr>
                    <?php if ($total_discounts > 0): ?>
                    <tr style="background:var(--warning-bg);">
                        <td style="padding-left:20px;"><i class="fas fa-tag" style="color:var(--warning);"></i> <strong style="color:var(--warning);">Total Discounts</strong></td>
                        <td class="money-cell warning">-<?= number_format($total_discounts, 0) ?></td>
                        <td style="text-align:right;color:var(--warning);">—</td>
                        <td style="text-align:right;color:var(--warning);">—</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($total_premiums > 0): ?>
                    <tr style="background:var(--purple-bg);">
                        <td style="padding-left:20px;"><i class="fas fa-star" style="color:var(--purple);"></i> <strong style="color:var(--purple);">Total Premiums</strong></td>
                        <td class="money-cell purple">+<?= number_format($total_premiums, 0) ?></td>
                        <td style="text-align:right;color:var(--purple);">—</td>
                        <td style="text-align:right;color:var(--purple);">—</td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td style="font-weight:800;font-size:0.88rem;"><i class="fas fa-calculator"></i> TOTAL REVENUE (Payments + OTC)</td>
                        <td style="text-align:right;font-weight:800;font-size:0.9rem;font-family:var(--font-mono);"><?= $currency ?> <?= number_format($total_revenue, 0) ?></td>
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
            <div class="header-left">
                <span class="title"><i class="fas fa-receipt"></i> All Expenses</span>
                <span class="count"><?= count($expenses_list) ?> records • Total: <?= $currency ?> <?= number_format($total_expenses, 0) ?></span>
            </div>
            <div class="header-right">
                <div class="header-scroll-buttons">
                    <button type="button" class="header-scroll-btn" onclick="scrollTable('expWrapper', 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="header-scroll-btn" onclick="scrollTable('expWrapper', 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
        <?php if (count($expenses_list) > 0): ?>
        <div class="table-scroll-wrapper" id="expWrapper">
            <table class="data-table" style="min-width:1300px;">
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
                        $initials = count($name_parts) >= 2 ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) : strtoupper(substr($exp['recorded_by_name'] ?? 'NA', 0, 2));
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $exp_num++ ?></td>
                            <td><span class="expense-ref-badge"><?= highlightSearchTerm($exp['expense_number'] ?? 'N/A', $search) ?></span></td>
                            <td><span class="expense-category-badge"><i class="fas fa-tag"></i> <?= htmlspecialchars($exp['category'] ?? 'N/A') ?></span></td>
                            <td style="max-width:250px;"><div style="font-size:0.75rem;font-weight:500;"><?= htmlspecialchars($exp['description'] ?? 'N/A') ?></div></td>
                            <td><span class="payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $exp['payment_method'] ?? 'Cash'))) ?></span></td>
                            <td style="text-align:center;">
                                <?php if ($exp['status'] === 'paid'): ?><span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                <?php else: ?><span class="status-badge pending"><i class="fas fa-clock"></i> <?= strtoupper($exp['status']) ?></span><?php endif; ?>
                            </td>
                            <td>
                                <div class="received-by">
                                    <div class="received-by-avatar red"><?= htmlspecialchars($initials) ?></div>
                                    <div class="received-by-info">
                                        <span class="received-by-name"><?= htmlspecialchars($exp['recorded_by_name'] ?? 'N/A') ?></span>
                                        <span class="received-by-role"><span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span></span>
                                    </div>
                                </div>
                            </td>
                            <td><span style="font-size:0.7rem;color:var(--text-secondary);"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?></span></td>
                            <td class="money-cell red"><span class="currency-prefix"><?= $currency ?></span><?= number_format($exp['amount'] ?? 0, 0) ?></td>
                            <td>
                                <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($exp['payment_date'])) ?></div>
                                <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($exp['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_expense.php?id=<?= $exp['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view" title="View Expense"><i class="fas fa-eye"></i></a>
                                    <button type="button" class="btn-action delete" title="Delete Expense" onclick="confirmDeleteExpense(<?= $exp['id'] ?>, '<?= htmlspecialchars(addslashes($exp['expense_number'] ?? 'N/A')) ?>')"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="8" style="font-weight:800;font-size:0.88rem;text-align:right;color:#DC2626;"><i class="fas fa-calculator"></i> TOTAL EXPENSES</td>
                        <td style="text-align:right;font-weight:900;font-size:0.95rem;color:#DC2626;font-family:var(--font-mono);"><?= $currency ?> <?= number_format($total_expenses, 0) ?></td>
                        <td colspan="2" style="text-align:center;font-weight:800;color:#DC2626;font-size:0.78rem;"><?= number_format($expenses_count) ?> records</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-receipt"></i><p>No expenses found for <?= htmlspecialchars($date_label) ?></p></div>
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
                        <td style="font-weight:600;font-size:0.88rem;padding:16px 20px;"><i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:10px;"></i> Total Revenue (Patient + OTC)</td>
                        <td style="text-align:right;font-weight:800;color:var(--primary);font-size:0.95rem;font-family:var(--font-mono);padding:16px 20px;"><?= $currency ?> <?= number_format($total_revenue, 0) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;font-size:0.88rem;padding:16px 20px;"><i class="fas fa-receipt" style="color:var(--danger);margin-right:10px;"></i> Less: Total Expenses</td>
                        <td style="text-align:right;font-weight:800;color:var(--danger);font-size:0.95rem;font-family:var(--font-mono);padding:16px 20px;">- <?= $currency ?> <?= number_format($total_expenses, 0) ?></td>
                    </tr>
                    <tr class="total-row" style="border-top-color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;background:<?= $net_profit >= 0 ? 'var(--success-bg)' : 'var(--danger-bg)' ?> !important;">
                        <td style="font-weight:800;font-size:1.05rem;padding:18px 20px;color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i> <?= $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS' ?></td>
                        <td style="text-align:right;font-weight:900;font-size:1.15rem;color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-family:var(--font-mono);padding:18px 20px;"><?= $currency ?> <?= number_format(abs($net_profit), 0) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="modal-title" id="deleteModalTitle">Delete Record?</h3>
        <p class="modal-text" id="deleteModalText">
            Are you sure you want to delete<br>
            <strong id="deleteRecordNumber">#</strong><br>
            <span class="modal-warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone!</span>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" id="deleteAction" value="">
            <input type="hidden" name="visit_id" id="deleteVisitId" value="">
            <input type="hidden" name="sale_id" id="deleteSaleId" value="">
            <input type="hidden" name="expense_id" id="deleteExpenseId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="modal-btn danger"><i class="fas fa-trash"></i> Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// ✅ LIVE SEARCH WITH HIGHLIGHT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    
    const searchInput = document.getElementById('liveSearchInput');
    const searchWrapper = document.getElementById('liveSearchWrapper');
    const resultsCount = document.getElementById('liveResultsCount');
    const clearBtn = document.getElementById('liveClearBtn');
    const paymentSelect = document.getElementById('paymentMethodSelect');
    const dateFrom = document.getElementById('customDateFrom');
    const dateTo = document.getElementById('customDateTo');
    
    if (!searchInput) return;
    
    const patientCards = document.querySelectorAll('.patient-group-card');
    const patientCardsOriginal = new Map();
    
    patientCards.forEach((card, i) => {
        patientCardsOriginal.set(i, card.innerHTML);
    });
    
    function highlightInElement(element, query) {
        if (!query) return;
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        
        const textSelectors = [
            '.patient-name', '.patient-meta span', '.received-by-name',
            '.otc-customer-name', '.otc-customer-phone', '.otc-sale-id-badge',
            '.visit-number-badge', '.expense-ref-badge', '.otc-item-name',
            '.badge-value', '.patient-number-badge'
        ];
        
        textSelectors.forEach(selector => {
            element.querySelectorAll(selector).forEach(el => {
                if (el.querySelector('mark.search-highlight')) return;
                
                const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
                const textNodes = [];
                let node;
                while (node = walker.nextNode()) {
                    if (node.nodeValue.trim()) textNodes.push(node);
                }
                
                textNodes.forEach(textNode => {
                    const text = node.nodeValue || textNode.nodeValue;
                    if (regex.test(text)) {
                        const span = document.createElement('span');
                        span.innerHTML = text.replace(regex, '<mark class="search-highlight">$1</mark>');
                        textNode.parentNode.replaceChild(span, textNode);
                    }
                });
            });
        });
    }
    
    function applyLiveFilter(query) {
        const q = query.trim().toLowerCase();
        let visiblePatients = 0;
        
        patientCards.forEach((card, i) => {
            const original = patientCardsOriginal.get(i);
            
            if (!q) {
                card.style.display = '';
                card.innerHTML = original;
                visiblePatients++;
                return;
            }
            
            card.innerHTML = original;
            
            const patientName = (card.querySelector('.patient-name')?.textContent || '').toLowerCase();
            const patientCode = (card.querySelector('.patient-meta span:nth-child(1)')?.textContent || '').toLowerCase();
            const patientPhone = (card.querySelector('.patient-meta span:nth-child(2)')?.textContent || '').toLowerCase();
            const visitNumbers = Array.from(card.querySelectorAll('.visit-number-badge'))
                .map(el => el.textContent.toLowerCase()).join(' ');
            const itemNames = Array.from(card.querySelectorAll('.data-table tbody td div'))
                .map(el => el.textContent.toLowerCase()).join(' ');
            
            const searchableText = `${patientName} ${patientCode} ${patientPhone} ${visitNumbers} ${itemNames}`;
            
            if (searchableText.includes(q)) {
                card.style.display = '';
                visiblePatients++;
                highlightInElement(card, query);
            } else {
                card.style.display = 'none';
            }
        });
        
        if (resultsCount) {
            resultsCount.innerHTML = `${visiblePatients} patients • <?= count($otc_sales_list) ?> OTC sales`;
        }
    }
    
    let liveTimeout;
    searchInput.addEventListener('input', function() {
        const query = this.value;
        
        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'inline-flex' : 'none';
        }
        
        if (searchWrapper) {
            searchWrapper.classList.add('live-typing');
        }
        
        clearTimeout(liveTimeout);
        liveTimeout = setTimeout(() => {
            applyLiveFilter(query);
        }, 30);
    });
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.closest('form').submit();
        }
    });
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchInput.value = '';
            clearBtn.style.display = 'none';
            applyLiveFilter('');
            searchInput.focus();
        });
    }
    
    if (paymentSelect) {
        paymentSelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
    
    if (dateFrom && dateTo) {
        [dateFrom, dateTo].forEach(input => {
            input.addEventListener('change', function() {
                const form = this.closest('form');
                if (form && this.value) {
                    form.submit();
                }
            });
        });
    }
    
    if (searchInput.value.trim()) {
        applyLiveFilter(searchInput.value);
    }
    
    var firstBody = document.getElementById('patientBody_0');
    var firstBtn = document.getElementById('toggleBtn_0');
    if (firstBody && firstBtn) {
        setTimeout(function() {
            firstBody.classList.add('open');
            firstBtn.classList.add('rotated');
        }, 200);
    }
});

function togglePatientCard(index) {
    var body = document.getElementById('patientBody_' + index);
    var btn = document.getElementById('toggleBtn_' + index);
    
    if (!body || !btn) return;
    
    if (body.classList.contains('open')) {
        body.classList.remove('open');
        btn.classList.remove('rotated');
    } else {
        body.classList.add('open');
        btn.classList.add('rotated');
    }
}

function confirmDeleteVisit(id, visitNumber) {
    document.getElementById('deleteAction').value = 'delete_visit';
    document.getElementById('deleteVisitId').value = id;
    document.getElementById('deleteModalTitle').textContent = 'Delete Visit?';
    document.getElementById('deleteRecordNumber').textContent = visitNumber;
    document.getElementById('deleteModalText').innerHTML = 
        'Are you sure you want to delete visit<br><strong>' + visitNumber + '</strong>?<br>' +
        '<span style="color:var(--danger);font-weight:700;font-size:0.78rem;display:block;margin-top:8px;">' +
        '<i class="fas fa-exclamation-circle"></i> This will delete ALL related bills, items, payments!</span>' +
        '<span class="modal-warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone!</span>';
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function confirmDeleteOtc(id, saleNumber) {
    document.getElementById('deleteAction').value = 'delete_otc';
    document.getElementById('deleteSaleId').value = id;
    document.getElementById('deleteModalTitle').textContent = 'Delete OTC Sale?';
    document.getElementById('deleteRecordNumber').textContent = saleNumber;
    document.getElementById('deleteModalText').innerHTML = 
        'Are you sure you want to delete OTC sale<br><strong>' + saleNumber + '</strong>?' +
        '<span class="modal-warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone!</span>';
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function confirmDeleteExpense(id, expenseNumber) {
    document.getElementById('deleteAction').value = 'delete_expense';
    document.getElementById('deleteExpenseId').value = id;
    document.getElementById('deleteModalTitle').textContent = 'Delete Expense?';
    document.getElementById('deleteRecordNumber').textContent = expenseNumber;
    document.getElementById('deleteModalText').innerHTML = 
        'Are you sure you want to delete expense<br><strong>' + expenseNumber + '</strong>?' +
        '<span class="modal-warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone!</span>';
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDeleteModal(); });

function scrollItemsTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    var amount = 400;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function scrollTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    var amount = 500;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function toggleCustomFilter() {
    var section = document.getElementById('customFilterSection');
    var btn = document.getElementById('customFilterBtn');
    if (section.style.display === 'none' || section.style.display === '') {
        section.style.display = 'grid';
        btn.classList.add('active');
        setTimeout(function() { section.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    } else {
        section.style.display = 'none';
        btn.classList.remove('active');
    }
}

function resetCustomFilter() {
    var today = new Date();
    document.getElementById('customDateFrom').value = formatDateForInput(today);
    document.getElementById('customDateTo').value = formatDateForInput(today);
}

function formatDateForInput(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

document.addEventListener('DOMContentLoaded', function() {
    var fromInput = document.getElementById('customDateFrom');
    var toInput = document.getElementById('customDateTo');
    if (fromInput && toInput) {
        fromInput.addEventListener('change', function() {
            if (toInput.value && this.value > toInput.value) toInput.value = this.value;
            toInput.min = this.value;
        });
        toInput.addEventListener('change', function() {
            if (fromInput.value && this.value < fromInput.value) fromInput.value = this.value;
            fromInput.max = this.value;
        });
    }
    
    <?php if ($quick_filter === 'custom'): ?>
    var section = document.getElementById('customFilterSection');
    if (section) section.style.display = 'grid';
    <?php endif; ?>
});

console.log('%c📊 Revenue Report V59 - ADMIN (PENDING BILLS FIXED)', 'font-size:18px; font-weight:bold; color:#059669;');
console.log('%c✅ V59: Pending bills zinaonekana bila kuhitaji payments', 'font-size:13px; color:#059669; font-weight:bold;');
console.log('%c✅ V59: All filter inaonyesha pending + paid + partial', 'font-size:13px; color:#7C3AED; font-weight:bold;');
console.log('%c✅ V58: Quick Filters JUU + Patients Filter CHINI', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c💰 Total Revenue: <?= $currency ?> <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
</script>

</body>
</html>