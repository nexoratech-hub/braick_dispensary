<?php
// ================================================================
// FILE: frontend/pages/doctor/export_patient_pdf.php
// EXPORT PATIENT PDF - FULL PATIENT REPORT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Doctor Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    die('Invalid patient ID');
}

$doctor_id = $_SESSION['user_id'];

// ================================================================
// GET PATIENT DATA - Verify doctor has access
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN users u ON p.assigned_doctor_id = u.id
    WHERE p.id = ? AND p.assigned_doctor_id = ?
");
$stmt->execute([$patient_id, $doctor_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient not found or you do not have access');
}

// ================================================================
// GET ALL DATA
// ================================================================

// Get all visits
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$patient_id]);
$all_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all bills
$stmt = $db->prepare("
    SELECT pb.*
    FROM patient_bills pb
    WHERE pb.patient_id = ?
    ORDER BY pb.created_at DESC
");
$stmt->execute([$patient_id]);
$all_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all prescriptions
$stmt = $db->prepare("
    SELECT p.*, u.full_name as doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$patient_id]);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all lab tests
$stmt = $db->prepare("
    SELECT lt.*, u.full_name as doctor_name
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$patient_id]);
$all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all appointments
$stmt = $db->prepare("
    SELECT a.*, u.full_name as doctor_name
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$patient_id]);
$all_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all vital signs
$stmt = $db->prepare("
    SELECT vs.*, u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
");
$stmt->execute([$patient_id]);
$all_vital_signs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all payments
$stmt = $db->prepare("
    SELECT py.*, u.full_name as received_by_name
    FROM payments py
    LEFT JOIN users u ON py.received_by = u.id
    WHERE py.patient_id = ?
    ORDER BY py.received_at DESC
");
$stmt->execute([$patient_id]);
$all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_visits = count($all_visits);
$total_bills = count($all_bills);
$total_prescriptions = count($all_prescriptions);
$total_lab_tests = count($all_lab_tests);
$total_appointments = count($all_appointments);
$total_vital_signs = count($all_vital_signs);
$total_payments = count($all_payments);

$total_bill_amount = 0;
foreach ($all_bills as $bill) {
    if ($bill['status'] !== 'cancelled') {
        $total_bill_amount += $bill['total_amount'] ?? 0;
    }
}

$total_payments_amount = 0;
foreach ($all_payments as $payment) {
    $total_payments_amount += $payment['amount'] ?? 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_base64 = '';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
    $logo_data = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Report - <?= htmlspecialchars($patient['full_name']) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F1F5F9;
            padding: 30px;
            color: #1E293B;
        }
        
        /* Report Container */
        .report-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        /* Toolbar */
        .toolbar {
            background: #0B5ED7;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .toolbar .toolbar-title {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toolbar .toolbar-title i {
            font-size: 1.2rem;
        }
        
        .toolbar .toolbar-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .toolbar .btn-tool {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            color: white;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
        }
        
        .toolbar .btn-tool:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-1px);
        }
        
        .toolbar .btn-tool.download {
            background: #FFFFFF;
            color: #0B5ED7;
        }
        
        .toolbar .btn-tool.download:hover {
            background: #E8F0FE;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .toolbar .btn-tool.print {
            background: #059669;
            color: white;
        }
        
        .toolbar .btn-tool.print:hover {
            background: #047857;
        }
        
        .toolbar .btn-tool.cancel {
            background: #EF4444;
            color: white;
        }
        
        .toolbar .btn-tool.cancel:hover {
            background: #DC2626;
        }
        
        /* Report Content */
        .report-content {
            padding: 40px 50px;
        }
        
        /* Report Header */
        .report-header {
            text-align: center;
            border-bottom: 3px solid #0B5ED7;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .report-header .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        
        .report-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0B5ED7;
        }
        
        .report-header .subtitle {
            font-size: 0.9rem;
            color: #64748B;
        }
        
        .report-header .report-date {
            font-size: 0.8rem;
            color: #94A3B8;
            margin-top: 4px;
        }
        
        /* Section */
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0B5ED7;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 14px;
        }
        
        .section-title .badge-count {
            font-size: 0.7rem;
            font-weight: 400;
            color: #64748B;
            margin-left: 4px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 8px 20px;
        }
        
        .info-item {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid #F1F5F9;
        }
        
        .info-item .label {
            font-weight: 600;
            color: #64748B;
            font-size: 0.8rem;
            width: 120px;
            flex-shrink: 0;
        }
        
        .info-item .value {
            font-size: 0.85rem;
            color: #1E293B;
        }
        
        /* Table */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: #0B5ED7;
            color: white;
            font-weight: 600;
            padding: 8px 12px;
            text-align: left;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .data-table thead th:first-child {
            border-radius: 6px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }
        
        .data-table tbody td {
            padding: 7px 12px;
            border-bottom: 1px solid #E2E8F0;
            color: #1E293B;
            vertical-align: middle;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        
        .data-table tbody tr:hover {
            background: #E8F0FE;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .status-badge.success { background: #D1FAE5; color: #059669; }
        .status-badge.warning { background: #FEF3C7; color: #D97706; }
        .status-badge.danger { background: #FEE2E2; color: #EF4444; }
        .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.secondary { background: #E2E8F0; color: #64748B; }
        
        /* Vital Signs Cards */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        
        .vital-card {
            background: #F8FAFC;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        
        .vital-card .vital-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .vital-card .vital-label {
            font-size: 0.65rem;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.03em;
        }
        
        .vital-card .vital-icon {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: #F8FAFC;
            border-radius: 10px;
            padding: 14px 16px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        
        .summary-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .summary-card .label {
            font-size: 0.65rem;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.03em;
        }
        
        .summary-card .amount {
            font-size: 0.75rem;
            font-weight: 600;
            color: #059669;
            margin-top: 2px;
        }
        
        /* Footer */
        .report-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid #E2E8F0;
            margin-top: 20px;
            font-size: 0.7rem;
            color: #94A3B8;
        }
        
        .report-footer .brand {
            color: #0B5ED7;
            font-weight: 600;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .toolbar {
                display: none !important;
            }
            .report-container {
                box-shadow: none;
                border-radius: 0;
            }
            .report-content {
                padding: 20px 30px;
            }
            .data-table tbody tr:nth-child(even) {
                background: #F8FAFC;
            }
            .data-table tbody tr:hover {
                background: #F8FAFC;
            }
            .summary-card, .vital-card {
                background: #F8FAFC;
                border: 1px solid #E2E8F0;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .report-content { padding: 20px; }
            .toolbar { padding: 12px 16px; }
            .toolbar .toolbar-title { font-size: 0.85rem; }
            .toolbar .btn-tool { padding: 6px 12px; font-size: 0.7rem; }
            .toolbar .btn-tool span { display: none; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
            .report-header h1 { font-size: 1.3rem; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
        }
        
        @media print and (max-width: 768px) {
            .report-content { padding: 10px 15px; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="report-container" id="reportContainer">
    
    <!-- ================================================================ -->
    <!-- TOOLBAR -->
    <!-- ================================================================ -->
    <div class="toolbar" id="toolbar">
        <div class="toolbar-title">
            <i class="fas fa-file-pdf"></i>
            Patient Report - <?= htmlspecialchars($patient['full_name']) ?>
        </div>
        <div class="toolbar-buttons">
            <button onclick="downloadPDF()" class="btn-tool download">
                <i class="fas fa-download"></i>
                <span>Download</span>
            </button>
            <button onclick="window.print()" class="btn-tool print">
                <i class="fas fa-print"></i>
                <span>Print</span>
            </button>
            <button onclick="window.close()" class="btn-tool cancel">
                <i class="fas fa-times"></i>
                <span>Cancel</span>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- REPORT CONTENT -->
    <!-- ================================================================ -->
    <div class="report-content" id="reportContent">
        
        <!-- ============================================================ -->
        <!-- REPORT HEADER -->
        <!-- ============================================================ -->
        <div class="report-header">
            <?php if ($logo_base64): ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Dispensary" class="logo">
            <?php endif; ?>
            <h1>BRAICK DISPENSARY</h1>
            <p class="subtitle">Quality Healthcare Services • Patient Medical Report</p>
            <p class="report-date">Report Generated: <?= date('l, F d, Y h:i:s A') ?></p>
        </div>
        
        <!-- ============================================================ -->
        <!-- PATIENT INFORMATION -->
        <!-- ============================================================ -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-user-circle"></i>
                Patient Information
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Full Name</span>
                    <span class="value"><?= htmlspecialchars($patient['full_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Patient ID</span>
                    <span class="value"><?= htmlspecialchars($patient['patient_id']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Gender</span>
                    <span class="value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Marital Status</span>
                    <span class="value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Blood Group</span>
                    <span class="value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email</span>
                    <span class="value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Address</span>
                    <span class="value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Emergency Contact</span>
                    <span class="value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Allergies</span>
                    <span class="value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Assigned Doctor</span>
                    <span class="value"><?= htmlspecialchars($patient['assigned_doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Branch</span>
                    <span class="value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Registered</span>
                    <span class="value"><?= date('M d, Y h:i A', strtotime($patient['created_at'])) ?></span>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- SUMMARY STATISTICS -->
        <!-- ============================================================ -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-chart-bar"></i>
                Summary Statistics
            </div>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number"><?= $total_visits ?></div>
                    <div class="label">Total Visits</div>
                </div>
                <div class="summary-card">
                    <div class="number"><?= $total_bills ?></div>
                    <div class="label">Total Bills</div>
                    <div class="amount">TSh <?= number_format($total_bill_amount) ?></div>
                </div>
                <div class="summary-card">
                    <div class="number"><?= $total_prescriptions ?></div>
                    <div class="label">Prescriptions</div>
                </div>
                <div class="summary-card">
                    <div class="number"><?= $total_lab_tests ?></div>
                    <div class="label">Lab Tests</div>
                </div>
                <div class="summary-card">
                    <div class="number"><?= $total_appointments ?></div>
                    <div class="label">Appointments</div>
                </div>
                <div class="summary-card">
                    <div class="number"><?= $total_vital_signs ?></div>
                    <div class="label">Vital Signs</div>
                </div>
                <div class="summary-card">
                    <div class="number"><?= $total_payments ?></div>
                    <div class="label">Payments</div>
                    <div class="amount">TSh <?= number_format($total_payments_amount) ?></div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- VITAL SIGNS -->
        <!-- ============================================================ -->
        <?php if (count($all_vital_signs) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-heartbeat" style="color: #EC4899;"></i>
                Vital Signs History
                <span class="badge-count">(<?= count($all_vital_signs) ?> records)</span>
            </div>
            
            <?php if (count($all_vital_signs) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Temp (°C)</th>
                            <th>BP (mmHg)</th>
                            <th>Pulse (bpm)</th>
                            <th>Weight (kg)</th>
                            <th>Height (cm)</th>
                            <th>BMI</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_vital_signs as $vs): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= date('M d, Y', strtotime($vs['recorded_at'])) ?></td>
                            <td><?= $vs['temperature'] ?? '-' ?></td>
                            <td>
                                <?php 
                                    $systolic = $vs['blood_pressure_systolic'] ?? null;
                                    $diastolic = $vs['blood_pressure_diastolic'] ?? null;
                                    if ($systolic && $diastolic) {
                                        echo $systolic . '/' . $diastolic;
                                    } elseif ($systolic) {
                                        echo $systolic;
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td><?= $vs['pulse_rate'] ?? '-' ?></td>
                            <td><?= $vs['weight'] ?? '-' ?></td>
                            <td><?= $vs['height'] ?? '-' ?></td>
                            <td><?= $vs['bmi'] ?? '-' ?></td>
                            <td><?= htmlspecialchars($vs['recorded_by_name'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- VISITS -->
        <!-- ============================================================ -->
        <?php if (count($all_visits) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-notes-medical"></i>
                Visit History
                <span class="badge-count">(<?= count($all_visits) ?> visits)</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Visit #</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_visits as $visit): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($visit['visit_number']) ?></td>
                            <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($visit['visit_date'])) ?></td>
                            <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $visit['status'] === 'completed' ? 'success' : ($visit['status'] === 'pending' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($visit['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- BILLS -->
        <!-- ============================================================ -->
        <?php if (count($all_bills) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-file-invoice"></i>
                Bill History
                <span class="badge-count">(<?= count($all_bills) ?> bills)</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bill #</th>
                            <th>Total Amount</th>
                            <th>Paid Amount</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_bills as $bill): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($bill['bill_number']) ?></td>
                            <td>TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                            <td>TSh <?= number_format($bill['paid_amount'] ?? 0) ?></td>
                            <td>TSh <?= number_format($bill['balance'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'pending' ? 'warning' : ($bill['status'] === 'partial' ? 'info' : 'danger')) ?>">
                                    <?= ucfirst($bill['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- PRESCRIPTIONS -->
        <!-- ============================================================ -->
        <?php if (count($all_prescriptions) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-prescription"></i>
                Prescription History
                <span class="badge-count">(<?= count($all_prescriptions) ?> prescriptions)</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prescription #</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_prescriptions as $prescription): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                            <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($prescription['diagnosis'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $prescription['status'] === 'dispensed' ? 'success' : ($prescription['status'] === 'pending' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- LAB TESTS -->
        <!-- ============================================================ -->
        <?php if (count($all_lab_tests) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-flask"></i>
                Lab Test History
                <span class="badge-count">(<?= count($all_lab_tests) ?> tests)</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Doctor</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_lab_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                            <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $test['status'] === 'completed' ? 'success' : ($test['status'] === 'pending' ? 'warning' : 'info') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- APPOINTMENTS -->
        <!-- ============================================================ -->
        <?php if (count($all_appointments) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-calendar-check"></i>
                Appointment History
                <span class="badge-count">(<?= count($all_appointments) ?> appointments)</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_appointments as $appointment): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                            <td><?= ucfirst($appointment['visit_type'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'scheduled' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($appointment['status'] ?? 'N/A') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- PAYMENTS -->
        <!-- ============================================================ -->
        <?php if (count($all_payments) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-credit-card"></i>
                Payment History
                <span class="badge-count">(<?= count($all_payments) ?> payments)</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Receipt #</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($all_payments as $payment): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($payment['receipt_number']) ?></td>
                            <td>TSh <?= number_format($payment['amount'] ?? 0) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'N/A')) ?></td>
                            <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($payment['received_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- REPORT FOOTER -->
        <!-- ============================================================ -->
        <div class="report-footer">
            <p>
                <span class="brand">Braick Dispensary</span> Management System
                <span class="text-gray-300 mx-2">|</span>
                Patient Medical Report
                <span class="text-gray-300 mx-2">|</span>
                Generated: <?= date('Y-m-d h:i:s A') ?>
            </p>
            <p style="margin-top:4px;font-size:0.65rem;color:#CBD5E1;">
                This is a computer-generated report. No signature is required.
            </p>
        </div>
        
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js">
</script>
<script>
    // ================================================================
    // DOWNLOAD PDF
    // ================================================================
    function downloadPDF() {
        var element = document.getElementById('reportContent');
        var toolbar = document.getElementById('toolbar');
        
        // Show loading state
        var downloadBtn = document.querySelector('.btn-tool.download');
        var originalText = downloadBtn.innerHTML;
        downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        downloadBtn.disabled = true;
        
        // Hide toolbar for PDF
        toolbar.style.display = 'none';
        
        var opt = {
            margin:        [0.5, 0.5, 0.5, 0.5],
            filename:     'Patient_Report_<?= htmlspecialchars($patient['full_name']) ?>_<?= date('Y-m-d') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' },
            pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
        };
        
        html2pdf().set(opt).from(element).save().then(function() {
            // Restore toolbar
            toolbar.style.display = 'flex';
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }).catch(function(err) {
            console.error('PDF generation error:', err);
            toolbar.style.display = 'flex';
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
            alert('Error generating PDF. Please try again or use Print option.');
        });
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+P = Print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        // Escape = Close
        if (e.key === 'Escape') {
            window.close();
        }
    });

    // ================================================================
    // AUTO PRINT ON LOAD (Optional - commented out)
    // ================================================================
    // window.onload = function() {
    //     setTimeout(function() {
    //         window.print();
    //     }, 1000);
    // };

    console.log('%c📄 Braick Dispensary - Patient Report', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Visits: <?= $total_visits ?> | Bills: <?= $total_bills ?> | Prescriptions: <?= $total_prescriptions ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔬 Lab Tests: <?= $total_lab_tests ?> | Appointments: <?= $total_appointments ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c❤️ Vital Signs: <?= $total_vital_signs ?> | Payments: <?= $total_payments ?>', 'font-size:13px; color:#EC4899;');
    console.log('%c🖨️ Ctrl+P to Print | ESC to Close', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>