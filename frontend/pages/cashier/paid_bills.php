<?php
// ================================================================
// FILE: frontend/pages/cashier/paid_bills.php
// V9 FINAL: Received By (Grouped) + Payment Dates + Scroll Buttons
// ================================================================
// ✅ V9: Scroll buttons < > kwenye search bar (kulia)
// ✅ V9: Search bar imepunguzwa ukubwa
// ✅ V8: Received By inaonyesha majina YOTE + (count received)
// ✅ V8: Kama user mmoja: SALOME SANGA (2 received)
// ✅ V8: Kama users 2: SALOME SANGA (1), LUCY MUSSA (1)
// ✅ V8: Expand → unaona kila payment na tarehe yake
// ✅ V8: HAKUNA amounts
// ✅ V8: Rangi za Regular/OTC/Equipment
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+00:00'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$premium_filter = isset($_GET['premium_filter']) ? $_GET['premium_filter'] : 'all';

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {
    $currency = 'TSh';
}

// DATE FILTER
$date_condition_bills = "";
$date_condition_otc = "";
$params_bills = [$user_branch_id];
$params_otc = [$user_branch_id];

switch ($filter) {
    case 'today':
        $date_condition_bills = "AND DATE(p.received_at) = CURDATE()";
        $date_condition_otc = "AND DATE(o.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition_bills = "AND DATE(p.received_at) BETWEEN ? AND ?";
            $date_condition_otc = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params_bills[] = $start_date;
            $params_bills[] = $end_date;
            $params_otc[] = $start_date;
            $params_otc[] = $end_date;
        }
        break;
    default:
        $date_condition_bills = "";
        $date_condition_otc = "";
        break;
}

// PREMIUM/DISCOUNT FILTER
$premium_discount_condition = "";
if ($premium_filter === 'pharm_premium') $premium_discount_condition = " AND b.pharmacy_premium > 0";
elseif ($premium_filter === 'cashier_premium') $premium_discount_condition = " AND b.cashier_premium > 0";
elseif ($premium_filter === 'any_premium') $premium_discount_condition = " AND (b.pharmacy_premium > 0 OR b.cashier_premium > 0)";
elseif ($premium_filter === 'pharm_discount') $premium_discount_condition = " AND b.pharmacy_discount > 0";
elseif ($premium_filter === 'cashier_discount') $premium_discount_condition = " AND b.cashier_discount > 0";
elseif ($premium_filter === 'any_discount') $premium_discount_condition = " AND (b.pharmacy_discount > 0 OR b.cashier_discount > 0)";
elseif ($premium_filter === 'both_premium') $premium_discount_condition = " AND b.pharmacy_premium > 0 AND b.cashier_premium > 0";
elseif ($premium_filter === 'both_discount') $premium_discount_condition = " AND b.pharmacy_discount > 0 AND b.cashier_discount > 0";

// ================================================================
// ✅ V9: GET GROUPED BILLS
// ================================================================
$grouped_bills = [];
$total_bills = 0;

try {
    // 1. REGULAR BILLS
    $sql_bills = "
        SELECT 
            b.id as bill_id,
            b.bill_number,
            b.patient_id,
            b.visit_id,
            b.status as bill_status,
            b.premium_amount,
            b.pharmacy_premium,
            b.cashier_premium,
            b.pharmacy_discount,
            b.cashier_discount,
            b.discount_amount,
            b.created_at as bill_created,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            v.visit_number,
            v.visit_date,
            COUNT(p.id) as payment_count,
            MAX(p.received_at) as last_payment_date,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            'Regular' as bill_type,
            'regular' as row_type
        FROM bills b
        LEFT JOIN payments p ON b.id = p.bill_id
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ? 
          AND b.patient_id IS NOT NULL
          AND b.visit_id IS NOT NULL
          AND b.bill_number NOT LIKE 'BILL-OTC-%'
          $date_condition_bills
          $premium_discount_condition
        GROUP BY b.id
        HAVING payment_count > 0
    ";
    
    $stmt = $db->prepare($sql_bills);
    $stmt->execute($params_bills);
    $bill_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bill_rows as $row) {
        $pharm_disc = (float)($row['pharmacy_discount'] ?? 0);
        if ($pharm_disc == 0 && (float)($row['discount_amount'] ?? 0) > 0) {
            $pharm_disc = (float)$row['discount_amount'];
        }
        $row['pharmacy_discount'] = $pharm_disc;

        $pharm_prem = (float)($row['pharmacy_premium'] ?? 0);
        if ($pharm_prem == 0 && (float)($row['premium_amount'] ?? 0) > 0) {
            $pharm_prem = (float)$row['premium_amount'];
        }
        $row['pharmacy_premium'] = $pharm_prem;
        
        // Get all payments for this bill
        $stmt_payments = $db->prepare("
            SELECT 
                p.id,
                p.receipt_number,
                p.amount,
                p.payment_method,
                p.reference_number,
                p.notes,
                p.received_at,
                u.full_name as received_by_name,
                u.role as received_by_role
            FROM payments p
            LEFT JOIN users u ON p.received_by = u.id
            WHERE p.bill_id = ?
            ORDER BY p.received_at ASC
        ");
        $stmt_payments->execute([$row['bill_id']]);
        $payments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);
        
        $row['payments'] = $payments;
        $row['payment_count'] = count($payments);
        
        // Build received_by_summary (GROUPED)
        $received_by_summary = [];
        foreach ($payments as $pmt) {
            $name = $pmt['received_by_name'] ?? 'Unknown';
            $role = strtolower($pmt['received_by_role'] ?? 'user');
            
            if (!isset($received_by_summary[$name])) {
                $received_by_summary[$name] = [
                    'name' => $name,
                    'role' => $role,
                    'count' => 0
                ];
            }
            $received_by_summary[$name]['count']++;
        }
        $row['received_by_summary'] = array_values($received_by_summary);
        
        if (!empty($payments)) {
            $row['last_payment_date'] = end($payments)['received_at'];
        }
        
        // Build search data
        $search_data = strtolower(
            ($row['bill_number'] ?? '') . ' ' .
            ($row['patient_name'] ?? '') . ' ' .
            ($row['patient_phone'] ?? '') . ' ' .
            ($row['visit_number'] ?? '')
        );
        
        foreach ($payments as $pmt) {
            $search_data .= ' ' . strtolower($pmt['receipt_number'] ?? '');
            $search_data .= ' ' . strtolower($pmt['received_by_name'] ?? '');
        }
        
        $row['search_data'] = $search_data;
        
        $grouped_bills[] = $row;
    }

    // 2. OTC SALES
    $sql_otc = "
        SELECT 
            o.id as bill_id,
            o.sale_number as bill_number,
            o.customer_name as patient_name,
            o.customer_phone as patient_phone,
            o.discount_amount as cashier_discount,
            o.payment_method,
            o.created_at as last_payment_date,
            o.sold_by as received_by,
            CASE 
                WHEN o.sale_number LIKE 'OTC-EQP-%' THEN 'Equipment'
                ELSE 'OTC'
            END as bill_type,
            CASE 
                WHEN o.sale_number LIKE 'OTC-EQP-%' THEN 'equipment'
                ELSE 'otc'
            END as row_type,
            u_sold.full_name as received_by_name,
            u_sold.role as received_by_role,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count
        FROM otc_sales o
        LEFT JOIN users u_sold ON o.sold_by = u_sold.id
        WHERE o.branch_id = ? 
          AND o.payment_status = 'paid'
          $date_condition_otc
    ";
    
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $otc_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($otc_rows as $row) {
        $row['payments'] = [
            [
                'id' => 0,
                'receipt_number' => $row['bill_number'],
                'amount' => 0,
                'payment_method' => $row['payment_method'],
                'reference_number' => null,
                'notes' => 'OTC Sale',
                'received_at' => $row['last_payment_date'],
                'received_by_name' => $row['received_by_name'],
                'received_by_role' => $row['received_by_role']
            ]
        ];
        $row['payment_count'] = 1;
        $row['first_payment_date'] = $row['last_payment_date'];
        $row['pharmacy_discount'] = 0;
        $row['pharmacy_premium'] = 0;
        $row['cashier_premium'] = 0;
        
        // OTC received_by_summary
        $row['received_by_summary'] = [
            [
                'name' => $row['received_by_name'] ?? 'Unknown',
                'role' => strtolower($row['received_by_role'] ?? 'user'),
                'count' => 1
            ]
        ];
        
        $row['search_data'] = strtolower(
            ($row['bill_number'] ?? '') . ' ' .
            ($row['patient_name'] ?? '') . ' ' .
            ($row['patient_phone'] ?? '') . ' ' .
            ($row['received_by_name'] ?? '')
        );
        
        $grouped_bills[] = $row;
    }

    usort($grouped_bills, function($a, $b) {
        return strtotime($b['last_payment_date'] ?? '1970-01-01') - strtotime($a['last_payment_date'] ?? '1970-01-01');
    });

    $total_bills = count($grouped_bills);

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $grouped_bills = [];
    $total_bills = 0;
}

// SUMMARY COUNTS
$summary_regular_count = 0;
$summary_otc_count = 0;
$summary_equipment_count = 0;
$summary_pharm_premium_count = 0;
$summary_cashier_premium_count = 0;
$summary_pharm_discount_count = 0;
$summary_cashier_discount_count = 0;
$summary_both_premium_count = 0;
$summary_both_discount_count = 0;

foreach ($grouped_bills as $b) {
    $pharm_disc = (float)($b['pharmacy_discount'] ?? 0);
    $cashier_disc = (float)($b['cashier_discount'] ?? 0);
    $pharm_prem = (float)($b['pharmacy_premium'] ?? 0);
    $cashier_prem = (float)($b['cashier_premium'] ?? 0);

    if (($b['bill_type'] ?? '') === 'Regular') $summary_regular_count++;
    if (($b['bill_type'] ?? '') === 'OTC') $summary_otc_count++;
    if (($b['bill_type'] ?? '') === 'Equipment') $summary_equipment_count++;

    if ($pharm_prem > 0) $summary_pharm_premium_count++;
    if ($cashier_prem > 0) $summary_cashier_premium_count++;
    if ($pharm_disc > 0) $summary_pharm_discount_count++;
    if ($cashier_disc > 0) $summary_cashier_discount_count++;
    if ($pharm_prem > 0 && $cashier_prem > 0) $summary_both_premium_count++;
    if ($pharm_disc > 0 && $cashier_disc > 0) $summary_both_discount_count++;
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paid Bills V9 - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-primary: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --primary: #0B5ED7;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --pharm-color: #D97706;
            --pharm-bg: #FEF3C7;
            --cashier-discount-color: #2563EB;
            --cashier-discount-bg: #DBEAFE;
            --cashier-premium-color: #7C3AED;
            --cashier-premium-bg: #EDE9FE;
            --otc-color: #0B5ED7;
            --otc-bg: #DBEAFE;
            --equipment-color: #0891B2;
            --equipment-bg: #CFFAFE;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --otc-bg: #1E3A5F;
            --pharm-bg: #3D2E0A;
            --cashier-discount-bg: #1E3A5F;
            --cashier-premium-bg: #2A1A3A;
            --equipment-bg: #0A2E3A;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }

        .main-content { margin-left: 270px; margin-top: 68px; padding: 28px 32px; min-height: calc(100vh - 68px); }

        .mono { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }

        .page-header {
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 16px; padding: 24px 32px; margin-bottom: 28px;
            display: flex; justify-content: space-between; align-items: center;
            gap: 16px; flex-wrap: wrap;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
            max-width: 1400px; margin-left: auto; margin-right: auto;
        }

        .page-header .page-title { color: white; font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .page-header .role-badge { background: rgba(255,255,255,0.2); color: white; padding: 4px 14px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
        .page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.95rem; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .header-badge { background: rgba(255,255,255,0.15); color: white; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 500; backdrop-filter: blur(4px); display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,0.1); }

        .btn-outline-light { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 18px; border-radius: 10px; font-weight: 500; font-size: 0.82rem; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); cursor: pointer; }
        .btn-outline-light:hover { background: rgba(255,255,255,0.25); transform: translateY(-2px); }

        /* SUMMARY CARDS */
        .summary-cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 16px; max-width: 1400px; margin-left: auto; margin-right: auto; }
        .summary-card { background: var(--bg-card); border-radius: 12px; padding: 12px 14px; border: 2px solid var(--border-color); text-align: center; transition: all 0.3s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.05); position: relative; overflow: hidden; min-height: 82px; display: flex; flex-direction: column; justify-content: center; }
        .summary-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.07); }
        .summary-card .card-label { font-size: 0.55rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; display: block; line-height: 1.15; }
        .summary-card .card-value { font-size: 1.5rem; font-weight: 800; display: block; margin-top: 3px; font-family: var(--font-mono); line-height: 1.1; }
        .summary-card .card-sub { font-size: 0.5rem; color: var(--text-secondary); display: block; margin-top: 2px; font-weight: 500; }

        .summary-card.total-card { border-color: var(--primary); }
        .summary-card.total-card::before { background: var(--primary); }
        .summary-card.total-card .card-value { color: var(--primary); }
        .summary-card.regular-card { border-color: var(--success); }
        .summary-card.regular-card::before { background: var(--success); }
        .summary-card.regular-card .card-value { color: var(--success); }
        .summary-card.otc-card { border-color: var(--otc-color); }
        .summary-card.otc-card::before { background: var(--otc-color); }
        .summary-card.otc-card .card-value { color: var(--otc-color); }
        .summary-card.equipment-card { border-color: var(--equipment-color); }
        .summary-card.equipment-card::before { background: var(--equipment-color); }
        .summary-card.equipment-card .card-value { color: var(--equipment-color); }
        .summary-card.both-card { border-color: var(--danger); }
        .summary-card.both-card::before { background: var(--danger); }
        .summary-card.both-card .card-value { color: var(--danger); }
        .summary-card.pharm-discount-card { border-color: var(--pharm-color); background: linear-gradient(135deg, var(--bg-card) 0%, rgba(217, 119, 6, 0.04) 100%); }
        .summary-card.pharm-discount-card::before { background: var(--pharm-color); }
        .summary-card.pharm-discount-card .card-value { color: var(--pharm-color); }
        .summary-card.cashier-discount-card { border-color: var(--cashier-discount-color); background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 99, 235, 0.04) 100%); }
        .summary-card.cashier-discount-card::before { background: var(--cashier-discount-color); }
        .summary-card.cashier-discount-card .card-value { color: var(--cashier-discount-color); }
        .summary-card.pharm-premium-card { border-color: #F59E0B; background: linear-gradient(135deg, var(--bg-card) 0%, rgba(245, 158, 11, 0.04) 100%); }
        .summary-card.pharm-premium-card::before { background: #F59E0B; }
        .summary-card.pharm-premium-card .card-value { color: #F59E0B; }
        .summary-card.cashier-premium-card { border-color: var(--cashier-premium-color); background: linear-gradient(135deg, var(--bg-card) 0%, rgba(124, 58, 237, 0.04) 100%); }
        .summary-card.cashier-premium-card::before { background: var(--cashier-premium-color); }
        .summary-card.cashier-premium-card .card-value { color: var(--cashier-premium-color); }

        /* FILTER */
        .filter-section { background: var(--bg-card); border-radius: 14px; padding: 14px 18px; border: 1px solid var(--border-color); margin-bottom: 16px; max-width: 1400px; margin-left: auto; margin-right: auto; }
        .filter-btn { padding: 5px 12px; border-radius: 20px; font-size: 0.68rem; font-weight: 500; border: 2px solid var(--border-color); background: transparent; color: var(--text-secondary); cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .filter-btn:hover { border-color: var(--success); color: var(--success); background: var(--success-bg); }
        .filter-btn.active { background: var(--success); color: white; border-color: var(--success); }
        .filter-btn.pharm { border-color: var(--pharm-color); color: var(--pharm-color); }
        .filter-btn.pharm:hover { background: var(--pharm-bg); }
        .filter-btn.pharm.active { background: var(--pharm-color); color: white; }
        .filter-btn.cashier-disc { border-color: var(--cashier-discount-color); color: var(--cashier-discount-color); }
        .filter-btn.cashier-disc:hover { background: var(--cashier-discount-bg); }
        .filter-btn.cashier-disc.active { background: var(--cashier-discount-color); color: white; }
        .filter-btn.cashier-prem { border-color: var(--cashier-premium-color); color: var(--cashier-premium-color); }
        .filter-btn.cashier-prem:hover { background: var(--cashier-premium-bg); }
        .filter-btn.cashier-prem.active { background: var(--cashier-premium-color); color: white; }
        .filter-btn.both { border-color: var(--danger); color: var(--danger); }
        .filter-btn.both:hover { background: var(--danger-bg); }
        .filter-btn.both.active { background: var(--danger); color: white; }
        .filter-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .filter-label { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); margin-right: 4px; }
        .date-picker-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .form-control { padding: 4px 10px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.75rem; background: var(--bg-card); color: var(--text-primary); outline: none; width: auto; }
        .form-control:focus { border-color: var(--success); }
        .btn-apply { padding: 4px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; background: var(--success); color: white; border: none; cursor: pointer; }
        .btn-apply:hover { background: var(--success-dark); }

        /* CARD */
        .card { background: var(--bg-card); border-radius: 16px; padding: 20px 24px; border: 1px solid var(--border-color); max-width: 1400px; margin: 0 auto; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .card-title { font-size: 0.95rem; font-weight: 700; }

        /* ROW LEGEND */
        .row-legend { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 8px; padding: 8px 12px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border-color); }
        .legend-item { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }

        /* ✅ V9: SEARCH BAR with SCROLL BUTTONS */
        .table-search-bar { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-wrap: wrap; 
            margin-bottom: 12px; 
            padding: 10px 14px; 
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5); 
            border: 2px solid var(--success); 
            border-radius: 12px; 
        }
        [data-theme="dark"] .table-search-bar { background: linear-gradient(135deg, #1A3A2A, #0F2A1E); }
        
        .search-wrapper { 
            position: relative; 
            display: flex; 
            align-items: center; 
            background: var(--bg-card); 
            border: 2px solid var(--border-color); 
            border-radius: 10px; 
            padding: 0 12px; 
            flex: 1; 
            min-width: 200px; 
            max-width: 400px;  /* ✅ Punguza ukubwa */
            height: 36px;  /* ✅ Punguza urefu */
        }
        .search-wrapper:focus-within { border-color: var(--success); }
        .search-wrapper .search-icon { color: var(--success); font-size: 0.8rem; margin-right: 8px; }
        .search-wrapper input { flex: 1; background: transparent; border: none; outline: none; color: var(--text-primary); font-size: 0.78rem; padding: 0; font-weight: 500; }
        
        .search-results-count { 
            color: var(--success); 
            font-size: 0.68rem; 
            font-weight: 700; 
            background: var(--bg-card); 
            padding: 5px 10px; 
            border-radius: 8px; 
            white-space: nowrap; 
            border: 1px solid var(--success); 
            display: none; 
        }
        .search-results-count.visible { display: inline-block; }

        /* ✅ V9: SCROLL BUTTONS <> */
        .scroll-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;  /* ✅ Push kwa kulia */
            flex-shrink: 0;
        }
        .scroll-controls .scroll-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-scroll-table {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: 2px solid #059669;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 900;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
            flex-shrink: 0;
        }
        .btn-scroll-table:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.5);
            border-color: #047857;
        }
        .btn-scroll-table:active {
            transform: translateY(0) scale(0.95);
        }
        .btn-scroll-table i { color: white; }

        /* TABLE */
        .table-wrap { overflow-x: auto; scroll-behavior: smooth; }
        .table-wrap::-webkit-scrollbar { height: 8px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; min-width: 1300px; }
        .data-table thead th { text-align: left; padding: 10px 8px; font-weight: 700; font-size: 0.6rem; text-transform: uppercase; color: white; background: var(--success); border-bottom: 3px solid var(--success-dark); white-space: nowrap; }
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        .data-table td { padding: 10px 8px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }

        .bill-row { cursor: pointer; transition: all 0.2s ease; }

        .bill-row.row-regular { background: linear-gradient(90deg, rgba(5, 150, 105, 0.03) 0%, transparent 100%); }
        .bill-row.row-regular:hover td { background: #D1FAE5; }
        [data-theme="dark"] .bill-row.row-regular:hover td { background: #1A3A2A; }

        .bill-row.row-otc { background: linear-gradient(90deg, rgba(11, 94, 215, 0.05) 0%, transparent 100%); border-left: 4px solid #0B5ED7; }
        .bill-row.row-otc:hover td { background: #DBEAFE; }
        [data-theme="dark"] .bill-row.row-otc:hover td { background: #1E3A5F; }

        .bill-row.row-equipment { background: linear-gradient(90deg, rgba(8, 145, 178, 0.05) 0%, transparent 100%); border-left: 4px solid #0891B2; }
        .bill-row.row-equipment:hover td { background: #CFFAFE; }
        [data-theme="dark"] .bill-row.row-equipment:hover td { background: #0A2E3A; }

        .row-number-cell { text-align: center; font-weight: 700; font-size: 0.75rem; color: var(--success); background: var(--success-bg); font-family: var(--font-mono); width: 50px; min-width: 50px; }
        .bill-row.row-otc .row-number-cell { background: #DBEAFE; color: #0B5ED7; }
        .bill-row.row-equipment .row-number-cell { background: #CFFAFE; color: #0891B2; }
        [data-theme="dark"] .row-number-cell { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .bill-row.row-otc .row-number-cell { background: #1E3A5F; color: #60A5FA; }
        [data-theme="dark"] .bill-row.row-equipment .row-number-cell { background: #0A2E3A; color: #22D3EE; }

        .expand-arrow { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--success); color: white; font-size: 0.75rem; transition: all 0.3s ease; }
        .bill-row.row-otc .expand-arrow { background: #0B5ED7; }
        .bill-row.row-equipment .expand-arrow { background: #0891B2; }
        .expand-arrow.rotated { transform: rotate(90deg); }

        .payments-detail-row { display: none; background: var(--gray-100); }
        .payments-detail-row.show { display: table-row; }
        .payments-detail-cell { padding: 16px 24px !important; background: linear-gradient(135deg, #F8FAFC, #F1F5F9); }
        [data-theme="dark"] .payments-detail-cell { background: linear-gradient(135deg, #1A2332, #0F172A); }

        .payment-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-card); border-radius: 10px; margin-bottom: 8px; border: 1px solid var(--border-color); border-left: 4px solid #059669; flex-wrap: wrap; gap: 10px; }
        [data-theme="dark"] .payment-item { border-left-color: #34D399; }
        .payment-item:last-child { margin-bottom: 0; }

        .payment-left { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }

        .payment-index { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #D1FAE5; color: #059669; font-weight: 900; font-size: 0.75rem; font-family: var(--font-mono); }
        [data-theme="dark"] .payment-index { background: #1A3A2A; color: #34D399; }

        .payment-receipt { display: inline-flex; align-items: center; gap: 4px; font-family: var(--font-mono); font-size: 0.7rem; font-weight: 800; color: #059669; background: #D1FAE5; padding: 3px 10px; border-radius: 6px; }
        [data-theme="dark"] .payment-receipt { background: #1A3A2A; color: #34D399; }

        .payment-method { display: inline-flex; align-items: center; gap: 4px; font-size: 0.65rem; font-weight: 700; color: #0B5ED7; background: #DBEAFE; padding: 3px 10px; border-radius: 6px; text-transform: uppercase; }
        [data-theme="dark"] .payment-method { background: #1E3A5F; color: #60A5FA; }

        .payment-date { display: inline-flex; align-items: center; gap: 5px; font-size: 0.72rem; font-weight: 700; color: var(--text-primary); background: var(--gray-100); padding: 3px 10px; border-radius: 6px; font-family: var(--font-mono); }
        [data-theme="dark"] .payment-date { background: #334155; }

        .payment-received-by { display: inline-flex; align-items: center; gap: 4px; font-size: 0.68rem; font-weight: 600; color: #7C3AED; background: #EDE9FE; padding: 3px 10px; border-radius: 6px; }
        [data-theme="dark"] .payment-received-by { background: #2A1A3A; color: #A78BFA; }

        .payment-count-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.68rem; font-weight: 800; color: #D97706; background: #FEF3C7; padding: 3px 10px; border-radius: 10px; border: 1.5px solid #D97706; }
        [data-theme="dark"] .payment-count-badge { background: #3D2E0A; color: #FBBF24; }

        .bill-type-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 0.5rem; font-weight: 700; text-transform: uppercase; }
        .bill-type-badge.regular { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
        .bill-type-badge.otc { background: #DBEAFE; color: #0B5ED7; border: 1px solid #0B5ED7; }
        .bill-type-badge.equipment { background: #CFFAFE; color: #0891B2; border: 1px solid #0891B2; }
        [data-theme="dark"] .bill-type-badge.regular { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .bill-type-badge.otc { background: #1E3A5F; color: #60A5FA; }
        [data-theme="dark"] .bill-type-badge.equipment { background: #0A2E3A; color: #22D3EE; }

        .badge-yes { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.55rem; font-weight: 700; text-transform: uppercase; }
        .badge-yes.pharm { background: var(--pharm-bg); color: var(--pharm-color); border: 1px solid var(--pharm-color); }
        .badge-yes.cashier-disc { background: var(--cashier-discount-bg); color: var(--cashier-discount-color); border: 1px solid var(--cashier-discount-color); }
        .badge-yes.cashier-prem { background: var(--cashier-premium-bg); color: var(--cashier-premium-color); border: 1px solid var(--cashier-premium-color); }
        .col-dash { color: var(--text-secondary); font-size: 0.65rem; text-align: center; display: block; }

        /* RECEIVED BY SUMMARY STYLE */
        .received-by-summary {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .received-by-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 700;
            background: #E8F0FE;
            color: #0B5ED7;
            white-space: nowrap;
        }

        .received-by-item.reception { background: #DBEAFE; color: #1E40AF; }
        .received-by-item.cashier { background: #FEF3C7; color: #D97706; }
        .received-by-item.admin { background: #FCE7F3; color: #BE185D; }
        .received-by-item.pharmacy { background: #D1FAE5; color: #059669; }

        .received-by-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 0.55rem;
            font-weight: 900;
            background: rgba(0,0,0,0.1);
            margin-left: 4px;
            font-family: var(--font-mono);
        }

        .received-by-item.reception .received-by-count { background: rgba(30, 64, 175, 0.2); color: #1E40AF; }
        .received-by-item.cashier .received-by-count { background: rgba(217, 119, 6, 0.2); color: #D97706; }
        .received-by-item.admin .received-by-count { background: rgba(190, 24, 93, 0.2); color: #BE185D; }
        .received-by-item.pharmacy .received-by-count { background: rgba(5, 150, 105, 0.2); color: #059669; }

        .btn-print-action { display: inline-flex; align-items: center; justify-content: center; padding: 5px 12px; border-radius: 8px; font-weight: 600; font-size: 0.65rem; background: #059669; color: white; text-decoration: none; transition: all 0.3s; }
        .btn-print-action:hover { background: var(--success-dark); transform: translateY(-2px); color: white; }
        .btn-print-action.otc { background: #0B5ED7; }
        .btn-print-action.otc:hover { background: #0A4CA8; }
        .btn-print-action.equipment { background: #0891B2; }
        .btn-print-action.equipment:hover { background: #0E7490; }

        .footer { padding: 14px 0; border-top: 1px solid var(--border-color); margin-top: 24px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); }
        .footer .footer-brand { color: var(--success); font-weight: 600; }

        @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 16px; } }
        @media (max-width: 768px) { 
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .search-wrapper { max-width: 100%; }
            .scroll-controls { margin-left: 0; width: 100%; justify-content: flex-end; }
        }
        @media (max-width: 640px) { .summary-cards { grid-template-columns: 1fr 1fr; } }
    </style>

    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i> Paid Bills
                <span class="role-badge"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                All payments received in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-receipt"></i> <?= $total_bills ?> Bills
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
            <button onclick="window.location.reload()" class="btn-outline-light"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
    </div>

    <!-- SUMMARY CARDS ROW 1 -->
    <div class="summary-cards">
        <div class="summary-card total-card">
            <span class="card-label">📋 Total</span>
            <span class="card-value"><?= $total_bills ?></span>
            <span class="card-sub">All records</span>
        </div>
        <div class="summary-card regular-card">
            <span class="card-label">✅ Regular</span>
            <span class="card-value"><?= $summary_regular_count ?></span>
            <span class="card-sub">Bill payments</span>
        </div>
        <div class="summary-card otc-card">
            <span class="card-label">💊 OTC Med</span>
            <span class="card-value"><?= $summary_otc_count ?></span>
            <span class="card-sub">OTC medicine</span>
        </div>
        <div class="summary-card equipment-card">
            <span class="card-label">🛠️ Equipment</span>
            <span class="card-value"><?= $summary_equipment_count ?></span>
            <span class="card-sub">OTC equipment</span>
        </div>
        <div class="summary-card both-card">
            <span class="card-label">⚠️ Both Prem</span>
            <span class="card-value"><?= $summary_both_premium_count ?></span>
            <span class="card-sub">Pharm + Cashier</span>
        </div>
    </div>

    <!-- SUMMARY CARDS ROW 2 -->
    <div class="summary-cards" style="grid-template-columns: repeat(4, 1fr);">
        <div class="summary-card pharm-discount-card">
            <span class="card-label">🏷️ Pharm Discount</span>
            <span class="card-value"><?= $summary_pharm_discount_count ?></span>
            <span class="card-sub">Bills with Pharm Disc</span>
        </div>
        <div class="summary-card cashier-discount-card">
            <span class="card-label">🏷️ Cashier Discount</span>
            <span class="card-value"><?= $summary_cashier_discount_count ?></span>
            <span class="card-sub">Bills with Cashier Disc</span>
        </div>
        <div class="summary-card pharm-premium-card">
            <span class="card-label">👑 Pharm Premium</span>
            <span class="card-value"><?= $summary_pharm_premium_count ?></span>
            <span class="card-sub">Bills with Pharm Prem</span>
        </div>
        <div class="summary-card cashier-premium-card">
            <span class="card-label">👑 Cashier Premium</span>
            <span class="card-value"><?= $summary_cashier_premium_count ?></span>
            <span class="card-sub">Bills with Cashier Prem</span>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Date:</span>
            <a href="?filter=all&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>"><i class="fas fa-globe"></i> All</a>
            <a href="?filter=today&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="?filter=week&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 7D</a>
            <a href="?filter=month&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 6M</a>
            <a href="?filter=year&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1Y</a>
        </div>

        <div class="filter-group" style="margin-bottom:8px;border-top:1px solid var(--border-color);padding-top:8px;">
            <span class="filter-label"><i class="fas fa-crown"></i> Premium/Disc:</span>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=all" class="filter-btn <?= $premium_filter === 'all' ? 'active' : '' ?>"><i class="fas fa-globe"></i> All</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=pharm_premium" class="filter-btn pharm <?= $premium_filter === 'pharm_premium' ? 'active' : '' ?>"><i class="fas fa-pills"></i> Pharm Prem</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=cashier_premium" class="filter-btn cashier-prem <?= $premium_filter === 'cashier_premium' ? 'active' : '' ?>"><i class="fas fa-cash-register"></i> Cashier Prem</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=any_premium" class="filter-btn <?= $premium_filter === 'any_premium' ? 'active' : '' ?>"><i class="fas fa-crown"></i> Any Prem</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=both_premium" class="filter-btn both <?= $premium_filter === 'both_premium' ? 'active' : '' ?>"><i class="fas fa-exclamation-triangle"></i> Both Prem</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=pharm_discount" class="filter-btn pharm <?= $premium_filter === 'pharm_discount' ? 'active' : '' ?>"><i class="fas fa-tag"></i> Pharm Disc</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=cashier_discount" class="filter-btn cashier-disc <?= $premium_filter === 'cashier_discount' ? 'active' : '' ?>"><i class="fas fa-tag"></i> Cashier Disc</a>
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&premium_filter=any_discount" class="filter-btn cashier-disc <?= $premium_filter === 'any_discount' ? 'active' : '' ?>"><i class="fas fa-tag"></i> Any Disc</a>
        </div>

        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="premium_filter" value="<?= htmlspecialchars($premium_filter) ?>">
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                <button type="submit" class="btn-apply"><i class="fas fa-check"></i> Apply</button>
            </div>
        </form>
    </div>

    <!-- BILLS TABLE -->
    <div class="card">

        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Payments & OTC Sales
                <span style="font-size:0.8rem;font-weight:400;color:var(--text-secondary);">(<?= $total_bills ?> bills)</span>
            </h3>
            <span style="font-size:0.7rem;color:var(--text-secondary);">
                <i class="fas fa-mouse-pointer"></i> Click row to see payment dates
            </span>
        </div>

        <!-- ROW LEGEND -->
        <div class="row-legend">
            <span style="font-weight:700;color:var(--text-primary);"><i class="fas fa-palette"></i> Row Colors:</span>
            <span class="legend-item"><span class="legend-dot" style="background:#059669;"></span><span style="color:#059669;">Regular (Cashier)</span></span>
            <span class="legend-item"><span class="legend-dot" style="background:#0B5ED7;"></span><span style="color:#0B5ED7;">OTC Medicine</span></span>
            <span class="legend-item"><span class="legend-dot" style="background:#0891B2;"></span><span style="color:#0891B2;">OTC Equipment</span></span>
        </div>

        <!-- ✅ V9: SEARCH BAR + SCROLL BUTTONS <> -->
        <div class="table-search-bar">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Search receipt, bill, patient, received by..." autocomplete="off" value="<?= htmlspecialchars($search) ?>">
                <button type="button" id="searchClear" style="background:var(--gray-200);border:none;color:var(--text-secondary);width:22px;height:22px;border-radius:50%;cursor:pointer;display:none;align-items:center;justify-content:center;font-size:0.7rem;margin-left:8px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <span class="search-results-count" id="searchResultsCount"></span>
            
            <!-- ✅ V9: SCROLL BUTTONS <> -->
            <div class="scroll-controls">
                <span class="scroll-label"><i class="fas fa-arrows-alt-h"></i> Scroll:</span>
                <button type="button" class="btn-scroll-table" onclick="scrollTable('left')" title="Scroll Left (Alt+←)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="btn-scroll-table" onclick="scrollTable('right')" title="Scroll Right (Alt+→)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <div class="table-wrap" id="tableScrollWrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width:50px;text-align:center;">#</th>
                        <th style="min-width:130px;">Receipt #</th>
                        <th style="min-width:150px;">Bill #</th>
                        <th style="min-width:65px;text-align:center;">Type</th>
                        <th style="min-width:140px;">Patient</th>
                        <th style="min-width:55px;text-align:center;">Items</th>
                        <th style="min-width:180px;">Received By</th>
                        <th style="min-width:85px;text-align:center;">Pharm Disc</th>
                        <th style="min-width:85px;text-align:center;">Cashier Disc</th>
                        <th style="min-width:85px;text-align:center;">Pharm Prem</th>
                        <th style="min-width:85px;text-align:center;">Cashier Prem</th>
                        <th style="min-width:120px;text-align:center;">Last Payment</th>
                        <th style="min-width:70px;text-align:center;">Print</th>
                    </tr>
                </thead>
                <tbody id="paidBillsTableBody">
                    <?php if (count($grouped_bills) > 0): ?>
                        <?php
                        $row_number = 1;
                        foreach ($grouped_bills as $bill):
                            $bill_id = $bill['bill_id'];
                            $row_type = $bill['row_type'] ?? 'regular';
                            $is_otc = ($row_type === 'otc');
                            $is_equipment = ($row_type === 'equipment');
                            $is_regular = ($row_type === 'regular');

                            $pharm_disc = (float)($bill['pharmacy_discount'] ?? 0);
                            $pharm_prem = (float)($bill['pharmacy_premium'] ?? 0);
                            $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
                            $cashier_prem = (float)($bill['cashier_premium'] ?? 0);

                            $has_pharm_disc = ($pharm_disc > 0);
                            $has_cashier_disc = ($cashier_disc > 0);
                            $has_pharm_prem = ($pharm_prem > 0);
                            $has_cashier_prem = ($cashier_prem > 0);
                            $has_both_prem = ($has_pharm_prem && $has_cashier_prem);

                            $payments = $bill['payments'] ?? [];
                            $received_by_summary = $bill['received_by_summary'] ?? [];
                            $latest_receipt = '';
                            $latest_paid_date = $bill['last_payment_date'] ?? null;
                            if (!empty($payments)) {
                                $last_pmt = end($payments);
                                $latest_receipt = $last_pmt['receipt_number'] ?? '';
                            }
                        ?>
                            <!-- ✅ MAIN BILL ROW -->
                            <tr class="bill-row row-<?= $row_type ?>" 
                                data-search="<?= htmlspecialchars($bill['search_data']) ?>" 
                                data-row-type="<?= $row_type ?>"
                                data-bill-id="<?= $bill_id ?>"
                                onclick="togglePayments(<?= $bill_id ?>)">
                                
                                <td class="row-number-cell">
                                    <?= $row_number++ ?>
                                    <?php if ($has_both_prem): ?>
                                        <div style="font-size:0.5rem;color:var(--danger);margin-top:2px;">⚠️</div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span style="font-size:0.62rem;font-weight:800;padding:3px 8px;border-radius:5px;display:inline-block;font-family:var(--font-mono);<?php
                                        if ($is_regular) echo 'color:#059669;background:#D1FAE5;';
                                        elseif ($is_otc) echo 'color:#0B5ED7;background:#DBEAFE;';
                                        elseif ($is_equipment) echo 'color:#0891B2;background:#CFFAFE;';
                                    ?>">
                                        <?= htmlspecialchars($latest_receipt ?: 'N/A') ?>
                                    </span>
                                    <?php if (count($payments) > 1): ?>
                                        <div class="payment-count-badge" style="margin-top:4px;">
                                            <i class="fas fa-receipt"></i> <?= count($payments) ?> payments
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span style="font-size:0.65rem;font-weight:700;font-family:var(--font-mono);<?php
                                        if ($is_regular) echo 'color:#059669;';
                                        elseif ($is_otc) echo 'color:#0B5ED7;';
                                        elseif ($is_equipment) echo 'color:#0891B2;';
                                    ?>">
                                        <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($is_equipment): ?>
                                        <span class="bill-type-badge equipment"><i class="fas fa-tools"></i> EQP</span>
                                    <?php elseif ($is_otc): ?>
                                        <span class="bill-type-badge otc"><i class="fas fa-pills"></i> OTC</span>
                                    <?php else: ?>
                                        <span class="bill-type-badge regular"><i class="fas fa-file-invoice"></i> REG</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div style="font-weight:700;font-size:0.78rem;"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.58rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($bill['patient_phone'] ?? 'No phone') ?>
                                    </div>
                                </td>
                                
                                <td style="text-align:center;">
                                    <span style="font-weight:700;font-size:0.78rem;"><?= $bill['item_count'] ?? 0 ?></span>
                                </td>
                                
                                <!-- RECEIVED BY (GROUPED) -->
                                <td>
                                    <?php if (count($received_by_summary) > 0): ?>
                                        <div class="received-by-summary">
                                            <?php foreach ($received_by_summary as $rb): ?>
                                                <span class="received-by-item <?= htmlspecialchars($rb['role']) ?>">
                                                    <i class="fas fa-user"></i>
                                                    <?= htmlspecialchars($rb['name']) ?>
                                                    <?php if ($rb['count'] > 1): ?>
                                                        <span class="received-by-count">(<?= $rb['count'] ?> received)</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.65rem;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($has_pharm_disc): ?>
                                        <span class="badge-yes pharm"><i class="fas fa-check"></i> YES</span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($has_cashier_disc): ?>
                                        <span class="badge-yes cashier-disc"><i class="fas fa-check"></i> YES</span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($has_pharm_prem): ?>
                                        <span class="badge-yes pharm"><i class="fas fa-crown"></i> YES</span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($has_cashier_prem): ?>
                                        <span class="badge-yes cashier-prem"><i class="fas fa-crown"></i> YES</span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php if ($latest_paid_date): ?>
                                        <div style="font-size:0.68rem;font-weight:700;font-family:var(--font-mono);">
                                            <?= date('d/m/y', strtotime($latest_paid_date)) ?>
                                        </div>
                                        <div style="font-size:0.55rem;color:var(--text-secondary);">
                                            <?= date('h:i A', strtotime($latest_paid_date)) ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.65rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                        <span class="expand-arrow" id="arrow-<?= $bill_id ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                        <?php if ($is_equipment): ?>
                                            <a href="print_receipt.php?type=otc&sale_id=<?= $bill_id ?>&print=1" class="btn-print-action equipment" target="_blank" onclick="event.stopPropagation();"><i class="fas fa-print"></i></a>
                                        <?php elseif ($is_otc): ?>
                                            <a href="print_receipt.php?type=otc&sale_id=<?= $bill_id ?>&print=1" class="btn-print-action otc" target="_blank" onclick="event.stopPropagation();"><i class="fas fa-print"></i></a>
                                        <?php else: ?>
                                            <a href="print_receipt.php?payment_id=<?= $payments[0]['id'] ?? 0 ?>&bill_id=<?= $bill_id ?>&print=1" class="btn-print-action" target="_blank" onclick="event.stopPropagation();"><i class="fas fa-print"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- PAYMENT DETAILS (EXPANDABLE) -->
                            <tr class="payments-detail-row" id="payments-<?= $bill_id ?>">
                                <td colspan="13" class="payments-detail-cell">
                                    <div style="margin-bottom:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <strong style="color:#059669;font-size:0.85rem;">
                                            <i class="fas fa-receipt"></i> Payment History (<?= count($payments) ?>)
                                        </strong>
                                        <span style="font-size:0.72rem;color:var(--text-secondary);">
                                            Bill: <strong style="color:var(--text-primary);font-family:var(--font-mono);"><?= htmlspecialchars($bill['bill_number']) ?></strong>
                                        </span>
                                    </div>
                                    
                                    <?php if (count($payments) > 0): ?>
                                        <?php foreach ($payments as $payment_idx => $payment): 
                                            $method_icon = 'money-bill';
                                            if (strpos($payment['payment_method'] ?? '', 'pesa') !== false || strpos($payment['payment_method'] ?? '', 'mpesa') !== false) $method_icon = 'mobile-alt';
                                            elseif ($payment['payment_method'] === 'bank') $method_icon = 'university';
                                            elseif ($payment['payment_method'] === 'card') $method_icon = 'credit-card';
                                        ?>
                                            <div class="payment-item">
                                                <div class="payment-left">
                                                    <span class="payment-index"><?= $payment_idx + 1 ?></span>
                                                    <span class="payment-receipt">
                                                        <i class="fas fa-hashtag"></i> <?= htmlspecialchars($payment['receipt_number']) ?>
                                                    </span>
                                                    <span class="payment-method">
                                                        <i class="fas fa-<?= $method_icon ?>"></i>
                                                        <?= htmlspecialchars(str_replace('_', ' ', $payment['payment_method'] ?? 'cash')) ?>
                                                    </span>
                                                </div>
                                                <div class="payment-left">
                                                    <span class="payment-date">
                                                        <i class="far fa-calendar"></i>
                                                        <?= date('d M Y, h:i A', strtotime($payment['received_at'])) ?>
                                                    </span>
                                                    <?php if (!empty($payment['received_by_name'])): ?>
                                                        <span class="payment-received-by">
                                                            <i class="fas fa-user"></i> <?= htmlspecialchars($payment['received_by_name']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                                            <i class="fas fa-info-circle"></i> No payments recorded
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                                <i class="fas fa-check-circle" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--success);opacity:0.4;"></i>
                                <p style="font-size:1rem;font-weight:600;">No payments found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span> Paid Bills V9
            <span>|</span>
            <span style="color:#059669;"><i class="fas fa-circle"></i> Regular</span>
            <span style="color:#0B5ED7;"><i class="fas fa-circle"></i> OTC Med</span>
            <span style="color:#0891B2;"><i class="fas fa-circle"></i> Equipment</span>
            <span>|</span>
            <span>👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<script>
    function togglePayments(billId) {
        var detailRow = document.getElementById('payments-' + billId);
        var arrow = document.getElementById('arrow-' + billId);
        
        if (!detailRow || !arrow) return;
        
        if (detailRow.classList.contains('show')) {
            detailRow.classList.remove('show');
            arrow.classList.remove('rotated');
        } else {
            detailRow.classList.add('show');
            arrow.classList.add('rotated');
        }
    }

    // ✅ V9: SCROLL TABLE LEFT/RIGHT
    function scrollTable(direction) {
        var wrapper = document.getElementById('tableScrollWrapper');
        if (!wrapper) return;
        
        if (wrapper.scrollWidth <= wrapper.clientWidth) {
            console.log('No scroll needed - table fits in viewport');
            return;
        }
        
        var scrollAmount = 500;
        var currentScroll = wrapper.scrollLeft;
        var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
        
        var targetScroll = direction === 'left' 
            ? Math.max(0, currentScroll - scrollAmount)
            : Math.min(maxScroll, currentScroll + scrollAmount);
        
        wrapper.scrollTo({ left: targetScroll, behavior: 'smooth' });
    }

    // ✅ V9: KEYBOARD SHORTCUTS for scrolling
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'ArrowLeft') {
            e.preventDefault();
            scrollTable('left');
        }
        if (e.altKey && e.key === 'ArrowRight') {
            e.preventDefault();
            scrollTable('right');
        }
    });

    (function() {
        var tableSearch = document.getElementById('tableSearch');
        var billRows = document.querySelectorAll('.bill-row');
        var searchClear = document.getElementById('searchClear');
        var searchResultsCount = document.getElementById('searchResultsCount');
        var totalRows = billRows.length;
        
        function performSearch() {
            var query = tableSearch.value.toLowerCase().trim();
            var visibleCount = 0;
            
            if (query.length > 0) {
                searchClear.style.display = 'flex';
            } else {
                searchClear.style.display = 'none';
            }
            
            billRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                var billId = row.getAttribute('data-bill-id');
                var detailRow = document.getElementById('payments-' + billId);
                
                if (query === '' || searchData.includes(query)) {
                    row.style.display = '';
                    if (detailRow) detailRow.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    if (detailRow) {
                        detailRow.style.display = 'none';
                        detailRow.classList.remove('show');
                    }
                }
            });
            
            if (query !== '') {
                searchResultsCount.textContent = visibleCount + ' of ' + totalRows + ' found';
                searchResultsCount.classList.add('visible');
            } else {
                searchResultsCount.classList.remove('visible');
            }
        }
        
        if (tableSearch) {
            tableSearch.addEventListener('input', performSearch);
            tableSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    tableSearch.value = '';
                    performSearch();
                    tableSearch.blur();
                }
            });
        }
        
        if (searchClear) {
            searchClear.addEventListener('click', function() {
                tableSearch.value = '';
                performSearch();
                tableSearch.focus();
            });
        }
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (tableSearch) { tableSearch.focus(); tableSearch.select(); }
            }
        });
    })();

    console.log('%c✅ Braick - Paid Bills V9', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c✅ V9: Scroll buttons <> kwenye search bar', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ V9: Search bar imepunguzwa', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💡 Tip: Tumia Alt + ←/→ kwa keyboard', 'font-size:12px; color:#94A3B8;');
</script>

</body>
</html>