<?php
// ================================================================
// FILE: frontend/pages/admin/export_bills.php
// SUPER ADMIN - EXPORT BILLS (VIEW & EXCEL)
// PDF: Display in new window first with Braick Logo
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
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
// BUILD TIME PERIOD FILTER - FIXED: Using bills table
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
// BUILD QUERY CONDITIONS - FIXED: Using bills table
// ================================================================
$conditions = [$date_condition];
$params = [];

// Branch filter
if ($selected_branch_id !== 'all') {
    $conditions[] = "b.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// Status filter
if (!empty($status_filter)) {
    $conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

// Search filter
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
// FETCH BILLS - FIXED: Using bills table
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

// ================================================================
// VIEW / PDF FORMAT
// ================================================================
if ($format === 'pdf' || $format === 'view') {
    $is_pdf_view = ($format === 'pdf');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bills Report - Braick Dispensary</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Helvetica', 'Arial', sans-serif;
                background: #f0f4f8;
                padding: 20px;
                min-height: 100vh;
            }
            
            .control-bar {
                max-width: 1200px;
                margin: 0 auto 20px auto;
                background: white;
                border-radius: 16px;
                padding: 16px 24px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
                border: 2px solid #059669;
            }
            
            .control-bar .title-section h2 {
                font-size: 18px;
                font-weight: 700;
                color: #059669;
                margin: 0;
            }
            
            .control-bar .title-section p {
                font-size: 12px;
                color: #64748B;
                margin: 2px 0 0 0;
            }
            
            .control-bar .button-group {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                border: none;
                text-decoration: none;
                font-family: 'Helvetica', 'Arial', sans-serif;
            }
            
            .btn-download {
                background: #059669;
                color: white;
                box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            }
            
            .btn-download:hover {
                background: #047857;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
            }
            
            .btn-print {
                background: #0B5ED7;
                color: white;
                box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
            }
            
            .btn-print:hover {
                background: #0A4CA8;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
            }
            
            .btn-close {
                background: #EF4444;
                color: white;
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            }
            
            .btn-close:hover {
                background: #DC2626;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
            }
            
            .btn i {
                font-size: 16px;
            }
            
            .report-container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 16px;
                padding: 30px 35px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                border: 1px solid #E2E8F0;
            }
            
            .report-header {
                text-align: center;
                border-bottom: 4px solid #059669;
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
                color: #059669;
                font-weight: 800;
                letter-spacing: 1px;
                margin: 0;
                line-height: 1.1;
            }
            
            .report-header .logo-container .logo-text .subtitle {
                font-size: 14px;
                color: #059669;
                font-weight: 600;
                letter-spacing: 2px;
                text-transform: uppercase;
            }
            
            .report-header .logo-container .logo-text .tagline {
                font-size: 10px;
                color: #64748B;
                font-weight: 400;
            }
            
            .report-header .info {
                font-size: 12px;
                color: #64748B;
                margin-top: 10px;
                display: flex;
                justify-content: center;
                gap: 20px;
                flex-wrap: wrap;
            }
            
            .report-header .info span {
                background: #F8FAFC;
                padding: 4px 14px;
                border-radius: 20px;
                border: 1px solid #E2E8F0;
            }
            
            .report-header .info strong {
                color: #059669;
            }
            
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 14px;
                margin-bottom: 25px;
            }
            
            .summary-card {
                background: #F8FAFC;
                border: 2px solid #E2E8F0;
                border-radius: 12px;
                padding: 14px 16px;
                text-align: center;
                transition: all 0.3s ease;
            }
            
            .summary-card:hover {
                border-color: #059669;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
            }
            
            .summary-card .number {
                font-size: 24px;
                font-weight: 800;
                line-height: 1.2;
            }
            
            .summary-card .number.green { color: #059669; }
            .summary-card .number.orange { color: #F59E0B; }
            .summary-card .number.red { color: #EF4444; }
            .summary-card .number.blue { color: #0B5ED7; }
            
            .summary-card .label {
                font-size: 10px;
                color: #64748B;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
                margin-top: 4px;
            }
            
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
                border: 2px solid #E2E8F0;
            }
            
            .financial-card.blue { background: #E8F0FE; border-color: #0B5ED7; }
            .financial-card.blue .amount { color: #0B5ED7; }
            
            .financial-card.green { background: #D1FAE5; border-color: #059669; }
            .financial-card.green .amount { color: #059669; }
            
            .financial-card.orange { background: #FEF3C7; border-color: #F59E0B; }
            .financial-card.orange .amount { color: #D97706; }
            
            .financial-card .label {
                font-size: 10px;
                color: #64748B;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
            }
            
            .financial-card .amount {
                font-size: 22px;
                font-weight: 800;
                margin-top: 2px;
            }
            
            .table-wrapper {
                overflow-x: auto;
                margin-top: 5px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }
            
            table thead th {
                background: #059669;
                color: white;
                padding: 10px 12px;
                text-align: left;
                font-weight: 700;
                text-transform: uppercase;
                font-size: 9px;
                letter-spacing: 0.05em;
                border-bottom: 3px solid #047857;
                white-space: nowrap;
            }
            
            table thead th:first-child {
                border-radius: 8px 0 0 0;
            }
            
            table thead th:last-child {
                border-radius: 0 8px 0 0;
            }
            
            table tbody td {
                padding: 8px 12px;
                border-bottom: 1px solid #E2E8F0;
                vertical-align: middle;
            }
            
            table tbody tr:nth-child(even) {
                background: #F8FAFC;
            }
            
            table tbody tr:hover {
                background: #D1FAE5;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .bill-number {
                font-weight: 700;
                font-family: monospace;
                font-size: 11px;
                color: #059669;
            }
            
            .patient-name {
                font-weight: 600;
                font-size: 11px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 3px 12px;
                border-radius: 20px;
                font-size: 8px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            
            .status-badge.paid {
                background: #D1FAE5;
                color: #059669;
                border: 1px solid #059669;
            }
            
            .status-badge.pending {
                background: #FEF3C7;
                color: #D97706;
                border: 1px solid #D97706;
            }
            
            .status-badge.partial {
                background: #EDE9FE;
                color: #7B2FBE;
                border: 1px solid #7B2FBE;
            }
            
            .status-badge.cancelled {
                background: #FEE2E2;
                color: #DC2626;
                border: 1px solid #DC2626;
            }
            
            .report-footer {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 3px solid #059669;
                text-align: center;
                font-size: 10px;
                color: #94A3B8;
            }
            
            .report-footer .brand {
                color: #059669;
                font-weight: 700;
                font-size: 12px;
            }
            
            .report-footer .brand span {
                font-weight: 300;
                color: #94A3B8;
            }
            
            .pdf-hint {
                text-align: center;
                margin-top: 20px;
                padding: 12px;
                background: #F8FAFC;
                border: 2px dashed #059669;
                border-radius: 10px;
                font-size: 13px;
                color: #059669;
            }
            
            .pdf-hint i {
                margin-right: 8px;
            }
            
            .pdf-hint strong {
                font-weight: 700;
            }
            
            @media print {
                body {
                    background: white !important;
                    padding: 0 !important;
                }
                
                .control-bar {
                    display: none !important;
                }
                
                .pdf-hint {
                    display: none !important;
                }
                
                .report-container {
                    box-shadow: none !important;
                    border-radius: 0 !important;
                    padding: 20px !important;
                    max-width: 100% !important;
                    border: none !important;
                }
                
                .report-header .logo-container .logo-img {
                    max-height: 50px !important;
                }
                
                table thead th {
                    background: #059669 !important;
                    color: white !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .status-badge {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .financial-card {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .summary-card {
                    border-color: #ddd !important;
                }
            }
            
            @media (max-width: 768px) {
                .summary-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                .financial-summary {
                    grid-template-columns: 1fr;
                }
                .control-bar {
                    flex-direction: column;
                    align-items: stretch;
                    text-align: center;
                }
                .control-bar .button-group {
                    justify-content: center;
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
                table {
                    font-size: 9px;
                }
                table thead th,
                table tbody td {
                    padding: 5px 8px;
                }
                .btn {
                    padding: 8px 14px;
                    font-size: 12px;
                }
            }
        </style>
    </head>
    <body>
        
        <!-- Control Bar -->
        <div class="control-bar">
            <div class="title-section">
                <h2><i class="fas fa-file-invoice" style="color:#059669;margin-right:8px;"></i>Bills Report</h2>
                <p><?= htmlspecialchars($branch_name) ?> &bull; <?= $period_label ?> &bull; <?= number_format($total_bills) ?> bills</p>
            </div>
            <div class="button-group">
                <button onclick="window.print()" class="btn btn-download">
                    <i class="fas fa-file-pdf"></i> Save as PDF
                </button>
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="window.close()" class="btn btn-close">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        
        <!-- Report -->
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
                <table>
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
                                            <br><span style="font-size:8px;color:#94A3B8;"><?= $bill['item_count'] ?> items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="patient-name"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
                                        <br><span style="font-size:8px;color:#94A3B8;"><?= htmlspecialchars($bill['patient_id_number'] ?? '') ?></span>
                                    </td>
                                    <td class="text-right" style="font-weight:700;">TSh <?= number_format($bill['total_amount'], 0) ?></td>
                                    <td class="text-right" style="color:#059669;font-weight:600;">TSh <?= number_format($bill['paid_amount'], 0) ?></td>
                                    <td class="text-right" style="font-weight:700;color:<?= $bill['balance'] > 0 ? '#EF4444' : '#059669' ?>;">
                                        TSh <?= number_format($bill['balance'], 0) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $bill['status'] ?>">
                                            <?= getStatusLabel($bill['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                                    <td style="font-size:9px;"><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:30px;color:#94A3B8;font-size:14px;">
                                    <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;color:#D1D5DB;"></i>
                                    No bills found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer -->
            <div class="report-footer">
                <span class="brand">Braick Dispensary <span>&bull; Quality Healthcare Services</span></span>
                <br>
                <span style="font-size:9px;color:#CBD5E1;">
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
        
        <script>
            <?php if ($is_pdf_view): ?>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1500);
            };
            <?php endif; ?>
            
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
                if (e.key === 'Escape') {
                    window.close();
                }
            });
            
            console.log('%c📄 Braick Dispensary - Bills Report', 'font-size:18px; font-weight:bold; color:#059669;');
            console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
            console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
            console.log('%c📊 Total Bills: <?= number_format($total_bills) ?>', 'font-size:13px; color:#0B5ED7;');
            console.log('%c💰 Total Amount: TSh <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#7B2FBE;');
            console.log('%c🖼️ Logo: <?= $logo_available ? '✅ Loaded' : '❌ Not found' ?>', 'font-size:13px; color:#059669;');
            console.log('%c✅ Using bills table (not patient_bills)', 'font-size:13px; color:#34D399;');
        </script>
        
    </body>
    </html>
    <?php
    exit;
}

// ================================================================
// EXCEL EXPORT (CSV)
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
// INVALID FORMAT
// ================================================================
header('Location: bills.php?branch=' . $selected_branch_id);
exit;
?>