<?php
// ================================================================
// FILE: frontend/pages/cashier/pending_bills.php
// CASHIER - PENDING BILLS LIST v2.4 - COMPACT DESIGN
// ================================================================
// ✅ V2.4: Summary cards - COMPACT (ndogo)
// ✅ V2.4: Stats grid - CARD MOJA (compact)
// ✅ V2.4: Table - Combined Discount & Premium columns
// ✅ V2.4: Filter buttons - compact pill-style
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';

$pending_bills = [];
$total_pending_amount = 0;
$total_bills_count = 0;
$currency = 'TSh';
$all_bills = [];
$patient_bills = [];

try {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }

    // ================================================================
    // BUILD FILTERS
    // ================================================================
    $date_condition = "";
    $params = [];

    switch ($filter) {
        case 'today':    $date_condition = "AND DATE(b.created_at) = CURDATE()"; break;
        case 'week':     $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
        case 'month':    $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
        case '3months':  $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)"; break;
        case '6months':  $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)"; break;
        case 'year':     $date_condition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_condition = "AND DATE(b.created_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            break;
        default: $date_condition = ""; break;
    }

    $search_condition = "";
    if (!empty($search)) {
        $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // ================================================================
    // GET PENDING BILLS
    // ================================================================
    $sql = "
        SELECT
            b.*,
            b.subtotal, b.total_amount, b.paid_amount, b.balance,
            b.total_discount, b.discount_amount,
            b.pharmacy_discount, b.cashier_discount,
            b.premium_amount, b.pharmacy_premium, b.cashier_premium,
            b.premium_note, b.pharmacy_premium_note, b.cashier_premium_note,
            p.full_name as patient_name,
            p.patient_id as patient_id_number,
            p.phone, p.gender, p.date_of_birth,
            u.full_name as created_by_name,
            v.visit_number, v.visit_type, v.status as visit_status,
            'regular' as bill_type,
            NULL as customer_name,
            NULL as otc_sale_id,
            NULL as otc_discount,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = b.id) as total_paid,
            (SELECT COUNT(*)
             FROM bill_items bi2
             JOIN prescriptions pr ON bi2.reference_id = pr.id
             WHERE bi2.bill_id = b.id
             AND bi2.item_type = 'medication'
             AND bi2.reference_type = 'prescription'
             AND pr.status IN ('confirmed', 'dispensed')
            ) as confirmed_prescriptions,
            (SELECT COUNT(*)
             FROM bill_items bi2
             JOIN prescriptions pr ON bi2.reference_id = pr.id
             WHERE bi2.bill_id = b.id
             AND bi2.item_type = 'medication'
             AND bi2.reference_type = 'prescription'
             AND pr.status NOT IN ('confirmed', 'dispensed')
            ) as pending_prescriptions
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ?
        AND b.status = 'pending'
        AND b.balance > 0
        AND b.visit_id IS NOT NULL
        $date_condition
        $search_condition
        ORDER BY b.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $exec_params = [$user_branch_id];
    foreach ($params as $param) {
        $exec_params[] = $param;
    }
    $stmt->execute($exec_params);
    $regular_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // GET OTC SALES
    // ================================================================
    $otc_sql = "
        SELECT
            o.id, o.sale_number, o.customer_name, o.customer_phone, o.patient_id,
            o.subtotal, o.discount_amount as otc_discount, o.total_amount, o.bill_id,
            o.payment_method, o.payment_status, o.sold_by, o.branch_id, o.notes,
            o.created_at, o.updated_at,
            o.premium_amount, o.premium_note,
            'otc' as bill_type,
            o.customer_name as patient_name,
            CONCAT('OTC-', o.id) as patient_id_number,
            o.customer_phone as phone,
            NULL as gender, NULL as date_of_birth,
            u.full_name as created_by_name,
            NULL as visit_number, NULL as visit_type, NULL as visit_status,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            0 as payment_count,
            0 as total_paid,
            0 as pharmacy_discount,
            0 as cashier_discount,
            0 as pharmacy_premium,
            0 as cashier_premium,
            0 as confirmed_prescriptions,
            0 as pending_prescriptions,
            o.id as otc_sale_id,
            o.sale_number as bill_number,
            o.discount_amount as discount_amount
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        WHERE o.branch_id = ?
        AND o.payment_status = 'pending'
        AND o.total_amount > 0
    ";

    $otc_params = [$user_branch_id];
    $otc_date_condition = "";

    if ($filter === 'custom' && !empty($start_date) && !empty($end_date)) {
        $otc_date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
        $otc_params[] = $start_date;
        $otc_params[] = $end_date;
    } elseif ($filter !== 'all' && $filter !== 'custom') {
        switch ($filter) {
            case 'today':    $otc_date_condition = "AND DATE(o.created_at) = CURDATE()"; break;
            case 'week':     $otc_date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
            case 'month':    $otc_date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
            case '3months':  $otc_date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)"; break;
            case '6months':  $otc_date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)"; break;
            case 'year':     $otc_date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
        }
    }

    if (!empty($otc_date_condition)) {
        $otc_sql .= " $otc_date_condition";
    }

    if (!empty($search)) {
        $otc_sql .= " AND (o.customer_name LIKE ? OR o.sale_number LIKE ? OR o.customer_phone LIKE ?)";
        $otc_params[] = "%$search%";
        $otc_params[] = "%$search%";
        $otc_params[] = "%$search%";
    }

    $otc_sql .= " ORDER BY o.created_at DESC";

    $stmt = $db->prepare($otc_sql);
    $stmt->execute($otc_params);
    $otc_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // GET OTC ITEMS
    // ================================================================
    foreach ($otc_bills as &$otc) {
        $stmt = $db->prepare("
            SELECT id, sale_id, patient_id, inventory_id, medicine_name, item_name,
                   quantity, unit_price, total_price, instructions, branch_id, created_at
            FROM otc_sale_items
            WHERE sale_id = ?
        ");
        $stmt->execute([$otc['id']]);
        $otc['otc_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $item_names = [];
        foreach ($otc['otc_items'] as $item) {
            $item_names[] = $item['item_name'] ?? $item['medicine_name'] ?? 'Unknown';
        }
        $otc['item_names'] = implode(', ', $item_names);
    }
    unset($otc);

    $all_bills = array_merge($regular_bills, $otc_bills);

    // ================================================================
    // GROUP BILLS BY PATIENT
    // ================================================================
    $patient_bills = [];
    foreach ($all_bills as $bill) {
        if ($bill['bill_type'] !== 'otc') {
            $balance = ($bill['total_amount'] ?? 0) - ($bill['total_paid'] ?? 0);
            if ($balance <= 0) continue;
        }

        $patient_key = $bill['bill_type'] === 'otc' ? 'otc_' . $bill['id'] : $bill['patient_id'];
        $patient_name = $bill['bill_type'] === 'otc' ? ($bill['customer_name'] ?? 'OTC Customer') : ($bill['patient_name'] ?? 'Unknown Patient');

        if (!isset($patient_bills[$patient_key])) {
            $patient_bills[$patient_key] = [
                'patient_id' => $patient_key,
                'patient_name' => $patient_name,
                'patient_id_number' => $bill['bill_type'] === 'otc' ? ($bill['sale_number'] ?? 'OTC-' . $bill['id']) : ($bill['patient_id_number'] ?? 'N/A'),
                'phone' => $bill['phone'] ?? 'N/A',
                'gender' => $bill['gender'] ?? 'N/A',
                'date_of_birth' => $bill['date_of_birth'] ?? null,
                'is_otc' => ($bill['bill_type'] === 'otc'),
                'bills' => [],
                'total_amount' => 0, 'total_subtotal' => 0, 'total_balance' => 0, 'total_paid' => 0,
                'total_pharmacy_discount' => 0, 'total_cashier_discount' => 0, 'total_discount' => 0,
                'total_pharmacy_premium' => 0, 'total_cashier_premium' => 0, 'total_premium' => 0,
                'bill_count' => 0, 'total_pending_prescriptions' => 0, 'total_confirmed_prescriptions' => 0,
                'latest_created_at' => $bill['created_at'],
                'oldest_created_at' => $bill['created_at'],
            ];
        }

        if (!empty($bill['created_at'])) {
            if (strtotime($bill['created_at']) > strtotime($patient_bills[$patient_key]['latest_created_at'])) {
                $patient_bills[$patient_key]['latest_created_at'] = $bill['created_at'];
            }
            if (strtotime($bill['created_at']) < strtotime($patient_bills[$patient_key]['oldest_created_at'])) {
                $patient_bills[$patient_key]['oldest_created_at'] = $bill['created_at'];
            }
        }

        $patient_bills[$patient_key]['bills'][] = $bill;
        $patient_bills[$patient_key]['total_amount'] += $bill['total_amount'];
        $patient_bills[$patient_key]['total_subtotal'] += ($bill['subtotal'] ?? $bill['total_amount'] ?? 0);
        $patient_bills[$patient_key]['total_balance'] += ($bill['total_amount'] - ($bill['total_paid'] ?? 0));
        $patient_bills[$patient_key]['total_paid'] += ($bill['total_paid'] ?? 0);

        $pharmacy_disc = (float)($bill['pharmacy_discount'] ?? 0);
        if ($pharmacy_disc == 0 && $bill['bill_type'] !== 'otc' && (float)($bill['discount_amount'] ?? 0) > 0) {
            $pharmacy_disc = (float)$bill['discount_amount'];
        }
        if ($bill['bill_type'] === 'otc') $pharmacy_disc = 0;
        $patient_bills[$patient_key]['total_pharmacy_discount'] += $pharmacy_disc;

        $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
        if ($bill['bill_type'] === 'otc') $cashier_disc = (float)($bill['otc_discount'] ?? $bill['discount_amount'] ?? 0);
        $patient_bills[$patient_key]['total_cashier_discount'] += $cashier_disc;

        $total_disc = (float)($bill['total_discount'] ?? 0);
        if ($total_disc == 0) $total_disc = $pharmacy_disc + $cashier_disc;
        $patient_bills[$patient_key]['total_discount'] += $total_disc;

        $pharmacy_prem = (float)($bill['pharmacy_premium'] ?? 0);
        if ($pharmacy_prem == 0 && $bill['bill_type'] !== 'otc' && (float)($bill['premium_amount'] ?? 0) > 0) {
            $pharmacy_prem = (float)$bill['premium_amount'];
        }
        if ($bill['bill_type'] === 'otc') $pharmacy_prem = 0;
        $patient_bills[$patient_key]['total_pharmacy_premium'] += $pharmacy_prem;

        $cashier_prem = (float)($bill['cashier_premium'] ?? 0);
        if ($bill['bill_type'] === 'otc') $cashier_prem = (float)($bill['premium_amount'] ?? 0);
        $patient_bills[$patient_key]['total_cashier_premium'] += $cashier_prem;

        $total_prem = (float)($bill['premium_amount'] ?? 0);
        if ($total_prem == 0) $total_prem = $pharmacy_prem + $cashier_prem;
        $patient_bills[$patient_key]['total_premium'] += $total_prem;

        $patient_bills[$patient_key]['bill_count']++;
        $patient_bills[$patient_key]['total_pending_prescriptions'] += ($bill['pending_prescriptions'] ?? 0);
        $patient_bills[$patient_key]['total_confirmed_prescriptions'] += ($bill['confirmed_prescriptions'] ?? 0);
    }

    $total_bills_count = count($all_bills);
    $total_pending_amount = 0;
    foreach ($patient_bills as $patient) {
        $total_pending_amount += $patient['total_balance'];
    }

    // ================================================================
    // GLOBAL SUMMARY
    // ================================================================
    $summary_pharmacy_discount = 0;
    $summary_cashier_discount = 0;
    $summary_total_discount = 0;
    $summary_pharmacy_premium = 0;
    $summary_cashier_premium = 0;
    $summary_total_premium = 0;
    $summary_paid = 0;
    $summary_balance = 0;
    $summary_subtotal = 0;
    $summary_total_amount = 0;

    foreach ($patient_bills as $patient) {
        $summary_pharmacy_discount += $patient['total_pharmacy_discount'] ?? 0;
        $summary_cashier_discount += $patient['total_cashier_discount'] ?? 0;
        $summary_total_discount += $patient['total_discount'] ?? 0;
        $summary_pharmacy_premium += $patient['total_pharmacy_premium'] ?? 0;
        $summary_cashier_premium += $patient['total_cashier_premium'] ?? 0;
        $summary_total_premium += $patient['total_premium'] ?? 0;
        $summary_paid += $patient['total_paid'] ?? 0;
        $summary_balance += $patient['total_balance'] ?? 0;
        $summary_subtotal += $patient['total_subtotal'] ?? 0;
        $summary_total_amount += $patient['total_amount'] ?? 0;
    }

    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    $today = date('Y-m-d');

    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total FROM bills WHERE branch_id = ? AND DATE(updated_at) = ? AND paid_amount > 0 AND status = 'paid'");
    $stmt->execute([$user_branch_id, $today]);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_payments_count = $today_payments['count'] ?? 0;
    $today_payments_total = $today_payments['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total FROM bills WHERE branch_id = ? AND status = 'paid'");
    $stmt->execute([$user_branch_id]);
    $paid_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $paid_bills_count = $paid_bills['count'] ?? 0;
    $paid_bills_total = $paid_bills['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM bills WHERE branch_id = ? AND status = 'cancelled'");
    $stmt->execute([$user_branch_id]);
    $cancelled_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancelled_bills_count = $cancelled_stats['count'] ?? 0;
    $cancelled_bills_total = $cancelled_stats['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM bills WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_bills_count_all = $total_bills['count'] ?? 0;
    $total_bills_amount = $total_bills['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance), 0) as total_balance FROM bills WHERE branch_id = ? AND status = 'partial' AND balance > 0");
    $stmt->execute([$user_branch_id]);
    $partial_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_bills_count = $partial_bills['count'] ?? 0;
    $partial_bills_paid = $partial_bills['total_paid'] ?? 0;
    $partial_bills_balance = $partial_bills['total_balance'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM otc_sales WHERE branch_id = ? AND payment_status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $otc_pending = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_pending_count = $otc_pending['count'] ?? 0;
    $otc_pending_total = $otc_pending['total'] ?? 0;

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $all_bills = [];
    $patient_bills = [];
    $total_pending_amount = 0;
    $total_bills_count = 0;
    error_log("Pending bills error: " . $e->getMessage());
}

$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bills - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --info: #0B5ED7;
            --info-bg: #E8F0FE;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --otc-color: #8B5CF6;
            --otc-bg: #EDE9FE;
            --locked-color: #DC2626;
            --locked-bg: #FEE2E2;
            --premium-color: #D97706;
            --premium-bg: #FEF3C7;
            --cashier-discount: #2563EB;
            --cashier-discount-bg: #DBEAFE;
            --cashier-premium: #7C3AED;
            --cashier-premium-bg: #EDE9FE;
            --white: #FFFFFF;
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
            --deep-green: #064E3B;
            --deep-green-dark: #042F2E;
            --deep-green-light: #0D9488;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --font-mono: 'JetBrains Mono', monospace;
            --radius: 16px;
            --radius-sm: 10px;
            --radius-xs: 6px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --info-bg: #1E3A5F;
            --purple-bg: #2A1A3A;
            --otc-bg: #2A1A3A;
            --locked-bg: #3A1A1A;
            --premium-bg: #3D2E0A;
            --cashier-discount-bg: #1E3A5F;
            --cashier-premium-bg: #2A1A3A;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --deep-green: #0D9488;
            --deep-green-dark: #0F766E;
            --deep-green-light: #14B8A6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .mono {
            font-family: var(--font-mono) !important;
            font-feature-settings: 'tnum';
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }

        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header {
            background: linear-gradient(135deg, #059669 0%, #0B5ED7 50%, #7C3AED 100%);
            border-radius: var(--radius);
            padding: 22px 28px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.25);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .header-content { position: relative; z-index: 1; flex: 1; min-width: 250px; }

        .page-header .page-title {
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-header .page-title i { font-size: 1.5rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.15);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .page-header .header-badge.locked {
            background: rgba(239, 68, 68, 0.35);
            border-color: rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }
        .page-header .header-badge.confirmed {
            background: rgba(52, 211, 153, 0.25);
            border-color: rgba(52, 211, 153, 0.2);
            color: #6EE7B7;
        }
        .page-header .header-badge.pending-only {
            background: rgba(217, 119, 6, 0.4);
            border-color: rgba(217, 119, 6, 0.3);
            color: #FCD34D;
        }
        .page-header .header-badge.otc {
            background: rgba(139, 92, 246, 0.35);
            border-color: rgba(139, 92, 246, 0.3);
            color: #DDD6FE;
        }

        .page-header .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(6px);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.75rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            cursor: pointer;
            white-space: nowrap;
            position: relative;
            z-index: 1;
        }
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* ============================================================
           FILTER SECTION - COMPACT
           ============================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            margin-bottom: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .filter-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(180deg, #059669, #0B5ED7, #7C3AED);
        }

        .filter-section:hover {
            border-color: #059669;
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.1);
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding-left: 6px;
        }

        .filter-row .filter-label {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1.5px solid var(--border-color);
            font-family: var(--font-mono);
        }

        .filter-row .filter-label i {
            color: #059669;
            font-size: 0.75rem;
        }

        .filter-btn {
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 800;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            font-family: var(--font-mono);
        }

        .filter-btn:hover {
            border-color: #059669;
            color: #059669;
            background: var(--success-bg);
            transform: translateY(-1px);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border-color: #059669;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        }

        .filter-btn i { font-size: 0.65rem; }

        .filter-divider {
            width: 1.5px;
            height: 24px;
            background: linear-gradient(180deg, transparent, var(--border-color), transparent);
            margin: 0 6px;
        }

        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            background: var(--bg-body);
            padding: 6px 10px;
            border-radius: 10px;
            border: 1.5px dashed #059669;
        }

        .date-picker-group .form-control {
            padding: 6px 10px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.7rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.25s;
            width: auto;
            font-family: var(--font-mono);
            font-weight: 700;
        }

        .date-picker-group .form-control:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }

        .date-picker-group .arrow-sep {
            color: #059669;
            font-size: 0.85rem;
            font-weight: 800;
        }

        .date-picker-group .btn-apply {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 800;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: var(--font-mono);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
        }

        .date-picker-group .btn-apply:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(5, 150, 105, 0.4);
        }

        .date-picker-group .btn-apply.btn-clear {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            box-shadow: 0 3px 10px rgba(220, 38, 38, 0.3);
        }

        .date-picker-group .btn-apply.btn-clear:hover {
            box-shadow: 0 5px 14px rgba(220, 38, 38, 0.4);
        }

        .date-picker-group .btn-apply i { font-size: 0.65rem; }

        /* ============================================================
           SUMMARY CARDS - COMPACT
           ============================================================ */
        .summary-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .summary-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            padding: 14px 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            transition: width 0.35s ease;
        }

        .summary-card.pharmacy-card::before {
            background: linear-gradient(180deg, #F59E0B, #D97706, #B45309);
        }
        .summary-card.cashier-card::before {
            background: linear-gradient(180deg, #8B5CF6, #7C3AED, #6D28D9);
        }

        .summary-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .summary-card:hover::before { width: 6px; }

        .summary-card.pharmacy-card {
            border-color: #D97706;
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(217, 119, 6, 0.04) 100%);
        }
        .summary-card.cashier-card {
            border-color: #7C3AED;
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(124, 58, 237, 0.04) 100%);
        }

        .summary-card .summary-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1.5px solid var(--border-color);
        }

        .summary-card .summary-header .summary-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .summary-card.pharmacy-card .summary-header .summary-icon {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
            box-shadow: 0 3px 10px rgba(217, 119, 6, 0.35);
        }
        .summary-card.cashier-card .summary-header .summary-icon {
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            color: white;
            box-shadow: 0 3px 10px rgba(124, 58, 237, 0.35);
        }

        .summary-card .summary-header .summary-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .summary-card .summary-header .summary-subtitle {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-top: 1px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .summary-card .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .summary-card .summary-item {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 8px 10px;
            border: 1.5px solid var(--border-color);
            transition: all 0.25s ease;
        }
        .summary-card .summary-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.06);
        }

        .summary-card.pharmacy-card .summary-item.discount-item { border-left: 3px solid #D97706; }
        .summary-card.pharmacy-card .summary-item.premium-item { border-left: 3px solid #F59E0B; }
        .summary-card.cashier-card .summary-item.discount-item { border-left: 3px solid #2563EB; }
        .summary-card.cashier-card .summary-item.premium-item { border-left: 3px solid #7C3AED; }

        .summary-card .summary-item .item-label {
            font-size: 0.55rem;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 2px;
        }
        .summary-card .summary-item .item-label i { font-size: 0.6rem; }

        .summary-card .summary-item .item-value {
            font-size: 0.82rem;
            font-weight: 800;
            font-family: var(--font-mono);
            color: var(--text-primary);
            line-height: 1.2;
        }

        .summary-card .summary-item .item-value.pharm-discount { color: #D97706; }
        .summary-card .summary-item .item-value.pharm-premium { color: #F59E0B; }
        .summary-card .summary-item .item-value.cashier-discount { color: #2563EB; }
        .summary-card .summary-item .item-value.cashier-premium { color: #7C3AED; }

        .summary-card .summary-item .item-count {
            font-size: 0.5rem;
            color: var(--text-secondary);
            margin-top: 2px;
            font-weight: 600;
            font-family: var(--font-mono);
        }

        .summary-card .summary-footer {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1.5px dashed var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        .summary-card .summary-footer .footer-label {
            font-size: 0.55rem;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .summary-card .summary-footer .footer-value {
            font-size: 0.85rem;
            font-weight: 900;
            font-family: var(--font-mono);
        }
        .summary-card.pharmacy-card .summary-footer .footer-value { color: #D97706; }
        .summary-card.cashier-card .summary-footer .footer-value { color: #7C3AED; }

        /* ============================================================
           STATS GRID - COMPACT (Card Moja)
           ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 16px;
            background: var(--bg-card);
            border-radius: 16px;
            padding: 14px;
            border: 2px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 10px 12px;
            border: 1.5px solid var(--border-color);
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 12px 12px 0 0;
        }

        .stat-card.orange::before { background: linear-gradient(90deg, #F59E0B, #D97706); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3B82F6, #0B5ED7); }
        .stat-card.purple::before { background: linear-gradient(90deg, #A78BFA, #7C3AED); }
        .stat-card.locked::before { background: linear-gradient(90deg, #EF4444, #DC2626); }
        .stat-card.pharm-discount::before { background: linear-gradient(90deg, #F59E0B, #D97706); }
        .stat-card.cashier-discount::before { background: linear-gradient(90deg, #60A5FA, #2563EB); }
        .stat-card.pharm-premium::before { background: linear-gradient(90deg, #FBBF24, #F59E0B); }
        .stat-card.cashier-premium::before { background: linear-gradient(90deg, #A78BFA, #7C3AED); }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            border-color: var(--success);
        }

        .stat-card .stat-icon {
            font-size: 1.1rem;
            line-height: 1;
            display: block;
        }

        .stat-card .stat-number {
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            font-family: var(--font-mono);
            line-height: 1.1;
        }

        .stat-card .stat-number.orange { color: #D97706; }
        .stat-card .stat-number.blue { color: #0B5ED7; }
        .stat-card .stat-number.purple { color: #7C3AED; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.pharm-discount { color: #D97706; }
        .stat-card .stat-number.cashier-discount { color: #2563EB; }
        .stat-card .stat-number.pharm-premium { color: #F59E0B; }
        .stat-card .stat-number.cashier-premium { color: #7C3AED; }

        .stat-card .stat-label {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1.25;
        }

        /* ============================================================
           COMBINED CELLS (Discount & Premium)
           ============================================================ */
        .combined-cell {
            display: flex;
            flex-direction: column;
            gap: 3px;
            font-family: var(--font-mono);
            font-size: 0.62rem;
            line-height: 1.2;
        }
        .combined-cell .combined-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding: 2px 0;
        }
        .combined-cell .combined-line .combined-label {
            font-size: 0.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 6px;
            border-radius: 6px;
            white-space: nowrap;
        }
        .combined-cell .combined-line .combined-label.pharm {
            background: #FEF3C7; color: #D97706; border: 1px solid #D97706;
        }
        .combined-cell .combined-line .combined-label.cashier {
            background: #DBEAFE; color: #2563EB; border: 1px solid #2563EB;
        }
        .combined-cell .combined-line .combined-label.pharm-prem {
            background: #FEF3C7; color: #D97706; border: 1px solid #D97706;
        }
        .combined-cell .combined-line .combined-label.cashier-prem {
            background: #EDE9FE; color: #7C3AED; border: 1px solid #7C3AED;
        }
        .combined-cell .combined-line .combined-value {
            font-weight: 800;
            font-size: 0.65rem;
            white-space: nowrap;
        }
        .combined-cell .combined-line .combined-value.pharm { color: #D97706; }
        .combined-cell .combined-line .combined-value.cashier { color: #2563EB; }
        .combined-cell .combined-line .combined-value.pharm-prem { color: #D97706; }
        .combined-cell .combined-line .combined-value.cashier-prem { color: #7C3AED; }

        [data-theme="dark"] .combined-cell .combined-line .combined-label.pharm {
            background: #3D2E0A; color: #F59E0B; border-color: #D97706;
        }
        [data-theme="dark"] .combined-cell .combined-line .combined-label.cashier {
            background: #1E3A5F; color: #93C5FD; border-color: #2563EB;
        }
        [data-theme="dark"] .combined-cell .combined-line .combined-label.pharm-prem {
            background: #3D2E0A; color: #F59E0B; border-color: #D97706;
        }
        [data-theme="dark"] .combined-cell .combined-line .combined-label.cashier-prem {
            background: #2A1A3A; color: #C4B5FD; border-color: #7C3AED;
        }

        .combined-dash {
            color: var(--text-secondary);
            font-size: 0.65rem;
            text-align: center;
            display: block;
            opacity: 0.6;
        }

        /* ============================================================
           PATIENT CARDS
           ============================================================ */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: var(--transition);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .patient-card:hover {
            border-color: var(--success);
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.12);
        }

        .patient-card.otc-card:hover {
            border-color: #8B5CF6;
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.12);
        }

        .patient-card.otc-card .patient-card-header {
            border-left: 5px solid #8B5CF6;
        }

        .patient-card-header {
            padding: 16px 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-card);
            border-bottom: 1px solid transparent;
        }

        .patient-card-header:hover {
            background: var(--primary-bg);
        }

        .patient-card.otc-card .patient-card-header:hover {
            background: var(--purple-bg);
        }

        .patient-card-header.expanded {
            border-bottom-color: var(--border-color);
            background: var(--primary-bg);
        }

        .patient-card.otc-card .patient-card-header.expanded {
            background: var(--purple-bg);
        }

        .patient-card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 200px;
        }

        .patient-card-header .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 3px solid rgba(255,255,255,0.2);
        }

        .patient-card-header .patient-avatar.regular {
            background: linear-gradient(135deg, var(--success), var(--primary));
        }

        .patient-card-header .patient-avatar.otc {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
        }

        .patient-card-header .patient-name {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .patient-card-header .patient-id {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: var(--font-mono);
            font-weight: 600;
        }

        .patient-card-header .patient-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 5px;
        }

        .patient-card-header .patient-meta i {
            color: var(--success);
            font-size: 0.7rem;
        }

        .patient-card-header .datetime-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.35);
            white-space: nowrap;
            font-family: var(--font-mono);
            letter-spacing: -0.02em;
        }

        .patient-card-header .datetime-badge .separator {
            opacity: 0.5;
            margin: 0 2px;
        }

        .patient-card-header .datetime-badge i {
            font-size: 0.65rem;
            color: white;
        }

        .patient-card.otc-card .patient-card-header .datetime-badge {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            box-shadow: 0 3px 10px rgba(139, 92, 246, 0.35);
        }

        [data-theme="dark"] .patient-card-header .datetime-badge {
            background: linear-gradient(135deg, #1E3A5F, #0A4CA8);
        }

        [data-theme="dark"] .patient-card.otc-card .patient-card-header .datetime-badge {
            background: linear-gradient(135deg, #4C1D95, #6D28D9);
        }

        .patient-card-header .patient-totals {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .patient-card-header .total-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: var(--font-mono);
            letter-spacing: -0.01em;
            border: 1.5px solid transparent;
        }

        .total-badge.orange { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .total-badge.green { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .total-badge.red { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .total-badge.blue { background: var(--info-bg); color: var(--info); border-color: var(--info); }
        .total-badge.purple { background: var(--purple-bg); color: var(--purple); border-color: var(--purple); }
        .total-badge.discount { background: #FEF3C7; color: #D97706; border-color: #D97706; }
        .total-badge.cashier-discount { background: #DBEAFE; color: #2563EB; border-color: #2563EB; }
        .total-badge.locked { background: var(--locked-bg); color: var(--locked-color); border-color: var(--locked-color); }
        .total-badge.premium { background: var(--premium-bg); color: var(--premium-color); border-color: var(--premium-color); }
        .total-badge.cashier-premium { background: #EDE9FE; color: #7C3AED; border-color: #7C3AED; }

        .patient-card-header .total-amount {
            font-weight: 900;
            font-size: 1.1rem;
            color: var(--danger);
            font-family: var(--font-mono);
            letter-spacing: -0.02em;
        }

        .otc-tag {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 6px rgba(139, 92, 246, 0.35);
        }

        [data-theme="dark"] .otc-tag {
            background: linear-gradient(135deg, #6D28D9, #5B21B6);
            color: #DDD6FE;
        }

        .locked-tag {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
        }

        .chevron-icon {
            transition: transform 0.35s ease;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .chevron-icon.rotated { transform: rotate(180deg); }

        .patient-card-body {
            overflow: hidden;
            transition: max-height 0.45s ease, padding 0.3s ease;
            max-height: 0;
            padding: 0 22px;
            background: var(--bg-card);
        }

        .patient-card-body.open {
            max-height: 5000px;
            padding: 16px 22px 22px;
        }

        /* ============================================================
           TABLE
           ============================================================ */
        .table-header-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            padding: 0 4px;
        }

        .table-scroll-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: all 0.25s ease;
            padding: 0;
            flex-shrink: 0;
        }

        .table-scroll-btn:hover {
            background: var(--success);
            color: white;
            border-color: var(--success);
            transform: scale(1.12);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        }

        .table-scroll-btn:active { transform: scale(0.9); }

        .table-scroll-hint {
            font-size: 0.6rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
            margin-right: auto;
            padding-left: 6px;
            opacity: 0.75;
            font-weight: 600;
        }

        .table-scroll-hint i { font-size: 0.65rem; }

        [data-theme="dark"] .table-scroll-btn:hover {
            background: #0D9488;
            border-color: #0D9488;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            position: relative;
            scroll-behavior: smooth;
        }

        .table-wrap::-webkit-scrollbar { height: 8px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb:hover { background: var(--success-dark); }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
            min-width: 1100px;
        }

        .data-table thead th {
            text-align: left;
            padding: 12px 14px;
            font-weight: 800;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: white;
            background: linear-gradient(135deg, #064E3B, #065F46, #0D9488);
            border-bottom: 3px solid #14B8A6;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .data-table thead th:first-child { border-radius: var(--radius-xs) 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 var(--radius-xs) 0 0; }

        .data-table.otc-table thead th {
            background: linear-gradient(135deg, #4C1D95, #6D28D9, #8B5CF6);
            border-bottom: 3px solid #A78BFA;
        }

        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-family: var(--font-mono);
            font-size: 0.7rem;
        }

        .data-table tbody tr { transition: var(--transition); }
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table .bill-number {
            font-weight: 700;
            font-size: 0.68rem;
            font-family: var(--font-mono);
            color: var(--text-primary);
        }

        .locked-row td {
            background: var(--locked-bg) !important;
            opacity: 0.75;
        }

        [data-theme="dark"] .locked-row td {
            background: #3A1A1A !important;
        }

        .otc-item-list {
            font-size: 0.62rem;
            color: var(--text-secondary);
            max-width: 200px;
        }

        .otc-item-list .item-tag {
            display: inline-block;
            background: var(--purple-bg);
            color: var(--purple);
            padding: 2px 10px;
            border-radius: 10px;
            margin: 2px 2px;
            font-size: 0.58rem;
            font-weight: 700;
            border: 1px solid var(--purple);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1.5px solid transparent;
        }

        .status-badge.pending { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .status-badge.partial { background: var(--info-bg); color: var(--info); border-color: var(--info); }
        .status-badge.paid { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .status-badge.otc-pending { background: var(--purple-bg); color: var(--purple); border-color: var(--purple); }
        .status-badge.locked { background: var(--locked-bg); color: var(--locked-color); border-color: var(--locked-color); }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: stretch;
            min-width: 75px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: var(--radius-xs);
            font-weight: 800;
            font-size: 0.6rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            text-align: center;
            white-space: nowrap;
            min-height: 30px;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .btn i { font-size: 0.6rem; }
        .btn-view { background: var(--primary); color: white; }
        .btn-view:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35); }
        .btn-process { background: linear-gradient(135deg, #059669, #047857); color: white; }
        .btn-process:hover { background: linear-gradient(135deg, #047857, #065F46); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4); }
        .btn-process:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        .btn-otc { background: linear-gradient(135deg, #8B5CF6, #6D28D9); color: white; }
        .btn-otc:hover { background: linear-gradient(135deg, #6D28D9, #5B21B6); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }

        .action-status {
            font-size: 0.55rem;
            padding: 6px 8px;
            text-align: center;
            border-radius: var(--radius-xs);
            background: var(--gray-100);
            color: var(--text-secondary);
            display: block;
            width: 100%;
            min-height: 30px;
            line-height: 1.3;
            font-weight: 700;
        }

        .patient-total-row {
            background: linear-gradient(135deg, var(--primary-bg), var(--bg-card));
            font-weight: 800;
        }

        .patient-total-row td {
            border-top: 3px solid var(--primary);
            padding: 10px 14px;
            font-size: 0.72rem;
        }

        .patient-total-row.otc-total {
            background: linear-gradient(135deg, var(--purple-bg), var(--bg-card));
        }

        .patient-total-row.otc-total td {
            border-top-color: #7C3AED;
        }

        .message-box {
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            border: 2px solid transparent;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .message-box.success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .message-box.error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .message-box.warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .message-box i { font-size: 1.1rem; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 2px dashed var(--border-color);
        }
        .empty-state i { font-size: 3.5rem; color: var(--success); display: block; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-primary); margin-bottom: 8px; font-weight: 800; }
        .empty-state p { color: var(--text-secondary); font-size: 0.85rem; }

        .footer {
            padding: 16px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .brand { color: var(--success); font-weight: 700; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(20px) scale(0.9); }
            30% { opacity: 1; transform: translateY(0) scale(1); }
            80% { opacity: 1; transform: translateY(0) scale(1); }
            100% { opacity: 0; transform: translateY(-15px) scale(0.9); }
        }

        .scroll-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 12px 22px;
            border-radius: 24px;
            font-size: 0.82rem;
            font-weight: 700;
            z-index: 9999;
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeInOut 1.2s ease forwards;
            pointer-events: none;
        }

        .scroll-toast i { font-size: 1rem; }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .summary-section { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .page-header { padding: 16px 20px; }
            .page-header .page-title { font-size: 1.2rem; }
            .filter-section { padding: 12px 14px; }
            .filter-row { padding-left: 0; }
            .filter-btn { padding: 5px 12px; font-size: 0.65rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .summary-section { grid-template-columns: 1fr; }
            .patient-card-header { flex-direction: column; align-items: flex-start; }
            .patient-card-header .patient-totals { width: 100%; justify-content: flex-start; }
            .data-table { min-width: 900px; }
            .action-buttons { min-width: 65px; }
            .table-scroll-btn { width: 26px; height: 26px; font-size: 0.65rem; }
            .table-scroll-hint { font-size: 0.55rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-divider { display: none; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .table-scroll-btn { width: 24px; height: 24px; font-size: 0.6rem; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Pending Bills
                <span class="role-badge"><?= strtoupper($user_role) ?></span>
                <span class="header-badge pending-only">
                    <i class="fas fa-filter"></i> PENDING ONLY
                </span>
                <span class="header-badge locked">
                    <i class="fas fa-lock"></i> Prescriptions Locked
                </span>
                <span class="header-badge confirmed">
                    <i class="fas fa-check-circle"></i> Confirmed Ready
                </span>
                <?php if ($is_admin): ?>
                    <span class="role-badge" style="background:rgba(124,58,237,0.4);">
                        <i class="fas fa-user-shield"></i> Admin
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="role-badge" style="background:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> Reception
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                Manage pending bills at <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i> <?= $total_bills_count ?> Bills
                </span>
                <span class="header-badge">
                    <i class="fas fa-users"></i> <?= count($patient_bills) ?> Patients/Customers
                </span>
                <span class="header-badge otc">
                    <i class="fas fa-shopping-cart"></i> OTC: <?= $otc_pending_count ?? 0 ?>
                </span>
            </p>
        </div>
        <div class="header-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         FILTER SECTION - COMPACT
         ============================================================ -->
    <div class="filter-section">
        <div class="filter-row">
            <span class="filter-label">
                <i class="fas fa-calendar-alt"></i> Filter:
            </span>

            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 7D
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1M
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3M
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6M
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1Y
            </a>

            <span class="filter-divider"></span>

            <form method="GET" action="" class="date-picker-group">
                <input type="hidden" name="filter" value="custom">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" placeholder="Start">
                <span class="arrow-sep">→</span>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" placeholder="End">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply btn-clear">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ============================================================
         SUMMARY CARDS - COMPACT
         ============================================================ -->
    <div class="summary-section">

        <!-- PHARMACY SUMMARY -->
        <div class="summary-card pharmacy-card">
            <div class="summary-header">
                <div class="summary-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <div>
                    <div class="summary-title">💊 Pharmacy</div>
                    <div class="summary-subtitle">Discount & Premium</div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-item discount-item">
                    <div class="item-label">
                        <i class="fas fa-tag"></i> Discount
                    </div>
                    <div class="item-value pharm-discount">
                        <?= $currency ?> <?= number_format($summary_pharmacy_discount, 0) ?>
                    </div>
                    <div class="item-count">
                        <?php
                            $pharm_disc_count = 0;
                            foreach ($patient_bills as $p) {
                                foreach ($p['bills'] as $b) {
                                    if ((float)($b['pharmacy_discount'] ?? 0) > 0) $pharm_disc_count++;
                                }
                            }
                            echo $pharm_disc_count . ' bill(s)';
                        ?>
                    </div>
                </div>

                <div class="summary-item premium-item">
                    <div class="item-label">
                        <i class="fas fa-crown"></i> Premium
                    </div>
                    <div class="item-value pharm-premium">
                        <?= $currency ?> <?= number_format($summary_pharmacy_premium, 0) ?>
                    </div>
                    <div class="item-count">
                        <?php
                            $pharm_prem_count = 0;
                            foreach ($patient_bills as $p) {
                                foreach ($p['bills'] as $b) {
                                    if ((float)($b['pharmacy_premium'] ?? 0) > 0) $pharm_prem_count++;
                                }
                            }
                            echo $pharm_prem_count . ' bill(s)';
                        ?>
                    </div>
                </div>
            </div>

            <div class="summary-footer">
                <span class="footer-label">
                    <i class="fas fa-calculator"></i> Net Impact
                </span>
                <span class="footer-value">
                    +<?= $currency ?> <?= number_format($summary_pharmacy_premium - $summary_pharmacy_discount, 0) ?>
                </span>
            </div>
        </div>

        <!-- CASHIER SUMMARY -->
        <div class="summary-card cashier-card">
            <div class="summary-header">
                <div class="summary-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div>
                    <div class="summary-title">💰 Cashier</div>
                    <div class="summary-subtitle">Discount & Premium</div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-item discount-item">
                    <div class="item-label">
                        <i class="fas fa-tag"></i> Discount
                    </div>
                    <div class="item-value cashier-discount">
                        <?= $currency ?> <?= number_format($summary_cashier_discount, 0) ?>
                    </div>
                    <div class="item-count">
                        <?php
                            $cashier_disc_count = 0;
                            foreach ($patient_bills as $p) {
                                foreach ($p['bills'] as $b) {
                                    if ((float)($b['cashier_discount'] ?? 0) > 0) $cashier_disc_count++;
                                }
                            }
                            echo $cashier_disc_count . ' bill(s)';
                        ?>
                    </div>
                </div>

                <div class="summary-item premium-item">
                    <div class="item-label">
                        <i class="fas fa-crown"></i> Premium
                    </div>
                    <div class="item-value cashier-premium">
                        <?= $currency ?> <?= number_format($summary_cashier_premium, 0) ?>
                    </div>
                    <div class="item-count">
                        <?php
                            $cashier_prem_count = 0;
                            foreach ($patient_bills as $p) {
                                foreach ($p['bills'] as $b) {
                                    if ((float)($b['cashier_premium'] ?? 0) > 0) $cashier_prem_count++;
                                }
                            }
                            echo $cashier_prem_count . ' bill(s)';
                        ?>
                    </div>
                </div>
            </div>

            <div class="summary-footer">
                <span class="footer-label">
                    <i class="fas fa-calculator"></i> Net Impact
                </span>
                <span class="footer-value">
                    +<?= $currency ?> <?= number_format($summary_cashier_premium - $summary_cashier_discount, 0) ?>
                </span>
            </div>
        </div>

    </div>

    <!-- ============================================================
         STATS GRID - COMPACT (Card Moja)
         ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card orange">
            <div class="stat-icon">📋</div>
            <div class="stat-number orange mono"><?= $total_bills_count ?></div>
            <div class="stat-label">Total Pending Bills</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon">👤</div>
            <div class="stat-number blue mono"><?= count($patient_bills) ?></div>
            <div class="stat-label">Patients / Customers</div>
        </div>
        <div class="stat-card locked">
            <div class="stat-icon">🔒</div>
            <div class="stat-number red mono">
                <?php
                    $total_locked = 0;
                    foreach ($patient_bills as $patient) {
                        $total_locked += $patient['total_pending_prescriptions'] ?? 0;
                    }
                    echo $total_locked;
                ?>
            </div>
            <div class="stat-label">Locked Prescriptions</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon">🛒</div>
            <div class="stat-number purple mono"><?= $otc_pending_count ?? 0 ?></div>
            <div class="stat-label">OTC Sales Pending</div>
        </div>

        <div class="stat-card pharm-discount">
            <div class="stat-icon">🏷️</div>
            <div class="stat-number pharm-discount mono"><?= number_format($summary_pharmacy_discount, 0) ?></div>
            <div class="stat-label">Pharmacy Discount</div>
        </div>
        <div class="stat-card cashier-discount">
            <div class="stat-icon">🏷️</div>
            <div class="stat-number cashier-discount mono"><?= number_format($summary_cashier_discount, 0) ?></div>
            <div class="stat-label">Cashier Discount</div>
        </div>
        <div class="stat-card pharm-premium">
            <div class="stat-icon">👑</div>
            <div class="stat-number pharm-premium mono"><?= number_format($summary_pharmacy_premium, 0) ?></div>
            <div class="stat-label">Pharmacy Premium</div>
        </div>
        <div class="stat-card cashier-premium">
            <div class="stat-icon">👑</div>
            <div class="stat-number cashier-premium mono"><?= number_format($summary_cashier_premium, 0) ?></div>
            <div class="stat-label">Cashier Premium</div>
        </div>
    </div>

    <!-- ============================================================
         PATIENT BILLS LIST
         ============================================================ -->
    <?php if (count($patient_bills) > 0): ?>
        <?php foreach ($patient_bills as $patient):
            $is_otc = $patient['is_otc'] ?? false;
            $card_class = $is_otc ? 'otc-card' : '';
            $has_pending_prescriptions = ($patient['total_pending_prescriptions'] ?? 0) > 0;
            $has_confirmed_prescriptions = ($patient['total_confirmed_prescriptions'] ?? 0) > 0;
            $has_pharm_premium = ($patient['total_pharmacy_premium'] ?? 0) > 0;
            $has_cashier_premium = ($patient['total_cashier_premium'] ?? 0) > 0;
            $has_pharm_discount = ($patient['total_pharmacy_discount'] ?? 0) > 0;
            $has_cashier_discount = ($patient['total_cashier_discount'] ?? 0) > 0;
            $table_id = 'table_' . $patient['patient_id'];
        ?>
            <div class="patient-card <?= $card_class ?> animate-fade-in-up">
                <div class="patient-card-header" onclick="togglePatient('<?= $patient['patient_id'] ?>')">
                    <div class="patient-info">
                        <div class="patient-avatar <?= $is_otc ? 'otc' : 'regular' ?>">
                            <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="patient-name">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                                <?php if ($is_otc): ?>
                                    <span class="otc-tag"><i class="fas fa-shopping-cart"></i> OTC</span>
                                <?php endif; ?>
                                <?php if ($has_pending_prescriptions): ?>
                                    <span class="locked-tag"><i class="fas fa-lock"></i> <?= $patient['total_pending_prescriptions'] ?> Locked</span>
                                <?php endif; ?>
                            </div>
                            <div class="patient-meta">
                                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id_number'] ?? 'N/A') ?></span>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                                <?php if ($patient['gender'] && !$is_otc): ?>
                                    <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                                <?php endif; ?>
                                <?php if ($patient['date_of_birth'] && !$is_otc): ?>
                                    <span><i class="fas fa-birthday-cake"></i> <?= date('d/m/Y', strtotime($patient['date_of_birth'])) ?></span>
                                <?php endif; ?>

                                <?php if (!empty($patient['latest_created_at'])):
                                    $latest_date = date('d/m/Y', strtotime($patient['latest_created_at']));
                                    $latest_time = date('h:i A', strtotime($patient['latest_created_at']));
                                    $has_multiple_dates = ($patient['oldest_created_at'] !== $patient['latest_created_at']) && $patient['bill_count'] > 1;
                                ?>
                                    <span class="datetime-badge" title="Latest bill: <?= $latest_date ?> <?= $latest_time ?>">
                                        <i class="fas fa-calendar-alt"></i> <?= $latest_date ?>
                                        <span class="separator">|</span>
                                        <i class="fas fa-clock"></i> <?= $latest_time ?>
                                        <?php if ($has_multiple_dates): ?>
                                            <span class="separator">|</span>
                                            <i class="fas fa-layer-group"></i> <?= $patient['bill_count'] ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="patient-totals">
                        <span class="total-badge orange">
                            <i class="fas fa-file-invoice"></i> <?= $patient['bill_count'] ?> Bills
                        </span>

                        <?php if ($has_pharm_discount): ?>
                            <span class="total-badge discount">
                                <i class="fas fa-tag"></i> Pharm Disc: <?= $currency ?> <?= number_format($patient['total_pharmacy_discount'], 0) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($has_cashier_discount): ?>
                            <span class="total-badge cashier-discount">
                                <i class="fas fa-tag"></i> Cashier Disc: <?= $currency ?> <?= number_format($patient['total_cashier_discount'], 0) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($has_pharm_premium): ?>
                            <span class="total-badge premium">
                                <i class="fas fa-crown"></i> Pharm Prem: <?= $currency ?> <?= number_format($patient['total_pharmacy_premium'], 0) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($has_cashier_premium): ?>
                            <span class="total-badge cashier-premium">
                                <i class="fas fa-crown"></i> Cashier Prem: <?= $currency ?> <?= number_format($patient['total_cashier_premium'], 0) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($patient['total_paid'] > 0): ?>
                            <span class="total-badge green">
                                <i class="fas fa-check-circle"></i> Paid: <?= $currency ?> <?= number_format($patient['total_paid'], 0) ?>
                            </span>
                        <?php endif; ?>

                        <span class="total-amount">
                            <?= $currency ?> <?= number_format($patient['total_amount'], 0) ?>
                        </span>
                        <i class="fas fa-chevron-down chevron-icon" id="chevron_<?= $patient['patient_id'] ?>"></i>
                    </div>
                </div>

                <div class="patient-card-body" id="patient_<?= $patient['patient_id'] ?>">
                    <div class="table-header-actions">
                        <span class="table-scroll-hint">
                            <i class="fas fa-arrows-alt-h"></i>
                            Scroll table:
                        </span>
                        <button class="table-scroll-btn" onclick="scrollTableById('<?= $table_id ?>', 'left')" title="Scroll Left">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="table-scroll-btn" onclick="scrollTableById('<?= $table_id ?>', 'right')" title="Scroll Right">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <div class="table-wrap" id="<?= $table_id ?>">
                        <table class="data-table <?= $is_otc ? 'otc-table' : '' ?>">
                            <thead>
                                <tr>
                                    <th style="width:30px;text-align:center;">#</th>
                                    <th style="min-width:120px;">Bill #</th>
                                    <?php if ($is_otc): ?>
                                        <th style="min-width:150px;">Items</th>
                                    <?php else: ?>
                                        <th style="min-width:80px;">Visit</th>
                                    <?php endif; ?>
                                    <th style="min-width:90px;text-align:right;">Subtotal</th>
                                    <th style="min-width:130px;text-align:right;">Discount</th>
                                    <th style="min-width:130px;text-align:right;">Premium</th>
                                    <th style="min-width:90px;text-align:right;">Paid</th>
                                    <th style="min-width:90px;text-align:right;">Balance</th>
                                    <th style="min-width:70px;text-align:center;">Status</th>
                                    <th style="min-width:40px;text-align:center;">Items</th>
                                    <th style="min-width:90px;">Created</th>
                                    <th style="min-width:80px;text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($patient['bills'] as $bill):
                                    $is_otc_bill = ($bill['bill_type'] ?? '') === 'otc';

                                    $pharm_disc = (float)($bill['pharmacy_discount'] ?? 0);
                                    if ($pharm_disc == 0 && !$is_otc_bill && (float)($bill['discount_amount'] ?? 0) > 0) {
                                        $pharm_disc = (float)$bill['discount_amount'];
                                    }
                                    if ($is_otc_bill) $pharm_disc = 0;

                                    $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
                                    if ($is_otc_bill) {
                                        $cashier_disc = (float)($bill['otc_discount'] ?? $bill['discount_amount'] ?? 0);
                                    }

                                    $pharm_prem = (float)($bill['pharmacy_premium'] ?? 0);
                                    if ($pharm_prem == 0 && !$is_otc_bill && (float)($bill['premium_amount'] ?? 0) > 0) {
                                        $pharm_prem = (float)$bill['premium_amount'];
                                    }
                                    if ($is_otc_bill) $pharm_prem = 0;

                                    $cashier_prem = (float)($bill['cashier_premium'] ?? 0);
                                    if ($is_otc_bill) $cashier_prem = (float)($bill['premium_amount'] ?? 0);

                                    $status = $bill['status'] ?? ($is_otc_bill ? 'pending' : 'pending');
                                    $status_class = $is_otc_bill ? 'otc-pending' : $status;
                                    $bill_balance = ($bill['total_amount'] ?? 0) - ($bill['total_paid'] ?? 0);
                                    $subtotal = $bill['subtotal'] ?? $bill['total_amount'] ?? 0;

                                    $has_pending_prescriptions_bill = ($bill['pending_prescriptions'] ?? 0) > 0;
                                    $has_confirmed_prescriptions_bill = ($bill['confirmed_prescriptions'] ?? 0) > 0;
                                    $is_locked = $has_pending_prescriptions_bill;
                                    $row_class = $is_locked ? 'locked-row' : '';
                                ?>
                                    <tr class="<?= $row_class ?>">
                                        <td style="text-align:center;"><?= $i++ ?></td>
                                        <td>
                                            <span class="bill-number">
                                                <?= $is_otc_bill ? htmlspecialchars($bill['sale_number'] ?? $bill['bill_number'] ?? 'OTC-' . $bill['id']) : htmlspecialchars($bill['bill_number']) ?>
                                            </span>
                                            <?php if ($is_locked): ?>
                                                <span class="locked-tag" style="font-size:0.45rem;padding:2px 6px;display:block;margin-top:3px;">
                                                    <i class="fas fa-lock"></i> <?= $bill['pending_prescriptions'] ?> Pending
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($has_confirmed_prescriptions_bill): ?>
                                                <span style="font-size:0.45rem;color:var(--success);display:block;margin-top:3px;font-weight:700;">
                                                    <i class="fas fa-check-circle"></i> <?= $bill['confirmed_prescriptions'] ?> Confirmed
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($is_otc_bill): ?>
                                            <td>
                                                <div class="otc-item-list">
                                                    <?php
                                                        $items = $bill['otc_items'] ?? [];
                                                        if (count($items) > 0):
                                                            foreach ($items as $item):
                                                    ?>
                                                        <span class="item-tag">
                                                            <?= htmlspecialchars($item['item_name'] ?? $item['medicine_name'] ?? 'Unknown') ?>
                                                            (x<?= $item['quantity'] ?? 1 ?>)
                                                        </span>
                                                    <?php endforeach; else: ?>
                                                        <span style="color:var(--text-secondary);">No items</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <td>
                                                <?php if ($bill['visit_number']): ?>
                                                    <span style="font-size:0.68rem;font-weight:700;"><?= htmlspecialchars($bill['visit_number']) ?></span>
                                                    <span style="font-size:0.55rem;color:var(--text-secondary);display:block;"><?= ucfirst($bill['visit_type'] ?? 'N/A') ?></span>
                                                <?php else: ?>
                                                    <span style="font-size:0.68rem;color:var(--text-secondary);">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>

                                        <td style="text-align:right;">
                                            <span style="font-weight:700;"><?= $currency ?> <?= number_format($subtotal, 0) ?></span>
                                        </td>

                                        <!-- DISCOUNT (Combined) -->
                                        <td style="text-align:right;vertical-align:top;">
                                            <?php if ($pharm_disc > 0 || $cashier_disc > 0): ?>
                                                <div class="combined-cell">
                                                    <?php if ($pharm_disc > 0): ?>
                                                        <div class="combined-line">
                                                            <span class="combined-label pharm"><i class="fas fa-pills"></i> Pharm</span>
                                                            <span class="combined-value pharm"><?= $currency ?> <?= number_format($pharm_disc, 0) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($cashier_disc > 0): ?>
                                                        <div class="combined-line">
                                                            <span class="combined-label cashier"><i class="fas fa-cash-register"></i> Cash</span>
                                                            <span class="combined-value cashier"><?= $currency ?> <?= number_format($cashier_disc, 0) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="combined-dash">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- PREMIUM (Combined) -->
                                        <td style="text-align:right;vertical-align:top;">
                                            <?php if ($pharm_prem > 0 || $cashier_prem > 0): ?>
                                                <div class="combined-cell">
                                                    <?php if ($pharm_prem > 0): ?>
                                                        <div class="combined-line">
                                                            <span class="combined-label pharm-prem"><i class="fas fa-pills"></i> Pharm</span>
                                                            <span class="combined-value pharm-prem"><?= $currency ?> <?= number_format($pharm_prem, 0) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($cashier_prem > 0): ?>
                                                        <div class="combined-line">
                                                            <span class="combined-label cashier-prem"><i class="fas fa-cash-register"></i> Cash</span>
                                                            <span class="combined-value cashier-prem"><?= $currency ?> <?= number_format($cashier_prem, 0) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="combined-dash">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align:right;">
                                            <span style="color:var(--success);font-weight:700;">
                                                <?= $currency ?> <?= number_format($bill['total_paid'] ?? 0, 0) ?>
                                            </span>
                                        </td>

                                        <td style="text-align:right;">
                                            <span style="font-weight:800;color:var(--danger);">
                                                <?= $currency ?> <?= number_format($bill_balance, 0) ?>
                                            </span>
                                        </td>

                                        <td style="text-align:center;">
                                            <?php if ($is_locked): ?>
                                                <span class="status-badge locked">
                                                    <i class="fas fa-lock"></i> Locked
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= $is_otc_bill ? 'Pending (OTC)' : ucfirst($status) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align:center;">
                                            <span style="font-size:0.8rem;font-weight:800;"><?= $bill['item_count'] ?? 0 ?></span>
                                        </td>

                                        <td>
                                            <span style="font-size:0.68rem;font-weight:700;"><?= isset($bill['created_at']) ? date('d/m/Y', strtotime($bill['created_at'])) : 'N/A' ?></span>
                                            <br>
                                            <span style="color:var(--text-secondary);font-size:0.55rem;">
                                                <?= isset($bill['created_at']) ? date('h:i A', strtotime($bill['created_at'])) : '' ?>
                                            </span>
                                        </td>

                                        <td style="text-align:center;">
                                            <div class="action-buttons">
                                                <?php if ($is_otc_bill): ?>
                                                    <a href="view_otc_sale.php?id=<?= $bill['id'] ?>" class="btn btn-otc" title="View OTC Sale">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <?php if (($bill['total_amount'] ?? 0) > 0): ?>
                                                        <a href="process_otc_payment.php?sale_id=<?= $bill['id'] ?>" class="btn btn-process" title="Process OTC Payment">
                                                            <i class="fas fa-money-bill-wave"></i> Pay
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-view" title="View Details">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>

                                                    <?php if (!$is_locked && ($bill['total_amount'] ?? 0) > 0 && $bill_balance > 0): ?>
                                                        <a href="process_payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-process" title="Process Payment">
                                                            <i class="fas fa-money-bill-wave"></i> Pay
                                                        </a>
                                                    <?php elseif ($is_locked): ?>
                                                        <span class="action-status" title="Prescription pending confirmation">
                                                            <i class="fas fa-lock"></i> Wait Pharm
                                                        </span>
                                                    <?php elseif ($bill_balance <= 0): ?>
                                                        <span class="action-status" style="background:var(--success-bg);color:var(--success);border:1px solid var(--success);">
                                                            <i class="fas fa-check-circle"></i> Paid
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="patient-total-row <?= $is_otc ? 'otc-total' : '' ?>">
                                    <td colspan="2" style="text-align:right;font-size:0.72rem;">
                                        <i class="fas fa-calculator"></i> <?= $is_otc ? 'Customer' : 'Patient' ?> Total:
                                    </td>
                                    <td></td>
                                    <td style="text-align:right;font-weight:800;">
                                        <?= $currency ?> <?= number_format($patient['total_subtotal'] ?? $patient['total_amount'], 0) ?>
                                    </td>
                                    
                                    <!-- Combined Discount Total -->
                                    <td style="text-align:right;vertical-align:top;">
                                        <?php if ($patient['total_pharmacy_discount'] > 0 || $patient['total_cashier_discount'] > 0): ?>
                                            <div class="combined-cell">
                                                <?php if ($patient['total_pharmacy_discount'] > 0): ?>
                                                    <div class="combined-line">
                                                        <span class="combined-label pharm"><i class="fas fa-pills"></i> Pharm</span>
                                                        <span class="combined-value pharm"><?= $currency ?> <?= number_format($patient['total_pharmacy_discount'], 0) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($patient['total_cashier_discount'] > 0): ?>
                                                    <div class="combined-line">
                                                        <span class="combined-label cashier"><i class="fas fa-cash-register"></i> Cash</span>
                                                        <span class="combined-value cashier"><?= $currency ?> <?= number_format($patient['total_cashier_discount'], 0) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="combined-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Combined Premium Total -->
                                    <td style="text-align:right;vertical-align:top;">
                                        <?php if ($patient['total_pharmacy_premium'] > 0 || $patient['total_cashier_premium'] > 0): ?>
                                            <div class="combined-cell">
                                                <?php if ($patient['total_pharmacy_premium'] > 0): ?>
                                                    <div class="combined-line">
                                                        <span class="combined-label pharm-prem"><i class="fas fa-pills"></i> Pharm</span>
                                                        <span class="combined-value pharm-prem"><?= $currency ?> <?= number_format($patient['total_pharmacy_premium'], 0) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($patient['total_cashier_premium'] > 0): ?>
                                                    <div class="combined-line">
                                                        <span class="combined-label cashier-prem"><i class="fas fa-cash-register"></i> Cash</span>
                                                        <span class="combined-value cashier-prem"><?= $currency ?> <?= number_format($patient['total_cashier_premium'], 0) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="combined-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="text-align:right;font-weight:800;"><?= $currency ?> <?= number_format($patient['total_paid'], 0) ?></td>
                                    <td style="text-align:right;color:var(--danger);font-weight:800;"><?= $currency ?> <?= number_format($patient['total_balance'], 0) ?></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding-top:12px;display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--border-color);margin-top:12px;">
                        <?php if ($is_otc): ?>
                            <a href="otc_sales.php?customer=<?= urlencode($patient['patient_name']) ?>" class="btn btn-otc" style="width:auto;padding:7px 16px;">
                                <i class="fas fa-shopping-cart"></i> All OTC Sales
                            </a>
                        <?php else: ?>
                            <a href="patient_bills.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-view" style="width:auto;padding:7px 16px;">
                                <i class="fas fa-file-invoice"></i> All Bills
                            </a>
                            <?php
                                $has_locked = false;
                                $has_unpaid = false;
                                foreach ($patient['bills'] as $bill) {
                                    if (($bill['pending_prescriptions'] ?? 0) > 0) $has_locked = true;
                                    $bal = ($bill['total_amount'] ?? 0) - ($bill['total_paid'] ?? 0);
                                    if ($bal > 0 && ($bill['pending_prescriptions'] ?? 0) == 0) $has_unpaid = true;
                                }
                            ?>
                            <?php if ($has_unpaid && !$has_locked && !$is_admin): ?>
                                <a href="process_payment.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-process" style="width:auto;padding:7px 16px;">
                                    <i class="fas fa-money-bill-wave"></i> Pay All
                                </a>
                            <?php elseif ($has_locked): ?>
                                <span class="action-status" style="width:auto;padding:7px 16px;background:var(--locked-bg);color:var(--locked-color);border:1px solid var(--locked-color);">
                                    <i class="fas fa-lock"></i> Wait for Prescription Confirmation
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3>No Pending Bills</h3>
            <p>All bills have been cleared or paid. Check back later for new pending bills.</p>
            <p style="font-size:0.75rem;margin-top:8px;color:var(--text-secondary);">
                <i class="fas fa-info-circle"></i> Only bills with <strong>status = PENDING</strong> are shown here.
            </p>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="brand">Braick Dispensary</span> Management System
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            Pending Bills v2.4 (Compact)
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:700;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<script>
    function scrollTableById(tableId, direction) {
        var wrapper = document.getElementById(tableId);
        if (!wrapper) return;
        var scrollAmount = 300;
        if (direction === 'left') {
            wrapper.scrollLeft -= scrollAmount;
        } else {
            wrapper.scrollLeft += scrollAmount;
        }
        var msg = direction === 'left' ? '⬅️ Scrolled Left' : '➡️ Scrolled Right';
        showScrollToast(msg);
    }

    function showScrollToast(message) {
        var existing = document.querySelector('.scroll-toast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.className = 'scroll-toast';
        toast.innerHTML = '<i class="fas fa-arrows-alt-h"></i> ' + message;
        document.body.appendChild(toast);
        setTimeout(function() {
            if (toast.parentNode) toast.remove();
        }, 1200);
    }

    (function() {
        var html = document.documentElement;
        function syncDark() {
            if (localStorage.getItem('darkMode') === 'true') {
                html.setAttribute('data-theme', 'dark');
            } else {
                html.removeAttribute('data-theme');
            }
        }
        syncDark();
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDark();
        });
    })();

    function togglePatient(patientId) {
        var body = document.getElementById('patient_' + patientId);
        var chevron = document.getElementById('chevron_' + patientId);
        var header = body ? body.closest('.patient-card').querySelector('.patient-card-header') : null;
        if (body) {
            body.classList.toggle('open');
            if (header) header.classList.toggle('expanded');
        }
        if (chevron) chevron.classList.toggle('rotated');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var firstBody = document.querySelector('.patient-card-body');
        var firstHeader = document.querySelector('.patient-card-header');
        var firstChevron = document.querySelector('.chevron-icon');
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstHeader) firstHeader.classList.add('expanded');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
        setTimeout(function() { window.location.reload(); }, 800);
    }

    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('footerTimestamp');
        if (el) el.textContent = timeStr;
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    console.log('%c🏥 Braick - Pending Bills v2.4 (Compact)', 'font-size:16px;font-weight:bold;color:#059669;');
    console.log('%c✅ Summary cards - COMPACT (ndogo)', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
    console.log('%c✅ Stats grid - CARD MOJA (compact)', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
    console.log('%c✅ Table - Combined Discount & Premium columns', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
    console.log('%c✅ Filter buttons - compact pill-style', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
</script>

</body>
</html>