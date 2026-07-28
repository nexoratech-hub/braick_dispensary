<?php
// ================================================================
// FILE: frontend/pages/doctor/export_patient_pdf.php
// DOCTOR - FULL PATIENT PDF EXPORT WITH BLUE THEME
// FIXED: Bills table shows all items in separate rows
// FIXED: Uniform borders throughout PDF (no individual card bolders)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Dr. John Mushi (ID: 5)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['specialty'] = 'General Medicine';
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    die("❌ Invalid patient ID");
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// GET LOGO PATH
// ================================================================
$logo_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
} else {
    $alt_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
    ];
    foreach ($alt_paths as $path) {
        if (file_exists($path)) {
            $logo_data = file_get_contents($path);
            $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
            break;
        }
    }
}

try {
    $db = getDB();
    
    // ================================================================
    // GET PATIENT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        die("❌ Patient not found");
    }
    
    // ================================================================
    // GET VITAL SIGNS
    // ================================================================
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
    ");
    $stmt->execute([$patient_id]);
    $vital_signs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET VISITS WITH FULL DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET PRESCRIPTIONS FOR EACH VISIT
    // ================================================================
    $visit_prescriptions = [];
    $visit_prescription_items = [];
    foreach ($visits as $visit) {
        $stmt = $db->prepare("
            SELECT p.* FROM prescriptions p
            WHERE p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit['id']]);
        $visit_prescriptions[$visit['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($visit_prescriptions[$visit['id']] as $pres) {
            $stmt = $db->prepare("
                SELECT * FROM prescription_items 
                WHERE prescription_id = ?
            ");
            $stmt->execute([$pres['id']]);
            $visit_prescription_items[$pres['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ================================================================
    // GET LAB TESTS FOR EACH VISIT
    // ================================================================
    $visit_lab_tests = [];
    foreach ($visits as $visit) {
        $stmt = $db->prepare("
            SELECT lt.* FROM lab_tests lt
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit['id']]);
        $visit_lab_tests[$visit['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET PROCEDURES FOR EACH VISIT
    // ================================================================
    $visit_procedures = [];
    foreach ($visits as $visit) {
        $stmt = $db->prepare("
            SELECT bi.* FROM bill_items bi
            JOIN patient_bills pb ON bi.bill_id = pb.id
            WHERE pb.visit_id = ? AND bi.item_type = 'procedure'
            ORDER BY bi.created_at DESC
        ");
        $stmt->execute([$visit['id']]);
        $visit_procedures[$visit['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET MEDICATIONS FOR EACH VISIT
    // ================================================================
    $visit_medications = [];
    foreach ($visits as $visit) {
        $stmt = $db->prepare("
            SELECT bi.* FROM bill_items bi
            JOIN patient_bills pb ON bi.bill_id = pb.id
            WHERE pb.visit_id = ? AND bi.item_type = 'medication'
            ORDER BY bi.created_at DESC
        ");
        $stmt->execute([$visit['id']]);
        $visit_medications[$visit['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET BILLS & PAYMENTS WITH ALL ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT pb.*, u.full_name as created_by_name
        FROM patient_bills pb
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.patient_id = ?
        ORDER BY pb.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all bill items for each bill
    $bill_items = [];
    $all_bill_items = [];
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT bi.*, 
                   CASE 
                       WHEN bi.payment_status = 'paid' THEN 'Paid'
                       WHEN bi.payment_status = 'pending' THEN 'Pending'
                       ELSE 'Pending'
                   END as item_status_display
            FROM bill_items bi
            WHERE bi.bill_id = ?
            ORDER BY bi.id
        ");
        $stmt->execute([$bill['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $bill_items[$bill['id']] = $items;
        foreach ($items as $item) {
            $all_bill_items[] = $item;
        }
    }
    
    // ================================================================
    // GET APPOINTMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT a.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as created_by_name
        FROM appointments a
        LEFT JOIN users u ON a.doctor_id = u.id
        LEFT JOIN users r ON a.created_by = r.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("❌ Database error: " . $e->getMessage());
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

function formatDateShort($date) {
    if (empty($date)) return 'N/A';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y h:i A', strtotime($date));
}

function formatTime($date) {
    if (empty($date)) return 'N/A';
    return date('h:i A', strtotime($date));
}

function getStatusBadgeColor($status) {
    $colors = [
        'pending' => '#D97706',
        'in_progress' => '#0B5ED7',
        'completed' => '#059669',
        'cancelled' => '#DC2626',
        'paid' => '#059669',
        'partial' => '#D97706',
        'scheduled' => '#0B5ED7',
        'confirmed' => '#059669',
        'dispensed' => '#059669',
        'assigned' => '#0B5ED7',
        'with_doctor' => '#7C3AED',
        'lab_test' => '#0B5ED7',
        'prescribed' => '#D97706'
    ];
    return $colors[$status] ?? '#64748B';
}

function getStatusText($status) {
    return ucfirst(str_replace('_', ' ', $status));
}

function getStatusIcon($status) {
    $icons = [
        'completed' => '✅',
        'paid' => '✅',
        'dispensed' => '✅',
        'confirmed' => '✅',
        'pending' => '⏳',
        'partial' => '⏳',
        'scheduled' => '📅',
        'in_progress' => '⏳',
        'cancelled' => '❌',
        'assigned' => '👨‍⚕️',
        'with_doctor' => '👨‍⚕️'
    ];
    return $icons[$status] ?? '📌';
}

function formatCurrency($amount) {
    if (empty($amount)) return 'TSh 0';
    return 'TSh ' . number_format($amount, 0);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Complete Patient Report - <?= htmlspecialchars($patient['full_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================
           PAGE SETUP - UNIFORM BORDERS
           ================================================================ */
        @page {
            margin: 12mm 10mm 12mm 10mm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12px;
            color: #1E293B;
            background: #E8ECF0;
            line-height: 1.5;
            display: flex;
            justify-content: center;
            padding: 20px 0;
        }
        
        .page {
            max-width: 210mm;
            width: 100%;
            background: #FFFFFF;
            padding: 10mm 12mm 8mm 12mm;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        /* ================================================================
           HEADER - BLUE THEME (NO BORDER CHANGE)
           ================================================================ */
        .report-header {
            text-align: center;
            border: 2px solid #0B5ED7;
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #F8FAFC, #E8F0FE);
            position: relative;
        }
        
        .report-header .report-id {
            position: absolute;
            top: 8px;
            right: 14px;
            font-size: 9px;
            color: #94A3B8;
            font-weight: 700;
            font-family: monospace;
            background: #E8F0FE;
            padding: 2px 10px;
            border-radius: 4px;
            border: 1px solid #0B5ED7;
        }
        
        .report-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }
        
        .report-header .logo {
            max-height: 55px;
            max-width: 110px;
            object-fit: contain;
        }
        
        .report-header h1 {
            font-size: 22px;
            color: #0B5ED7;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .report-header .subtitle {
            font-size: 13px;
            color: #64748B;
            font-weight: 500;
        }
        
        .report-header .patient-name {
            font-size: 20px;
            font-weight: 700;
            color: #0B5ED7;
            margin-top: 8px;
            border-top: 2px solid #0B5ED7;
            padding-top: 8px;
        }
        
        .report-header .meta-info {
            font-size: 10px;
            color: #64748B;
            margin-top: 4px;
        }
        
        .report-header .meta-info span {
            margin: 0 4px;
        }
        
        .report-header .meta-info .separator {
            color: #CBD5E1;
        }
        
        /* ================================================================
           SECTIONS - UNIFORM BORDER (2px solid #0B5ED7)
           ================================================================ */
        .section {
            margin-bottom: 14px;
            border: 2px solid #0B5ED7;
            border-radius: 8px;
            padding: 12px 16px;
            background: #FFFFFF;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 6px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 14px;
            width: 20px;
            text-align: center;
        }
        
        .section-title .badge {
            font-size: 9px;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
            color: white;
            background: #0B5ED7;
            margin-left: auto;
        }
        
        .section-title .badge.green { background: #059669; }
        .section-title .badge.orange { background: #D97706; }
        .section-title .badge.purple { background: #7C3AED; }
        .section-title .badge.red { background: #DC2626; }
        .section-title .badge.teal { background: #0D9488; }
        
        /* ================================================================
           PATIENT INFO - UNIFORM BORDER
           ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 10px 16px;
            background: #FFFFFF;
        }
        
        .info-item .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
            letter-spacing: 0.05em;
            border-bottom: 1px dashed #E2E8F0;
            padding-bottom: 2px;
        }
        
        .info-item .value {
            font-size: 13px;
            font-weight: 600;
            color: #1E293B;
            padding-top: 2px;
        }
        
        .info-item .value .marital-badge {
            display: inline-block;
            padding: 1px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            color: white;
        }
        
        /* ================================================================
           VITAL SIGNS
           ================================================================ */
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }
        
        .vital-card {
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
            background: #F8FAFC;
        }
        
        .vital-card .vital-value {
            font-size: 18px;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .vital-card .vital-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        
        .vital-card .vital-normal {
            font-size: 8px;
            color: #059669;
        }
        
        .vital-card.bmi {
            border-color: #0B5ED7;
            background: #E8F0FE;
        }
        
        /* ================================================================
           VISIT CARDS - UNIFORM BORDER
           ================================================================ */
        .visit-card {
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 8px;
            background: #FFFFFF;
            page-break-inside: avoid;
        }
        
        .visit-card:last-child {
            margin-bottom: 0;
        }
        
        .visit-card .visit-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }
        
        .visit-card .visit-number {
            font-size: 13px;
            font-weight: 700;
            color: #0B5ED7;
            font-family: monospace;
        }
        
        .visit-card .visit-date {
            font-size: 11px;
            color: #64748B;
        }
        
        .visit-card .visit-doctor {
            font-size: 12px;
            color: #1E293B;
            font-weight: 600;
        }
        
        .visit-card .visit-status {
            font-size: 11px;
            font-weight: 600;
        }
        
        .visit-card .visit-symptoms {
            font-size: 11px;
            color: #475569;
            margin: 4px 0;
            padding: 4px 10px;
            background: #E8F0FE;
            border-radius: 4px;
            border: 1px solid #0B5ED7;
        }
        
        .visit-card .visit-symptoms strong {
            color: #0B5ED7;
        }
        
        .visit-card .visit-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 14px;
            margin-top: 6px;
        }
        
        .visit-card .detail-item {
            padding: 5px 8px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            background: #FAFAFA;
        }
        
        .visit-card .detail-item .label {
            color: #64748B;
            font-weight: 700;
            font-size: 10px;
            display: block;
            border-bottom: 1px dashed #E2E8F0;
            padding-bottom: 2px;
            margin-bottom: 3px;
        }
        
        .visit-card .detail-item .items {
            color: #1E293B;
            font-size: 11px;
        }
        
        .visit-card .detail-item .item-tag {
            display: inline-block;
            border: 1px solid #0B5ED7;
            border-radius: 4px;
            padding: 1px 8px;
            font-size: 10px;
            margin: 2px 4px 2px 0;
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .visit-card .detail-item .item-tag.paid {
            border-color: #059669;
            background: #D1FAE5;
            color: #059669;
        }
        
        .visit-card .detail-item .item-tag.pending {
            border-color: #D97706;
            background: #FEF3C7;
            color: #D97706;
        }
        
        .visit-card .detail-item .item-tag.cancelled {
            border-color: #DC2626;
            background: #FEE2E2;
            color: #DC2626;
        }
        
        /* ================================================================
           APPOINTMENT CARDS - UNIFORM BORDER
           ================================================================ */
        .appointment-card {
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 6px;
            background: #FFFFFF;
            page-break-inside: avoid;
        }
        
        .appointment-card:last-child {
            margin-bottom: 0;
        }
        
        .appointment-card .appt-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #0B5ED7;
            padding-bottom: 4px;
            margin-bottom: 4px;
        }
        
        .appointment-card .appt-id {
            font-size: 12px;
            font-weight: 700;
            color: #0B5ED7;
            font-family: monospace;
        }
        
        .appointment-card .appt-date {
            font-size: 11px;
            color: #64748B;
        }
        
        .appointment-card .appt-doctor {
            font-size: 12px;
            color: #1E293B;
            font-weight: 600;
        }
        
        .appointment-card .appt-details {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
            font-size: 11px;
        }
        
        .appointment-card .appt-details strong {
            color: #64748B;
        }
        
        /* ================================================================
           BILLS TABLE - ALL ITEMS IN SEPARATE ROWS
           ================================================================ */
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 4px;
        }
        
        .bill-table th {
            border: 2px solid #0B5ED7;
            padding: 6px 8px;
            background: #E8F0FE;
            color: #0B5ED7;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
        }
        
        .bill-table td {
            border: 1px solid #E2E8F0;
            padding: 5px 8px;
            vertical-align: middle;
            font-size: 11px;
        }
        
        .bill-table .bill-header-row td {
            background: #F1F5F9;
            font-weight: 700;
            border-top: 2px solid #0B5ED7;
        }
        
        .bill-table .bill-header-row .bill-number {
            color: #0B5ED7;
            font-size: 12px;
        }
        
        .bill-table .item-row td {
            padding-left: 16px;
            font-size: 10px;
        }
        
        .bill-table .item-row .item-name {
            padding-left: 24px;
            color: #1E293B;
        }
        
        .bill-table .item-row .item-type {
            font-size: 9px;
            color: #64748B;
            background: #F1F5F9;
            padding: 1px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .bill-table .item-row .item-status {
            font-size: 9px;
            font-weight: 600;
            padding: 1px 10px;
            border-radius: 12px;
        }
        
        .bill-table .item-row .item-status.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        .bill-table .item-row .item-status.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .bill-table .bill-total-row td {
            font-weight: 700;
            border-top: 2px solid #0B5ED7;
            background: #F8FAFC;
        }
        
        .bill-table .bill-total-row .total-label {
            color: #0B5ED7;
        }
        
        .bill-table .bill-total-row .total-amount {
            color: #059669;
            font-size: 12px;
        }
        
        /* ================================================================
           BILL SUMMARY - UNIFORM BORDER
           ================================================================ */
        .bill-summary {
            margin-top: 8px;
            padding: 8px 16px;
            border: 2px solid #059669;
            border-radius: 6px;
            background: #F0FDF4;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            color: white;
            border: 1px solid transparent;
        }
        
        /* ================================================================
           FOOTER - UNIFORM BORDER
           ================================================================ */
        .report-footer {
            text-align: center;
            border: 2px solid #0B5ED7;
            border-radius: 6px;
            padding: 10px 16px;
            margin-top: 12px;
            font-size: 10px;
            color: #94A3B8;
            background: #F8FAFC;
        }
        
        .report-footer .brand {
            color: #0B5ED7;
            font-weight: 700;
            font-size: 12px;
        }
        
        /* ================================================================
           PRINT MEDIA
           ================================================================ */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .page {
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
                padding: 8mm 10mm 6mm 10mm;
            }
            .no-print {
                display: none !important;
            }
            .section {
                page-break-inside: avoid;
            }
            .visit-card {
                page-break-inside: avoid;
            }
            .appointment-card {
                page-break-inside: avoid;
            }
            .report-header {
                border-color: #0B5ED7 !important;
                background: #F8FAFC !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .info-grid {
                border-color: #E2E8F0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .section {
                border-color: #0B5ED7 !important;
            }
            .vital-card {
                background: #F8FAFC !important;
                border-color: #E2E8F0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .vital-card.bmi {
                background: #E8F0FE !important;
                border-color: #0B5ED7 !important;
            }
            .status-badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bill-table th {
                background: #E8F0FE !important;
                border-color: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bill-table .bill-header-row td {
                background: #F1F5F9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bill-summary {
                background: #F0FDF4 !important;
                border-color: #059669 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .report-footer {
                border-color: #0B5ED7 !important;
                background: #F8FAFC !important;
            }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .page {
                padding: 6mm 6mm 4mm 6mm;
            }
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .vital-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .visit-card .visit-details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .page {
                padding: 4mm 4mm 3mm 4mm;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .vital-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .visit-card .visit-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 3px;
            }
            .report-header h1 {
                font-size: 16px;
            }
            .report-header .patient-name {
                font-size: 15px;
            }
        }
        
        /* ================================================================
           NO-PRINT BUTTONS
           ================================================================ */
        .no-print {
            text-align: center;
            padding: 14px 20px;
            border-top: 2px solid #E2E8F0;
            background: #F8FAFC;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        }
        
        .no-print .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin: 0 8px;
            transition: all 0.3s ease;
        }
        
        .no-print .btn-primary {
            background: #0B5ED7;
            color: white;
        }
        
        .no-print .btn-primary:hover {
            background: #0A4CA8;
            transform: scale(1.03);
        }
        
        .no-print .btn-secondary {
            background: #64748B;
            color: white;
        }
        
        .no-print .btn-secondary:hover {
            background: #475569;
            transform: scale(1.03);
        }
        
        .no-print .btn-success {
            background: #059669;
            color: white;
        }
        
        .no-print .btn-success:hover {
            background: #047857;
            transform: scale(1.03);
        }
        
        .no-print .btn-danger {
            background: #DC2626;
            color: white;
        }
        
        .no-print .btn-danger:hover {
            background: #B91C1C;
            transform: scale(1.03);
        }
        
        .no-print .info-text {
            margin-top: 8px;
            font-size: 11px;
            color: #64748B;
        }
        
        .no-print .info-text i {
            margin-right: 4px;
        }
        
        body {
            padding-bottom: 80px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page" id="reportContent">
        
        <!-- ================================================================ -->
        <!-- HEADER -->
        <!-- ================================================================ -->
        <div class="report-header">
            <div class="report-id">#<?= date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) ?></div>
            <div class="logo-container">
                <?php if (!empty($logo_base64)): ?>
                    <img src="<?= $logo_base64 ?>" alt="Braick Logo" class="logo">
                <?php else: ?>
                    <span style="font-size:32px;">🏥</span>
                <?php endif; ?>
                <div>
                    <h1>Braick Dispensary</h1>
                    <div class="subtitle">Complete Patient Health Report</div>
                </div>
            </div>
            <div class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></div>
            <div class="meta-info">
                <span>🆔 <?= htmlspecialchars($patient['patient_id']) ?></span>
                <span class="separator">|</span>
                <span>📅 <?= date('M d, Y h:i A') ?></span>
                <span class="separator">|</span>
                <span>🏥 <?= htmlspecialchars($branch_name) ?></span>
                <span class="separator">|</span>
                <span>📄 Page <span id="pageNumber"></span></span>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- 1. PATIENT INFORMATION -->
        <!-- ================================================================ -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-user-circle" style="color:#0B5ED7;"></i> Patient Information
                <span class="badge">Profile</span>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Full Name</div>
                    <div class="value"><?= htmlspecialchars($patient['full_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Patient ID</div>
                    <div class="value" style="font-family:monospace;"><?= htmlspecialchars($patient['patient_id']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Gender</div>
                    <div class="value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Date of Birth</div>
                    <div class="value"><?= $patient['date_of_birth'] ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Age</div>
                    <div class="value"><?= calculateAge($patient['date_of_birth']) ?> years</div>
                </div>
                <div class="info-item">
                    <div class="label">Marital Status</div>
                    <div class="value">
                        <?php 
                            $marital = $patient['marital_status'] ?? 'N/A';
                            $marital_colors = [
                                'Single' => '#0B5ED7',
                                'Married' => '#059669',
                                'Divorced' => '#D97706',
                                'Widowed' => '#7C3AED',
                                'Separated' => '#DC2626'
                            ];
                            $color = $marital_colors[$marital] ?? '#64748B';
                        ?>
                        <span class="marital-badge" style="background:<?= $color ?>;"><?= htmlspecialchars($marital) ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Blood Group</div>
                    <div class="value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Phone</div>
                    <div class="value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Email</div>
                    <div class="value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item" style="grid-column: span 2;">
                    <div class="label">Address</div>
                    <div class="value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item" style="grid-column: span 2;">
                    <div class="label">Allergies</div>
                    <div class="value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Branch</div>
                    <div class="value"><?= htmlspecialchars($branch_name) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Registered</div>
                    <div class="value"><?= formatDate($patient['created_at']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- 2. VITAL SIGNS -->
        <!-- ================================================================ -->
        <?php if (count($vital_signs) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs
                <span class="badge green"><?= count($vital_signs) ?> Recordings</span>
            </div>
            
            <?php $latest = $vital_signs[0]; ?>
            <div class="vital-grid">
                <div class="vital-card">
                    <div class="vital-value"><?= $latest['blood_pressure_systolic'] ?? '—' ?><?= $latest['blood_pressure_systolic'] && $latest['blood_pressure_diastolic'] ? '/'.$latest['blood_pressure_diastolic'] : '' ?></div>
                    <div class="vital-label">Blood Pressure</div>
                    <div class="vital-normal">Normal: 120/80</div>
                </div>
                <div class="vital-card">
                    <div class="vital-value"><?= $latest['temperature'] ?? '—' ?><?= $latest['temperature'] ? '°C' : '' ?></div>
                    <div class="vital-label">Temperature</div>
                    <div class="vital-normal">Normal: 36.5-37.5</div>
                </div>
                <div class="vital-card">
                    <div class="vital-value"><?= $latest['pulse_rate'] ?? '—' ?><?= $latest['pulse_rate'] ? ' bpm' : '' ?></div>
                    <div class="vital-label">Pulse Rate</div>
                    <div class="vital-normal">Normal: 60-100</div>
                </div>
                <div class="vital-card">
                    <div class="vital-value"><?= $latest['weight'] ?? '—' ?><?= $latest['weight'] ? ' kg' : '' ?></div>
                    <div class="vital-label">Weight</div>
                    <div class="vital-normal">Recorded</div>
                </div>
                <div class="vital-card">
                    <div class="vital-value"><?= $latest['height'] ?? '—' ?><?= $latest['height'] ? ' cm' : '' ?></div>
                    <div class="vital-label">Height</div>
                    <div class="vital-normal">Recorded</div>
                </div>
                <div class="vital-card bmi">
                    <div class="vital-value"><?= $latest['bmi'] ?? '—' ?><?= $latest['bmi'] ? ' kg/m²' : '' ?></div>
                    <div class="vital-label">BMI</div>
                    <?php if ($latest['bmi']): 
                        $bmi = $latest['bmi'];
                        $category = 'Normal';
                        $color = '#059669';
                        if ($bmi < 18.5) { $category = 'Underweight'; $color = '#D97706'; }
                        elseif ($bmi < 25) { $category = 'Normal'; $color = '#059669'; }
                        elseif ($bmi < 30) { $category = 'Overweight'; $color = '#D97706'; }
                        else { $category = 'Obese'; $color = '#DC2626'; }
                    ?>
                        <div class="vital-normal" style="color:<?= $color ?>;font-weight:700;"><?= $category ?></div>
                    <?php else: ?>
                        <div class="vital-normal">Normal: 18.5-24.9</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (count($vital_signs) > 1): ?>
            <div style="margin-top:8px;">
                <div style="font-size:10px;font-weight:600;color:#0B5ED7;margin-bottom:4px;">📊 History</div>
                <div style="border:1px solid #E2E8F0;border-radius:4px;overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:10px;">
                        <thead>
                            <tr>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">Date</th>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">BP</th>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">Temp</th>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">Pulse</th>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">Weight</th>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">Height</th>
                                <th style="border:1px solid #E2E8F0;padding:3px 6px;background:#F1F5F9;text-align:left;">BMI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($vital_signs, 0, 10) as $vs): ?>
                            <tr>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= formatDateShort($vs['recorded_at']) ?></td>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= $vs['blood_pressure_systolic'] ? $vs['blood_pressure_systolic'].'/'.$vs['blood_pressure_diastolic'] : '—' ?></td>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= $vs['temperature'] ?? '—' ?></td>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= $vs['pulse_rate'] ?? '—' ?></td>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= $vs['weight'] ?? '—' ?></td>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= $vs['height'] ?? '—' ?></td>
                                <td style="border:1px solid #E2E8F0;padding:3px 6px;"><?= $vs['bmi'] ?? '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- 3. VISITS HISTORY -->
        <!-- ================================================================ -->
        <?php if (count($visits) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-hospital" style="color:#0B5ED7;"></i> Visit History
                <span class="badge purple"><?= count($visits) ?> Visits</span>
            </div>
            
            <?php foreach ($visits as $visit): ?>
            <div class="visit-card">
                <div class="visit-header">
                    <div>
                        <span class="visit-number"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        <span class="visit-date"> • <?= formatDateTime($visit['created_at']) ?></span>
                    </div>
                    <div>
                        <span class="visit-doctor">👨‍⚕️ Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span>
                        <span class="visit-status" style="color:<?= getStatusBadgeColor($visit['status']) ?>;margin-left:8px;">
                            <?= getStatusIcon($visit['status']) ?> <?= getStatusText($visit['status']) ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($visit['symptoms'])): ?>
                <div class="visit-symptoms">
                    <strong>🤒 Symptoms:</strong> <?= htmlspecialchars($visit['symptoms']) ?>
                </div>
                <?php endif; ?>
                
                <div class="visit-details-grid">
                    
                    <?php if (isset($visit_prescriptions[$visit['id']]) && count($visit_prescriptions[$visit['id']]) > 0): ?>
                    <div class="detail-item">
                        <span class="label">💊 Prescriptions</span>
                        <div class="items">
                            <?php foreach ($visit_prescriptions[$visit['id']] as $pres): ?>
                                <span class="item-tag <?= $pres['status'] === 'dispensed' ? 'paid' : 'pending' ?>">
                                    #<?= htmlspecialchars($pres['prescription_number']) ?>
                                    <?php if (isset($visit_prescription_items[$pres['id']])): ?>
                                        <?php 
                                            $meds = [];
                                            foreach ($visit_prescription_items[$pres['id']] as $item) {
                                                $meds[] = $item['medication_name'];
                                            }
                                            echo implode(', ', array_slice($meds, 0, 3));
                                            if (count($meds) > 3) echo ' ...';
                                        ?>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($visit_lab_tests[$visit['id']]) && count($visit_lab_tests[$visit['id']]) > 0): ?>
                    <div class="detail-item">
                        <span class="label">🧪 Lab Tests</span>
                        <div class="items">
                            <?php foreach ($visit_lab_tests[$visit['id']] as $test): ?>
                                <span class="item-tag <?= $test['status'] === 'completed' ? 'paid' : 'pending' ?>">
                                    <?= htmlspecialchars($test['test_name']) ?>
                                    <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
                                        (<?= htmlspecialchars($test['results']) ?>)
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($visit_procedures[$visit['id']]) && count($visit_procedures[$visit['id']]) > 0): ?>
                    <div class="detail-item">
                        <span class="label">💉 Procedures</span>
                        <div class="items">
                            <?php foreach ($visit_procedures[$visit['id']] as $proc): ?>
                                <span class="item-tag pending">
                                    <?= htmlspecialchars($proc['item_name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($visit_medications[$visit['id']]) && count($visit_medications[$visit['id']]) > 0): ?>
                    <div class="detail-item">
                        <span class="label">💊 Medications</span>
                        <div class="items">
                            <?php foreach ($visit_medications[$visit['id']] as $med): ?>
                                <span class="item-tag <?= $med['status'] === 'paid' ? 'paid' : 'pending' ?>">
                                    <?= htmlspecialchars($med['item_name']) ?>
                                    x<?= $med['quantity'] ?? 1 ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- 4. BILLS & PAYMENTS - ALL ITEMS IN SEPARATE ROWS -->
        <!-- ================================================================ -->
        <?php if (count($bills) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-money-bill-wave" style="color:#059669;"></i> Bills & Payments
                <span class="badge green"><?= count($bills) ?> Bills</span>
            </div>
            
            <table class="bill-table">
                <thead>
                    <tr>
                        <th style="width:15%;">Bill #</th>
                        <th style="width:12%;">Date</th>
                        <th style="width:30%;">Item Name</th>
                        <th style="width:10%;">Type</th>
                        <th style="width:8%;">Qty</th>
                        <th style="width:12%;">Unit Price</th>
                        <th style="width:13%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $bill_count = 0;
                    $grand_total = 0;
                    foreach ($bills as $bill):
                        $bill_count++;
                        $items = $bill_items[$bill['id']] ?? [];
                        $total_items = count($items);
                        $bill_total = $bill['total_amount'] ?? 0;
                        $grand_total += $bill_total;
                    ?>
                        <!-- Bill Header Row -->
                        <tr class="bill-header-row">
                            <td colspan="7">
                                <span class="bill-number">📋 Bill #<?= htmlspecialchars($bill['bill_number']) ?></span>
                                <span style="margin-left:16px;font-weight:400;color:#64748B;font-size:10px;">
                                    <?= formatDateShort($bill['created_at']) ?> 
                                    | Status: <span style="color:<?= getStatusBadgeColor($bill['status']) ?>;"><?= getStatusText($bill['status']) ?></span>
                                    | Total: <span style="color:#059669;"><?= formatCurrency($bill_total) ?></span>
                                </span>
                            </td>
                        </tr>
                        
                        <!-- Items Rows -->
                        <?php if ($total_items > 0): ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="item-row">
                                    <td></td>
                                    <td></td>
                                    <td class="item-name">• <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                    <td><span class="item-type"><?= htmlspecialchars($item['item_type'] ?? 'Other') ?></span></td>
                                    <td><?= $item['quantity'] ?? 1 ?></td>
                                    <td><?= formatCurrency($item['unit_price'] ?? 0) ?></td>
                                    <td><?= formatCurrency($item['total_price'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="item-row">
                                <td></td>
                                <td></td>
                                <td class="item-name" style="color:#94A3B8;">No items found</td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                            </tr>
                        <?php endif; ?>
                        
                        <!-- Bill Total Row -->
                        <tr class="bill-total-row">
                            <td colspan="6" style="text-align:right;font-size:11px;color:#0B5ED7;">
                                <strong>Bill Total:</strong>
                            </td>
                            <td style="font-size:12px;color:#059669;font-weight:700;">
                                <?= formatCurrency($bill_total) ?>
                            </td>
                        </tr>
                        
                        <!-- Spacer between bills -->
                        <?php if ($bill_count < count($bills)): ?>
                            <tr><td colspan="7" style="padding:2px 0;border:none;background:transparent;"></td></tr>
                        <?php endif; ?>
                        
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Grand Total Summary -->
            <div class="bill-summary">
                <span>💰 Total Bills: <strong><?= formatCurrency($grand_total) ?></strong></span>
                <span>📊 Total Bills Count: <strong><?= count($bills) ?></strong></span>
                <span>📦 Total Items: <strong><?= count($all_bill_items) ?></strong></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- 5. APPOINTMENTS -->
        <!-- ================================================================ -->
        <?php if (count($appointments) > 0): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-calendar-check" style="color:#0D9488;"></i> Appointments
                <span class="badge teal"><?= count($appointments) ?> Appointments</span>
            </div>
            
            <?php foreach ($appointments as $appt): ?>
            <div class="appointment-card">
                <div class="appt-header">
                    <div>
                        <span class="appt-id">#<?= $appt['id'] ?></span>
                        <span class="appt-date"> • <?= formatDateTime($appt['appointment_date']) ?></span>
                    </div>
                    <div>
                        <span class="appt-doctor">👨‍⚕️ Dr. <?= htmlspecialchars($appt['doctor_name'] ?? 'N/A') ?></span>
                        <span class="status-badge" style="background:<?= getStatusBadgeColor($appt['status']) ?>;margin-left:8px;">
                            <?= getStatusText($appt['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="appt-details">
                    <?php if (!empty($appt['purpose'])): ?>
                        <span><strong>Purpose:</strong> <?= htmlspecialchars($appt['purpose']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($appt['doctor_specialty'])): ?>
                        <span><strong>Specialty:</strong> <?= htmlspecialchars($appt['doctor_specialty']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($appt['created_by_name'])): ?>
                        <span><strong>Created by:</strong> <?= htmlspecialchars($appt['created_by_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- ================================================================ -->
        <!-- FOOTER -->
        <!-- ================================================================ -->
        <div class="report-footer">
            <p>
                <span class="brand">🏥 Braick Dispensary</span>
                <span style="margin:0 4px;color:#CBD5E1;">|</span>
                Generated: <?= date('M d, Y h:i A') ?>
                <span style="margin:0 4px;color:#CBD5E1;">|</span>
                By: <?= htmlspecialchars($user_full_name) ?>
                <span style="margin:0 4px;color:#CBD5E1;">|</span>
                Patient: <?= htmlspecialchars($patient['full_name']) ?>
            </p>
            <p style="margin-top:3px;font-size:9px;color:#CBD5E1;">
                © <?= date('Y') ?> Braick Dispensary - All rights reserved | This is a computer generated report
            </p>
        </div>
        
    </div>
    
    <!-- ================================================================ -->
    <!-- FIXED BUTTONS -->
    <!-- ================================================================ -->
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> 🖨️ Print / Save PDF
        </button>
        <button onclick="downloadPDF()" class="btn btn-success">
            <i class="fas fa-download"></i> ⬇️ Download PDF
        </button>
        <button onclick="window.close()" class="btn btn-danger">
            <i class="fas fa-times"></i> ❌ Close
        </button>
        <div class="info-text">
            <i class="fas fa-info-circle"></i> 
            Click "Print / Save PDF" then select "Save as PDF" OR click "Download PDF" to download directly
        </div>
    </div>
    
    <script>
        // ================================================================
        // PAGE NUMBERING
        // ================================================================
        document.addEventListener('DOMContentLoaded', function() {
            var pageNumber = document.getElementById('pageNumber');
            if (pageNumber) {
                var totalSections = document.querySelectorAll('.section').length;
                pageNumber.textContent = '1 of ' + (totalSections + 1);
            }
        });
        
        // ================================================================
        // DOWNLOAD PDF FUNCTION
        // ================================================================
        function downloadPDF() {
            window.print();
        }
        
        // ================================================================
        // AUTO PRINT IF REQUESTED
        // ================================================================
        <?php if (isset($_GET['print']) && $_GET['print'] == 1): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1500);
        };
        <?php endif; ?>
        
        console.log('%c📄 Braick - Complete Patient PDF Export (Blue Theme)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
        console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:12px; color:#059669;');
        console.log('%c📋 Visits: <?= count($visits) ?> | Bills: <?= count($bills) ?> | Appointments: <?= count($appointments) ?>', 'font-size:12px; color:#64748B;');
        console.log('%c💙 Blue Theme - Uniform Borders', 'font-size:12px; color:#0B5ED7;');
        console.log('%c📋 Bills table shows all items in separate rows', 'font-size:12px; color:#059669;');
    </script>
</body>
</html>