<?php
// ================================================================
// FILE: frontend/pages/audit/revenue.php
// AUDIT ROLE - REVENUE REPORT (BRANCH LOCKED V2 - ALIGNED WITH DASHBOARD)
// ✅ Inaonyesha data za branch ya mtumiaji aliye login TU
// ✅ ALIGNED WITH DASHBOARD V14
// ✅ Patient Payments query inatumia patient_id + visit_id + NOT BILL-OTC-%
// ✅ OTC query inatumia paid + partial
// ✅ PRESCRIPTION CARD = GROSS PEKEE (bila discount, bila premium)
// ✅ AUDIT role only - no Delete/Edit
// ✅ 8 CARDS TU - Summary cards
// ✅ Discount & Premium = CARD MOJA yenye width kubwa (grid 6 cols)
// ✅ Patient Group Cards with BLUE BORDER
// ✅ Visit Header 3-Row (info + DP cards + diagnosis)
// ✅ OTC Sales as CARD per Sale
// ✅ Expenses: scroll arrows kwenye header
// ✅ Timezone: Africa/Dar_es_Salaam
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ✅ LAZIMISHA branch ya mtumiaji aliye login TU
$selected_branch_id = (int)$user_branch_id;

require_once __DIR__ . '/../../../backend/config/database.php';

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

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// BRANCH NAME
$branch_name_display = $user_branch_name;
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
} catch (Exception $e) {}

// FILTERS
$quick_filter = $_GET['quick'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_method = $_GET['payment_method'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// CALENDAR DAY FILTER
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
        $date_label = "All Time";
}

$pay_cond_payments = ""; $pay_cond_otc = ""; $pay_params = [];
if ($payment_method !== 'all') {
    $pay_cond_payments = " AND p.payment_method = ?";
    $pay_cond_otc = " AND o.payment_method = ?";
    $pay_params = [$payment_method];
}

// ================================================================
// ✅ BRANCH CONDITIONS
// ================================================================
$branch_cond_p = " AND p.branch_id = ?";
$branch_cond_o = " AND o.branch_id = ?";
$branch_cond_e = " AND e.branch_id = ?";
$branch_cond_bi = " AND bi.branch_id = ?";
$branch_params_p = [$selected_branch_id];
$branch_params_o = [$selected_branch_id];
$branch_params_e = [$selected_branch_id];
$branch_params_bi = [$selected_branch_id];

// ================================================================
// ✅ PATIENT PAYMENTS - ALIGNED WITH DASHBOARD
// (patient_id NOT NULL, visit_id NOT NULL, NOT BILL-OTC-%)
// ================================================================
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

// ================================================================
// ✅ OTC REVENUE - ALIGNED WITH DASHBOARD (paid + partial)
// ================================================================
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

// ================================================================
// BREAKDOWN (GROSS - total_price bila discount)
// ================================================================
$breakdown_types = ['consultation', 'lab_test', 'procedure', 'medication', 'registration', 'equipment'];
$breakdown_data = [];
foreach ($breakdown_types as $type) {
    $breakdown_data[$type] = ['revenue' => 0, 'count' => 0];
    try {
        $sql = "SELECT COALESCE(SUM(bi.total_price), 0) as total, COUNT(DISTINCT bi.id) as count 
                FROM bill_items bi INNER JOIN bills b ON bi.bill_id = b.id
                WHERE bi.item_type = ? AND bi.status != 'cancelled'
                AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL 
                AND b.bill_number NOT LIKE 'BILL-OTC-%' AND b.status IN ('paid', 'partial')
                AND b.id IN (SELECT DISTINCT p.bill_id FROM payments p 
                    INNER JOIN bills b2 ON p.bill_id = b2.id
                    WHERE p.bill_id IS NOT NULL
                    AND b2.patient_id IS NOT NULL
                    AND b2.visit_id IS NOT NULL
                    AND b2.bill_number NOT LIKE 'BILL-OTC-%'
                    $branch_cond_p $date_cond_payments $pay_cond_payments)
                $branch_cond_bi";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$type], $branch_params_p, $date_params, $pay_params, $branch_params_bi));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $breakdown_data[$type] = ['revenue' => (float)($data['total'] ?? 0), 'count' => (int)($data['count'] ?? 0)];
    } catch (Exception $e) {}
}

// ================================================================
// DISCOUNTS + PREMIUMS
// ================================================================
$patient_discounts = 0; $patient_premiums = 0;
$pharmacy_discounts = 0; $cashier_discounts = 0;
$pharmacy_premiums = 0; $cashier_premiums = 0;
try {
    $sql = "SELECT COALESCE(SUM(b.pharmacy_discount), 0) as pharmacy_discount,
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

// CATEGORIES - GROSS
$consultation_revenue = $breakdown_data['consultation']['revenue'];
$consultation_count = $breakdown_data['consultation']['count'];
$lab_revenue = $breakdown_data['lab_test']['revenue'];
$lab_count = $breakdown_data['lab_test']['count'];
$procedure_revenue = $breakdown_data['procedure']['revenue'];
$procedure_count = $breakdown_data['procedure']['count'];
$medication_revenue_raw = $breakdown_data['medication']['revenue'];
$medication_count = $breakdown_data['medication']['count'];
$registration_revenue = $breakdown_data['registration']['revenue'];
$registration_count = $breakdown_data['registration']['count'];
$equipment_revenue = $breakdown_data['equipment']['revenue'];
$equipment_count = $breakdown_data['equipment']['count'];

// PRESCRIPTION = GROSS PEKEE
$prescription_gross = $medication_revenue_raw;
$prescription_revenue = $prescription_gross;
$prescription_count = $medication_count;
$medication_revenue = $medication_revenue_raw;

$clinical_services_revenue = $consultation_revenue + $procedure_revenue + $equipment_revenue;
$clinical_services_count = $consultation_count + $procedure_count + $equipment_count;

$breakdown_total_raw = $consultation_revenue + $lab_revenue + $procedure_revenue 
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

// ROUND - FINAL
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
$prescription_gross = roundTo50($prescription_gross);
$prescription_revenue = roundTo50($prescription_gross);
$medication_revenue = roundTo50($medication_revenue_raw);
$registration_revenue = roundTo50($registration_revenue);
$equipment_revenue = roundTo50($equipment_revenue);
$clinical_services_revenue = roundTo50($clinical_services_revenue);

$breakdown_total = roundTo50($breakdown_total_raw);

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
                (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count
            FROM otc_sales o
            LEFT JOIN users u ON o.sold_by = u.id
            LEFT JOIN branches br ON o.branch_id = br.id
            WHERE o.payment_status IN ('paid', 'partial')
            $branch_cond_o $date_cond_otc $pay_cond_otc $search_otc
            ORDER BY o.{$otc_date_col} DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params, $search_otc_params));
    $otc_sales_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($otc_sales_list)) {
        $sale_ids = array_column($otc_sales_list, 'sale_id');
        $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
        $item_stmt = $db->prepare("SELECT * FROM otc_sale_items WHERE sale_id IN ($placeholders) ORDER BY id ASC");
        $item_stmt->execute($sale_ids);
        $all_items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $items_by_sale = [];
        foreach ($all_items as $item) {
            $items_by_sale[$item['sale_id']][] = $item;
        }
        
        foreach ($otc_sales_list as &$sale) {
            $sale['items'] = $items_by_sale[$sale['sale_id']] ?? [];
        }
        unset($sale);
    }
} catch (Exception $e) {
    error_log("OTC sales list error: " . $e->getMessage());
}

// ================================================================
// PATIENT-GROUPED BILLS - ALIGNED WITH DASHBOARD
// ================================================================
$patient_groups = [];

try {
    $search_patient = "";
    $search_patient_params = [];
    if (!empty($search)) {
        $search_patient = " AND (pat.full_name LIKE ? OR pat.patient_id LIKE ? OR b.bill_number LIKE ?)";
        $search_patient_params = ["%$search%", "%$search%", "%$search%"];
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
                WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
                AND b.bill_number NOT LIKE 'BILL-OTC-%' AND b.status IN ('paid', 'partial')
                AND b.id IN (SELECT DISTINCT p.bill_id FROM payments p 
                    INNER JOIN bills b2 ON p.bill_id = b2.id
                    WHERE p.bill_id IS NOT NULL
                    AND b2.patient_id IS NOT NULL
                    AND b2.visit_id IS NOT NULL
                    AND b2.bill_number NOT LIKE 'BILL-OTC-%'
                    $branch_cond_p $date_cond_payments $pay_cond_payments)
                $search_patient
                ORDER BY pat.full_name ASC, v.visit_date DESC, b.created_at DESC LIMIT 200";

    $stmt = $db->prepare($sql_bills);
    $stmt->execute(array_merge($branch_params_p, $date_params, $pay_params, $search_patient_params));
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
                'total_balance' => 0
            ];
        }

        $patient_groups[$patient_key]['total_paid'] += (float)$bill['paid_amount'];
        $patient_groups[$patient_key]['total_billed'] += (float)$bill['total_amount'];
        $patient_groups[$patient_key]['total_balance'] += (float)$bill['balance'];

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
                'pharmacy_discount' => 0,
                'cashier_discount' => 0,
                'pharmacy_premium' => 0,
                'cashier_premium' => 0,
                'items_by_category' => [
                    'consultation' => [], 'lab_test' => [], 'medication' => [],
                    'procedure' => [], 'equipment' => [], 'registration' => [], 'other' => []
                ]
            ];
        }

        $patient_groups[$patient_key]['visits'][$visit_key]['visit_total'] += (float)$bill['total_amount'];
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

// MONTHLY CHART
$monthly_labels = []; $monthly_patient = []; $monthly_otc = []; $monthly_expenses = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_labels[] = date('M Y', strtotime("-$i months"));
    $p_p = [$month, $selected_branch_id]; 
    $p_o = [$month, $selected_branch_id]; 
    $p_e = [$month, $selected_branch_id];

    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total 
            FROM payments p 
            INNER JOIN bills b ON p.bill_id = b.id 
            WHERE p.bill_id IS NOT NULL 
            AND b.patient_id IS NOT NULL 
            AND b.visit_id IS NOT NULL 
            AND b.bill_number NOT LIKE 'BILL-OTC-%' 
            AND DATE_FORMAT(p.{$payments_date_col}, '%Y-%m') = ? 
            AND p.branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_p);
    $monthly_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales WHERE payment_status IN ('paid', 'partial') AND DATE_FORMAT({$otc_date_col}, '%Y-%m') = ? AND branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_o);
    $monthly_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 'paid' AND DATE_FORMAT({$expenses_date_col}, '%Y-%m') = ? AND branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_e);
    $monthly_expenses[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

// DAILY CHART
$daily_labels = []; $daily_patient = []; $daily_otc = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    $p_p = [$date, $selected_branch_id]; 
    $p_o = [$date, $selected_branch_id];

    $sql = "SELECT COALESCE(SUM(p.amount), 0) as total 
            FROM payments p 
            INNER JOIN bills b ON p.bill_id = b.id 
            WHERE p.bill_id IS NOT NULL 
            AND b.patient_id IS NOT NULL 
            AND b.visit_id IS NOT NULL 
            AND b.bill_number NOT LIKE 'BILL-OTC-%' 
            AND DATE(p.{$payments_date_col}) = ? 
            AND p.branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_p);
    $daily_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales WHERE payment_status IN ('paid', 'partial') AND DATE({$otc_date_col}) = ? AND branch_id = ?";
    $stmt = $db->prepare($sql); $stmt->execute($p_o);
    $daily_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revenue Report • Braick Audit</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

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
body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); -webkit-font-smoothing: antialiased; line-height: 1.5; min-height: 100vh; }

.money-number, .stat-value, .money-cell, .font-mono, .card-value { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); border-radius: var(--radius-lg); padding: 24px 28px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.5rem; font-weight: 900; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; position: relative; z-index: 1; }
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.9); font-size: 0.8rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.68rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); }
.branch-tag.filter-tag { background: linear-gradient(135deg, #10B981, #059669); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.branch-tag.user-tag { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3); }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 10px 16px; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.75rem; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(10px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

.filter-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); margin-bottom: 20px; box-shadow: var(--shadow-sm); }
.filter-section { margin-bottom: 14px; }
.filter-section:last-child { margin-bottom: 0; }
.filter-section-title { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.quick-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.quick-btn { padding: 8px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-secondary); font-weight: 700; font-size: 0.72rem; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; }
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.quick-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-color: transparent; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35); }

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

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.pulse-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: currentColor; margin-right: 3px; animation: pulseDot 1.5s infinite; }

@keyframes pulseDot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.discount-premium-card { background: var(--bg-card); border-radius: 14px; padding: 18px 20px; border: 2px solid var(--border-color); margin-bottom: 20px; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
.discount-premium-card:hover { box-shadow: var(--shadow-md); border-color: #0B5ED7; }
.discount-premium-card .dp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px dashed var(--border-color); flex-wrap: wrap; gap: 8px; }
.discount-premium-card .dp-title { font-size: 0.95rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.discount-premium-card .dp-title i { color: var(--primary); font-size: 1rem; }
.dp-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; }
.dp-item { background: var(--bg-body); border-radius: 10px; padding: 12px 14px; border: 2px solid var(--border-color); transition: all 0.3s ease; position: relative; overflow: hidden; }
.dp-item::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.dp-item:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.dp-item .dp-label { font-size: 0.55rem; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
.dp-item .dp-value { font-size: 1rem; font-weight: 900; font-family: var(--font-mono); line-height: 1.1; }
.dp-item .dp-sub { font-size: 0.55rem; color: var(--text-secondary); margin-top: 4px; font-weight: 600; }
.dp-item.pharmacy-disc::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
.dp-item.pharmacy-disc .dp-value { color: #D97706; }
.dp-item.pharmacy-disc .dp-label i { color: #D97706; }
.dp-item.cashier-disc::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.dp-item.cashier-disc .dp-value { color: #7C3AED; }
.dp-item.cashier-disc .dp-label i { color: #7C3AED; }
.dp-item.total-disc::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.dp-item.total-disc .dp-value { color: #DC2626; }
.dp-item.total-disc .dp-label i { color: #DC2626; }
.dp-item.pharmacy-prem::before { background: linear-gradient(90deg, #059669, #34D399); }
.dp-item.pharmacy-prem .dp-value { color: #059669; }
.dp-item.pharmacy-prem .dp-label i { color: #059669; }
.dp-item.cashier-prem::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.dp-item.cashier-prem .dp-value { color: #0891B2; }
.dp-item.cashier-prem .dp-label i { color: #0891B2; }
.dp-item.total-prem::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.dp-item.total-prem .dp-value { color: #0B5ED7; }
.dp-item.total-prem .dp-label i { color: #0B5ED7; }

.chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.chart-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); }
.chart-card .chart-header { padding: 14px 18px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, var(--primary-bg), transparent); }
.chart-card .chart-header .chart-title { font-size: 0.88rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.chart-card .chart-header .chart-title i { color: var(--primary); }
.chart-card .chart-body { padding: 18px; height: 280px; position: relative; }

.patient-group-card { background: var(--bg-card); border-radius: var(--radius-xl); border: 3px solid var(--primary); overflow: hidden; box-shadow: 0 8px 30px rgba(11, 94, 215, 0.15), 0 0 0 1px rgba(11, 94, 215, 0.1); margin-bottom: 36px; position: relative; transition: all 0.35s ease; }
.patient-group-card::before { content: ''; position: absolute; inset: -8px; border-radius: calc(var(--radius-xl) + 8px); background: linear-gradient(135deg, rgba(11, 94, 215, 0.12), rgba(124, 58, 237, 0.08)); z-index: -1; pointer-events: none; }
.patient-group-card:hover { border-color: var(--primary-light); box-shadow: 0 12px 40px rgba(11, 94, 215, 0.25), 0 0 0 1px rgba(11, 94, 215, 0.2); transform: translateY(-2px); }

.patient-info-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); padding: 18px 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; position: relative; overflow: hidden; }
.patient-info-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%; pointer-events: none; z-index: 0; }
.patient-info-header > * { position: relative; z-index: 1; }
.patient-number-badge { position: relative; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); border: 1.5px solid rgba(255, 255, 255, 0.45); color: white; padding: 6px 14px; border-radius: 999px; font-size: 0.68rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); align-self: flex-start; flex-shrink: 0; white-space: nowrap; }
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

.patient-footer { background: linear-gradient(135deg, rgba(11, 94, 215, 0.08), rgba(124, 58, 237, 0.05)); padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 2px dashed var(--primary); }
.patient-footer .footer-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.patient-footer .footer-end-label { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 900; color: var(--primary); text-transform: uppercase; letter-spacing: 0.04em; }
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
.visit-staff-chip .staff-label { color: var(--text-secondary); font-weight: 600; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.04em; }
.visit-staff-chip .staff-name { color: var(--text-primary); font-weight: 800; }

.visit-header-row2 { background: var(--bg-card); padding: 14px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: stretch; border-bottom: 1px solid var(--border-color); }
.visit-dp-card { background: var(--bg-body); border-radius: var(--radius-md); padding: 10px 14px; display: flex; flex-direction: column; gap: 3px; border: 1.5px solid var(--border-color); transition: all 0.25s; min-height: 70px; justify-content: center; }
.visit-dp-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
.visit-dp-card .dp-label { font-size: 0.55rem; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; display: flex; align-items: center; gap: 4px; }
.visit-dp-card .dp-label i { font-size: 0.65rem; }
.visit-dp-card .dp-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; letter-spacing: -0.02em; }
.visit-dp-card.pharmacy-disc { border-color: rgba(217, 119, 6, 0.3); background: linear-gradient(135deg, #FFFBEB, #FEF3C7); }
.visit-dp-card.pharmacy-disc .dp-label i, .visit-dp-card.pharmacy-disc .dp-value { color: #B45309; }
.visit-dp-card.cashier-disc { border-color: rgba(217, 119, 6, 0.4); background: linear-gradient(135deg, #FEF3C7, #FDE68A); }
.visit-dp-card.cashier-disc .dp-label i, .visit-dp-card.cashier-disc .dp-value { color: #92400E; }
.visit-dp-card.pharmacy-prem { border-color: rgba(124, 58, 237, 0.3); background: linear-gradient(135deg, #F5F3FF, #EDE9FE); }
.visit-dp-card.pharmacy-prem .dp-label i, .visit-dp-card.pharmacy-prem .dp-value { color: #6D28D9; }
.visit-dp-card.cashier-prem { border-color: rgba(168, 85, 247, 0.4); background: linear-gradient(135deg, #EDE9FE, #DDD6FE); }
.visit-dp-card.cashier-prem .dp-label i, .visit-dp-card.cashier-prem .dp-value { color: #7C3AED; }
.visit-dp-card.visit-total { background: linear-gradient(135deg, #059669, #047857); border-color: rgba(5, 150, 105, 0.4); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25); }
.visit-dp-card.visit-total .dp-label, .visit-dp-card.visit-total .dp-label i { color: rgba(255,255,255,0.9); }
.visit-dp-card.visit-total .dp-value { color: white; font-size: 1.05rem; }

.visit-header-row3 { background: var(--bg-card); padding: 14px 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
.visit-diagnosis-box { display: flex; align-items: flex-start; gap: 10px; background: linear-gradient(135deg, rgba(220, 38, 38, 0.06), rgba(220, 38, 38, 0.02)); border-left: 4px solid var(--danger); padding: 10px 14px; border-radius: var(--radius-sm); flex: 1; min-width: 250px; max-width: 100%; }
.visit-diagnosis-box i { color: var(--danger); font-size: 1rem; margin-top: 2px; flex-shrink: 0; }
.visit-diagnosis-box .diagnosis-content { display: flex; flex-direction: column; gap: 3px; flex: 1; }
.visit-diagnosis-box .diagnosis-label { font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--danger); }
.visit-diagnosis-box .diagnosis-text { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); line-height: 1.4; }
.visit-diagnosis-box .diagnosis-code { font-size: 0.62rem; font-weight: 700; color: var(--danger); background: rgba(220, 38, 38, 0.1); padding: 2px 8px; border-radius: 4px; font-family: var(--font-mono); display: inline-block; align-self: flex-start; margin-top: 2px; }
.visit-no-diagnosis { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); font-style: italic; font-weight: 600; }

.visit-action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.visit-action-btn { padding: 8px 16px; border-radius: var(--radius-sm); font-weight: 800; font-size: 0.72rem; border: none; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
.visit-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
.visit-action-btn.view { background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; }

.visit-items-container { padding: 0; background: var(--bg-body); }
.items-table-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: var(--primary-bg); border-bottom: 1px solid var(--border-color); gap: 12px; flex-wrap: wrap; }
.items-table-header .items-title { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em; }
.items-scroll-buttons { display: flex; gap: 6px; }
.scroll-btn { width: 30px; height: 30px; border-radius: var(--radius-sm); border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 700; transition: all 0.25s; flex-shrink: 0; }
.scroll-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }

.table-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
.table-card .table-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.table-card .table-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.table-card .table-header.profit { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.profit.loss { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.table-card .table-header .title { color: white; font-size: 0.88rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.table-card .table-header .title i { color: #93C5FD; }
.table-card .table-header .count { color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.18); padding: 5px 12px; border-radius: var(--radius-full); backdrop-filter: blur(10px); }
.table-card .table-header .header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.table-card .table-header .header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.header-scroll-buttons { display: flex; gap: 6px; }
.header-scroll-btn { width: 32px; height: 32px; border-radius: var(--radius-sm); border: 1.5px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.2); color: white; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; transition: all 0.25s; backdrop-filter: blur(10px); flex-shrink: 0; }
.header-scroll-btn:hover { background: rgba(255,255,255,0.4); transform: translateY(-2px); border-color: rgba(255,255,255,0.6); }

.table-card.expenses-red { border-color: rgba(220, 38, 38, 0.3); }
.table-card.expenses-red .table-header { background: linear-gradient(135deg, #DC2626, #B91C1C) !important; }
.table-card.expenses-red .data-table thead th { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.table-card.expenses-red .data-table tbody tr:hover td { background: var(--danger-bg) !important; }

.table-scroll-wrapper { overflow-x: auto; scroll-behavior: smooth; background: var(--bg-card); }
.table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 10px; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th { text-align: left; padding: 11px 14px; font-weight: 800; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.08em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; font-weight: 500; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr.total-row td { border-top: 3px solid var(--primary); font-weight: 800; font-size: 0.88rem; background: var(--primary-bg) !important; color: var(--primary); }

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
.received-by-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.68rem; flex-shrink: 0; text-transform: uppercase; }
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
.role-tag.audit { background: #FCE7F3; color: #BE185D; }
.role-tag.user { background: var(--border-color); color: var(--text-secondary); }

.payment-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.66rem; font-weight: 700; background: var(--primary-bg); color: var(--primary); white-space: nowrap; }
.expense-ref-badge { font-size: 0.68rem; color: #DC2626; font-weight: 800; background: var(--danger-bg); padding: 4px 8px; border-radius: 6px; display: inline-block; font-family: var(--font-mono); }
.expense-category-badge { font-size: 0.68rem; font-weight: 700; background: var(--warning-bg); color: var(--warning); padding: 4px 9px; border-radius: 6px; display: inline-block; }

.action-buttons { display: flex; gap: 5px; justify-content: center; align-items: center; }
.btn-action { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; border: none; cursor: pointer; transition: all 0.25s; text-decoration: none; }
.btn-action:hover { transform: translateY(-2px) scale(1.1); }
.btn-action.view { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
.btn-action.view:hover { background: #0B5ED7; color: white; }

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

.otc-sale-card { background: var(--bg-card); border: 2px solid var(--cyan); border-radius: var(--radius-lg); margin: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(8, 145, 178, 0.1); transition: all 0.3s ease; }
.otc-sale-card:hover { box-shadow: 0 8px 28px rgba(8, 145, 178, 0.2); border-color: var(--cyan-light); }
.otc-sale-header { background: linear-gradient(135deg, #0891B2, #0E7490); padding: 14px 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; color: white; }
.otc-header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1; min-width: 300px; }
.otc-sale-id-badge { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 1.5px solid rgba(255,255,255,0.4); color: white; padding: 6px 14px; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 900; font-family: var(--font-mono); display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.otc-customer-info { display: flex; flex-direction: column; gap: 2px; }
.otc-customer-name { font-size: 0.88rem; font-weight: 900; display: flex; align-items: center; gap: 6px; color: white; }
.otc-customer-phone { font-size: 0.68rem; color: rgba(255,255,255,0.85); font-weight: 600; display: flex; align-items: center; gap: 4px; }
.otc-header-middle { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.otc-stat-chip { display: flex; flex-direction: column; gap: 2px; padding: 8px 14px; border-radius: var(--radius-md); min-width: 110px; border: 1.5px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.otc-stat-chip .stat-chip-label { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.85); display: flex; align-items: center; gap: 4px; }
.otc-stat-chip .stat-chip-value { font-size: 0.95rem; font-weight: 900; font-family: var(--font-mono); color: white; }
.otc-stat-chip.discount { background: linear-gradient(135deg, rgba(217, 119, 6, 0.4), rgba(217, 119, 6, 0.2)); border-color: rgba(251, 191, 36, 0.6); }
.otc-stat-chip.discount .stat-chip-value { color: #FEF3C7; }
.otc-stat-chip.premium { background: linear-gradient(135deg, rgba(124, 58, 237, 0.4), rgba(124, 58, 237, 0.2)); border-color: rgba(167, 139, 250, 0.6); }
.otc-stat-chip.premium .stat-chip-value { color: #EDE9FE; }
.otc-stat-chip.grand-total { background: linear-gradient(135deg, rgba(5, 150, 105, 0.5), rgba(5, 150, 105, 0.3)); border-color: rgba(52, 211, 153, 0.7); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
.otc-stat-chip.grand-total .stat-chip-value { color: #D1FAE5; font-size: 1.05rem; }
.otc-header-right { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.otc-action-btn { padding: 8px 14px; border-radius: var(--radius-sm); font-weight: 800; font-size: 0.68rem; border: 1.5px solid rgba(255,255,255,0.3); cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; backdrop-filter: blur(10px); box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
.otc-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); }
.otc-action-btn.view { background: rgba(255,255,255,0.2); color: white; }
.otc-action-btn.view:hover { background: rgba(255,255,255,0.35); }
.otc-scroll-buttons { display: flex; gap: 5px; margin-left: 8px; }
.otc-scroll-btn { width: 32px; height: 32px; border-radius: var(--radius-sm); border: 1.5px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.2); color: white; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; transition: all 0.25s; backdrop-filter: blur(10px); flex-shrink: 0; }
.otc-scroll-btn:hover { background: rgba(255,255,255,0.4); transform: translateY(-2px); border-color: rgba(255,255,255,0.6); }
.otc-items-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.otc-items-table thead th { text-align: left; padding: 10px 14px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.08em; color: white; background: linear-gradient(135deg, #0891B2, #0E7490); white-space: nowrap; }
.otc-items-table tbody td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; font-weight: 500; }
.otc-items-table tbody tr:hover td { background: var(--cyan-bg); }
.otc-items-table tbody tr:last-child td { border-bottom: none; }
.otc-item-name-cell { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--text-primary); }
.otc-item-name-cell .item-icon { width: 26px; height: 26px; border-radius: 6px; background: linear-gradient(135deg, #0891B2, #22D3EE); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0; }
.otc-qty-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; padding: 4px 10px; border-radius: 6px; background: var(--cyan-bg); color: var(--cyan); font-family: var(--font-mono); font-weight: 800; font-size: 0.78rem; border: 1.5px solid rgba(8, 145, 178, 0.3); }
.otc-sale-footer { background: linear-gradient(135deg, rgba(8, 145, 178, 0.08), rgba(8, 145, 178, 0.03)); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 2px dashed var(--cyan); }
.otc-footer-info { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
.otc-footer-stat { display: inline-flex; align-items: center; gap: 5px; font-size: 0.65rem; font-weight: 700; color: var(--text-secondary); background: var(--bg-card); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-color); }
.otc-footer-stat strong { font-family: var(--font-mono); color: var(--text-primary); font-weight: 900; }

.dot-indicator { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
.empty-state { padding: 60px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 14px; color: var(--primary); }
.empty-state p { font-weight: 600; font-size: 0.9rem; }

@media (max-width: 1200px) { .stats-grid-8 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1400px) { .dp-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) {
    .chart-grid { grid-template-columns: 1fr; }
    .stats-grid-8 { grid-template-columns: repeat(2, 1fr); }
    .dp-grid { grid-template-columns: repeat(2, 1fr); }
    .patient-info-header { flex-direction: column; align-items: stretch; }
    .patient-number-badge { align-self: flex-start; }
}
@media (max-width: 768px) {
    .page-header { padding: 18px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    .stats-grid-8 { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .stat-card .card-value { font-size: 1.1rem; }
    .dp-grid { grid-template-columns: 1fr 1fr; }
    .discount-premium-card { padding: 14px; }
    .visit-header-row1 { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .visit-header-row2 { grid-template-columns: repeat(2, 1fr); padding: 12px 14px; }
    .visit-header-row3 { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .visit-action-buttons { width: 100%; }
    .visit-action-btn { flex: 1; justify-content: center; }
    .patient-footer { padding: 12px 16px; flex-direction: column; align-items: flex-start; }
    .otc-sale-header { flex-direction: column; align-items: stretch; }
    .otc-stat-chip { min-width: auto; flex: 1; }
}
@media (max-width: 480px) {
    .stats-grid-8 { grid-template-columns: 1fr; }
    .dp-grid { grid-template-columns: 1fr; }
    .visit-header-row2 { grid-template-columns: 1fr; }
}
@media print {
    .filter-card, .btn-header, .visit-action-buttons, .action-buttons, .header-scroll-buttons, .otc-scroll-buttons { display: none !important; }
    .page-header { background: white !important; color: black !important; }
    .stat-card { break-inside: avoid; }
    .patient-group-card { break-inside: avoid; }
    .otc-sale-card { break-inside: avoid; }
}
</style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Revenue Report
                <span class="branch-tag" style="background:rgba(255,255,255,0.25);"><i class="fas fa-shield-alt"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-circle"></i>
                Karibu, <span class="branch-tag user-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?></span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i>
                    <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                </span>
                <span class="branch-tag"><i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($total_revenue, 0) ?></span>
                <span class="branch-tag"><i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Payments</span>
                <span class="branch-tag filter-tag"><i class="fas fa-filter"></i> <?= htmlspecialchars($date_label) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header"><i class="fas fa-print"></i> Print</button>
            <a href="dashboard.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section">
            <div class="filter-section-title"><i class="fas fa-bolt"></i> Quick Filters</div>
            <div class="quick-filters">
                <a href="?quick=today" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
                <a href="?quick=yesterday" class="quick-btn <?= $quick_filter === 'yesterday' ? 'active' : '' ?>"><i class="fas fa-calendar-minus"></i> Yesterday</a>
                <a href="?quick=1d" class="quick-btn <?= $quick_filter === '1d' ? 'active' : '' ?>"><i class="fas fa-clock"></i> 1D</a>
                <a href="?quick=1w" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 1W</a>
                <a href="?quick=1m" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
                <a href="?quick=3m" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
                <a href="?quick=6m" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 6M</a>
                <a href="?quick=1y" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> 1Y</a>
                <a href="?quick=all" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>"><i class="fas fa-infinity"></i> All</a>
                <a href="?quick=custom&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="quick-btn <?= $quick_filter === 'custom' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i> Custom</a>
            </div>
        </div>
        <form method="GET">
            <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end;margin-top:12px;padding-top:14px;border-top:1.5px dashed var(--border-color);">
                <?php if ($quick_filter === 'custom'): ?>
                    <div><label style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);display:block;margin-bottom:4px;">FROM</label><input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.8rem;font-weight:600;"></div>
                    <div><label style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);display:block;margin-bottom:4px;">TO</label><input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.8rem;font-weight:600;"></div>
                <?php endif; ?>
                <div>
                    <label style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);display:block;margin-bottom:4px;">PAYMENT METHOD</label>
                    <select name="payment_method" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.8rem;font-weight:600;">
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
                <div>
                    <label style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);display:block;margin-bottom:4px;">SEARCH</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Patient, bill, OTC..." style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:0.8rem;font-weight:600;">
                </div>
                <button type="submit" style="padding:9px 18px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;border:none;font-weight:700;font-size:0.78rem;cursor:pointer;height:38px;"><i class="fas fa-filter"></i> Apply</button>
                <a href="?" style="padding:9px 18px;border-radius:8px;background:transparent;color:var(--text-secondary);border:1.5px solid var(--border-color);font-weight:700;font-size:0.78rem;text-decoration:none;text-align:center;line-height:20px;"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
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
                    <?= number_format($total_revenue, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-info-circle"></i>
                Payments + OTC
            </div>
        </div>

        <!-- CARD 2: PATIENT PAYMENTS -->
        <div class="stat-card payments">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-file-invoice"></i></div>
                <span class="card-badge"><i class="fas fa-star"></i> +PREM</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-hand-holding-usd"></i> Patient Payments</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($patient_bills_revenue_rounded, 0) ?>
                </div>
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

        <!-- CARD 3: PRESCRIPTION (GROSS PEKEE) -->
        <div class="stat-card prescription">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-prescription"></i></div>
                <span class="card-badge"><i class="fas fa-pills"></i> Rx GROSS</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-prescription-bottle-medical"></i> Prescription (Gross)</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($prescription_gross, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list"></i>
                Items: <span class="highlight"><?= number_format($prescription_count) ?></span>
                <span style="color:var(--purple);font-weight:800;">Gross Only</span>
            </div>
        </div>

        <!-- CARD 4: OTC SALES -->
        <div class="stat-card otc">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-cash-register"></i></div>
                <span class="card-badge"><i class="fas fa-store"></i> OTC</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-shopping-cart"></i> OTC Sales</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($otc_revenue, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-receipt"></i>
                Sales: <span class="highlight"><?= number_format($otc_count) ?></span>
                <?php if ($otc_discounts > 0): ?>
                    <span class="discount-badge"><i class="fas fa-tag"></i> <?= number_format($otc_discounts, 0) ?></span>
                <?php endif; ?>
                <?php if ($otc_premiums > 0): ?>
                    <span class="premium-badge"><i class="fas fa-star"></i> <?= number_format($otc_premiums, 0) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- CARD 5: CLINICAL SERVICES -->
        <div class="stat-card consultation">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-stethoscope"></i></div>
                <span class="card-badge"><i class="fas fa-user-md"></i> CLINICAL</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-notes-medical"></i> Clinical Services</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($clinical_services_revenue, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list"></i>
                Cons: <span class="highlight"><?= number_format($consultation_count) ?></span> •
                Proc: <span class="highlight"><?= number_format($procedure_count) ?></span> •
                Equip: <span class="highlight"><?= number_format($equipment_count) ?></span>
            </div>
        </div>

        <!-- CARD 6: LAB TESTS -->
        <div class="stat-card lab">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-flask"></i></div>
                <span class="card-badge"><i class="fas fa-vial"></i> LAB</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-microscope"></i> Lab Tests</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($lab_revenue, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-check-circle"></i>
                Tests: <span class="highlight"><?= number_format($lab_count) ?></span>
            </div>
        </div>

        <!-- CARD 7: EXPENSES -->
        <div class="stat-card expenses">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-receipt"></i></div>
                <span class="card-badge"><i class="fas fa-arrow-down"></i> OUT</span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-wallet"></i> Total Expenses</div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format($total_expenses, 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-list"></i>
                Records: <span class="highlight"><?= number_format($expenses_count) ?></span>
            </div>
        </div>

        <!-- CARD 8: NET PROFIT -->
        <div class="stat-card profit <?= $net_profit < 0 ? 'loss' : '' ?>">
            <div class="card-top">
                <div class="card-icon"><i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i></div>
                <span class="card-badge"><i class="fas fa-<?= $net_profit >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> <?= $net_profit >= 0 ? 'PROFIT' : 'LOSS' ?></span>
            </div>
            <div>
                <div class="card-label"><i class="fas fa-balance-scale"></i> <?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
                <div class="card-value">
                    <span class="currency"><?= $currency ?></span>
                    <?= number_format(abs($net_profit), 0) ?>
                </div>
            </div>
            <div class="card-footer">
                <i class="fas fa-percentage"></i>
                Margin: <span class="highlight"><?= $profit_percentage ?>%</span>
            </div>
        </div>

    </div>

    <!-- DISCOUNT & PREMIUM -->
    <div class="discount-premium-card">
        <div class="dp-header">
            <div class="dp-title">
                <i class="fas fa-tags"></i>
                Discount & Premium Breakdown
                <span style="font-size:0.65rem;background:var(--primary-bg);color:var(--primary);padding:2px 8px;border-radius:6px;font-weight:700;">
                    Total: <?= $currency ?> <?= number_format($total_discounts + $total_premiums, 0) ?>
                </span>
            </div>
            <div style="font-size:0.65rem;color:var(--text-secondary);font-weight:600;">
                <i class="fas fa-info-circle"></i> Pharmacy + Cashier
            </div>
        </div>
        <div class="dp-grid">
            <div class="dp-item pharmacy-disc">
                <div class="dp-label"><i class="fas fa-prescription-bottle-medical"></i> Pharmacy Discount</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($pharmacy_discounts, 0) ?></div>
                <div class="dp-sub">From medications</div>
            </div>
            <div class="dp-item cashier-disc">
                <div class="dp-label"><i class="fas fa-cash-register"></i> Cashier Discount</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($cashier_discounts, 0) ?></div>
                <div class="dp-sub">From cashier</div>
            </div>
            <div class="dp-item total-disc">
                <div class="dp-label"><i class="fas fa-tag"></i> Total Discount</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($total_discounts, 0) ?></div>
                <div class="dp-sub">Pharmacy + Cashier</div>
            </div>
            <div class="dp-item pharmacy-prem">
                <div class="dp-label"><i class="fas fa-prescription-bottle-medical"></i> Pharmacy Premium</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($pharmacy_premiums, 0) ?></div>
                <div class="dp-sub">From medications</div>
            </div>
            <div class="dp-item cashier-prem">
                <div class="dp-label"><i class="fas fa-cash-register"></i> Cashier Premium</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($cashier_premiums, 0) ?></div>
                <div class="dp-sub">From cashier</div>
            </div>
            <div class="dp-item total-prem">
                <div class="dp-label"><i class="fas fa-star"></i> Total Premium</div>
                <div class="dp-value"><?= $currency ?> <?= number_format($total_premiums, 0) ?></div>
                <div class="dp-sub">Pharmacy + Cashier</div>
            </div>
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
        ?>
        <div class="patient-group-card">
            
            <div class="patient-info-header">
                <div class="patient-number-badge">
                    <i class="fas fa-user-tag"></i>
                    PATIENT #<?= $patient_index + 1 ?> / <?= count($patient_groups) ?>
                </div>
                
                <div class="patient-identity">
                    <div class="patient-avatar"><?= htmlspecialchars($patient_initials) ?></div>
                    <div class="patient-details">
                        <div class="patient-name">
                            <i class="fas fa-user-circle" style="color:#93C5FD;"></i>
                            <?= htmlspecialchars($patient['patient_name'] ?? 'N/A') ?>
                        </div>
                        <div class="patient-meta">
                            <?php if (!empty($patient['patient_code'])): ?>
                                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_code']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($patient['patient_phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
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
            </div>

            <?php foreach ($patient['visits'] as $visit_index => $visit): 
                $visit_status = strtolower($visit['visit_status'] ?? 'pending');
                $visit_total_rounded = roundTo50($visit['visit_total']);
                
                $v_pharm_disc = (float)($visit['pharmacy_discount'] ?? 0);
                $v_cash_disc = (float)($visit['cashier_discount'] ?? 0);
                $v_pharm_prem = (float)($visit['pharmacy_premium'] ?? 0);
                $v_cash_prem = (float)($visit['cashier_premium'] ?? 0);
            ?>
            <div class="visit-section">
                
                <div class="visit-header-row1">
                    <div class="visit-info-left">
                        <span class="visit-number-badge">
                            <i class="fas fa-notes-medical"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
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
                            <div class="visit-no-diagnosis">
                                <i class="fas fa-info-circle"></i>
                                No diagnosis recorded
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="visit-action-buttons">
                        <a href="view_visit.php?id=<?= (int)$visit['visit_id'] ?>" class="visit-action-btn view">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>

                <div class="visit-items-container">
                    <div class="items-table-header">
                        <span class="items-title"><i class="fas fa-list-ul"></i> Visit Items (<?= count($visit['items']) ?> items)</span>
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
                                    <th>Code</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:right;">Unit Price</th>
                                    <th style="text-align:right;">Total Price</th>
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
                                    $cat_total = 0;
                                    foreach ($cat_items as $ci) {
                                        $cat_total += (float)($ci['total_price'] ?? 0) - (float)($ci['discount_amount'] ?? 0);
                                    }
                                ?>
                                    <tr class="category-group-row <?= $cat_info['class'] ?>">
                                        <td colspan="9">
                                            <span class="category-icon"><i class="fas <?= $cat_info['icon'] ?>"></i></span>
                                            <?= htmlspecialchars($cat_info['label']) ?> (<?= count($cat_items) ?> items)
                                            <span class="category-total"><?= $currency ?> <?= number_format(roundTo50($cat_total), 0) ?></span>
                                        </td>
                                    </tr>
                                    <?php foreach ($cat_items as $item): 
                                        $item_status = strtolower($item['item_status'] ?? 'pending');
                                        $item_status_class = 'pending';
                                        $item_status_icon = 'fa-clock';
                                        switch ($item_status) {
                                            case 'paid': $item_status_class = 'paid'; $item_status_icon = 'fa-check-circle'; break;
                                            case 'pending': $item_status_class = 'pending'; $item_status_icon = 'fa-clock'; break;
                                            case 'cancelled': $item_status_class = 'cancelled'; $item_status_icon = 'fa-times-circle'; break;
                                        }
                                    ?>
                                        <tr>
                                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_counter++ ?></td>
                                            <td><div style="font-weight:700;font-size:0.78rem;"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></div></td>
                                            <td>
                                                <?php if (!empty($item['item_code'])): ?>
                                                    <span style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-mono);"><?= htmlspecialchars($item['item_code']) ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);font-size:0.68rem;">—</span>
                                                <?php endif; ?>
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
                                    <td style="text-align:center;font-weight:800;color:var(--primary);font-size:0.72rem;">
                                        <?= count($visit['items']) ?> items
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

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
                        <i class="fas fa-check-double"></i>
                        Fully Paid
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="table-card">
            <div class="table-header">
                <span class="title"><i class="fas fa-file-invoice"></i> All Patient Bills</span>
                <span class="count">0 bills</span>
            </div>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No patient bills found for <?= htmlspecialchars($date_label) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- OTC SALES -->
    <div class="table-card" style="background:var(--bg-body);">
        <div class="table-header cyan">
            <div class="header-left">
                <span class="title"><i class="fas fa-cash-register"></i> OTC Sales (Over-The-Counter)</span>
                <span class="count"><i class="fas fa-list"></i> <?= count($otc_sales_list) ?> sales • Total: <?= $currency ?> <?= number_format($otc_revenue, 0) ?></span>
            </div>
            <div class="header-right">
                <div class="header-scroll-buttons">
                    <button type="button" class="header-scroll-btn" onclick="scrollOtcContainer('left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="header-scroll-btn" onclick="scrollOtcContainer('right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
        
        <?php if (count($otc_sales_list) > 0): ?>
            <div id="otcCardsContainer" style="overflow-x: auto; scroll-behavior: smooth; padding: 8px 0; min-width: 100%;">
                <?php foreach ($otc_sales_list as $otc): 
                    $role = strtolower($otc['sold_by_role'] ?? 'user');
                    $name_parts = explode(' ', trim($otc['sold_by_name'] ?? 'N/A'));
                    $initials = count($name_parts) >= 2 ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) : strtoupper(substr($otc['sold_by_name'] ?? 'NA', 0, 2));
                    $items = $otc['items'] ?? [];
                    $item_count = count($items);
                    $otc_status = strtolower($otc['payment_status'] ?? 'paid');
                ?>
                
                <div class="otc-sale-card">
                    
                    <div class="otc-sale-header">
                        <div class="otc-header-left">
                            <span class="otc-sale-id-badge">
                                <i class="fas fa-receipt"></i> <?= htmlspecialchars($otc['sale_number'] ?? 'N/A') ?>
                            </span>
                            <div class="otc-customer-info">
                                <div class="otc-customer-name">
                                    <i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($otc['customer_name'] ?? 'Walk-in Customer') ?>
                                </div>
                                <?php if (!empty($otc['customer_phone'])): ?>
                                    <div class="otc-customer-phone">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($otc['customer_phone']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="otc-header-middle">
                            <div class="otc-stat-chip discount">
                                <span class="stat-chip-label"><i class="fas fa-tag"></i> Discount</span>
                                <span class="stat-chip-value">
                                    <?php if ((float)($otc['discount_amount'] ?? 0) > 0): ?>
                                        -<?= $currency ?> <?= number_format((float)$otc['discount_amount'], 0) ?>
                                    <?php else: ?>
                                        <?= $currency ?> 0
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="otc-stat-chip premium">
                                <span class="stat-chip-label"><i class="fas fa-star"></i> Premium</span>
                                <span class="stat-chip-value">
                                    <?php if ((float)($otc['premium_amount'] ?? 0) > 0): ?>
                                        +<?= $currency ?> <?= number_format((float)$otc['premium_amount'], 0) ?>
                                    <?php else: ?>
                                        <?= $currency ?> 0
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="otc-stat-chip grand-total">
                                <span class="stat-chip-label"><i class="fas fa-calculator"></i> Grand Total</span>
                                <span class="stat-chip-value"><?= $currency ?> <?= number_format((float)$otc['total_amount'], 0) ?></span>
                            </div>
                        </div>
                        
                        <div class="otc-header-right">
                            <a href="view_otc.php?id=<?= (int)$otc['sale_id'] ?>" class="otc-action-btn view" title="View OTC Sale">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <div class="otc-scroll-buttons">
                                <button type="button" class="otc-scroll-btn" onclick="scrollOtcCard(this, 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                                <button type="button" class="otc-scroll-btn" onclick="scrollOtcCard(this, 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <div style="overflow-x:auto; scroll-behavior:smooth;" class="otc-items-wrapper">
                        <table class="otc-items-table" style="min-width:1100px;">
                            <thead>
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Item Name</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:right;">Unit Price</th>
                                    <th style="text-align:right;">Total Price</th>
                                    <th>Sold By</th>
                                    <th>Payment Method</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($item_count > 0): ?>
                                    <?php $item_num = 1; foreach ($items as $item): ?>
                                        <tr>
                                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_num++ ?></td>
                                            <td>
                                                <div class="otc-item-name-cell">
                                                    <span class="item-icon"><i class="fas fa-capsules"></i></span>
                                                    <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                                </div>
                                            </td>
                                            <td style="text-align:center;">
                                                <span class="otc-qty-badge"><?= (int)($item['quantity'] ?? 0) ?></span>
                                            </td>
                                            <td class="money-cell cyan" style="font-size:0.78rem;">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['unit_price'] ?? 0), 0) ?>
                                            </td>
                                            <td class="money-cell" style="font-size:0.78rem;font-weight:900;">
                                                <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['total_price'] ?? 0), 0) ?>
                                            </td>
                                            <td>
                                                <div class="received-by">
                                                    <div class="received-by-avatar cyan"><?= htmlspecialchars($initials) ?></div>
                                                    <div class="received-by-info">
                                                        <span class="received-by-name"><?= htmlspecialchars($otc['sold_by_name'] ?? 'N/A') ?></span>
                                                        <span class="received-by-role"><span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $otc['payment_method'] ?? 'Cash'))) ?></span>
                                            </td>
                                            <td>
                                                <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                                <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($otc['created_at'] ?? 'now')) ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center;color:var(--text-secondary);font-style:italic;padding:20px;">No items recorded for this sale</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="otc-sale-footer">
                        <div class="otc-footer-info">
                            <span class="otc-footer-stat">
                                <i class="fas fa-list-ul" style="color:var(--cyan);"></i>
                                Items: <strong><?= $item_count ?></strong>
                            </span>
                            <span class="otc-footer-stat">
                                <i class="fas fa-store-alt" style="color:var(--primary);"></i>
                                Branch: <strong><?= htmlspecialchars($otc['branch_name'] ?? 'N/A') ?></strong>
                            </span>
                            <span class="otc-footer-stat">
                                <i class="fas fa-credit-card" style="color:var(--primary);"></i>
                                Subtotal: <strong><?= $currency ?> <?= number_format((float)($otc['subtotal'] ?? 0), 0) ?></strong>
                            </span>
                        </div>
                        <div class="otc-footer-info">
                            <?php if ($otc_status === 'paid'): ?>
                                <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                            <?php elseif ($otc_status === 'partial'): ?>
                                <span class="status-badge pending"><i class="fas fa-hourglass-half"></i> PARTIAL</span>
                            <?php else: ?>
                                <span class="status-badge pending"><i class="fas fa-clock"></i> <?= strtoupper($otc_status) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-cash-register"></i>
                <p>No OTC sales found for <?= htmlspecialchars($date_label) ?></p>
            </div>
        <?php endif; ?>
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
                        <td class="money-cell cyan"><span class="currency-prefix"><?= $currency ?></span><?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($otc_count) ?></td>
                    </tr>
                    <tr style="background:var(--primary-bg);">
                        <td colspan="4" style="padding:10px 16px;font-weight:800;font-size:0.75rem;color:var(--primary);text-transform:uppercase;">
                            <i class="fas fa-info-circle"></i> Patient Payments Breakdown (BILL ITEMS GROSS)
                        </td>
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
                        <td style="padding-left:20px;"><i class="fas fa-tag" style="color:var(--warning);"></i> <strong style="color:var(--warning);">Total Discounts</strong> (Pharm: <?= number_format($pharmacy_discounts, 0) ?> + Cashier: <?= number_format($cashier_discounts, 0) ?>)</td>
                        <td class="money-cell warning">-<?= number_format($total_discounts, 0) ?></td>
                        <td style="text-align:right;color:var(--warning);">—</td>
                        <td style="text-align:right;color:var(--warning);">—</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($total_premiums > 0): ?>
                    <tr style="background:var(--purple-bg);">
                        <td style="padding-left:20px;"><i class="fas fa-star" style="color:var(--purple);"></i> <strong style="color:var(--purple);">Total Premiums</strong> (Pharm: <?= number_format($pharmacy_premiums, 0) ?> + Cashier: <?= number_format($cashier_premiums, 0) ?>)</td>
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
                        <th style="text-align:center;">View</th>
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
                            <td><span class="expense-ref-badge"><?= htmlspecialchars($exp['expense_number'] ?? 'N/A') ?></span></td>
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
                                    <a href="view_expense.php?id=<?= $exp['id'] ?>" class="btn-action view" title="View Expense"><i class="fas fa-eye"></i></a>
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
                        <td style="font-weight:600;font-size:0.88rem;padding:16px 20px;"><i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:10px;"></i> Total Revenue</td>
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

<script>
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

function scrollOtcContainer(direction) {
    var container = document.getElementById('otcCardsContainer');
    if (!container) return;
    var amount = 500;
    container.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function scrollOtcCard(btn, direction) {
    var card = btn.closest('.otc-sale-card');
    if (!card) return;
    var wrapper = card.querySelector('.otc-items-wrapper');
    if (!wrapper) return;
    var amount = 400;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#94A3B8' : '#64748B';
    var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
    
    var ctxM = document.getElementById('monthlyChart')?.getContext('2d');
    if (ctxM && typeof Chart !== 'undefined') {
        new Chart(ctxM, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthly_labels) ?>,
                datasets: [
                    { label: 'Patient Payments', data: <?= json_encode($monthly_patient) ?>, backgroundColor: '#0B5ED7', borderRadius: 6 },
                    { label: 'OTC Sales', data: <?= json_encode($monthly_otc) ?>, backgroundColor: '#0891B2', borderRadius: 6 },
                    { label: 'Expenses', data: <?= json_encode($monthly_expenses) ?>, backgroundColor: '#DC2626', borderRadius: 6 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Inter', size: 11, weight: '700' }, boxWidth: 12, padding: 12, color: textColor } },
                    tooltip: { backgroundColor: '#0B5ED7', callbacks: { label: function(c) { return c.dataset.label + ': <?= $currency ?> ' + c.raw.toLocaleString(); } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return (v/1000).toFixed(0) + 'K'; }, color: textColor }, grid: { color: gridColor } },
                    x: { grid: { display: false }, ticks: { color: textColor } }
                }
            }
        });
    }
    
    var ctxD = document.getElementById('dailyChart')?.getContext('2d');
    if (ctxD && typeof Chart !== 'undefined') {
        new Chart(ctxD, {
            type: 'line',
            data: {
                labels: <?= json_encode($daily_labels) ?>,
                datasets: [
                    { label: 'Patient Payments', data: <?= json_encode($daily_patient) ?>, borderColor: '#0B5ED7', backgroundColor: 'rgba(11,94,215,0.1)', fill: true, tension: 0.4, borderWidth: 2.5 },
                    { label: 'OTC Sales', data: <?= json_encode($daily_otc) ?>, borderColor: '#0891B2', backgroundColor: 'rgba(8,145,178,0.1)', fill: true, tension: 0.4, borderWidth: 2.5 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Inter', size: 11, weight: '700' }, color: textColor } },
                    tooltip: { backgroundColor: '#0B5ED7', callbacks: { label: function(c) { return c.dataset.label + ': <?= $currency ?> ' + c.raw.toLocaleString(); } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return (v/1000).toFixed(0) + 'K'; }, color: textColor }, grid: { color: gridColor } },
                    x: { grid: { display: false }, ticks: { color: textColor, maxTicksLimit: 15 } }
                }
            }
        });
    }
});

console.log('%c📊 Revenue Report - BRANCH LOCKED V2 (ALIGNED WITH DASHBOARD)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#F59E0B; font-weight:bold;');
console.log('%c✅ Branch: <?= htmlspecialchars($branch_name_display) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Patient Payments: patient_id + visit_id + NOT BILL-OTC-%', 'font-size:12px; color:#10B981; font-weight:bold;');
console.log('%c✅ OTC: paid + partial', 'font-size:12px; color:#10B981; font-weight:bold;');
console.log('%c💰 Total Revenue: <?= $currency ?> <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c📊 Patient Payments: <?= $currency ?> <?= number_format($patient_bills_revenue_rounded, 0) ?>', 'font-size:13px; color:#059669; font-weight:bold;');
console.log('%c🛒 OTC Revenue: <?= $currency ?> <?= number_format($otc_revenue, 0) ?>', 'font-size:13px; color:#0891B2; font-weight:bold;');
</script>

</body>
</html>