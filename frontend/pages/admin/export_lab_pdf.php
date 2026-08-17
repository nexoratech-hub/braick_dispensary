<?php
// ================================================================
// FILE: frontend/pages/admin/export_lab_pdf.php
// EXPORT LAB REPORT TO PDF - HTML FALLBACK VERSION
// BRAICK DISPENSARY - PURPLE THEME
// FIXED: GROUP BY lt.id to avoid duplicate tests
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// SESSION START
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

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><rect width="60" height="60" rx="12" fill="#0B5ED7"/><text x="30" y="38" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="Arial">B</text></svg>');

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
// BUILD DATE FILTER
// ================================================================
$date_filter = "";
if (!empty($date_from) && !empty($date_to)) {
    $date_filter = " AND lt.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
} elseif (!empty($date_from)) {
    $date_filter = " AND lt.created_at >= '$date_from 00:00:00'";
} elseif (!empty($date_to)) {
    $date_filter = " AND lt.created_at <= '$date_to 23:59:59'";
}

// ================================================================
// BRANCH FILTER
// ================================================================
$branch_filter = "";
if ($branch_id > 0) {
    $branch_filter = " AND lt.branch_id = $branch_id";
}

// ================================================================
// FETCH LAB DATA - WITH GROUP BY TO AVOID DUPLICATES
// ================================================================

// All lab tests with patient info - GROUP BY lt.id to avoid duplicates
$stmt = $db->query("
    SELECT 
        lt.id,
        lt.visit_id,
        lt.doctor_id,
        lt.lab_technician_id,
        lt.test_name,
        lt.test_price,
        lt.test_type,
        lt.sample_type,
        lt.test_date,
        lt.results,
        lt.reference_range,
        lt.interpretation,
        lt.performed_by,
        lt.status,
        lt.bill_created,
        lt.notes,
        lt.technician_id,
        lt.branch_id,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        lt.result_template_id,
        lt.formatted_result,
        lt.printed_at,
        lt.printed_by,
        p.full_name as patient_name, 
        p.patient_id as patient_code,
        v.visit_number,
        u.full_name as doctor_name,
        u2.full_name as technician_name,
        pb.total_amount as bill_amount,
        pb.status as bill_status
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
    LEFT JOIN patient_bills pb ON pb.visit_id = v.id AND pb.status = 'paid'
    WHERE 1=1 $branch_filter $date_filter
    GROUP BY lt.id
    ORDER BY lt.created_at DESC
");
$lab_tests_all = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE SUMMARY
// ================================================================
$total_lab_revenue = 0;
$total_tests = count($lab_tests_all);
$completed_tests = 0;
$pending_tests = 0;
$in_progress_tests = 0;
$cancelled_tests = 0;
$tests_with_results = 0;
$tests_without_results = 0;

// Group by test name for top tests
$test_counts = [];

foreach ($lab_tests_all as $test) {
    $total_lab_revenue += $test['test_price'] ?? 0;
    
    if ($test['status'] === 'completed') $completed_tests++;
    elseif ($test['status'] === 'pending') $pending_tests++;
    elseif ($test['status'] === 'in_progress') $in_progress_tests++;
    elseif ($test['status'] === 'cancelled') $cancelled_tests++;
    
    // Check if test has results
    if (!empty($test['results']) && $test['results'] !== 'NULL' && $test['results'] !== '') {
        $tests_with_results++;
    } else {
        $tests_without_results++;
    }
    
    // Count by test name
    $test_name = $test['test_name'] ?? 'Unknown';
    if (!isset($test_counts[$test_name])) {
        $test_counts[$test_name] = 0;
    }
    $test_counts[$test_name]++;
}

// Sort test counts by frequency
arsort($test_counts);
$top_tests = array_slice($test_counts, 0, 10, true);

// ================================================================
// FUNCTION TO GET STATUS LABEL
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

// ================================================================
// DISPLAY HTML REPORT (PRINTABLE)
// ================================================================

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Report - <?= htmlspecialchars($branch_name) ?></title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================
           PRINT STYLES - OPTIMIZED FOR PDF
           ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            padding: 20px;
            color: #1E293B;
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
           HEADER WITH LOGO - PURPLE THEME
           ================================================================ */
        .report-header {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            color: white;
            padding: 20px 24px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .report-header .brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .report-header .brand .logo-container {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.2);
        }
        
        .report-header .brand .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }
        
        .report-header .brand .logo-text h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
        }
        
        .report-header .brand .logo-text p {
            font-size: 12px;
            opacity: 0.85;
            margin: 2px 0 0 0;
        }
        
        .report-header .meta-info {
            text-align: right;
            font-size: 12px;
            opacity: 0.9;
        }
        
        .report-header .meta-info .badge-print {
            background: rgba(255,255,255,0.2);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* ================================================================
           SUMMARY CARDS - PURPLE THEME
           ================================================================ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 14px 10px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .summary-card .number {
            font-size: 18px;
            font-weight: 800;
        }
        
        .summary-card .number.purple { color: #7C3AED; }
        .summary-card .number.blue { color: #0B5ED7; }
        .summary-card .number.green { color: #059669; }
        .summary-card .number.orange { color: #D97706; }
        .summary-card .number.red { color: #DC2626; }
        .summary-card .number.teal { color: #0D9488; }
        
        .summary-card .label {
            font-size: 8px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-top: 4px;
        }
        
        .summary-card .sub-label {
            font-size: 7px;
            color: #94A3B8;
        }
        
        /* ================================================================
           SECTION TITLES
           ================================================================ */
        .section-title {
            background: #F1F5F9;
            padding: 8px 14px;
            font-weight: 700;
            font-size: 13px;
            border-left: 4px solid #7C3AED;
            margin: 16px 0 10px 0;
            border-radius: 0 4px 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: #7C3AED;
        }
        
        /* ================================================================
           FILTER INFO
           ================================================================ */
        .filter-info {
            background: #F8FAFC;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 11px;
            color: #64748B;
            margin-bottom: 12px;
            border: 1px solid #E2E8F0;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .filter-info span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .filter-info i {
            color: #7C3AED;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 700;
            color: white;
        }
        
        .badge-success { background: #059669; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-danger { background: #DC2626; }
        .badge-info { background: #0B5ED7; }
        .badge-purple { background: #7C3AED; }
        .badge-secondary { background: #64748B; }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        
        .data-table th {
            background: #7C3AED;
            color: white;
            padding: 5px 8px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid #5B21B6;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .data-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover td {
            background: #F8FAFC;
        }
        
        .text-right { text-align: right; }
        .text-green { color: #059669; }
        .text-red { color: #DC2626; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: 700; }
        
        /* ================================================================
           TOP TESTS TABLE
           ================================================================ */
        .top-tests-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .top-test-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 10px;
            background: #F8FAFC;
            border-radius: 4px;
            border: 1px solid #E2E8F0;
            font-size: 10px;
        }
        
        .top-test-item .name {
            font-weight: 600;
        }
        
        .top-test-item .count {
            color: #7C3AED;
            font-weight: 700;
        }
        
        /* ================================================================
           NO DATA
           ================================================================ */
        .no-data {
            text-align: center;
            color: #94A3B8;
            padding: 30px 0;
            font-style: italic;
        }
        
        .no-data i {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .report-footer {
            text-align: center;
            font-size: 10px;
            color: #94A3B8;
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #E2E8F0;
        }
        
        /* ================================================================
           PRINT BUTTON - HIDDEN IN PRINT
           ================================================================ */
        .print-btn-container {
            text-align: center;
            margin-bottom: 16px;
        }
        
        .print-btn {
            background: #7C3AED;
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .print-btn:hover {
            background: #5B21B6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        .print-btn i {
            margin-right: 8px;
        }
        
        .pdf-note {
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
            margin-bottom: 16px;
        }
        
        .pdf-note i {
            color: #DC2626;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .summary-grid { grid-template-columns: 1fr 1fr 1fr; }
            .report-header { flex-direction: column; text-align: center; }
            .report-header .brand { flex-direction: column; }
            .report-header .meta-info { text-align: center; }
            .data-table { font-size: 7px; }
            .data-table th, .data-table td { padding: 3px 4px; }
            .top-tests-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr 1fr; }
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .container {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 20px !important;
            }
            .print-btn-container, .pdf-note, .no-print {
                display: none !important;
            }
            .report-header {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .report-header .brand .logo-container {
                background: rgba(255,255,255,0.15) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .data-table th {
                background: #7C3AED !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .summary-card {
                border-color: #ddd !important;
            }
            .top-test-item {
                background: #f5f5f5 !important;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- ================================================================ -->
    <!-- PRINT BUTTON -->
    <!-- ================================================================ -->
    <div class="print-btn-container no-print">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-file-pdf"></i> Save as PDF / Print
        </button>
        <button onclick="window.close()" class="print-btn" style="background:#64748B;margin-left:8px;">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <div class="pdf-note no-print">
        <i class="fas fa-info-circle"></i> 
        Click <strong>"Save as PDF / Print"</strong> and select <strong>"Save as PDF"</strong> as the destination.
    </div>

    <!-- ================================================================ -->
    <!-- HEADER WITH LOGO - PURPLE THEME -->
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
                <p>Quality Healthcare Services</p>
            </div>
        </div>
        <div class="meta-info">
            <div><strong>Lab Report</strong></div>
            <div>Generated: <?= date('M d, Y h:i A') ?></div>
            <span class="badge-print">🧪 Laboratory Report</span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER INFO -->
    <!-- ================================================================ -->
    <div class="filter-info">
        <span><i class="fas fa-store"></i> Branch: <strong><?= htmlspecialchars($branch_name) ?></strong></span>
        <?php if (!empty($date_from) || !empty($date_to)): ?>
            <span><i class="fas fa-calendar"></i> Period: 
                <strong>
                    <?= !empty($date_from) ? date('M d, Y', strtotime($date_from)) : 'Start' ?>
                    -
                    <?= !empty($date_to) ? date('M d, Y', strtotime($date_to)) : 'End' ?>
                </strong>
            </span>
        <?php else: ?>
            <span><i class="fas fa-calendar"></i> Period: <strong>All Time</strong></span>
        <?php endif; ?>
        <span><i class="fas fa-flask"></i> Total Tests: <strong><?= number_format($total_tests) ?></strong></span>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="number purple">TSh <?= number_format($total_lab_revenue, 0) ?></div>
            <div class="label">Total Revenue</div>
            <div class="sub-label">Lab test fees</div>
        </div>
        <div class="summary-card">
            <div class="number blue"><?= number_format($total_tests) ?></div>
            <div class="label">Total Tests</div>
            <div class="sub-label">All tests performed</div>
        </div>
        <div class="summary-card">
            <div class="number green"><?= number_format($completed_tests) ?></div>
            <div class="label">Completed</div>
            <div class="sub-label">Tests finalized</div>
        </div>
        <div class="summary-card">
            <div class="number orange"><?= number_format($pending_tests + $in_progress_tests) ?></div>
            <div class="label">In Progress</div>
            <div class="sub-label"><?= number_format($pending_tests) ?> pending · <?= number_format($in_progress_tests) ?> in progress</div>
        </div>
        <div class="summary-card">
            <div class="number teal"><?= number_format($tests_with_results) ?> / <?= number_format($tests_without_results) ?></div>
            <div class="label">Results</div>
            <div class="sub-label"><?= number_format($tests_with_results) ?> with results · <?= number_format($tests_without_results) ?> no results</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TOP TESTS -->
    <!-- ================================================================ -->
    <?php if (!empty($top_tests)): ?>
    <div class="section-title">
        <i class="fas fa-chart-bar"></i> Most Frequent Tests
    </div>
    <div class="top-tests-grid">
        <?php foreach ($top_tests as $name => $count): ?>
            <div class="top-test-item">
                <span class="name"><?= htmlspecialchars($name) ?></span>
                <span class="count"><?= number_format($count) ?> tests</span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ALL LAB TESTS -->
    <!-- ================================================================ -->
    <div class="section-title" style="margin-top:16px;">
        <i class="fas fa-flask"></i> All Lab Tests (<?= count($lab_tests_all) ?>)
    </div>

    <?php if (!empty($lab_tests_all)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Visit #</th>
                    <th>Test Name</th>
                    <th style="text-align:right;">Price</th>
                    <th>Result</th>
                    <th>Doctor</th>
                    <th>Technician</th>
                    <th>Status</th>
                    <th>Bill</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $lt_total = 0;
                $lt_with_bill = 0;
                $lt_without_bill = 0;
                
                foreach ($lab_tests_all as $test):
                    $lt_total += $test['test_price'] ?? 0;
                    if (!empty($test['bill_amount']) && $test['bill_amount'] > 0) {
                        $lt_with_bill++;
                    } else {
                        $lt_without_bill++;
                    }
                    
                    // Determine status badge class
                    $status_class = 'warning';
                    if ($test['status'] === 'completed') $status_class = 'success';
                    elseif ($test['status'] === 'pending') $status_class = 'warning';
                    elseif ($test['status'] === 'in_progress') $status_class = 'info';
                    elseif ($test['status'] === 'cancelled') $status_class = 'danger';
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></strong>
                            <div style="font-size:7px;color:#94A3B8;"><?= htmlspecialchars($test['patient_code'] ?? '') ?></div>
                        </td>
                        <td style="font-size:8px;"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                        <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                        <td style="text-align:right;font-weight:bold;">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                        <td style="font-size:8px;max-width:100px;word-wrap:break-word;">
                            <?php 
                                if (!empty($test['results']) && $test['results'] !== 'NULL' && $test['results'] !== '') {
                                    // Truncate long results
                                    $result = htmlspecialchars($test['results']);
                                    if (strlen($result) > 30) {
                                        echo substr($result, 0, 30) . '...';
                                    } else {
                                        echo $result;
                                    }
                                } else {
                                    echo '<span style="color:#94A3B8;">-</span>';
                                }
                            ?>
                        </td>
                        <td style="font-size:8px;">Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                        <td style="font-size:8px;"><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge badge-<?= $status_class ?>">
                                <?= getStatusLabel($test['status'] ?? 'pending') ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($test['bill_amount']) && $test['bill_amount'] > 0): ?>
                                <span class="text-green">TSh <?= number_format($test['bill_amount'], 0) ?></span>
                            <?php else: ?>
                                <span style="color:#94A3B8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:8px;"><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:700;border-top:2px solid #7C3AED;">
                    <td colspan="3" style="text-align:right;">GRAND TOTAL</td>
                    <td style="text-align:right;">TSh <?= number_format($lt_total, 0) ?></td>
                    <td colspan="2" style="text-align:center;font-size:8px;">
                        <?= number_format($lt_with_bill) ?> with bill · <?= number_format($lt_without_bill) ?> no bill
                    </td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-flask"></i>
            No lab tests found for the selected filters
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <strong>Braick Dispensary</strong> Management System 
        <span style="margin:0 8px;color:#CBD5E1;">|</span>
        Lab Report 
        <span style="margin:0 8px;color:#CBD5E1;">|</span>
        <?= date('M d, Y h:i A') ?>
        <span style="margin:0 8px;color:#CBD5E1;">|</span>
        &copy; <?= date('Y') ?> All rights reserved
    </div>

</div>

<script>
    // Auto print if URL has ?print parameter
    if (window.location.search.includes('print=1')) {
        setTimeout(function() {
            window.print();
        }, 500);
    }
</script>

</body>
</html>
<?php
exit;
?>