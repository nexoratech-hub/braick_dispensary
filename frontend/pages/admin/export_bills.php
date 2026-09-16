<?php
// ================================================================
// FILE: frontend/pages/admin/export_bills.php
// SUPER ADMIN - EXPORT BILLS (VIEW & EXCEL)
// PDF: Display in new window first with Braick Logo
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
// ✅ Uses SHARED header & sidebar (view/pdf format)
// ✅ Page-specific CSS only
// ✅ Dark mode via header toggle
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
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

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$format = isset($_GET['format']) ? $_GET['format'] : 'view';
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$time_period = isset($_GET['period']) ? $_GET['period'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ================================================================
// BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
}

// ================================================================
// PERIOD LABEL
// ================================================================
$period_labels = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    '3months' => '3 Months',
    '6months' => '6 Months',
    'year' => '1 Year',
    'all' => 'All Time'
];
$period_label = $period_labels[$time_period] ?? 'All Time';

// ================================================================
// BUILD TIME PERIOD FILTER
// ================================================================
$date_condition = '';

switch ($time_period) {
    case 'today':
        $date_condition = "DATE(b.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "b.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "b.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "b.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'all':
    default:
        $date_condition = "1=1";
        break;
}

// ================================================================
// BUILD QUERY CONDITIONS
// ================================================================
$conditions = [$date_condition];
$params = [];

if ($selected_branch_id !== 'all') {
    $conditions[] = "b.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if (!empty($status_filter)) {
    $conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_filter)) {
    $conditions[] = "(b.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// ================================================================
// SORT
// ================================================================
$sort_map = [
    'created_at_desc' => 'b.created_at DESC',
    'created_at_asc' => 'b.created_at ASC',
    'amount_desc' => 'b.total_amount DESC',
    'amount_asc' => 'b.total_amount ASC',
    'bill_number_asc' => 'b.bill_number ASC',
    'bill_number_desc' => 'b.bill_number DESC'
];
$order_by = $sort_map[$sort_by] ?? 'b.created_at DESC';

// ================================================================
// FETCH BILLS
// ================================================================
$sql = "
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        u.full_name as created_by_name,
        br.name as branch_name,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id) as item_count,
        (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    $where_clause
    ORDER BY $order_by
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_bills = count($bills);
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;
$status_counts = ['pending' => 0, 'partial' => 0, 'paid' => 0, 'cancelled' => 0];

foreach ($bills as $bill) {
    $total_amount += (float)$bill['total_amount'];
    $total_paid += (float)$bill['paid_amount'];
    $total_balance += (float)$bill['balance'];
    if (isset($status_counts[$bill['status']])) {
        $status_counts[$bill['status']]++;
    }
}

// ================================================================
// GET STATUS LABEL
// ================================================================
function getStatusLabel($status) {
    $labels = [
        'paid' => 'Paid',
        'pending' => 'Pending',
        'partial' => 'Partial',
        'cancelled' => 'Cancelled'
    ];
    return $labels[$status] ?? ucfirst($status);
}

// ================================================================
// GET LOGO
// ================================================================
function getLogoBase64() {
    $logo_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpeg',
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
    ];
    
    foreach ($logo_paths as $path) {
        if (file_exists($path)) {
            $logo_data = file_get_contents($path);
            $mime_type = mime_content_type($path);
            return 'data:' . $mime_type . ';base64,' . base64_encode($logo_data);
        }
    }
    return '';
}

$logo_base64 = getLogoBase64();
$logo_available = !empty($logo_base64);
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// EXCEL EXPORT (CSV) - HAITUMII HTML
// ================================================================
if ($format === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bills_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'Bill Number',
        'Patient Name',
        'Patient ID',
        'Total Amount',
        'Paid Amount',
        'Balance',
        'Status',
        'Branch',
        'Items',
        'Created By',
        'Date Created'
    ]);
    
    foreach ($bills as $bill) {
        fputcsv($output, [
            $bill['bill_number'],
            $bill['patient_name'] ?? 'N/A',
            $bill['patient_id_number'] ?? 'N/A',
            'TSh ' . number_format($bill['total_amount'], 0),
            'TSh ' . number_format($bill['paid_amount'], 0),
            'TSh ' . number_format($bill['balance'], 0),
            getStatusLabel($bill['status']),
            $bill['branch_name'] ?? 'N/A',
            $bill['item_count'] ?? 0,
            $bill['created_by_name'] ?? 'N/A',
            date('Y-m-d H:i', strtotime($bill['created_at']))
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Bills', number_format($total_bills)]);
    fputcsv($output, ['Total Amount', 'TSh ' . number_format($total_amount, 0)]);
    fputcsv($output, ['Total Paid', 'TSh ' . number_format($total_paid, 0)]);
    fputcsv($output, ['Total Balance', 'TSh ' . number_format($total_balance, 0)]);
    fputcsv($output, ['Paid Bills', number_format($status_counts['paid'] ?? 0)]);
    fputcsv($output, ['Pending Bills', number_format($status_counts['pending'] ?? 0)]);
    fputcsv($output, ['Partial Bills', number_format($status_counts['partial'] ?? 0)]);
    fputcsv($output, ['Cancelled Bills', number_format($status_counts['cancelled'] ?? 0)]);
    fputcsv($output, []);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Branch', $branch_name]);
    fputcsv($output, ['Period', $period_label]);
    
    fclose($output);
    exit;
}

// ================================================================
// VIEW / PDF FORMAT - INAHITAJI HTML
// ================================================================
if ($format === 'pdf' || $format === 'view') {
    $is_pdf_view = ($format === 'pdf');
    
    // ================================================================
    // INCLUDE SHARED HEADER & SIDEBAR
    // ================================================================
    include_once __DIR__ . '/../../components/admin_header.php';
    include_once __DIR__ . '/../../components/admin_sidebar.php';
    ?>

    <!-- ================================================================ -->
    <!-- PAGE-SPECIFIC CSS -->
    <!-- ================================================================ -->
    <style>
        /* ================================================================
           PAGE VARIABLES
           ================================================================ */
        :root {
            --exp-primary: #0B5ED7;
            --exp-primary-dark: #0A4CA8;
            --exp-success: #059669;
            --exp-success-dark: #047857;
            --exp-danger: #DC2626;
            --exp-warning: #D97706;
            --exp-purple: #7C3AED;
            --exp-bg-body: #F1F5F9;
            --exp-bg-card: #FFFFFF;
            --exp-text-primary: #1E293B;
            --exp-text-secondary: #64748B;
            --exp-border-color: #E2E8F0;
            --exp-radius: 12px;
            --exp-radius-lg: 16px;
            --exp-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --exp-shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        }

        [data-theme="dark"] {
            --exp-bg-body: #0F172A;
            --exp-bg-card: #1E293B;
            --exp-text-primary: #F1F5F9;
            --exp-text-secondary: #94A3B8;
            --exp-border-color: #334155;
            --exp-shadow-md: 0 4px 20px rgba(0,0,0,0.4);
        }

        /* ================================================================
           DARK MODE - PAGE YOTE
           ================================================================ */
        html[data-theme="dark"] body {
            background: #0F172A !important;
        }

        html[data-theme="dark"] .main-content {
            background: #0F172A !important;
            color: #F1F5F9;
        }

        /* ================================================================
           PAGE HEADER CARD
           ================================================================ */
        .page-header-card {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .page-header-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header-card:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }

        .page-header-left {
            position: relative;
            z-index: 2;
            flex: 1;
            min-width: 280px;
        }

        .page-header-title {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            flex-wrap: wrap;
        }

        .page-header-title i {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            flex-shrink: 0;
        }

        .page-header-subtitle {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.9);
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .page-header-branch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            color: white;
            backdrop-filter: blur(10px);
        }

        .page-header-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            z-index: 2;
        }

        .page-header-stat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            backdrop-filter: blur(10px);
        }

        .page-header-stat i { font-size: 0.85rem; opacity: 0.9; }

        .page-header-stat .stat-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.72rem;
            margin-left: 2px;
        }

        .page-header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .page-header-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.3);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            white-space: nowrap;
            cursor: pointer;
            font-family: inherit;
        }

        .page-header-btn:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .page-header-btn.primary {
            background: rgba(255,255,255,0.95);
            color: #0B5ED7;
            border-color: white;
            font-weight: 700;
        }

        .page-header-btn.primary:hover {
            background: white;
            color: #0A4CA8;
        }

        /* ================================================================
           REPORT CONTAINER
           ================================================================ */
        .report-container {
            max-width: 100%;
            margin: 0 auto;
            background: var(--exp-bg-card);
            border-radius: var(--exp-radius-lg);
            padding: 30px 35px;
            box-shadow: var(--exp-shadow-md);
            border: 1px solid var(--exp-border-color);
            margin-bottom: 24px;
        }

        .report-header {
            text-align: center;
            border-bottom: 4px solid var(--exp-success);
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .report-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 8px;
        }

        .report-header .logo-container .logo-img {
            max-height: 70px;
            width: auto;
            max-width: 120px;
            object-fit: contain;
        }

        .report-header .logo-container .logo-text {
            text-align: left;
        }

        .report-header .logo-container .logo-text h1 {
            font-size: 28px;
            color: var(--exp-success);
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1.1;
        }

        .report-header .logo-container .logo-text .subtitle {
            font-size: 14px;
            color: var(--exp-success);
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .report-header .logo-container .logo-text .tagline {
            font-size: 10px;
            color: var(--exp-text-secondary);
            font-weight: 400;
        }

        .report-header .info {
            font-size: 12px;
            color: var(--exp-text-secondary);
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .report-header .info span {
            background: var(--exp-bg-body);
            padding: 4px 14px;
            border-radius: 20px;
            border: 1px solid var(--exp-border-color);
        }

        .report-header .info strong {
            color: var(--exp-success);
        }

        /* ================================================================
           SUMMARY GRID
           ================================================================ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: var(--exp-bg-body);
            border: 2px solid var(--exp-border-color);
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            border-color: var(--exp-success);
            transform: translateY(-2px);
        }

        .summary-card .number {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
        }

        .summary-card .number.green { color: var(--exp-success); }
        .summary-card .number.orange { color: #F59E0B; }
        .summary-card .number.red { color: #EF4444; }
        .summary-card .number.blue { color: var(--exp-primary); }

        .summary-card .label {
            font-size: 10px;
            color: var(--exp-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            margin-top: 4px;
        }

        /* ================================================================
           FINANCIAL SUMMARY
           ================================================================ */
        .financial-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 25px;
        }

        .financial-card {
            padding: 14px 18px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid var(--exp-border-color);
        }

        .financial-card.blue { background: #E8F0FE; border-color: #0B5ED7; }
        .financial-card.blue .amount { color: #0B5ED7; }

        .financial-card.green { background: #D1FAE5; border-color: #059669; }
        .financial-card.green .amount { color: #059669; }

        .financial-card.orange { background: #FEF3C7; border-color: #F59E0B; }
        .financial-card.orange .amount { color: #D97706; }

        [data-theme="dark"] .financial-card.blue { background: #1E3A5F; border-color: #3B82F6; }
        [data-theme="dark"] .financial-card.blue .amount { color: #6EA8FE; }

        [data-theme="dark"] .financial-card.green { background: #1A3A2A; border-color: #34D399; }
        [data-theme="dark"] .financial-card.green .amount { color: #34D399; }

        [data-theme="dark"] .financial-card.orange { background: #3D2E0A; border-color: #FBBF24; }
        [data-theme="dark"] .financial-card.orange .amount { color: #FBBF24; }

        .financial-card .label {
            font-size: 10px;
            color: var(--exp-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        .financial-card .amount {
            font-size: 22px;
            font-weight: 800;
            margin-top: 2px;
        }

        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 5px;
            border-radius: 8px;
        }

        .data-table-exp {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .data-table-exp thead th {
            background: var(--exp-success);
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.05em;
            border-bottom: 3px solid var(--exp-success-dark);
            white-space: nowrap;
        }

        .data-table-exp thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        .data-table-exp thead th:last-child {
            border-radius: 0 8px 0 0;
        }

        .data-table-exp tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--exp-border-color);
            vertical-align: middle;
            color: var(--exp-text-primary);
        }

        .data-table-exp tbody tr:nth-child(even) {
            background: var(--exp-bg-body);
        }

        .data-table-exp tbody tr:hover {
            background: #D1FAE5;
        }

        [data-theme="dark"] .data-table-exp tbody tr:hover {
            background: #1A3A2A;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .bill-number {
            font-weight: 700;
            font-family: monospace;
            font-size: 11px;
            color: var(--exp-success);
        }

        .patient-name {
            font-weight: 600;
            font-size: 11px;
        }

        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge-exp {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .status-badge-exp.paid {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #059669;
        }

        .status-badge-exp.pending {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #D97706;
        }

        .status-badge-exp.partial {
            background: #EDE9FE;
            color: #7B2FBE;
            border: 1px solid #7B2FBE;
        }

        .status-badge-exp.cancelled {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #DC2626;
        }

        [data-theme="dark"] .status-badge-exp.paid { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .status-badge-exp.pending { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
        [data-theme="dark"] .status-badge-exp.partial { background: #2D1B4E; color: #A78BFA; border-color: #A78BFA; }
        [data-theme="dark"] .status-badge-exp.cancelled { background: #3A1A1A; color: #F87171; border-color: #F87171; }

        /* ================================================================
           REPORT FOOTER
           ================================================================ */
        .report-footer-exp {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 3px solid var(--exp-success);
            text-align: center;
            font-size: 10px;
            color: var(--exp-text-secondary);
        }

        .report-footer-exp .brand {
            color: var(--exp-success);
            font-weight: 700;
            font-size: 12px;
        }

        .report-footer-exp .brand span {
            font-weight: 300;
            color: var(--exp-text-secondary);
        }

        /* ================================================================
           PDF HINT
           ================================================================ */
        .pdf-hint {
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            background: var(--exp-bg-body);
            border: 2px dashed var(--exp-success);
            border-radius: 10px;
            font-size: 13px;
            color: var(--exp-success);
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
        }

        .pdf-hint i { margin-right: 8px; }
        .pdf-hint strong { font-weight: 700; }

        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, #sidebarToggle, .dark-toggle-btn,
            .page-header-card, .pdf-hint, .footer,
            .main-content > *:not(.report-container) {
                display: none !important;
            }

            body {
                background: white !important;
                padding: 0 !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }

            .report-container {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 20px !important;
                max-width: 100% !important;
                border: none !important;
                margin: 0 !important;
            }

            .report-header .logo-container .logo-img {
                max-height: 50px !important;
            }

            .data-table-exp thead th {
                background: #059669 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .status-badge-exp,
            .financial-card,
            .summary-card {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .financial-summary {
                grid-template-columns: 1fr;
            }
            .report-container {
                padding: 16px 18px;
            }
            .report-header .logo-container {
                flex-direction: column;
                text-align: center;
            }
            .report-header .logo-container .logo-text {
                text-align: center;
            }
            .report-header .logo-container .logo-text h1 {
                font-size: 22px;
            }
            .report-header .logo-container .logo-img {
                max-height: 50px;
            }
            .data-table-exp {
                font-size: 9px;
            }
            .data-table-exp thead th,
            .data-table-exp tbody td {
                padding: 5px 8px;
            }
        }

        @media (max-width: 480px) {
            .page-header-card { flex-direction: column; align-items: flex-start !important; }
            .page-header-title { font-size: 1.2rem; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>

    <!-- ================================================================ -->
    <!-- MAIN CONTENT -->
    <!-- ================================================================ -->
    <main class="main-content">

        <!-- ================================================================ -->
        <!-- PAGE HEADER CARD -->
        <!-- ================================================================ -->
        <div class="page-header-card">
            <div class="page-header-left">
                <h1 class="page-header-title">
                    <i class="fas fa-file-invoice"></i>
                    Bills Report
                </h1>
                <p class="page-header-subtitle">
                    <i class="fas fa-chart-line"></i>
                    Financial report for <?= htmlspecialchars($branch_name) ?> &bull; <?= $period_label ?>
                    <span class="page-header-branch">
                        <i class="fas fa-store-alt"></i>
                        <?= htmlspecialchars($branch_name) ?>
                    </span>
                </p>
                <div class="page-header-stats">
                    <span class="page-header-stat">
                        <i class="fas fa-file-invoice"></i>
                        Total Bills
                        <span class="stat-count"><?= number_format($total_bills) ?></span>
                    </span>
                    <span class="page-header-stat">
                        <i class="fas fa-money-bill-wave"></i>
                        Total Amount
                        <span class="stat-count">TSh <?= number_format($total_amount, 0) ?></span>
                    </span>
                    <span class="page-header-stat">
                        <i class="fas fa-check-circle"></i>
                        Paid
                        <span class="stat-count"><?= number_format($status_counts['paid'] ?? 0) ?></span>
                    </span>
                </div>
            </div>
            <div class="page-header-actions">
                <button onclick="window.print()" class="page-header-btn primary">
                    <i class="fas fa-file-pdf"></i> Save as PDF
                </button>
                <button onclick="window.print()" class="page-header-btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="bills.php?branch=<?= $selected_branch_id ?>" class="page-header-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- REPORT CONTAINER -->
        <!-- ================================================================ -->
        <div class="report-container" id="reportContainer">
            
            <!-- Report Header -->
            <div class="report-header">
                <div class="logo-container">
                    <?php if ($logo_available): ?>
                        <img src="<?= $logo_base64 ?>" alt="Braick Dispensary Logo" class="logo-img">
                    <?php else: ?>
                        <span style="font-size: 50px; color: #059669;">🏥</span>
                    <?php endif; ?>
                    <div class="logo-text">
                        <h1>BRAICK DISPENSARY</h1>
                        <div class="subtitle">Bills Report</div>
                        <div class="tagline">Quality Healthcare Services</div>
                    </div>
                </div>
                <div class="info">
                    <span><strong>Branch:</strong> <?= htmlspecialchars($branch_name) ?></span>
                    <span><strong>Period:</strong> <?= $period_label ?></span>
                    <span><strong>Generated:</strong> <?= date('F d, Y h:i A') ?></span>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number blue"><?= number_format($total_bills) ?></div>
                    <div class="label">Total Bills</div>
                </div>
                <div class="summary-card">
                    <div class="number green"><?= number_format($status_counts['paid'] ?? 0) ?></div>
                    <div class="label">✅ Paid</div>
                </div>
                <div class="summary-card">
                    <div class="number orange"><?= number_format($status_counts['pending'] ?? 0) ?></div>
                    <div class="label">⏳ Pending</div>
                </div>
                <div class="summary-card">
                    <div class="number red"><?= number_format($status_counts['partial'] ?? 0) ?></div>
                    <div class="label">⏳ Partial</div>
                </div>
            </div>
            
            <!-- Financial Summary -->
            <div class="financial-summary">
                <div class="financial-card blue">
                    <div class="label">Total Amount</div>
                    <div class="amount">TSh <?= number_format($total_amount, 0) ?></div>
                </div>
                <div class="financial-card green">
                    <div class="label">Total Paid</div>
                    <div class="amount">TSh <?= number_format($total_paid, 0) ?></div>
                </div>
                <div class="financial-card orange">
                    <div class="label">Total Balance</div>
                    <div class="amount">TSh <?= number_format($total_balance, 0) ?></div>
                </div>
            </div>
            
            <!-- Bills Table -->
            <div class="table-wrapper">
                <table class="data-table-exp">
                    <thead>
                        <tr>
                            <th style="width:30px;">#</th>
                            <th>Bill Number</th>
                            <th>Patient</th>
                            <th style="text-align:right;">Amount</th>
                            <th style="text-align:right;">Paid</th>
                            <th style="text-align:right;">Balance</th>
                            <th>Status</th>
                            <th>Branch</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bills) > 0): ?>
                            <?php $i = 1; foreach ($bills as $bill): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="bill-number"><?= htmlspecialchars($bill['bill_number']) ?></span>
                                        <?php if ($bill['item_count'] > 0): ?>
                                            <br><span style="font-size:8px;color:var(--exp-text-secondary);"><?= $bill['item_count'] ?> items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="patient-name"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
                                        <br><span style="font-size:8px;color:var(--exp-text-secondary);"><?= htmlspecialchars($bill['patient_id_number'] ?? '') ?></span>
                                    </td>
                                    <td class="text-right" style="font-weight:700;">TSh <?= number_format($bill['total_amount'], 0) ?></td>
                                    <td class="text-right" style="color:var(--exp-success);font-weight:600;">TSh <?= number_format($bill['paid_amount'], 0) ?></td>
                                    <td class="text-right" style="font-weight:700;color:<?= $bill['balance'] > 0 ? '#EF4444' : 'var(--exp-success)' ?>;">
                                        TSh <?= number_format($bill['balance'], 0) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge-exp <?= $bill['status'] ?>">
                                            <?= getStatusLabel($bill['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                                    <td style="font-size:9px;"><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:30px;color:var(--exp-text-secondary);font-size:14px;">
                                    <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                                    No bills found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer -->
            <div class="report-footer-exp">
                <span class="brand">Braick Dispensary <span>&bull; Quality Healthcare Services</span></span>
                <br>
                <span style="font-size:9px;">
                    Report generated on <?= date('F d, Y h:i:s A') ?> &bull; 
                    <?= number_format($total_bills) ?> bills
                </span>
            </div>
            
        </div>

        <!-- PDF Hint -->
        <div class="pdf-hint">
            <i class="fas fa-info-circle"></i>
            <strong>To save as PDF:</strong> Click "Save as PDF" button, then choose <strong>"Save as PDF"</strong> in the print dialog
        </div>

        <!-- FOOTER -->
        <footer style="padding:14px 0;border-top:1px solid var(--exp-border-color);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--exp-text-secondary);">
            <p>
                <span style="color:#0B5ED7;font-weight:600;">Braick Dispensary</span> Management System
                <span>|</span>
                Bills Report
                <span>|</span>
                <span id="footerTime"><?= date('H:i:s') ?></span>
                <span>|</span>
                &copy; <?= date('Y') ?> All rights reserved
            </p>
        </footer>

    </main>

    <!-- ================================================================ -->
    <!-- PAGE-SPECIFIC JAVASCRIPT -->
    <!-- ================================================================ -->
    <script>
        // ================================================================
        // AUTO PRINT FOR PDF VIEW
        // ================================================================
        <?php if ($is_pdf_view): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1500);
        };
        <?php endif; ?>
        
        // ================================================================
        // KEYBOARD SHORTCUTS
        // ================================================================
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
        
        // ================================================================
        // FOOTER TIME
        // ================================================================
        setInterval(function() {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            var ftEl = document.getElementById('footerTime');
            if (ftEl) ftEl.textContent = timeStr;
        }, 1000);

        console.log('%c📄 Braick Dispensary - Bills Report', 'font-size:18px; font-weight:bold; color:#059669;');
        console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
        console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
        console.log('%c📊 Total Bills: <?= number_format($total_bills) ?>', 'font-size:13px; color:#0B5ED7;');
        console.log('%c💰 Total Amount: TSh <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#7B2FBE;');
        console.log('%c🖼️ Logo: <?= $logo_available ? '✅ Loaded' : '❌ Not found' ?>', 'font-size:13px; color:#059669;');
        console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
        console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
    </script>

    </body>
    </html>
    <?php
    exit;
}

// ================================================================
// INVALID FORMAT
// ================================================================
header('Location: bills.php?branch=' . $selected_branch_id);
exit;
?>