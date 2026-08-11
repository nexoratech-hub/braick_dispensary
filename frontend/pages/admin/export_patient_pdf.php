<?php
// ================================================================
// FILE: frontend/pages/admin/export_patient_pdf.php
// EXPORT PATIENT REPORT TO PDF - WITH BILL TYPE COLUMN
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

if ($patient_id <= 0) {
    die('Invalid patient ID');
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><rect width="60" height="60" rx="12" fill="#0B5ED7"/><text x="30" y="38" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="Arial">B</text></svg>');

// ================================================================
// FETCH PATIENT DATA
// ================================================================

// Get patient personal info
$stmt = $db->prepare("
    SELECT p.*, u.full_name as receptionist_name, b.name as branch_name
    FROM patients p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient_data) {
    die('Patient not found');
}

// Get all visits with diagnosis, complaint, symptoms
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$patient_id]);
$patient_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each visit, get details
$patient_bills_summary = [
    'total_paid' => 0,
    'total_prescription' => 0,
    'total_lab' => 0,
    'total_procedures_tools' => 0,
    'total_consultation' => 0,
    'total_bills' => 0
];

foreach ($patient_visits as &$visit) {
    $visit_id = $visit['id'];
    
    // Vital Signs
    $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $visit['vital_signs'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Lab Tests
    $stmt = $db->prepare("SELECT * FROM lab_tests WHERE visit_id = ? ORDER BY created_at DESC");
    $stmt->execute([$visit_id]);
    $visit['lab_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prescriptions with items
    $stmt = $db->prepare("
        SELECT p.*, pi.* 
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $visit['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procedures & Tools
    $stmt = $db->prepare("
        SELECT bi.* 
        FROM bill_items bi
        INNER JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.visit_id = ?
        AND bi.item_type IN ('procedure', 'tool')
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $visit['procedures_tools'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // BILLS WITH TYPE DETERMINATION
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM patient_bills 
        WHERE visit_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $visit['bills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Determine bill type for each bill
    foreach ($visit['bills'] as &$bill) {
        $bill['bill_type'] = 'Other';
        $bill['bill_type_icon'] = 'fa-file-invoice';
        $bill['bill_type_color'] = '#64748B';
        $bill['bill_type_class'] = 'other';
        
        // Check if it's a prescription bill
        if (strpos($bill['bill_number'], 'BILL-PRES-') !== false) {
            $bill['bill_type'] = 'Prescription';
            $bill['bill_type_icon'] = 'fa-prescription-bottle';
            $bill['bill_type_color'] = '#7C3AED';
            $bill['bill_type_class'] = 'prescription';
        } else {
            // Get items from this bill to determine type
            $stmt_items = $db->prepare("
                SELECT item_type, COUNT(*) as count, SUM(total_price) as total 
                FROM bill_items 
                WHERE bill_id = ? 
                GROUP BY item_type
                ORDER BY SUM(total_price) DESC
            ");
            $stmt_items->execute([$bill['id']]);
            $bill_items_types = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($bill_items_types)) {
                // Get the dominant type (by total amount)
                $dominant = $bill_items_types[0]['item_type'] ?? 'other';
                
                $type_map = [
                    'consultation' => ['label' => 'Consultation', 'icon' => 'fa-user-md', 'color' => '#0D9488', 'class' => 'consultation'],
                    'lab_test' => ['label' => 'Lab Test', 'icon' => 'fa-flask', 'color' => '#7C3AED', 'class' => 'lab_test'],
                    'procedure' => ['label' => 'Procedure', 'icon' => 'fa-syringe', 'color' => '#D97706', 'class' => 'procedure'],
                    'tool' => ['label' => 'Tool', 'icon' => 'fa-tools', 'color' => '#F59E0B', 'class' => 'tool'],
                    'medication' => ['label' => 'Medication', 'icon' => 'fa-pills', 'color' => '#059669', 'class' => 'medication'],
                    'registration' => ['label' => 'Registration', 'icon' => 'fa-file-medical', 'color' => '#0B5ED7', 'class' => 'registration'],
                    'other' => ['label' => 'Other', 'icon' => 'fa-file-invoice', 'color' => '#64748B', 'class' => 'other']
                ];
                
                $bill['bill_type'] = $type_map[$dominant]['label'] ?? 'Other';
                $bill['bill_type_icon'] = $type_map[$dominant]['icon'] ?? 'fa-file-invoice';
                $bill['bill_type_color'] = $type_map[$dominant]['color'] ?? '#64748B';
                $bill['bill_type_class'] = $type_map[$dominant]['class'] ?? 'other';
            }
        }
    }
    unset($bill);
    
    // Calculate summary
    foreach ($visit['bills'] as $bill) {
        $patient_bills_summary['total_bills']++;
        if ($bill['status'] === 'paid') {
            $patient_bills_summary['total_paid'] += $bill['total_amount'];
            
            if (strpos($bill['bill_number'], 'BILL-PRES-') !== false) {
                $patient_bills_summary['total_prescription'] += $bill['total_amount'];
            } else {
                $stmt = $db->prepare("SELECT item_type, total_price FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    if ($item['item_type'] === 'lab_test') {
                        $patient_bills_summary['total_lab'] += $item['total_price'];
                    } elseif ($item['item_type'] === 'procedure' || $item['item_type'] === 'tool') {
                        $patient_bills_summary['total_procedures_tools'] += $item['total_price'];
                    } elseif ($item['item_type'] === 'consultation') {
                        $patient_bills_summary['total_consultation'] += $item['total_price'];
                    }
                }
            }
        }
    }
}

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
        'assigned' => 'Assigned',
        'with_doctor' => 'With Doctor',
        'lab_test' => 'Lab Test',
        'lab_completed' => 'Lab Completed',
        'prescribed' => 'Prescribed',
        'active' => 'Active',
        'inactive' => 'Inactive'
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
    <title>Patient Report - <?= htmlspecialchars($patient_data['full_name']) ?></title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px 35px;
        }
        
        /* ================================================================
           HEADER WITH LOGO
           ================================================================ */
        .report-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
           SUMMARY CARDS
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
            padding: 12px 10px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .summary-card .number {
            font-size: 18px;
            font-weight: 800;
        }
        
        .summary-card .number.blue { color: #0B5ED7; }
        .summary-card .number.green { color: #059669; }
        .summary-card .number.purple { color: #7C3AED; }
        .summary-card .number.orange { color: #D97706; }
        .summary-card .number.teal { color: #0D9488; }
        
        .summary-card .label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-top: 4px;
        }
        
        .summary-card .sub-label {
            font-size: 8px;
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
            border-left: 4px solid #0B5ED7;
            margin: 16px 0 10px 0;
            border-radius: 0 4px 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: #0B5ED7;
        }
        
        /* ================================================================
           PATIENT INFO GRID
           ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 24px;
            padding: 6px 0;
        }
        
        .info-row {
            display: flex;
            padding: 3px 0;
            font-size: 12px;
        }
        
        .info-row .label {
            font-weight: 600;
            color: #64748B;
            min-width: 130px;
            flex-shrink: 0;
        }
        
        .info-row .value {
            font-weight: 500;
            color: #1E293B;
        }
        
        /* ================================================================
           VISIT CARDS
           ================================================================ */
        .visit-card {
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            margin-bottom: 14px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        
        .visit-header {
            background: #F8FAFC;
            padding: 10px 14px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .visit-header .number {
            font-weight: 700;
            font-size: 13px;
            color: #0B5ED7;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .visit-header .meta {
            font-size: 11px;
            color: #64748B;
        }
        
        .visit-body {
            padding: 12px 14px;
        }
        
        /* ================================================================
           DIAGNOSIS BOX - HIGHLIGHTED
           ================================================================ */
        .diagnosis-box {
            background: #EFF6FF;
            padding: 10px 14px;
            border-left: 4px solid #0B5ED7;
            margin: 6px 0 8px 0;
            border-radius: 4px;
        }
        
        .diagnosis-box .label {
            font-weight: 700;
            font-size: 10px;
            color: #0B5ED7;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .diagnosis-box .text {
            font-weight: 700;
            font-size: 14px;
            color: #1E293B;
            margin-top: 2px;
        }
        
        /* ================================================================
           COMPLAINT / SYMPTOMS BOX
           ================================================================ */
        .complaint-box {
            background: #F8FAFC;
            padding: 6px 12px;
            border: 1px dashed #CBD5E1;
            margin: 4px 0 6px 0;
            border-radius: 4px;
        }
        
        .complaint-box .label {
            font-weight: 700;
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .complaint-box .text {
            font-size: 12px;
            color: #1E293B;
            margin-top: 1px;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 10px;
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
           BILL TYPE BADGE - PRINTABLE
           ================================================================ */
        .bill-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .bill-type-badge i {
            font-size: 9px;
        }
        
        .bill-type-badge.consultation { background: #D1FAE5; color: #065F46; }
        .bill-type-badge.prescription { background: #EDE9FE; color: #5B21B6; }
        .bill-type-badge.lab_test { background: #EDE9FE; color: #5B21B6; }
        .bill-type-badge.procedure { background: #FEF3C7; color: #92400E; }
        .bill-type-badge.tool { background: #FEF3C7; color: #92400E; }
        .bill-type-badge.medication { background: #D1FAE5; color: #065F46; }
        .bill-type-badge.registration { background: #DBEAFE; color: #1E40AF; }
        .bill-type-badge.other { background: #F1F5F9; color: #475569; }
        
        /* ================================================================
           SUB TABLES
           ================================================================ */
        .sub-table-wrap {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #E2E8F0;
        }
        
        .sub-table-wrap .sub-title {
            font-size: 10px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 4px;
        }
        
        .sub-table-wrap .sub-title i {
            margin-right: 4px;
            color: #0B5ED7;
        }
        
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        
        .sub-table th {
            background: #F1F5F9;
            padding: 5px 8px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid #E2E8F0;
            font-size: 9px;
            text-transform: uppercase;
            color: #64748B;
        }
        
        .sub-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #F1F5F9;
        }
        
        .sub-table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right { text-align: right; }
        .text-green { color: #059669; }
        .text-red { color: #DC2626; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: 700; }
        
        /* ================================================================
           NO DATA
           ================================================================ */
        .no-data {
            text-align: center;
            color: #94A3B8;
            padding: 20px 0;
            font-style: italic;
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
            background: #0B5ED7;
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
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
            .summary-grid { grid-template-columns: 1fr 1fr; }
            .info-grid { grid-template-columns: 1fr; }
            .report-header { flex-direction: column; text-align: center; }
            .report-header .brand { flex-direction: column; }
            .report-header .meta-info { text-align: center; }
            .visit-header { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr; }
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
            .visit-card {
                page-break-inside: avoid !important;
                border-color: #ddd !important;
            }
            .diagnosis-box {
                background: #f0f7ff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
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
            .bill-type-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bill-type-badge.consultation { background: #D1FAE5 !important; color: #065F46 !important; }
            .bill-type-badge.prescription { background: #EDE9FE !important; color: #5B21B6 !important; }
            .bill-type-badge.lab_test { background: #EDE9FE !important; color: #5B21B6 !important; }
            .bill-type-badge.procedure { background: #FEF3C7 !important; color: #92400E !important; }
            .bill-type-badge.tool { background: #FEF3C7 !important; color: #92400E !important; }
            .bill-type-badge.medication { background: #D1FAE5 !important; color: #065F46 !important; }
            .bill-type-badge.registration { background: #DBEAFE !important; color: #1E40AF !important; }
            .bill-type-badge.other { background: #F1F5F9 !important; color: #475569 !important; }
            .summary-card {
                border-color: #ddd !important;
            }
            .sub-table th {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
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
    <!-- HEADER WITH LOGO -->
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
            <div><strong>Patient Report</strong></div>
            <div>Generated: <?= date('M d, Y h:i A') ?></div>
            <span class="badge-print">📋 Medical Record</span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="number blue">TSh <?= number_format($patient_bills_summary['total_paid'], 0) ?></div>
            <div class="label">Total Paid</div>
            <div class="sub-label">All bills</div>
        </div>
        <div class="summary-card">
            <div class="number green">TSh <?= number_format($patient_bills_summary['total_prescription'], 0) ?></div>
            <div class="label">Prescriptions</div>
            <div class="sub-label">Medication costs</div>
        </div>
        <div class="summary-card">
            <div class="number purple">TSh <?= number_format($patient_bills_summary['total_lab'], 0) ?></div>
            <div class="label">Lab Tests</div>
            <div class="sub-label">Lab services</div>
        </div>
        <div class="summary-card">
            <div class="number orange">TSh <?= number_format($patient_bills_summary['total_procedures_tools'], 0) ?></div>
            <div class="label">Procedures</div>
            <div class="sub-label">Medical procedures</div>
        </div>
        <div class="summary-card">
            <div class="number teal">TSh <?= number_format($patient_bills_summary['total_consultation'], 0) ?></div>
            <div class="label">Consultations</div>
            <div class="sub-label">Doctor fees</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-user"></i> Patient Personal Information
    </div>
    <div class="info-grid">
        <div class="info-row"><span class="label">Full Name</span><span class="value"><?= htmlspecialchars($patient_data['full_name']) ?></span></div>
        <div class="info-row"><span class="label">Patient ID</span><span class="value"><?= htmlspecialchars($patient_data['patient_id']) ?></span></div>
        <div class="info-row"><span class="label">Gender</span><span class="value"><?= htmlspecialchars($patient_data['gender'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Date of Birth</span><span class="value"><?= !empty($patient_data['date_of_birth']) ? date('M d, Y', strtotime($patient_data['date_of_birth'])) : 'N/A' ?></span></div>
        <div class="info-row"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($patient_data['phone'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Email</span><span class="value"><?= htmlspecialchars($patient_data['email'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Address</span><span class="value"><?= htmlspecialchars($patient_data['address'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Blood Group</span><span class="value"><?= htmlspecialchars($patient_data['blood_group'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Allergies</span><span class="value"><?= htmlspecialchars($patient_data['allergies'] ?? 'None') ?></span></div>
        <div class="info-row"><span class="label">Branch</span><span class="value"><?= htmlspecialchars($patient_data['branch_name'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Registered By</span><span class="value"><?= htmlspecialchars($patient_data['receptionist_name'] ?? 'N/A') ?></span></div>
        <div class="info-row"><span class="label">Registration Date</span><span class="value"><?= date('M d, Y h:i A', strtotime($patient_data['created_at'])) ?></span></div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT HISTORY -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-stethoscope"></i> Visit History (<?= count($patient_visits) ?> visits)
    </div>

    <?php if (count($patient_visits) > 0): ?>
        <?php foreach ($patient_visits as $visit):
            $has_diagnosis = !empty($visit['diagnosis']) && $visit['diagnosis'] !== 'NULL' && $visit['diagnosis'] !== '0';
            $has_complaint = !empty($visit['complaint']) && $visit['complaint'] !== 'NULL';
            $has_symptoms = !empty($visit['symptoms']) && $visit['symptoms'] !== 'NULL';
            
            $status_badge = 'badge-info';
            if ($visit['status'] === 'completed') $status_badge = 'badge-success';
            elseif ($visit['status'] === 'cancelled') $status_badge = 'badge-danger';
            elseif ($visit['status'] === 'pending') $status_badge = 'badge-warning';
        ?>
        <div class="visit-card">
            <div class="visit-header">
                <span class="number">
                    <i class="fas fa-file-medical"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                    <span class="badge <?= $status_badge ?>"><?= getStatusLabel($visit['status'] ?? 'pending') ?></span>
                    <?php if ($has_diagnosis): ?>
                        <span class="badge badge-purple"><i class="fas fa-stethoscope"></i> Diagnosed</span>
                    <?php endif; ?>
                </span>
                <span class="meta">
                    <i class="fas fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? $visit['created_at'])) ?>
                    <span style="margin-left:8px;color:#94A3B8;">#<?= $visit['id'] ?></span>
                </span>
            </div>
            <div class="visit-body">
                <div class="info-grid">
                    <div class="info-row"><span class="label">Doctor</span><span class="value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span></div>
                    <div class="info-row"><span class="label">Visit Type</span><span class="value"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span></div>
                </div>
                
                <!-- ================================================================ -->
                <!-- SYMPTOMS -->
                <!-- ================================================================ -->
                <?php if ($has_symptoms): ?>
                <div class="complaint-box">
                    <span class="label"><i class="fas fa-thermometer-half"></i> Symptoms / Presenting Complaints</span>
                    <div class="text"><?= htmlspecialchars($visit['symptoms']) ?></div>
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- COMPLAINT -->
                <!-- ================================================================ -->
                <?php if ($has_complaint): ?>
                <div class="complaint-box">
                    <span class="label"><i class="fas fa-question-circle"></i> Reason for Visit / Complaint</span>
                    <div class="text"><?= htmlspecialchars($visit['complaint']) ?></div>
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- DIAGNOSIS - HIGHLIGHTED -->
                <!-- ================================================================ -->
                <?php if ($has_diagnosis): ?>
                <div class="diagnosis-box">
                    <span class="label"><i class="fas fa-stethoscope"></i> Diagnosis / Impression</span>
                    <div class="text"><?= htmlspecialchars($visit['diagnosis']) ?></div>
                </div>
                <?php else: ?>
                <div style="color:#94A3B8;font-style:italic;padding:4px 0;font-size:12px;">
                    <i class="fas fa-info-circle"></i> No diagnosis recorded for this visit
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- TREATMENT -->
                <!-- ================================================================ -->
                <?php if (!empty($visit['treatment']) && $visit['treatment'] !== 'NULL'): ?>
                <div class="info-row">
                    <span class="label">Treatment Given</span>
                    <span class="value"><?= htmlspecialchars($visit['treatment']) ?></span>
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- VITAL SIGNS -->
                <!-- ================================================================ -->
                <?php if (!empty($visit['vital_signs'])): 
                    $vs = $visit['vital_signs'];
                    $vitals = [];
                    if (!empty($vs['temperature'])) $vitals[] = '🌡️ Temp: ' . $vs['temperature'] . '°C';
                    if (!empty($vs['blood_pressure_systolic']) && !empty($vs['blood_pressure_diastolic'])) 
                        $vitals[] = '❤️ BP: ' . $vs['blood_pressure_systolic'] . '/' . $vs['blood_pressure_diastolic'];
                    if (!empty($vs['pulse_rate'])) $vitals[] = '💓 Pulse: ' . $vs['pulse_rate'];
                    if (!empty($vs['weight'])) $vitals[] = '⚖️ Weight: ' . $vs['weight'] . 'kg';
                    if (!empty($vs['bmi'])) $vitals[] = 'BMI: ' . $vs['bmi'];
                ?>
                <div class="info-row">
                    <span class="label">Vital Signs</span>
                    <span class="value"><?= implode(' | ', $vitals) ?></span>
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- LAB TESTS -->
                <!-- ================================================================ -->
                <?php if (!empty($visit['lab_tests'])): ?>
                <div class="sub-table-wrap">
                    <span class="sub-title"><i class="fas fa-flask"></i> Lab Tests (<?= count($visit['lab_tests']) ?>)</span>
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th>Result</th>
                                <th>Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visit['lab_tests'] as $test): 
                                $badge_test = 'badge-info';
                                if ($test['status'] === 'completed') $badge_test = 'badge-success';
                                elseif ($test['status'] === 'pending') $badge_test = 'badge-warning';
                                elseif ($test['status'] === 'cancelled') $badge_test = 'badge-danger';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name']) ?></td>
                                <td><strong><?= htmlspecialchars($test['results'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($test['reference_range'] ?? '-') ?></td>
                                <td><span class="badge <?= $badge_test ?>"><?= getStatusLabel($test['status'] ?? 'pending') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- PRESCRIPTIONS -->
                <!-- ================================================================ -->
                <?php if (!empty($visit['prescriptions'])): ?>
                <div class="sub-table-wrap">
                    <span class="sub-title"><i class="fas fa-prescription"></i> Prescriptions (<?= count($visit['prescriptions']) ?>)</span>
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visit['prescriptions'] as $presc): 
                                $badge_presc = 'badge-info';
                                if ($presc['status'] === 'dispensed') $badge_presc = 'badge-success';
                                elseif ($presc['status'] === 'pending') $badge_presc = 'badge-warning';
                                elseif ($presc['status'] === 'cancelled') $badge_presc = 'badge-danger';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($presc['medication'] ?? $presc['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($presc['dosage'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($presc['frequency'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($presc['duration'] ?? '-') ?></td>
                                <td><span class="badge <?= $badge_presc ?>"><?= getStatusLabel($presc['status'] ?? 'pending') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- BILLS WITH TYPE COLUMN -->
                <!-- ================================================================ -->
                <?php if (!empty($visit['bills'])): ?>
                <div class="sub-table-wrap">
                    <span class="sub-title"><i class="fas fa-file-invoice"></i> Bills (<?= count($visit['bills']) ?>)</span>
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Type</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:right;">Paid</th>
                                <th style="text-align:right;">Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visit['bills'] as $bill): 
                                $bill_type_class = $bill['bill_type_class'] ?? 'other';
                                $bill_type_icon = $bill['bill_type_icon'] ?? 'fa-file-invoice';
                                $bill_type_label = $bill['bill_type'] ?? 'Other';
                            ?>
                            <tr>
                                <td class="font-mono" style="font-size:9px;"><?= htmlspecialchars($bill['bill_number']) ?></td>
                                <td>
                                    <span class="bill-type-badge <?= $bill_type_class ?>">
                                        <i class="fas <?= $bill_type_icon ?>"></i>
                                        <?= htmlspecialchars($bill_type_label) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;font-weight:bold;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td style="text-align:right;color:#059669;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td style="text-align:right;color:#DC2626;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'pending' ? 'warning' : 'danger') ?>" style="font-size:8px;padding:1px 8px;">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-stethoscope" style="font-size:24px;display:block;margin-bottom:8px;"></i>
            No visits found for this patient
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <strong>Braick Dispensary</strong> Management System 
        <span style="margin:0 8px;color:#CBD5E1;">|</span>
        Patient Report 
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