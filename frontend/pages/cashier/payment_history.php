<?php
// ================================================================
// FILE: frontend/pages/cashier/payment_history.php
// V5 FINAL: GROUPED + VISIT CREATED TIME/DATE
// ================================================================
// ✅ V5: Visit Created column (tarehe + saa ya visit)
// ✅ V5: Visit Created inaonekana kwenye expand
// ✅ V4: Payments za bill moja zimegroup kwenye row moja
// ✅ V4: Received By (grouped) na count
// ✅ V4: Expandable payment history (click row)
// ✅ V3: Timezone +03:00 (EAT)
// ✅ V3: Scroll buttons < > kwenye search bar
// ✅ V3: Live search
// ✅ V3: Row colors + legend
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
$user_phone = $_SESSION['phone'] ?? '';

$is_reception = ($user_role === 'reception');
$is_admin = ($user_role === 'admin');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// GET ADMIN CONTACT NUMBERS
$admin_phones = [];
try {
    $stmt = $db->prepare("SELECT phone FROM users WHERE role = 'admin' AND branch_id = ? AND status = 'active' ORDER BY id ASC");
    $stmt->execute([$user_branch_id]);
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $admin_phones = []; }

// GET BRANCH PHONE
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) { $branch_phone = ''; }

// FILTERS
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$grouped_payments = [];
$total_groups = 0;

// GET SYSTEM SETTINGS
try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) { $currency = 'TSh'; }

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
            $params_bills[] = $start_date; $params_bills[] = $end_date;
            $params_otc[] = $start_date; $params_otc[] = $end_date;
        }
        break;
    default:
        $date_condition_bills = "";
        $date_condition_otc = "";
        break;
}

// ================================================================
// ✅ V5: GET GROUPED PAYMENTS (with VISIT CREATED TIME)
// ================================================================
try {
    // ============================================================
    // 1. REGULAR BILLS - GROUP kwa bill_id, ONGEZA visit_created_at
    // ============================================================
    $sql_bills = "
        SELECT 
            b.id as bill_id,
            b.bill_number,
            b.patient_id,
            b.visit_id,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            v.visit_number,
            v.created_at as visit_created_at,
            v.visit_date,
            (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count,
            (SELECT MAX(received_at) FROM payments WHERE bill_id = b.id) as last_payment_date,
            (SELECT MIN(received_at) FROM payments WHERE bill_id = b.id) as first_payment_date,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            'Regular' as bill_type,
            'regular' as row_type,
            b.pharmacy_discount, b.cashier_discount, b.pharmacy_premium, b.cashier_premium
        FROM bills b
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ? 
          AND b.patient_id IS NOT NULL
          AND b.visit_id IS NOT NULL
          AND b.bill_number NOT LIKE 'BILL-OTC-%'
          $date_condition_bills
          AND EXISTS (SELECT 1 FROM payments WHERE bill_id = b.id)
        ORDER BY last_payment_date DESC
    ";
    
    $stmt = $db->prepare($sql_bills);
    $stmt->execute($params_bills);
    $bill_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bill_rows as $row) {
        $row['pharmacy_discount'] = $row['pharmacy_discount'] ?? 0;
        $row['cashier_discount'] = $row['cashier_discount'] ?? 0;
        $row['pharmacy_premium'] = $row['pharmacy_premium'] ?? 0;
        $row['cashier_premium'] = $row['cashier_premium'] ?? 0;
        
        // Get payments
        $stmt_payments = $db->prepare("
            SELECT 
                p.id, p.receipt_number, p.amount, p.payment_method,
                p.reference_number, p.notes, p.received_at,
                u.full_name as received_by_name,
                u.role as received_by_role
            FROM payments p
            LEFT JOIN users u ON p.received_by = u.id
            WHERE p.bill_id = ?
            ORDER BY p.received_at ASC, p.id ASC
        ");
        $stmt_payments->execute([$row['bill_id']]);
        $payments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);
        
        $row['payments'] = $payments;
        $row['payment_count'] = count($payments);
        
        // Received by summary
        $received_by_summary = [];
        foreach ($payments as $pmt) {
            $name = $pmt['received_by_name'] ?? 'Unknown';
            $role = strtolower($pmt['received_by_role'] ?? 'cashier');
            if (!isset($received_by_summary[$name])) {
                $received_by_summary[$name] = ['name' => $name, 'role' => $role, 'count' => 0];
            }
            $received_by_summary[$name]['count']++;
        }
        $row['received_by_summary'] = array_values($received_by_summary);
        
        if (!empty($payments)) {
            $row['last_payment_date'] = end($payments)['received_at'];
            $row['first_payment_date'] = $payments[0]['received_at'];
        }
        
        // ✅ V5: Search data INA-JUMUISHA visit_created_at
        $search_data = strtolower(
            ($row['bill_number'] ?? '') . ' ' .
            ($row['patient_name'] ?? '') . ' ' .
            ($row['patient_phone'] ?? '') . ' ' .
            ($row['visit_number'] ?? '') . ' ' .
            ($row['patient_code'] ?? '')
        );
        
        // Ongeza tarehe ya visit kwenye search
        if (!empty($row['visit_created_at'])) {
            $search_data .= ' ' . date('d/m/y', strtotime($row['visit_created_at']));
            $search_data .= ' ' . date('Y-m-d', strtotime($row['visit_created_at']));
        }
        
        foreach ($payments as $pmt) {
            $search_data .= ' ' . strtolower($pmt['receipt_number'] ?? '');
            $search_data .= ' ' . strtolower($pmt['received_by_name'] ?? '');
        }
        $row['search_data'] = $search_data;
        
        $grouped_payments[] = $row;
    }

    // ============================================================
    // 2. OTC SALES - kila sale ni row yake
    // ============================================================
    $sql_otc = "
        SELECT 
            o.id as bill_id,
            o.sale_number as bill_number,
            o.customer_name as patient_name,
            o.customer_phone as patient_phone,
            o.discount_amount as cashier_discount,
            o.payment_method,
            o.created_at as last_payment_date,
            o.created_at as visit_created_at,
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
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            0 as pharmacy_discount,
            0 as pharmacy_premium,
            0 as cashier_premium
        FROM otc_sales o
        LEFT JOIN users u_sold ON o.sold_by = u_sold.id
        WHERE o.branch_id = ? 
          AND o.payment_status = 'paid'
          $date_condition_otc
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $otc_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($otc_rows as $row) {
        $row['payments'] = [[
            'id' => 0,
            'receipt_number' => $row['bill_number'],
            'amount' => 0,
            'payment_method' => $row['payment_method'],
            'reference_number' => null,
            'notes' => 'OTC Sale',
            'received_at' => $row['last_payment_date'],
            'received_by_name' => $row['received_by_name'],
            'received_by_role' => $row['received_by_role']
        ]];
        $row['payment_count'] = 1;
        $row['first_payment_date'] = $row['last_payment_date'];
        $row['received_by_summary'] = [[
            'name' => $row['received_by_name'] ?? 'Unknown',
            'role' => strtolower($row['received_by_role'] ?? 'cashier'),
            'count' => 1
        ]];
        
        // ✅ V5: Search data ya OTC
        $search_data = strtolower(
            ($row['bill_number'] ?? '') . ' ' .
            ($row['patient_name'] ?? '') . ' ' .
            ($row['patient_phone'] ?? '') . ' ' .
            ($row['received_by_name'] ?? '')
        );
        if (!empty($row['visit_created_at'])) {
            $search_data .= ' ' . date('d/m/y', strtotime($row['visit_created_at']));
            $search_data .= ' ' . date('Y-m-d', strtotime($row['visit_created_at']));
        }
        $row['search_data'] = $search_data;
        
        $grouped_payments[] = $row;
    }

    usort($grouped_payments, function($a, $b) {
        return strtotime($b['last_payment_date'] ?? '1970-01-01') - strtotime($a['last_payment_date'] ?? '1970-01-01');
    });

    $total_groups = count($grouped_payments);

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $grouped_payments = [];
    $total_groups = 0;
}

// SUMMARY COUNTS
$summary_regular_count = 0;
$summary_otc_count = 0;
$summary_equipment_count = 0;
$summary_total_payments = 0;

foreach ($grouped_payments as $g) {
    $summary_total_payments += $g['payment_count'];
    if (($g['bill_type'] ?? '') === 'Regular') $summary_regular_count += $g['payment_count'];
    if (($g['bill_type'] ?? '') === 'OTC') $summary_otc_count += $g['payment_count'];
    if (($g['bill_type'] ?? '') === 'Equipment') $summary_equipment_count += $g['payment_count'];
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
    <title>Payment History V5 - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }

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
        .summary-card.groups-card { border-color: var(--danger); }
        .summary-card.groups-card::before { background: var(--danger); }
        .summary-card.groups-card .card-value { color: var(--danger); }

        .filter-section { background: var(--bg-card); border-radius: 14px; padding: 14px 18px; border: 1px solid var(--border-color); margin-bottom: 16px; max-width: 1400px; margin-left: auto; margin-right: auto; }
        .filter-btn { padding: 5px 12px; border-radius: 20px; font-size: 0.68rem; font-weight: 500; border: 2px solid var(--border-color); background: transparent; color: var(--text-secondary); cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .filter-btn:hover { border-color: var(--success); color: var(--success); background: var(--success-bg); }
        .filter-btn.active { background: var(--success); color: white; border-color: var(--success); }
        .filter-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .filter-label { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); margin-right: 4px; }
        .date-picker-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .form-control { padding: 4px 10px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.75rem; background: var(--bg-card); color: var(--text-primary); outline: none; width: auto; }
        .form-control:focus { border-color: var(--success); }
        .btn-apply { padding: 4px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; background: var(--success); color: white; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-apply:hover { background: var(--success-dark); }

        .card { background: var(--bg-card); border-radius: 16px; padding: 20px 24px; border: 1px solid var(--border-color); max-width: 1400px; margin: 0 auto; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .card-title { font-size: 0.95rem; font-weight: 700; }

        .row-legend { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 8px; padding: 8px 12px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border-color); }
        .legend-item { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }

        .table-search-bar { 
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            margin-bottom: 12px; padding: 10px 14px; 
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5); 
            border: 2px solid var(--success); border-radius: 12px; 
        }
        [data-theme="dark"] .table-search-bar { background: linear-gradient(135deg, #1A3A2A, #0F2A1E); }
        
        .search-wrapper { 
            position: relative; display: flex; align-items: center;
            background: var(--bg-card); border: 2px solid var(--border-color); 
            border-radius: 10px; padding: 0 12px; flex: 1; 
            min-width: 200px; max-width: 400px; height: 36px;
        }
        .search-wrapper:focus-within { border-color: var(--success); }
        .search-wrapper .search-icon { color: var(--success); font-size: 0.8rem; margin-right: 8px; }
        .search-wrapper input { flex: 1; background: transparent; border: none; outline: none; color: var(--text-primary); font-size: 0.78rem; padding: 0; font-weight: 500; }
        
        .search-results-count { 
            color: var(--success); font-size: 0.68rem; font-weight: 700; 
            background: var(--bg-card); padding: 5px 10px; border-radius: 8px; 
            white-space: nowrap; border: 1px solid var(--success); display: none; 
        }
        .search-results-count.visible { display: inline-block; }

        .scroll-controls { display: flex; align-items: center; gap: 6px; margin-left: auto; flex-shrink: 0; }
        .scroll-controls .scroll-label {
            font-size: 0.65rem; font-weight: 700; color: var(--text-secondary);
            text-transform: uppercase; letter-spacing: 0.05em;
            margin-right: 4px; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-scroll-table {
            width: 38px; height: 38px; border-radius: 10px;
            background: linear-gradient(135deg, #059669, #047857);
            color: white; border: 2px solid #059669; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.9rem; font-weight: 900;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3); flex-shrink: 0;
        }
        .btn-scroll-table:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.5); border-color: #047857;
        }
        .btn-scroll-table:active { transform: translateY(0) scale(0.95); }
        .btn-scroll-table i { color: white; }

        .table-wrap { overflow-x: auto; scroll-behavior: smooth; }
        .table-wrap::-webkit-scrollbar { height: 8px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }

        /* ✅ V5: TABLE WITH VISIT CREATED COLUMN */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; min-width: 1400px; }
        .data-table thead th { text-align: left; padding: 10px 8px; font-weight: 700; font-size: 0.6rem; text-transform: uppercase; color: white; background: var(--success); border-bottom: 3px solid var(--success-dark); white-space: nowrap; }
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        .data-table td { padding: 10px 8px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }

        .payment-row { cursor: pointer; transition: all 0.2s ease; }

        .payment-row.row-regular { background: linear-gradient(90deg, rgba(5, 150, 105, 0.03) 0%, transparent 100%); }
        .payment-row.row-regular:hover td { background: #D1FAE5; }
        [data-theme="dark"] .payment-row.row-regular:hover td { background: #1A3A2A; }

        .payment-row.row-otc { background: linear-gradient(90deg, rgba(11, 94, 215, 0.05) 0%, transparent 100%); border-left: 4px solid #0B5ED7; }
        .payment-row.row-otc:hover td { background: #DBEAFE; }
        [data-theme="dark"] .payment-row.row-otc:hover td { background: #1E3A5F; }

        .payment-row.row-equipment { background: linear-gradient(90deg, rgba(8, 145, 178, 0.05) 0%, transparent 100%); border-left: 4px solid #0891B2; }
        .payment-row.row-equipment:hover td { background: #CFFAFE; }
        [data-theme="dark"] .payment-row.row-equipment:hover td { background: #0A2E3A; }

        .row-number-cell { text-align: center; font-weight: 700; font-size: 0.75rem; color: var(--success); background: var(--success-bg); font-family: var(--font-mono); width: 50px; min-width: 50px; }
        .payment-row.row-otc .row-number-cell { background: #DBEAFE; color: #0B5ED7; }
        .payment-row.row-equipment .row-number-cell { background: #CFFAFE; color: #0891B2; }
        [data-theme="dark"] .row-number-cell { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .payment-row.row-otc .row-number-cell { background: #1E3A5F; color: #60A5FA; }
        [data-theme="dark"] .payment-row.row-equipment .row-number-cell { background: #0A2E3A; color: #22D3EE; }

        .expand-arrow { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--success); color: white; font-size: 0.75rem; transition: all 0.3s ease; }
        .payment-row.row-otc .expand-arrow { background: #0B5ED7; }
        .payment-row.row-equipment .expand-arrow { background: #0891B2; }
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

        .received-by-summary { display: flex; flex-direction: column; gap: 3px; }
        .received-by-item {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 10px;
            font-size: 0.6rem; font-weight: 700;
            background: #E8F0FE; color: #0B5ED7; white-space: nowrap;
        }
        .received-by-item.reception { background: #DBEAFE; color: #1E40AF; }
        .received-by-item.cashier { background: #FEF3C7; color: #D97706; }
        .received-by-item.admin { background: #FCE7F3; color: #BE185D; }
        .received-by-item.pharmacy { background: #D1FAE5; color: #059669; }
        [data-theme="dark"] .received-by-item { background: #1E3A5F; color: #60A5FA; }
        [data-theme="dark"] .received-by-item.cashier { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .received-by-item.admin { background: #2A1A3A; color: #A78BFA; }
        [data-theme="dark"] .received-by-item.pharmacy { background: #1A3A2A; color: #34D399; }

        .received-by-count {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 1px 6px; border-radius: 8px;
            font-size: 0.55rem; font-weight: 900;
            background: rgba(0,0,0,0.1); margin-left: 4px;
            font-family: var(--font-mono);
        }

        .method-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.6rem; font-weight: 700; }
        .method-badge.cash { background: #D1FAE5; color: #059669; }
        .method-badge.m-pesa { background: #E8F0FE; color: #0B5ED7; }
        .method-badge.card { background: #FEF3C7; color: #D97706; }
        .method-badge.bank { background: #F3E8FF; color: #7C3AED; }

        /* ✅ V5: VISIT CREATED CELL STYLE */
        .visit-created-cell {
            text-align: center;
        }
        .visit-date {
            font-size: 0.68rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: #0B5ED7;
        }
        .visit-time {
            font-size: 0.55rem;
            color: var(--text-secondary);
        }
        [data-theme="dark"] .visit-date { color: #60A5FA; }

        .btn-print-action { display: inline-flex; align-items: center; justify-content: center; padding: 5px 12px; border-radius: 8px; font-weight: 600; font-size: 0.65rem; background: #059669; color: white; text-decoration: none; transition: all 0.3s; cursor: pointer; border: none; }
        .btn-print-action:hover { background: var(--success-dark); transform: translateY(-2px); color: white; }
        .btn-print-action.otc { background: #0B5ED7; }
        .btn-print-action.otc:hover { background: #0A4CA8; }
        .btn-print-action.equipment { background: #0891B2; }
        .btn-print-action.equipment:hover { background: #0E7490; }

        .footer { padding: 14px 0; border-top: 1px solid var(--border-color); margin-top: 24px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); max-width: 1400px; margin-left: auto; margin-right: auto; }
        .footer .footer-brand { color: var(--success); font-weight: 600; }

        /* PDF MODAL */
        .pdf-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .pdf-modal-overlay.active { display: flex; }
        .pdf-modal { background: var(--bg-card); border-radius: 16px; width: 95%; max-width: 1100px; max-height: 95vh; display: flex; flex-direction: column; box-shadow: 0 20px 25px rgba(0,0,0,0.1); animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .pdf-modal-header { padding: 14px 22px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; background: linear-gradient(135deg, #059669, #047857); border-radius: 16px 16px 0 0; }
        .pdf-modal-header .modal-title { font-size: 1rem; font-weight: 700; color: white; display: flex; align-items: center; gap: 10px; }
        .pdf-modal-header .modal-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .pdf-modal-header .modal-actions .btn { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .pdf-modal-header .modal-actions .btn:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .pdf-modal-header .modal-actions .btn-danger-modal { background: rgba(220,38,38,0.3); border-color: rgba(220,38,38,0.2); }
        .pdf-modal-body { flex: 1; overflow-y: auto; padding: 20px 28px; background: var(--bg-body); }
        .pdf-modal-body .pdf-content { max-width: 100%; font-size: 14px; background: var(--bg-card); padding: 24px 28px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid var(--border-color); line-height: 1.5; }
        .pdf-content .pdf-header { text-align: center; padding-bottom: 12px; border-bottom: 3px solid #059669; margin-bottom: 16px; }
        .pdf-content .pdf-header .pdf-logo { display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 4px; }
        .pdf-content .pdf-header .pdf-logo img { height: 55px; width: auto; object-fit: contain; display: block; margin: 0 auto; }
        .pdf-content .pdf-header .clinic-name { font-size: 1.4rem; font-weight: 800; color: #059669; letter-spacing: -0.5px; margin-top: 4px; }
        .pdf-content .pdf-header .clinic-sub { font-size: 0.75rem; color: var(--text-secondary); letter-spacing: 0.5px; }
        .pdf-content .pdf-header .doc-title { font-size: 0.85rem; font-weight: 700; color: #059669; margin-top: 4px; background: #D1FAE5; padding: 4px 16px; border-radius: 20px; display: inline-block; }
        .pdf-content .pdf-section-title { font-weight: 700; font-size: 0.95rem; color: #059669; border-bottom: 2px solid #34D399; padding-bottom: 4px; margin: 6px 0 4px 0; display: flex; align-items: center; gap: 8px; }
        .pdf-content .pdf-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 4px 0; }
        .pdf-content .pdf-table th { background: #059669; color: white; padding: 4px 10px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; border: 1px solid #047857; }
        .pdf-content .pdf-table td { padding: 4px 10px; border-bottom: 1px solid #E2E8F0; font-size: 13px; word-wrap: break-word; }
        .pdf-content .pdf-table tr:nth-child(even) td { background: #F8FAFC; }
        .pdf-content .pdf-footer { margin-top: 12px; padding-top: 10px; border-top: 2px solid #E2E8F0; }
        .pdf-content .pdf-footer .footer-stamp { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .pdf-content .pdf-footer .footer-left { font-size: 14px; color: var(--text-secondary); }
        .pdf-content .pdf-footer .stamp-box { text-align: center; padding: 6px 14px; border: 3px solid #059669; border-radius: 10px; background: #D1FAE5; min-width: 150px; }
        .pdf-content .pdf-footer .stamp-box .stamp-title { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .pdf-content .pdf-footer .stamp-box .stamp-name { font-size: 14px; font-weight: 800; color: #059669; }
        .pdf-content .pdf-footer .stamp-box .stamp-line { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .pdf-content .pdf-footer .stamp-box .stamp-date { font-size: 10px; color: #94A3B8; margin-top: 2px; }
        .pdf-content .pdf-footer .footer-bottom { text-align: center; margin-top: 6px; font-size: 12px; color: #94A3B8; }

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
                <i class="fas fa-history"></i> Payment History
                <span class="role-badge"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <span class="role-badge" style="background:rgba(255,215,0,0.3);color:#FFD700;">
                        <i class="fas fa-user-shield"></i> ADMIN
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-money-bill-wave"></i>
                All payments in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-receipt"></i> <?= $summary_total_payments ?> Payments
                </span>
                <span class="header-badge">
                    <i class="fas fa-layer-group"></i> <?= $total_groups ?> Groups
                </span>
                <span class="header-badge" style="background: rgba(255,255,255,0.25);">
                    <i class="fas fa-clock"></i> EAT (UTC+3)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
            <button onclick="window.location.reload()" class="btn-outline-light"><i class="fas fa-sync-alt"></i> Refresh</button>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="max-width:1400px;margin:0 auto 16px;padding:12px 18px;border-radius:12px;<?= $message_type === 'success' ? 'background:#D1FAE5;color:#059669;border:1px solid #059669;' : 'background:#FEE2E2;color:#DC2626;border:1px solid #DC2626;' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="margin-right:8px;"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="summary-cards">
        <div class="summary-card total-card">
            <span class="card-label">📋 Total Payments</span>
            <span class="card-value"><?= $summary_total_payments ?></span>
            <span class="card-sub">All transactions</span>
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
        <div class="summary-card groups-card">
            <span class="card-label">📦 Groups</span>
            <span class="card-value"><?= $total_groups ?></span>
            <span class="card-sub">Bills / Sales</span>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Date:</span>
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>"><i class="fas fa-globe"></i> All</a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 7D</a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 6M</a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1Y</a>
        </div>

        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                <button type="submit" class="btn-apply"><i class="fas fa-check"></i> Apply</button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- PAYMENTS TABLE (GROUPED WITH VISIT CREATED) -->
    <div class="card">

        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Payment History (Grouped)
                <span style="font-size:0.8rem;font-weight:400;color:var(--text-secondary);">(<?= $total_groups ?> bills / sales)</span>
            </h3>
            <span style="font-size:0.7rem;color:var(--text-secondary);">
                <i class="fas fa-mouse-pointer"></i> Click row to see all payments
            </span>
        </div>

        <div class="row-legend">
            <span style="font-weight:700;color:var(--text-primary);"><i class="fas fa-palette"></i> Row Colors:</span>
            <span class="legend-item"><span class="legend-dot" style="background:#059669;"></span><span style="color:#059669;">Regular (Cashier)</span></span>
            <span class="legend-item"><span class="legend-dot" style="background:#0B5ED7;"></span><span style="color:#0B5ED7;">OTC Medicine</span></span>
            <span class="legend-item"><span class="legend-dot" style="background:#0891B2;"></span><span style="color:#0891B2;">OTC Equipment</span></span>
        </div>

        <div class="table-search-bar">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Search receipt, bill, patient, received by..." autocomplete="off" value="<?= htmlspecialchars($search) ?>">
                <button type="button" id="searchClear" style="background:var(--gray-200);border:none;color:var(--text-secondary);width:22px;height:22px;border-radius:50%;cursor:pointer;display:none;align-items:center;justify-content:center;font-size:0.7rem;margin-left:8px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <span class="search-results-count" id="searchResultsCount"></span>
            
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
                        <th style="min-width:60px;text-align:center;">Type</th>
                        <th style="min-width:140px;">Patient</th>
                        <th style="min-width:55px;text-align:center;">Items</th>
                        <th style="min-width:170px;">Received By</th>
                        <th style="min-width:85px;text-align:center;">Method</th>
                        <th style="min-width:120px;text-align:center;">Last Payment</th>
                        <!-- ✅ V5: VISIT CREATED COLUMN -->
                        <th style="min-width:120px;text-align:center;">
                            <i class="fas fa-user-plus"></i> Visit Created
                        </th>
                        <th style="min-width:70px;text-align:center;">Print</th>
                    </tr>
                </thead>
                <tbody id="paymentHistoryTableBody">
                    <?php if (count($grouped_payments) > 0): ?>
                        <?php
                        $row_number = 1;
                        foreach ($grouped_payments as $group):
                            $bill_id = $group['bill_id'];
                            $row_type = $group['row_type'] ?? 'regular';
                            $is_otc = ($row_type === 'otc');
                            $is_equipment = ($row_type === 'equipment');
                            $is_regular = ($row_type === 'regular');

                            $payments = $group['payments'] ?? [];
                            $received_by_summary = $group['received_by_summary'] ?? [];
                            $latest_receipt = '';
                            $latest_paid_date = $group['last_payment_date'] ?? null;
                            $visit_created_at = $group['visit_created_at'] ?? null;
                            $method = $payments[0]['payment_method'] ?? ($group['payment_method'] ?? 'cash');
                            
                            if (!empty($payments)) {
                                $last_pmt = end($payments);
                                $latest_receipt = $last_pmt['receipt_number'] ?? '';
                            }
                        ?>
                            <tr class="payment-row row-<?= $row_type ?>" 
                                data-search="<?= htmlspecialchars($group['search_data']) ?>"
                                data-row-type="<?= $row_type ?>"
                                data-bill-id="<?= $bill_id ?>"
                                onclick="togglePayments(<?= $bill_id ?>)">
                                
                                <td class="row-number-cell">
                                    <?= $row_number++ ?>
                                    <?php if (count($payments) > 1): ?>
                                        <div style="font-size:0.5rem;color:var(--warning);margin-top:2px;">📦<?= count($payments) ?></div>
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
                                        <?= htmlspecialchars($group['bill_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (!empty($group['visit_number'])): ?>
                                        <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;">
                                            <i class="fas fa-user-plus"></i> <?= htmlspecialchars($group['visit_number']) ?>
                                        </div>
                                    <?php endif; ?>
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
                                    <div style="font-weight:700;font-size:0.78rem;"><?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?></div>
                                    <div style="font-size:0.58rem;color:var(--text-secondary);">
                                        <?php if ($is_otc): ?>
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($group['patient_phone'] ?? 'N/A') ?>
                                        <?php else: ?>
                                            <i class="fas fa-id-card"></i> <?= htmlspecialchars($group['patient_code'] ?? 'N/A') ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td style="text-align:center;">
                                    <span style="font-weight:700;font-size:0.78rem;"><?= $group['item_count'] ?? 0 ?></span>
                                </td>
                                
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
                                    <span class="method-badge <?= $method === 'cash' ? 'cash' : ($method === 'm-pesa' ? 'm-pesa' : ($method === 'card' ? 'card' : 'bank')) ?>">
                                        <i class="fas fa-money-bill"></i>
                                        <?= strtoupper(str_replace('_', ' ', $method)) ?>
                                    </span>
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
                                
                                <!-- ✅ V5: VISIT CREATED COLUMN -->
                                <td class="visit-created-cell">
                                    <?php if ($visit_created_at): ?>
                                        <div class="visit-date">
                                            <?= date('d/m/y', strtotime($visit_created_at)) ?>
                                        </div>
                                        <div class="visit-time">
                                            <?= date('h:i A', strtotime($visit_created_at)) ?>
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
                                            <a href="print_receipt.php?type=otc&sale_id=<?= $bill_id ?>&print=1" class="btn-print-action equipment" target="_blank" onclick="event.stopPropagation();" title="Print Equipment Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php elseif ($is_otc): ?>
                                            <a href="print_receipt.php?type=otc&sale_id=<?= $bill_id ?>&print=1" class="btn-print-action otc" target="_blank" onclick="event.stopPropagation();" title="Print OTC Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="print_receipt.php?payment_id=<?= $payments[0]['id'] ?? 0 ?>&bill_id=<?= $bill_id ?>&print=1" class="btn-print-action" target="_blank" onclick="event.stopPropagation();" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- EXPANDABLE PAYMENT DETAILS -->
                            <tr class="payments-detail-row" id="payments-<?= $bill_id ?>">
                                <td colspan="11" class="payments-detail-cell">
                                    <div style="margin-bottom:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <strong style="color:#059669;font-size:0.85rem;">
                                            <i class="fas fa-receipt"></i> Payment History (<?= count($payments) ?>)
                                        </strong>
                                        <span style="font-size:0.72rem;color:var(--text-secondary);">
                                            Bill: <strong style="color:var(--text-primary);font-family:var(--font-mono);"><?= htmlspecialchars($group['bill_number']) ?></strong>
                                        </span>
                                        <?php if ($visit_created_at): ?>
                                            <span style="font-size:0.65rem;color:#0B5ED7;background:#DBEAFE;padding:2px 8px;border-radius:6px;font-weight:600;">
                                                <i class="fas fa-user-plus"></i> Visit: <?= date('d M Y, h:i A', strtotime($visit_created_at)) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span style="font-size:0.65rem;color:var(--success);background:var(--success-bg);padding:2px 8px;border-radius:6px;font-weight:600;">
                                            <i class="fas fa-clock"></i> EAT Time
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
                            <td colspan="11" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                                <i class="fas fa-money-bill-wave" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--success);opacity:0.4;"></i>
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
            <span>|</span> Payment History V5 (Grouped + Visit Time)
            <span>|</span>
            <span style="color:#059669;"><i class="fas fa-circle"></i> Regular</span>
            <span style="color:#0B5ED7;"><i class="fas fa-circle"></i> OTC Med</span>
            <span style="color:#0891B2;"><i class="fas fa-circle"></i> Equipment</span>
            <span>|</span>
            <span>👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?><span>|</span><span style="color:#34D399;">👀 Reception</span><?php endif; ?>
            <?php if ($is_admin): ?><span>|</span><span style="color:#FFD700;">⭐ Admin</span><?php endif; ?>
            <span>|</span>
            <span><i class="fas fa-clock"></i> EAT (UTC+3)</span>
            <span>|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                Payment History Report
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn"><i class="fas fa-download"></i> Download</button>
                <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Print</button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

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

    function scrollTable(direction) {
        var wrapper = document.getElementById('tableScrollWrapper');
        if (!wrapper) return;
        if (wrapper.scrollWidth <= wrapper.clientWidth) return;
        
        var scrollAmount = 500;
        var currentScroll = wrapper.scrollLeft;
        var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
        var targetScroll = direction === 'left' 
            ? Math.max(0, currentScroll - scrollAmount)
            : Math.min(maxScroll, currentScroll + scrollAmount);
        
        wrapper.scrollTo({ left: targetScroll, behavior: 'smooth' });
    }

    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); scrollTable('left'); }
        if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); scrollTable('right'); }
    });

    (function() {
        var tableSearch = document.getElementById('tableSearch');
        var paymentRows = document.querySelectorAll('.payment-row');
        var searchClear = document.getElementById('searchClear');
        var searchResultsCount = document.getElementById('searchResultsCount');
        var totalRows = paymentRows.length;
        
        function performSearch() {
            var query = tableSearch.value.toLowerCase().trim();
            var visibleCount = 0;
            
            if (query.length > 0) {
                searchClear.style.display = 'flex';
            } else {
                searchClear.style.display = 'none';
            }
            
            paymentRows.forEach(function(row) {
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

    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var adminPhones = '<?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>';
        var branchName = '<?= htmlspecialchars($user_branch_name) ?>';
        var totalGroups = <?= $total_groups ?>;
        var totalPayments = <?= $summary_total_payments ?>;
        
        var paymentsHtml = '';
        var counter = 1;
        <?php foreach ($grouped_payments as $g): 
            $is_otc = ($g['row_type'] ?? '') === 'otc';
            $is_equipment = ($g['row_type'] ?? '') === 'equipment';
            $payments_count = count($g['payments'] ?? []);
            $received_names = [];
            foreach ($g['received_by_summary'] ?? [] as $rb) {
                $received_names[] = $rb['name'] . ($rb['count'] > 1 ? ' (' . $rb['count'] . ')' : '');
            }
            $visit_date_display = !empty($g['visit_created_at']) ? date('d/m/Y h:i A', strtotime($g['visit_created_at'])) : 'N/A';
        ?>
            paymentsHtml += `
                <tr>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;">${counter}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;"><?= htmlspecialchars($g['bill_number'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:12px;"><span style="background:<?= $is_equipment ? '#CFFAFE;color:#0891B2;' : ($is_otc ? '#DBEAFE;color:#0B5ED7;' : '#D1FAE5;color:#059669;') ?>padding:2px 10px;border-radius:10px;font-weight:600;"><?= $is_equipment ? 'EQP' : ($is_otc ? 'OTC' : 'Regular') ?></span></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><strong><?= htmlspecialchars($g['patient_name'] ?? 'Unknown') ?></strong></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;">${payments_count}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= htmlspecialchars(implode(', ', $received_names)) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= isset($g['last_payment_date']) ? date('d/m/Y h:i A', strtotime($g['last_payment_date'])) : 'N/A' ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;">${'<?= $visit_date_display ?>'}</td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        if (!paymentsHtml) {
            paymentsHtml = `<tr><td colspan="8" style="text-align:center;padding:20px;font-size:14px;color:#64748B;">No payments found</td></tr>`;
        }
        
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;width:auto;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                    <div class="clinic-name">BRAICK DISPENSARY</div>
                    <div class="clinic-sub">Tunajali Afya Yako</div>
                </div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:4px;padding-top:4px;border-top:1px solid #E2E8F0;font-size:0.6rem;color:#64748B;">
                    <span>📞 Admin: ${adminPhones}</span>
                    <span>🏢 Branch: ${branchName}</span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div class="doc-title">💳 Payment History Report (Grouped)</div>
            </div>
            
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-chart-bar"></i> Summary</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:4px 0;">
                    <div style="background:#E8F0FE;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #6EA8FE;">
                        <div style="font-size:20px;font-weight:700;color:#0B5ED7;">${totalGroups}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">📦 Groups (Bills/Sales)</div>
                    </div>
                    <div style="background:#FEF3C7;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #D97706;">
                        <div style="font-size:20px;font-weight:700;color:#D97706;">${totalPayments}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">📋 Total Payments</div>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-list"></i> Payments (${totalGroups} bills)</div>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">#</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Bill #</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">Type</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Patient</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">Payments</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Received By</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Last Payment</th>
                            <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Visit Created</th>
                        </tr>
                    </thead>
                    <tbody>${paymentsHtml}</tbody>
                </table>
            </div>
            
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Generated by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) modalBody.scrollTop = 0;
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Payment_History_<?= date('Y-m-d') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false, allowTaint: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
            pagebreak: { mode: ['css', 'legacy'] }
        };
        html2pdf().set(opt).from(element).save();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePDFModal();
    });

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) closePDFModal();
    });

    console.log('%c💳 Braick - Payment History V5', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ V5: Visit Created column imeongezwa', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ V5: Visit date inaonekana kwenye expand', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ V5: Search inajumuisha visit date', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📦 Total Groups: <?= $total_groups ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Payments: <?= $summary_total_payments ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>