<?php
// ================================================================
// FILE: frontend/pages/admin/export_pharmacy_pdf.php
// EXPORT PHARMACY REPORT TO PDF - HTML FALLBACK VERSION
// BRAICK DISPENSARY - PURPLE THEME
// ✅ Print/Export Page — HAINA shared header/sidebar (kwa design)
// ✅ Purple theme (pharmacy branding)
// ✅ Full responsive + dark mode kwa screen viewing
// ✅ Print-optimized (PDF output ni nyeupe kila wakati)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        default: header('Location: ../../auth/login.php'); break;
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

require_once '../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET ADMIN CONTACT NUMBERS
// ================================================================
$admin_phones = [];
try {
    $stmt = $db->prepare("
        SELECT phone FROM users 
        WHERE role = 'admin' AND branch_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute([$user_branch_id]);
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $admin_phones = [];
}

// ================================================================
// GET BRANCH PHONE
// ================================================================
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

$admin_phones_display = !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001');

// ================================================================
// GET PARAMETERS
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// LOGO PATHS
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><rect width="60" height="60" rx="12" fill="#7C3AED"/><text x="30" y="38" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="Arial">B</text></svg>');

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
}

// ================================================================
// DATE FILTER
// ================================================================
$date_filter = "";
if (!empty($date_from) && !empty($date_to)) {
    $date_filter = " AND b.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
} elseif (!empty($date_from)) {
    $date_filter = " AND b.created_at >= '$date_from 00:00:00'";
} elseif (!empty($date_to)) {
    $date_filter = " AND b.created_at <= '$date_to 23:59:59'";
}

// ================================================================
// BRANCH FILTER
// ================================================================
$branch_filter = "";
if ($branch_id > 0) {
    $branch_filter = " AND b.branch_id = $branch_id";
}

// ================================================================
// FETCH PRESCRIPTION BILLS
// ================================================================
$stmt = $db->query("
    SELECT 
        b.id as bill_id,
        b.bill_number,
        b.patient_id,
        b.total_amount,
        b.paid_amount,
        b.balance,
        b.status as bill_status,
        b.payment_method,
        b.created_at as bill_date,
        b.branch_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as cashier_name,
        br.name as branch_name,
        (
            SELECT COUNT(*) 
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
        ) as medication_count,
        (
            SELECT COALESCE(SUM(bi.total_price), 0)
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
        ) as medication_total
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE EXISTS (
        SELECT 1 
        FROM bill_items bi 
        WHERE bi.bill_id = b.id 
        AND bi.item_type = 'medication'
        AND bi.status != 'cancelled'
    )
    AND b.status = 'paid'
    $branch_filter $date_filter
    ORDER BY b.created_at DESC
");
$prescription_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH MEDICATION ITEMS
// ================================================================
$stmt = $db->query("
    SELECT 
        bi.id,
        bi.bill_id,
        bi.item_name as medication_name,
        bi.quantity,
        bi.unit_price,
        bi.total_price,
        bi.status as item_status,
        bi.created_at,
        b.bill_number,
        b.patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        b.branch_id,
        br.name as branch_name,
        mi.batch_number,
        mi.category,
        mi.unit,
        mi.expiry_date
    FROM bill_items bi
    LEFT JOIN bills b ON bi.bill_id = b.id
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN branches br ON b.branch_id = br.id
    LEFT JOIN medications_inventory mi ON mi.medication_name = bi.item_name AND mi.branch_id = b.branch_id
    WHERE bi.item_type = 'medication'
    AND bi.status != 'cancelled'
    AND b.status = 'paid'
    $branch_filter $date_filter
    ORDER BY bi.created_at DESC
    LIMIT 200
");
$medication_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_prescription_bills = count($prescription_bills);
$total_prescription_amount = 0;
$total_medication_items = 0;
$total_medication_quantity = 0;
$total_medication_value = 0;

foreach ($prescription_bills as $bill) {
    $total_prescription_amount += $bill['medication_total'] ?? 0;
}

foreach ($medication_items as $item) {
    $total_medication_items++;
    $total_medication_quantity += $item['quantity'] ?? 0;
    $total_medication_value += $item['total_price'] ?? 0;
}

// ================================================================
// FETCH OTC SALES
// ================================================================
$otc_branch_filter = "";
$otc_date_filter = "";
if ($branch_id > 0) {
    $otc_branch_filter = " AND os.branch_id = $branch_id";
}
if (!empty($date_from) && !empty($date_to)) {
    $otc_date_filter = " AND os.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
} elseif (!empty($date_from)) {
    $otc_date_filter = " AND os.created_at >= '$date_from 00:00:00'";
} elseif (!empty($date_to)) {
    $otc_date_filter = " AND os.created_at <= '$date_to 23:59:59'";
}

$stmt = $db->query("
    SELECT os.*, u.full_name as cashier_name, br.name as branch_name
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches br ON os.branch_id = br.id
    WHERE os.payment_status = 'paid'
    $otc_branch_filter $otc_date_filter
    ORDER BY os.created_at DESC
");
$otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_otc_sales = count($otc_sales);
$total_otc_amount = 0;
$total_otc_items_sold = 0;

foreach ($otc_sales as $sale) {
    $total_otc_amount += $sale['total_amount'] ?? 0;
}

// ================================================================
// FETCH OTC ITEMS
// ================================================================
$stmt = $db->query("
    SELECT oi.*, os.sale_number, os.customer_name, os.created_at as sale_date,
           br.name as branch_name
    FROM otc_sale_items oi
    LEFT JOIN otc_sales os ON oi.sale_id = os.id
    LEFT JOIN branches br ON os.branch_id = br.id
    WHERE os.payment_status = 'paid'
    $otc_branch_filter $otc_date_filter
    ORDER BY oi.created_at DESC
    LIMIT 200
");
$otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($otc_items as $item) {
    $total_otc_items_sold += $item['quantity'] ?? 0;
}

// ================================================================
// HELPER FUNCTION
// ================================================================
function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'partial' => 'Partial',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'confirmed' => 'Confirmed',
        'dispensed' => 'Dispensed',
        'in_progress' => 'In Progress',
        'scheduled' => 'Scheduled',
        'assigned' => 'Assigned'
    ];
    return $labels[$status] ?? ucfirst($status);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Report - <?= htmlspecialchars($branch_name) ?> - <?= date('M d, Y') ?></title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================
           RESET & BASE
           ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Purple theme (pharmacy) */
            --primary: #7C3AED;
            --primary-dark: #6D28D9;
            --primary-light: #A78BFA;
            --primary-bg: #EDE9FE;

            /* Screen viewing (dark mode) */
            --screen-bg: #0F172A;
            --screen-card: #1E293B;
            --screen-text: #F1F5F9;
            --screen-border: #334155;

            /* Print colors (always light) */
            --print-bg: #F0F4F8;
            --print-card: #FFFFFF;
            --print-text: #1E293B;
            --print-border: #E2E8F0;
        }

        body {
            font-family: 'Segoe UI', 'Inter', Arial, sans-serif;
            background: #F0F4F8;
            padding: 20px;
            color: #1E293B;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Dark mode when system prefers dark (optional - kwa screen viewing tu) */
        @media (prefers-color-scheme: dark) and (screen) {
            body {
                background: #0F172A;
            }
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px 35px;
        }

        /* ================================================================
           ACTION BAR (Top) - Only for screen
           ================================================================ */
        .action-bar {
            max-width: 1100px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .action-bar-left {
            font-size: 13px;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-bar-left i {
            color: #7C3AED;
        }

        .action-bar-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: #7C3AED;
            color: white;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);
        }

        .btn-primary:hover {
            background: #6D28D9;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(124, 58, 237, 0.35);
            color: white;
        }

        .btn-secondary {
            background: #FFFFFF;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
        }

        .btn-secondary:hover {
            background: #F8FAFC;
            border-color: #7C3AED;
            color: #7C3AED;
            transform: translateY(-2px);
        }

        /* ================================================================
           REPORT HEADER - PURPLE THEME
           ================================================================ */
        .report-header {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            color: white;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.25);
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .report-header::after {
            content: '';
            position: absolute;
            bottom: -60%;
            left: -5%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .report-header .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .report-header .brand .logo-container {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
        }

        .report-header .brand .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
        }

        .report-header .brand .logo-text h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin: 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .report-header .brand .logo-text p {
            font-size: 12px;
            opacity: 0.9;
            margin: 2px 0 0 0;
            color: rgba(255,255,255,0.9);
            font-style: italic;
        }

        .report-header .meta-info {
            text-align: right;
            font-size: 12px;
            position: relative;
            z-index: 1;
        }

        .report-header .meta-info .report-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .report-header .meta-info .report-date {
            font-size: 11px;
            opacity: 0.85;
            margin-bottom: 6px;
        }

        .report-header .meta-info .badge-print {
            background: rgba(255,255,255,0.22);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(4px);
        }

        /* ================================================================
           ADMIN CONTACT LINE
           ================================================================ */
        .admin-contact-line {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #64748B;
            padding: 10px 14px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .admin-contact-line span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .admin-contact-line i {
            color: #7C3AED;
            font-size: 11px;
        }

        .admin-contact-line strong {
            color: #1E293B;
            font-weight: 600;
        }

        /* ================================================================
           SUMMARY CARDS
           ================================================================ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--card-color, #7C3AED);
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            border-color: var(--card-color, #7C3AED);
        }

        .summary-card.purple { --card-color: #7C3AED; }
        .summary-card.orange { --card-color: #D97706; }
        .summary-card.blue { --card-color: #0B5ED7; }
        .summary-card.green { --card-color: #059669; }

        .summary-card .number {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
            line-height: 1.1;
        }

        .summary-card.purple .number { color: #7C3AED; }
        .summary-card.orange .number { color: #D97706; }
        .summary-card.blue .number { color: #0B5ED7; }
        .summary-card.green .number { color: #059669; }

        .summary-card .label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .summary-card .sub-label {
            font-size: 8px;
            color: #94A3B8;
            margin-top: 2px;
        }

        /* ================================================================
           SECTION TITLES
           ================================================================ */
        .section-title {
            background: linear-gradient(90deg, #F1F5F9, #F8FAFC);
            padding: 10px 14px;
            font-weight: 700;
            font-size: 13px;
            border-left: 4px solid #7C3AED;
            margin: 20px 0 12px 0;
            border-radius: 0 6px 6px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: #1E293B;
        }

        .section-title i {
            color: #7C3AED;
            margin-right: 6px;
        }

        .section-title .section-count {
            font-size: 11px;
            font-weight: 600;
            color: #7C3AED;
            background: #EDE9FE;
            padding: 2px 10px;
            border-radius: 20px;
        }

        /* ================================================================
           FILTER INFO
           ================================================================ */
        .filter-info {
            background: #F8FAFC;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 11px;
            color: #64748B;
            margin-bottom: 14px;
            border: 1px solid #E2E8F0;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        .filter-info span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filter-info i {
            color: #7C3AED;
        }

        .filter-info strong {
            color: #1E293B;
            font-weight: 700;
        }

        /* ================================================================
           DATA TABLE
           ================================================================ */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            background: white;
        }

        .data-table th {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            color: white;
            padding: 8px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .data-table th:first-child { border-radius: 8px 0 0 0; }
        .data-table th:last-child { border-radius: 0 8px 0 0; }

        .data-table td {
            padding: 7px 12px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
            color: #1E293B;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #FAFBFD;
        }

        .data-table tbody tr:hover td {
            background: #F8F4FF;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tfoot td {
            background: #F1F5F9;
            font-weight: 700;
            font-size: 10px;
            padding: 8px 12px;
            color: #1E293B;
            border-top: 2px solid #7C3AED;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-green { color: #059669; }
        .text-red { color: #DC2626; }
        .text-purple { color: #7C3AED; }
        .font-mono { font-family: 'Courier New', monospace; }
        .font-bold { font-weight: 700; }

        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.3px;
        }

        .badge-success { background: #059669; }
        .badge-warning { background: #D97706; }
        .badge-danger { background: #DC2626; }
        .badge-info { background: #0B5ED7; }
        .badge-purple { background: #7C3AED; }
        .badge-secondary { background: #64748B; }

        /* ================================================================
           OFFICIAL STAMP
           ================================================================ */
        .official-stamp {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 2px dashed #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .official-stamp .stamp-left {
            font-size: 12px;
            color: #64748B;
            line-height: 1.8;
        }

        .official-stamp .stamp-left strong {
            color: #1E293B;
            font-weight: 700;
        }

        .official-stamp .stamp-left .print-info {
            display: block;
            font-size: 10px;
            color: #94A3B8;
            margin-top: 4px;
        }

        .official-stamp .stamp-box {
            text-align: center;
            padding: 12px 24px;
            border: 3px solid #7C3AED;
            border-radius: 12px;
            background: #EDE9FE;
            min-width: 180px;
            position: relative;
        }

        .official-stamp .stamp-box::before {
            content: '';
            position: absolute;
            inset: 4px;
            border: 1px dashed #A78BFA;
            border-radius: 8px;
            pointer-events: none;
        }

        .official-stamp .stamp-box .stamp-title {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
        }

        .official-stamp .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #7C3AED;
            margin: 4px 0;
            letter-spacing: 0.5px;
        }

        .official-stamp .stamp-box .stamp-line {
            font-size: 10px;
            color: #64748B;
            margin-top: 4px;
            border-top: 1px dashed #CBD5E1;
            padding-top: 4px;
        }

        .official-stamp .stamp-box .stamp-date {
            font-size: 9px;
            color: #94A3B8;
            margin-top: 2px;
        }

        /* ================================================================
           NO DATA
           ================================================================ */
        .no-data {
            text-align: center;
            color: #94A3B8;
            padding: 40px 20px;
            font-style: italic;
            background: #F8FAFC;
            border: 1px dashed #E2E8F0;
            border-radius: 8px;
        }

        .no-data i {
            font-size: 28px;
            display: block;
            margin-bottom: 10px;
            color: #CBD5E1;
        }

        .no-data p {
            font-size: 12px;
            margin: 0;
        }

        /* ================================================================
           FOOTER
           ================================================================ */
        .report-footer {
            text-align: center;
            font-size: 10px;
            color: #94A3B8;
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid #E2E8F0;
        }

        .report-footer strong {
            color: #7C3AED;
            font-weight: 700;
        }

        .report-footer .separator {
            margin: 0 8px;
            color: #CBD5E1;
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            body { padding: 12px; }
            .container { padding: 20px; }
            .report-header { flex-direction: column; text-align: center; }
            .report-header .brand { flex-direction: column; }
            .report-header .meta-info { text-align: center; }
            .data-table { font-size: 9px; }
            .data-table th, .data-table td { padding: 5px 8px; }
            .official-stamp { flex-direction: column; text-align: center; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .action-bar-buttons { justify-content: center; }
        }

        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr; }
            .btn { padding: 8px 14px; font-size: 12px; }
        }

        /* ================================================================
           ✅ PRINT STYLES - PDF OUTPUT (NYEUPE KILA WAKATI)
           ================================================================ */
        @media print {
            @page {
                size: A4;
                margin: 10mm 8mm;
            }

            body {
                background: white !important;
                padding: 0 !important;
                color: #1E293B !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .container {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                background: white !important;
            }

            /* Hide interactive elements */
            .action-bar,
            .no-print,
            .btn {
                display: none !important;
            }

            /* Keep colors */
            .report-header {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-shadow: none !important;
            }

            .report-header .brand .logo-container {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .badge,
            .summary-card::before,
            .data-table th,
            .official-stamp .stamp-box {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Table borders for print */
            .data-table {
                border: 1px solid #ddd !important;
            }

            .table-wrapper {
                border: none !important;
            }

            /* Prevent page breaks inside rows */
            .data-table tr {
                page-break-inside: avoid;
            }

            .section-title {
                page-break-after: avoid;
            }

            /* Footer & stamp */
            .official-stamp {
                page-break-inside: avoid;
            }

            .report-footer {
                page-break-before: avoid;
            }

            /* Force colors */
            .summary-card {
                border-color: #ddd !important;
                background: #F8FAFC !important;
            }

            .filter-info {
                background: #F8FAFC !important;
                border-color: #ddd !important;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ACTION BAR - Screen only (hidden in print) -->
<!-- ================================================================ -->
<div class="action-bar no-print">
    <div class="action-bar-left">
        <i class="fas fa-info-circle"></i>
        <span>Click <strong>"Save as PDF"</strong> and choose <strong>"Save as PDF"</strong> as destination</span>
    </div>
    <div class="action-bar-buttons">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Save as PDF / Print
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
</div>

<div class="container">

    <!-- ================================================================ -->
    <!-- REPORT HEADER -->
    <!-- ================================================================ -->
    <div class="report-header">
        <div class="brand">
            <div class="logo-container">
                <img src="<?= $logo_url ?>" 
                     alt="Braick Dispensary Logo" 
                     onerror="this.onerror=null; this.src='<?= $logo_fallback ?>'">
            </div>
            <div class="logo-text">
                <h1>BRAICK DISPENSARY</h1>
                <p>Tunajali Afya Yako</p>
            </div>
        </div>
        <div class="meta-info">
            <div class="report-title">Pharmacy Report</div>
            <div class="report-date">Generated: <?= date('M d, Y h:i A') ?></div>
            <span class="badge-print">
                <i class="fas fa-pills"></i> Pharmacy Report
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ADMIN CONTACT LINE -->
    <!-- ================================================================ -->
    <div class="admin-contact-line">
        <span><i class="fas fa-phone-alt"></i> Admin: <strong><?= htmlspecialchars($admin_phones_display) ?></strong></span>
        <span><i class="fas fa-store"></i> Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong></span>
        <span><i class="fas fa-user-shield"></i> Generated by: <strong><?= htmlspecialchars($user_full_name) ?></strong></span>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER INFO -->
    <!-- ================================================================ -->
    <div class="filter-info">
        <span><i class="fas fa-store"></i> Branch: <strong><?= htmlspecialchars($branch_name) ?></strong></span>
        <?php if (!empty($date_from) || !empty($date_to)): ?>
            <span><i class="fas fa-calendar-alt"></i> Period: 
                <strong>
                    <?= !empty($date_from) ? date('M d, Y', strtotime($date_from)) : 'Start' ?>
                    —
                    <?= !empty($date_to) ? date('M d, Y', strtotime($date_to)) : 'End' ?>
                </strong>
            </span>
        <?php else: ?>
            <span><i class="fas fa-calendar-alt"></i> Period: <strong>All Time</strong></span>
        <?php endif; ?>
        <span><i class="fas fa-prescription"></i> Prescriptions: <strong><?= number_format($total_prescription_bills) ?></strong></span>
        <span><i class="fas fa-shopping-cart"></i> OTC Sales: <strong><?= number_format($total_otc_sales) ?></strong></span>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="summary-grid">
        <div class="summary-card purple">
            <div class="number">TSh <?= number_format($total_prescription_amount, 0) ?></div>
            <div class="label">Prescription Revenue</div>
            <div class="sub-label"><?= number_format($total_prescription_bills) ?> transactions</div>
        </div>
        <div class="summary-card orange">
            <div class="number">TSh <?= number_format($total_otc_amount, 0) ?></div>
            <div class="label">OTC Revenue</div>
            <div class="sub-label"><?= number_format($total_otc_sales) ?> transactions</div>
        </div>
        <div class="summary-card blue">
            <div class="number">TSh <?= number_format($total_prescription_amount + $total_otc_amount, 0) ?></div>
            <div class="label">Total Revenue</div>
            <div class="sub-label">Prescription + OTC</div>
        </div>
        <div class="summary-card green">
            <div class="number"><?= number_format($total_medication_quantity + $total_otc_items_sold) ?></div>
            <div class="label">Total Items Sold</div>
            <div class="sub-label"><?= number_format($total_medication_quantity) ?> Rx · <?= number_format($total_otc_items_sold) ?> OTC</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION BILLS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span><i class="fas fa-prescription"></i> Prescription Bills</span>
        <span class="section-count"><?= count($prescription_bills) ?> records</span>
    </div>

    <?php if (!empty($prescription_bills)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th style="text-align:right;">Items</th>
                        <th style="text-align:right;">Medication Total</th>
                        <th>Branch</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pb_total = 0;
                    $pb_items = 0;
                    
                    foreach ($prescription_bills as $bill):
                        $pb_total += $bill['medication_total'] ?? 0;
                        $pb_items += $bill['medication_count'] ?? 0;
                    ?>
                        <tr>
                            <td class="font-mono" style="font-size:9px;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                            <td style="text-align:right;"><?= number_format($bill['medication_count'] ?? 0) ?></td>
                            <td style="text-align:right;font-weight:bold;color:#7C3AED;">TSh <?= number_format($bill['medication_total'] ?? 0, 0) ?></td>
                            <td><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                            <td style="font-size:9px;"><?= date('M d, Y', strtotime($bill['bill_date'] ?? 'now')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                        <td style="text-align:right;"><?= number_format($pb_items) ?></td>
                        <td style="text-align:right;color:#7C3AED;">TSh <?= number_format($pb_total, 0) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-prescription"></i>
            <p>No prescription bills found for the selected criteria</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MEDICATION ITEMS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span><i class="fas fa-pills"></i> Medication Items</span>
        <span class="section-count"><?= count($medication_items) ?> records</span>
    </div>

    <?php if (!empty($medication_items)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Medication</th>
                        <th>Batch</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $mi_total = 0;
                    $mi_qty = 0;
                    
                    foreach ($medication_items as $item):
                        $mi_total += $item['total_price'] ?? 0;
                        $mi_qty += $item['quantity'] ?? 0;
                    ?>
                        <tr>
                            <td style="font-size:8px;"><?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?></td>
                            <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                            <td style="font-size:8px;color:#94A3B8;"><?= htmlspecialchars($item['batch_number'] ?? '-') ?></td>
                            <td style="text-align:right;"><?= number_format($item['quantity']) ?></td>
                            <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                            <td style="text-align:right;font-weight:bold;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;">GRAND TOTAL</td>
                        <td style="text-align:right;"><?= number_format($mi_qty) ?></td>
                        <td></td>
                        <td style="text-align:right;">TSh <?= number_format($mi_total, 0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-pills"></i>
            <p>No medication items found for the selected criteria</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OTC SALES -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span><i class="fas fa-shopping-cart"></i> OTC Sales</span>
        <span class="section-count"><?= count($otc_sales) ?> records</span>
    </div>

    <?php if (!empty($otc_sales)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">Net</th>
                        <th>Payment</th>
                        <th>Sold By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $otc_total = 0;
                    $otc_discount = 0;
                    $otc_net = 0;
                    
                    foreach ($otc_sales as $sale):
                        $otc_total += $sale['total_amount'] ?? 0;
                        $otc_discount += $sale['discount_amount'] ?? 0;
                        $otc_net += $sale['net_amount'] ?? ($sale['total_amount'] ?? 0);
                    ?>
                        <tr>
                            <td class="font-mono" style="font-size:9px;"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                            <td style="text-align:right;font-weight:bold;">TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                            <td style="text-align:right;">TSh <?= number_format($sale['discount_amount'] ?? 0, 0) ?></td>
                            <td style="text-align:right;color:#059669;font-weight:bold;">TSh <?= number_format($sale['net_amount'] ?? ($sale['total_amount'] ?? 0), 0) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'N/A')) ?></td>
                            <td><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></td>
                            <td style="font-size:9px;"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                        <td style="text-align:right;">TSh <?= number_format($otc_total, 0) ?></td>
                        <td style="text-align:right;">TSh <?= number_format($otc_discount, 0) ?></td>
                        <td style="text-align:right;color:#059669;">TSh <?= number_format($otc_net, 0) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-shopping-cart"></i>
            <p>No OTC sales found for the selected criteria</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OTC ITEMS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span><i class="fas fa-boxes"></i> OTC Items</span>
        <span class="section-count"><?= count($otc_items) ?> records</span>
    </div>

    <?php if (!empty($otc_items)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th>Medicine</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $oi_total = 0;
                    $oi_qty = 0;
                    
                    foreach ($otc_items as $item):
                        $oi_total += $item['total_price'] ?? 0;
                        $oi_qty += $item['quantity'] ?? 0;
                    ?>
                        <tr>
                            <td style="font-size:8px;"><?= htmlspecialchars($item['sale_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['customer_name'] ?? 'Walk-in') ?></td>
                            <td><strong><?= htmlspecialchars($item['medicine_name'] ?? $item['item_name'] ?? 'N/A') ?></strong></td>
                            <td style="text-align:right;"><?= number_format($item['quantity']) ?></td>
                            <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                            <td style="text-align:right;font-weight:bold;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                            <td style="font-size:9px;"><?= date('M d, Y', strtotime($item['sale_date'] ?? 'now')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right;">GRAND TOTAL</td>
                        <td style="text-align:right;"><?= number_format($oi_qty) ?></td>
                        <td></td>
                        <td style="text-align:right;">TSh <?= number_format($oi_total, 0) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-boxes"></i>
            <p>No OTC items found for the selected criteria</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OFFICIAL STAMP -->
    <!-- ================================================================ -->
    <div class="official-stamp">
        <div class="stamp-left">
            <div>Generated by: <strong><?= htmlspecialchars($user_full_name) ?></strong></div>
            <div>Date: <strong><?= date('F d, Y') ?></strong></div>
            <span class="print-info">
                <i class="fas fa-print"></i> Printed: <?= date('h:i A') ?>
            </span>
        </div>
        <div class="stamp-box">
            <div class="stamp-title">Official Stamp</div>
            <div class="stamp-name">BRAICK DISPENSARY</div>
            <div class="stamp-line">Approved By: _________________</div>
            <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <strong>Braick Dispensary</strong> Management System
        <span class="separator">|</span>
        Pharmacy Report
        <span class="separator">|</span>
        <?= date('M d, Y h:i A') ?>
        <span class="separator">|</span>
        &copy; <?= date('Y') ?> All rights reserved
    </div>

</div>

<script>
    // Auto print if URL has ?print parameter
    if (window.location.search.includes('print=1')) {
        setTimeout(function() {
            window.print();
        }, 600);
    }

    // Keyboard shortcut: Ctrl+P to print
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        if (e.key === 'Escape') {
            window.close();
        }
    });

    console.log('%c💊 Braick Dispensary - Pharmacy Report (Print/Export Page)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ USING: bills table + medications_inventory', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Purple theme (pharmacy branding)', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Print-optimized — hakuna shared header/sidebar (kwa design)', 'font-size:13px; color:#F59E0B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Prescription Revenue: TSh <?= number_format($total_prescription_amount, 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Revenue: TSh <?= number_format($total_otc_amount, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📞 Admin Contacts: <?= htmlspecialchars($admin_phones_display) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💡 Keyboard: Ctrl+P = Print | ESC = Close', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>
<?php
exit;
?>