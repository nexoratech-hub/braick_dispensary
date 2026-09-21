<?php
// ================================================================
// FILE: frontend/pages/doctor/view_patient_pdf.php
// DOCTOR - PATIENT PDF VIEW v5.0
// FIXED: Paid card inatumia paid_amount column (sio total_amount)
// FIXED: Pending card inatumia balance column
// FIXED: Bill Summary Cards - Subtotal, Total, Paid, Pending
// REMOVED: Discount & Premium cards kwenye summary (zipo kwenye bill table)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$selected_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, profile_pic, status FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $selected_branch_id = $doctor_data['branch_id'] ?? 1;
    
} catch (Exception $e) {
    error_log("view_patient_pdf verification error: " . $e->getMessage());
    $profile_pic = '';
}

// ================================================================
// VARIABLES
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET PATIENT DATA - Verify doctor has access
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ? AND p.assigned_doctor_id = ?
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient = null;
}

if (!$patient) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET STATISTICS
// ================================================================

// Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ FIXED: Total Bills - Inatumia paid_amount & balance columns
// ================================================================
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(total_amount), 0) as total_amount,
        COALESCE(SUM(paid_amount), 0) as paid_amount,
        COALESCE(SUM(balance), 0) as pending_amount,
        COALESCE(SUM(subtotal), 0) as total_subtotal,
        COALESCE(SUM(pharmacy_discount), 0) as total_pharmacy_discount,
        COALESCE(SUM(cashier_discount), 0) as total_cashier_discount,
        COALESCE(SUM(total_discount), 0) as total_discount,
        COALESCE(SUM(pharmacy_premium), 0) as total_pharmacy_premium,
        COALESCE(SUM(cashier_premium), 0) as total_cashier_premium,
        COALESCE(SUM(premium_amount), 0) as total_premium
    FROM bills 
    WHERE patient_id = ? AND status != 'cancelled'
");
$stmt->execute([$patient_id]);
$bills_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_bills = $bills_stats['total'] ?? 0;
$total_bill_amount = $bills_stats['total_amount'] ?? 0;
$paid_bill_amount = $bills_stats['paid_amount'] ?? 0;
$pending_bill_amount = $bills_stats['pending_amount'] ?? 0;

// ✅ NEW: Discount & Premium variables
$total_subtotal = $bills_stats['total_subtotal'] ?? 0;
$total_pharmacy_discount = $bills_stats['total_pharmacy_discount'] ?? 0;
$total_cashier_discount = $bills_stats['total_cashier_discount'] ?? 0;
$total_discount_all = $bills_stats['total_discount'] ?? 0;
$total_pharmacy_premium = $bills_stats['total_pharmacy_premium'] ?? 0;
$total_cashier_premium = $bills_stats['total_cashier_premium'] ?? 0;
$total_premium_all = $bills_stats['total_premium'] ?? 0;

// Total Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Lab Tests
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    WHERE v.patient_id = ?
");
$stmt->execute([$patient_id]);
$total_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Procedures
$stmt = $db->prepare("SELECT COUNT(*) as total FROM procedures WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_procedures = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Payments
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM payments WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_payments = $payments_data['total'] ?? 0;
$total_payments_amount = $payments_data['total_amount'] ?? 0;

// ================================================================
// GET LATEST VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name, u.specialty as doctor_specialty
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$latest_visit = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET VITAL SIGNS - LATEST
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, 
           u.full_name as recorded_by_name,
           v.visit_number
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    LEFT JOIN visits v ON vs.visit_id = v.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL VITAL SIGNS HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT vs.*, 
           u.full_name as recorded_by_name,
           v.visit_number
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    LEFT JOIN visits v ON vs.visit_id = v.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 20
");
$stmt->execute([$patient_id]);
$vital_signs_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_vital_signs = count($vital_signs_history);

// ================================================================
// GET LAB TESTS
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, 
           u.full_name as doctor_name,
           tech.full_name as technician_name,
           v.visit_number
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users tech ON lt.performed_by = tech.id
    WHERE v.patient_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DIAGNOSIS HISTORY
// ================================================================
$stmt = $db->prepare("
    SELECT diagnosis, disease_id, disease_code, treatment, created_at, symptoms, hpi, physical_exam, complaint, notes
    FROM visits 
    WHERE patient_id = ? AND (diagnosis IS NOT NULL AND diagnosis != '' OR disease_id IS NOT NULL)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$diagnosis_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get disease names for diagnosis
foreach ($diagnosis_history as &$diag) {
    if ($diag['disease_id']) {
        $stmt = $db->prepare("SELECT disease_name FROM diseases WHERE id = ?");
        $stmt->execute([$diag['disease_id']]);
        $disease = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disease) {
            $diag['disease_name'] = $disease['disease_name'];
        }
    }
}
unset($diag);

// ================================================================
// GET PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, 
           u.full_name as doctor_name,
           pi.medication_name, pi.dosage, pi.frequency, pi.quantity, 
           pi.duration, pi.route, pi.instructions, pi.unit_price, pi.total_price
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PROCEDURES
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, u.full_name as doctor_name
    FROM procedures pr
    LEFT JOIN users u ON pr.doctor_id = u.id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET APPOINTMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT a.*, u.full_name as doctor_name
    FROM appointments a
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILLS
// ================================================================
$stmt = $db->prepare("
    SELECT b.*, 
           COALESCE(SUM(bi.total_price), 0) as items_total
    FROM bills b
    LEFT JOIN bill_items bi ON b.id = bi.bill_id
    WHERE b.patient_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL ITEMS FOR EACH BILL
// ================================================================
$bill_items_data = [];
foreach ($bills as $bill) {
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$bill['id']]);
    $bill_items_data[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y h:i A', strtotime($date));
}

function formatDateShort($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient PDF - <?= htmlspecialchars($patient['full_name']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* PRINT-FRIENDLY CSS */
        /* ================================================================ */
        @page {
            size: A4;
            margin: 12mm 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        body {
            font-family: 'Segoe UI', 'Inter', Arial, sans-serif;
            font-size: 10pt;
            color: #1E293B;
            background: #F1F5F9;
            line-height: 1.4;
        }
        
        .pdf-container {
            max-width: 210mm;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* ================================================================ */
        /* HEADER */
        /* ================================================================ */
        .pdf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 3px solid #0B5ED7;
            margin-bottom: 16px;
        }
        
        .pdf-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .pdf-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        
        .pdf-hospital-info h1 {
            font-size: 18pt;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: -0.5px;
        }
        
        .pdf-hospital-info p {
            font-size: 8pt;
            color: #64748B;
            margin-top: 2px;
        }
        
        .pdf-header-right {
            text-align: right;
            font-size: 8pt;
            color: #64748B;
        }
        
        .pdf-header-right .pdf-title {
            font-size: 14pt;
            font-weight: 800;
            color: #0B5ED7;
            margin-bottom: 4px;
        }
        
        /* ================================================================ */
        /* SECTIONS */
        /* ================================================================ */
        .pdf-section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        
        .pdf-section-title {
            font-size: 11pt;
            font-weight: 800;
            color: #0B5ED7;
            padding: 6px 10px;
            background: linear-gradient(90deg, #E8F0FE, #FFFFFF);
            border-left: 4px solid #0B5ED7;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* ================================================================ */
        /* PATIENT INFO GRID */
        /* ================================================================ */
        .pdf-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 20px;
            padding: 10px 12px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
        }
        
        .pdf-info-item {
            display: flex;
            font-size: 9pt;
            padding: 3px 0;
            border-bottom: 1px dashed #E2E8F0;
        }
        
        .pdf-info-item:last-child {
            border-bottom: none;
        }
        
        .pdf-info-label {
            width: 120px;
            font-weight: 600;
            color: #64748B;
            flex-shrink: 0;
        }
        
        .pdf-info-value {
            flex: 1;
            color: #1E293B;
            font-weight: 500;
        }
        
        .pdf-info-value.strong {
            font-weight: 700;
            color: #0B5ED7;
        }
        
        /* ================================================================ */
        /* VITAL SIGNS */
        /* ================================================================ */
        .pdf-vital-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        
        .pdf-vital-card {
            padding: 8px 6px;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            text-align: center;
            background: #F8FAFC;
        }
        
        .pdf-vital-card .vital-label {
            font-size: 7pt;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .pdf-vital-card .vital-value {
            font-size: 11pt;
            font-weight: 800;
            color: #0B5ED7;
            margin-top: 3px;
        }
        
        .pdf-vital-card .vital-unit {
            font-size: 7pt;
            color: #94A3B8;
            font-weight: 400;
        }
        
        /* ================================================================ */
        /* TABLES */
        /* ================================================================ */
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 8px;
        }
        
        .pdf-table thead th {
            background: #0B5ED7;
            color: #FFFFFF;
            font-weight: 700;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #0A4CA8;
        }
        
        .pdf-table tbody td {
            padding: 5px 8px;
            border: 1px solid #E2E8F0;
            color: #1E293B;
            vertical-align: top;
        }
        
        .pdf-table tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .pdf-table tbody tr:hover td {
            background: #E8F0FE;
        }
        
        .pdf-table .empty-cell {
            text-align: center;
            color: #94A3B8;
            font-style: italic;
            padding: 12px;
        }
        
        /* ================================================================ */
        /* ✅ BILL SUMMARY CARDS - v5.0 */
        /* ================================================================ */
        .pdf-bill-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        
        .pdf-bill-card {
            padding: 10px 12px;
            border-radius: 8px;
            border: 2px solid #E2E8F0;
            background: #FFFFFF;
            position: relative;
            overflow: hidden;
        }
        
        .pdf-bill-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        
        .pdf-bill-card .bill-label {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 3px;
        }
        
        .pdf-bill-card .bill-value {
            font-size: 12pt;
            font-weight: 800;
            display: block;
            font-family: 'Courier New', monospace;
        }
        
        .pdf-bill-card .bill-sub {
            font-size: 7pt;
            font-weight: 500;
            display: block;
            margin-top: 2px;
            color: #64748B;
        }
        
        /* Subtotal Card */
        .pdf-bill-card.subtotal-card { border-color: #64748B; }
        .pdf-bill-card.subtotal-card::before { background: #64748B; }
        .pdf-bill-card.subtotal-card .bill-label { color: #64748B; }
        .pdf-bill-card.subtotal-card .bill-value { color: #475569; }
        
        /* Total Card */
        .pdf-bill-card.total-card { border-color: #0B5ED7; }
        .pdf-bill-card.total-card::before { background: #0B5ED7; }
        .pdf-bill-card.total-card .bill-label { color: #0B5ED7; }
        .pdf-bill-card.total-card .bill-value { color: #0B5ED7; }
        
        /* Paid Card */
        .pdf-bill-card.paid-card { border-color: #059669; }
        .pdf-bill-card.paid-card::before { background: #059669; }
        .pdf-bill-card.paid-card .bill-label { color: #059669; }
        .pdf-bill-card.paid-card .bill-value { color: #059669; }
        
        /* Pending Card */
        .pdf-bill-card.pending-card { border-color: #D97706; }
        .pdf-bill-card.pending-card::before { background: #D97706; }
        .pdf-bill-card.pending-card .bill-label { color: #D97706; }
        .pdf-bill-card.pending-card .bill-value { color: #D97706; }
        
        /* ================================================================ */
        /* BILL BLOCK */
        /* ================================================================ */
        .pdf-bill-block {
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        
        .pdf-bill-header {
            background: #0B5ED7;
            color: #FFFFFF;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .pdf-bill-header .bill-number {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 9pt;
        }
        
        .pdf-bill-header .bill-status {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            background: rgba(255,255,255,0.2);
        }
        
        .pdf-bill-header .bill-totals {
            display: flex;
            gap: 12px;
            font-size: 8pt;
        }
        
        .pdf-bill-header .bill-totals span {
            font-weight: 600;
        }
        
        .pdf-bill-header .bill-totals strong {
            font-family: 'Courier New', monospace;
        }
        
        /* Bill Items Table */
        .pdf-bill-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        
        .pdf-bill-items thead th {
            background: #064E3B;
            color: #FFFFFF;
            padding: 5px 8px;
            text-align: left;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #047857;
        }
        
        .pdf-bill-items tbody td {
            padding: 4px 8px;
            border: 1px solid #E2E8F0;
        }
        
        .pdf-bill-items .subtotal-row td {
            background: #F1F5F9 !important;
            font-weight: 700;
            border-top: 2px solid #CBD5E1;
        }
        
        .pdf-bill-items .discount-row td {
            background: #FEF3C7 !important;
            color: #D97706;
            font-weight: 600;
        }
        
        .pdf-bill-items .premium-row td {
            background: #EDE9FE !important;
            color: #7C3AED;
            font-weight: 600;
        }
        
        .pdf-bill-items .total-row td {
            background: #E8F0FE !important;
            font-weight: 800;
            color: #0B5ED7;
            border-top: 2px solid #0B5ED7;
            font-size: 9pt;
        }
        
        .pdf-bill-items .paid-row td {
            background: #D1FAE5 !important;
            color: #059669;
            font-weight: 700;
            border-top: 2px solid #059669;
        }
        
        .pdf-bill-items .balance-row td {
            background: #FEE2E2 !important;
            color: #DC2626;
            font-weight: 700;
            border-top: 2px solid #DC2626;
        }
        
        .pdf-bill-items .balance-row.no-balance td {
            background: #D1FAE5 !important;
            color: #059669;
            border-top-color: #059669;
        }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .pdf-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #0B5ED7;
            text-align: center;
            font-size: 7.5pt;
            color: #64748B;
        }
        
        .pdf-footer .footer-brand {
            color: #0B5ED7;
            font-weight: 700;
        }
        
        /* ================================================================ */
        /* ACTION BUTTONS */
        /* ================================================================ */
        .pdf-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }
        
        .pdf-btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 9pt;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .pdf-btn-print {
            background: #059669;
            color: white;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
        }
        
        .pdf-btn-print:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }
        
        .pdf-btn-back {
            background: #64748B;
            color: white;
            box-shadow: 0 4px 14px rgba(100, 116, 139, 0.3);
        }
        
        .pdf-btn-back:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        
        /* ================================================================ */
        /* PRINT STYLES */
        /* ================================================================ */
        @media print {
            body {
                background: #FFFFFF;
                font-size: 9pt;
            }
            
            .pdf-container {
                max-width: 100%;
                padding: 0;
                box-shadow: none;
            }
            
            .pdf-actions {
                display: none !important;
            }
            
            .pdf-section {
                page-break-inside: avoid;
            }
            
            .pdf-bill-block {
                page-break-inside: avoid;
            }
            
            .pdf-bill-items {
                font-size: 7.5pt;
            }
            
            .pdf-bill-summary {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ACTION BUTTONS -->
<!-- ================================================================ -->
<div class="pdf-actions">
    <button onclick="window.print()" class="pdf-btn pdf-btn-print">
        <i class="fas fa-print"></i> Print / Save PDF
    </button>
    <a href="patient_details.php?id=<?= $patient_id ?>" class="pdf-btn pdf-btn-back">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<!-- ================================================================ -->
<!-- PDF CONTAINER -->
<!-- ================================================================ -->
<div class="pdf-container">

    <!-- ================================================================ -->
    <!-- HEADER -->
    <!-- ================================================================ -->
    <div class="pdf-header">
        <div class="pdf-header-left">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="pdf-logo" onerror="this.style.display='none'">
            <div class="pdf-hospital-info">
                <h1>BRAICK DISPENSARY</h1>
                <p><i class="fas fa-map-marker-alt"></i> Dodoma, Tanzania | <i class="fas fa-phone"></i> +255 XXX XXX XXX</p>
                <p><i class="fas fa-envelope"></i> info@braickdispensary.co.tz | <i class="fas fa-globe"></i> www.braickdispensary.co.tz</p>
            </div>
        </div>
        <div class="pdf-header-right">
            <div class="pdf-title">PATIENT MEDICAL RECORD</div>
            <p>Generated: <?= date('M d, Y H:i A') ?></p>
            <p>By: Dr. <?= htmlspecialchars($doctor_name) ?></p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 1. PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-user-circle"></i> PATIENT INFORMATION
        </div>
        <div class="pdf-info-grid">
            <div class="pdf-info-item">
                <span class="pdf-info-label">Full Name:</span>
                <span class="pdf-info-value strong"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Patient ID:</span>
                <span class="pdf-info-value" style="font-family:monospace;"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Date of Birth:</span>
                <span class="pdf-info-value"><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) . ' (' . calculateAge($patient['date_of_birth']) . ' yrs)' : 'N/A' ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Gender:</span>
                <span class="pdf-info-value"><?= ucfirst(htmlspecialchars($patient['gender'] ?? 'N/A')) ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Marital Status:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Blood Group:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Phone:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Email:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Address:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Emergency Contact:</span>
                <span class="pdf-info-value" style="color:#DC2626;font-weight:700;"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Allergies:</span>
                <span class="pdf-info-value" style="color:#DC2626;"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
            </div>
            <div class="pdf-info-item">
                <span class="pdf-info-label">Branch:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 2. VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-clinic-medical"></i> VISIT INFORMATION
        </div>
        <?php if ($latest_visit): ?>
            <div class="pdf-info-grid">
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Visit Number:</span>
                    <span class="pdf-info-value strong" style="font-family:monospace;"><?= htmlspecialchars($latest_visit['visit_number']) ?></span>
                </div>
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Date:</span>
                    <span class="pdf-info-value"><?= formatDate($latest_visit['visit_date']) ?></span>
                </div>
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Doctor:</span>
                    <span class="pdf-info-value">Dr. <?= htmlspecialchars($latest_visit['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Specialty:</span>
                    <span class="pdf-info-value"><?= htmlspecialchars($latest_visit['doctor_specialty'] ?? 'N/A') ?></span>
                </div>
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Visit Type:</span>
                    <span class="pdf-info-value"><?= ucfirst($latest_visit['visit_type'] ?? 'N/A') ?></span>
                </div>
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Consultation Fee:</span>
                    <span class="pdf-info-value">TSh <?= number_format($latest_visit['consultation_fee'] ?? 0, 0) ?></span>
                </div>
                <div class="pdf-info-item">
                    <span class="pdf-info-label">Status:</span>
                    <span class="pdf-info-value"><strong><?= ucfirst(str_replace('_', ' ', $latest_visit['status'] ?? 'Pending')) ?></strong></span>
                </div>
                <?php if ($latest_visit['is_completed']): ?>
                    <div class="pdf-info-item">
                        <span class="pdf-info-label">Completed:</span>
                        <span class="pdf-info-value"><?= formatDate($latest_visit['completed_at']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No visits recorded for this patient</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 3. VITAL SIGNS - 7 SIGNS -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-heartbeat"></i> VITAL SIGNS (7 Signs)
        </div>
        <?php if ($latest_vital_signs): ?>
            <div class="pdf-vital-grid">
                <div class="pdf-vital-card">
                    <div class="vital-label">🌡️ Temperature</div>
                    <div class="vital-value"><?= $latest_vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">❤️ Blood Pressure</div>
                    <div class="vital-value"><?= ($latest_vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($latest_vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">💓 Pulse Rate</div>
                    <div class="vital-value"><?= $latest_vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">🫁 SpO2</div>
                    <div class="vital-value"><?= $latest_vital_signs['oxygen_saturation'] ?? '--' ?> <span class="vital-unit">%</span></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">⚖️ Weight</div>
                    <div class="vital-value"><?= $latest_vital_signs['weight'] ?? '--' ?> <span class="vital-unit">kg</span></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">📏 Height</div>
                    <div class="vital-value"><?= $latest_vital_signs['height'] ?? '--' ?> <span class="vital-unit">cm</span></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">📊 BMI</div>
                    <div class="vital-value"><?= $latest_vital_signs['bmi'] ?? '--' ?></div>
                </div>
                <div class="pdf-vital-card">
                    <div class="vital-label">👤 Recorded By</div>
                    <div class="vital-value" style="font-size:8pt;"><?= htmlspecialchars($latest_vital_signs['recorded_by_name'] ?? 'N/A') ?></div>
                </div>
            </div>
            <p style="font-size:7.5pt;color:#64748B;margin-top:6px;">
                <i class="fas fa-clock"></i> Recorded: <?= formatDate($latest_vital_signs['recorded_at']) ?>
                <?php if ($latest_vital_signs['visit_number']): ?>
                    | <i class="fas fa-stethoscope"></i> Visit: <?= htmlspecialchars($latest_vital_signs['visit_number']) ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No vital signs recorded</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 4. CLINICAL ASSESSMENT -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-clipboard-list"></i> CLINICAL ASSESSMENT
        </div>
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width:20%;">Symptoms</th>
                    <th style="width:20%;">Complaints</th>
                    <th style="width:20%;">Notes</th>
                    <th style="width:20%;">HPI</th>
                    <th style="width:20%;">Physical Exam</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= !empty($latest_visit['symptoms']) ? nl2br(htmlspecialchars($latest_visit['symptoms'])) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($latest_visit['complaint']) ? nl2br(htmlspecialchars($latest_visit['complaint'])) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($latest_visit['notes']) ? nl2br(htmlspecialchars($latest_visit['notes'])) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($latest_visit['hpi']) ? nl2br(htmlspecialchars($latest_visit['hpi'])) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($latest_visit['physical_exam']) ? nl2br(htmlspecialchars($latest_visit['physical_exam'])) : '<span class="empty-cell">—</span>' ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ================================================================ -->
    <!-- 5. LAB TESTS -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-flask"></i> LAB TESTS (<?= count($lab_tests) ?> records)
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Visit</th>
                        <th>Results</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lab_tests as $test): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                            <td style="font-family:monospace;font-size:7.5pt;"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                            <td><?= !empty($test['results']) ? htmlspecialchars(substr($test['results'], 0, 60)) . (strlen($test['results']) > 60 ? '...' : '') : '<em>Pending</em>' ?></td>
                            <td><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?></td>
                            <td><?= formatDateShort($test['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No lab tests found</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 6. DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-diagnoses"></i> DIAGNOSIS (<?= count($diagnosis_history) ?> records)
        </div>
        <?php if (count($diagnosis_history) > 0): ?>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Diagnosis</th>
                        <th>Code</th>
                        <th>Treatment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diagnosis_history as $diag): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($diag['disease_name'] ?? $diag['diagnosis'] ?? 'N/A') ?></strong></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($diag['disease_code'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($diag['treatment'] ?? '—') ?></td>
                            <td><?= formatDateShort($diag['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No diagnosis recorded</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 7. PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-prescription"></i> PRESCRIPTIONS (<?= count($prescriptions) ?> records)
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Rx #</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Qty</th>
                        <th>Instructions</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $pres): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:7.5pt;"><?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></td>
                            <td><strong><?= htmlspecialchars($pres['medication_name'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($pres['dosage'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($pres['frequency'] ?? '—') ?></td>
                            <td><?= $pres['quantity'] ?? 0 ?></td>
                            <td><?= htmlspecialchars($pres['instructions'] ?? '—') ?></td>
                            <td><?= ucfirst($pres['status'] ?? 'Pending') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No prescriptions found</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 8. PROCEDURES -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-syringe"></i> PROCEDURES & EQUIPMENT (<?= count($procedures) ?> records)
        </div>
        <?php if (count($procedures) > 0): ?>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Procedure Name</th>
                        <th>Doctor</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procedures as $proc): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($proc['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= ($proc['procedure_price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['procedure_price'], 0) : '<span style="color:#059669;font-weight:700;">FREE</span>' ?></td>
                            <td><?= ucfirst($proc['status'] ?? 'Pending') ?></td>
                            <td><?= formatDateShort($proc['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No procedures found</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 9. APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-calendar-check"></i> APPOINTMENTS (<?= count($appointments) ?> records)
        </div>
        <?php if (count($appointments) > 0): ?>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Appointment Date</th>
                        <th>Type</th>
                        <th>Purpose</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $app): ?>
                        <tr>
                            <td>Dr. <?= htmlspecialchars($app['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= formatDate($app['appointment_date']) ?></td>
                            <td><?= ucfirst($app['visit_type'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($app['purpose'] ?? '—') ?></td>
                            <td><?= ucfirst($app['status'] ?? 'Pending') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No appointments found</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 10. BILLS & PAYMENTS - v5.0 -->
    <!-- ================================================================ -->
    <div class="pdf-section">
        <div class="pdf-section-title">
            <i class="fas fa-receipt"></i> BILLS & PAYMENTS (<?= $total_bills ?> bills)
        </div>
        
        <!-- ✅ BILL SUMMARY CARDS - v5.0 FIXED -->
        <div class="pdf-bill-summary">
            
            <!-- Subtotal Card -->
            <div class="pdf-bill-card subtotal-card">
                <span class="bill-label">📋 Subtotal</span>
                <span class="bill-value">TSh <?= number_format($total_subtotal, 0) ?></span>
                <span class="bill-sub">Bill amount</span>
            </div>
            
            <!-- Total Card -->
            <div class="pdf-bill-card total-card">
                <span class="bill-label">💰 Total Bills</span>
                <span class="bill-value">TSh <?= number_format($total_bill_amount, 0) ?></span>
                <span class="bill-sub"><?= $total_bills ?> bill<?= $total_bills != 1 ? 's' : '' ?></span>
            </div>
            
            <!-- ✅ Paid Card - FIXED: Inatumia paid_amount -->
            <div class="pdf-bill-card paid-card">
                <span class="bill-label">✅ Paid</span>
                <span class="bill-value">TSh <?= number_format($paid_bill_amount, 0) ?></span>
                <span class="bill-sub">
                    <?php 
                        $paid_percent = $total_bill_amount > 0 ? ($paid_bill_amount / $total_bill_amount) * 100 : 0;
                        echo number_format($paid_percent, 1) . '%';
                    ?>
                </span>
            </div>
            
            <!-- ✅ Pending Card - FIXED: Inatumia balance -->
            <div class="pdf-bill-card pending-card">
                <span class="bill-label">⏳ Pending</span>
                <span class="bill-value">TSh <?= number_format($pending_bill_amount, 0) ?></span>
                <span class="bill-sub">
                    <?php 
                        $pending_percent = $total_bill_amount > 0 ? ($pending_bill_amount / $total_bill_amount) * 100 : 0;
                        echo number_format($pending_percent, 1) . '%';
                    ?>
                </span>
            </div>
            
        </div>
        
        <!-- BILLS LIST -->
        <?php if (count($bills) > 0): ?>
            <?php foreach ($bills as $bill): 
                $items = $bill_items_data[$bill['id']] ?? [];
                $bill_total = (float)($bill['total_amount'] ?? 0);
                $bill_paid = (float)($bill['paid_amount'] ?? 0);
                $bill_balance = (float)($bill['balance'] ?? 0);
                $bill_status = $bill['status'] ?? 'pending';
                
                $status_colors = [
                    'paid' => ['bg' => '#D1FAE5', 'color' => '#059669'],
                    'partial' => ['bg' => '#DBEAFE', 'color' => '#2563EB'],
                    'pending' => ['bg' => '#FEF3C7', 'color' => '#D97706'],
                    'cancelled' => ['bg' => '#FEE2E2', 'color' => '#DC2626']
                ];
                $sclr = $status_colors[$bill_status] ?? $status_colors['pending'];
            ?>
            
            <div class="pdf-bill-block">
                <div class="pdf-bill-header">
                    <div>
                        <span class="bill-number"><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span>
                        <span class="bill-status" style="background:<?= $sclr['bg'] ?>;color:<?= $sclr['color'] ?>;margin-left:8px;">
                            <?= ucfirst($bill_status) ?>
                        </span>
                        <span style="margin-left:8px;font-size:7.5pt;opacity:0.9;">
                            <i class="fas fa-calendar-alt"></i> <?= formatDateShort($bill['created_at'] ?? '') ?>
                        </span>
                    </div>
                    <div class="bill-totals">
                        <span>Total: <strong>TSh <?= number_format($bill_total, 0) ?></strong></span>
                        <span>Paid: <strong style="color:#6EE7B7;">TSh <?= number_format($bill_paid, 0) ?></strong></span>
                        <span>Balance: <strong style="color:<?= $bill_balance > 0 ? '#FCA5A5' : '#6EE7B7' ?>;">TSh <?= number_format($bill_balance, 0) ?></strong></span>
                    </div>
                </div>
                
                <table class="pdf-bill-items">
                    <thead>
                        <tr>
                            <th style="width:30px;text-align:center;">#</th>
                            <th>Item Name</th>
                            <th style="width:80px;text-align:center;">Type</th>
                            <th style="width:40px;text-align:center;">Qty</th>
                            <th style="width:90px;text-align:right;">Unit Price</th>
                            <th style="width:100px;text-align:right;">Total</th>
                            <th style="width:70px;text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php 
                            $row_num = 1;
                            $items_subtotal = 0;
                            foreach ($items as $item): 
                                $item_price = (float)($item['total_price'] ?? 0);
                                $item_unit = (float)($item['unit_price'] ?? 0);
                                $item_qty = (int)($item['quantity'] ?? 1);
                                $item_status = $item['status'] ?? 'pending';
                                $item_type = $item['item_type'] ?? 'item';
                                $items_subtotal += $item_price;
                                
                                if ($item_unit <= 0 && $item_qty > 0) {
                                    $item_unit = $item_price / $item_qty;
                                }
                                
                                $item_status_color = [
                                    'paid' => 'color:#059669;font-weight:700;',
                                    'partial' => 'color:#2563EB;font-weight:700;',
                                    'pending' => 'color:#D97706;font-weight:700;',
                                    'cancelled' => 'color:#DC2626;font-weight:700;'
                                ][$item_status] ?? 'color:#64748B;';
                            ?>
                            <tr>
                                <td style="text-align:center;color:#64748B;"><?= $row_num++ ?></td>
                                <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;font-size:7pt;"><?= ucfirst(str_replace('_', ' ', $item_type)) ?></td>
                                <td style="text-align:center;font-weight:700;"><?= $item_qty ?></td>
                                <td style="text-align:right;font-family:monospace;">TSh <?= number_format($item_unit, 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:700;">TSh <?= number_format($item_price, 0) ?></td>
                                <td style="text-align:center;<?= $item_status_color ?>"><?= ucfirst($item_status) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Subtotal Row -->
                            <tr class="subtotal-row">
                                <td colspan="5" style="text-align:right;">SUBTOTAL:</td>
                                <td style="text-align:right;font-family:monospace;">TSh <?= number_format($items_subtotal, 0) ?></td>
                                <td></td>
                            </tr>
                            
                            <!-- Discount Row -->
                            <?php 
                            $pharm_disc = (float)($bill['pharmacy_discount'] ?? 0);
                            $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
                            $total_disc = $pharm_disc + $cashier_disc;
                            if ($total_disc > 0): 
                            ?>
                            <tr class="discount-row">
                                <td colspan="5" style="text-align:right;">
                                    DISCOUNT:
                                    <?php if ($pharm_disc > 0): ?>(Pharm: TSh <?= number_format($pharm_disc, 0) ?>)<?php endif; ?>
                                    <?php if ($cashier_disc > 0): ?>(Cashier: TSh <?= number_format($cashier_disc, 0) ?>)<?php endif; ?>
                                </td>
                                <td style="text-align:right;font-family:monospace;">- TSh <?= number_format($total_disc, 0) ?></td>
                                <td></td>
                            </tr>
                            <?php endif; ?>
                            
                            <!-- Premium Row -->
                            <?php 
                            $pharm_prem = (float)($bill['pharmacy_premium'] ?? 0);
                            $cashier_prem = (float)($bill['cashier_premium'] ?? 0);
                            $total_prem = $pharm_prem + $cashier_prem;
                            if ($total_prem > 0): 
                            ?>
                            <tr class="premium-row">
                                <td colspan="5" style="text-align:right;">
                                    PREMIUM:
                                    <?php if ($pharm_prem > 0): ?>(Pharm: TSh <?= number_format($pharm_prem, 0) ?>)<?php endif; ?>
                                    <?php if ($cashier_prem > 0): ?>(Cashier: TSh <?= number_format($cashier_prem, 0) ?>)<?php endif; ?>
                                </td>
                                <td style="text-align:right;font-family:monospace;">+ TSh <?= number_format($total_prem, 0) ?></td>
                                <td></td>
                            </tr>
                            <?php endif; ?>
                            
                            <!-- Total Row -->
                            <tr class="total-row">
                                <td colspan="5" style="text-align:right;">TOTAL:</td>
                                <td style="text-align:right;font-family:monospace;">TSh <?= number_format($bill_total, 0) ?></td>
                                <td></td>
                            </tr>
                            
                            <!-- Paid Row -->
                            <tr class="paid-row">
                                <td colspan="5" style="text-align:right;">PAID:</td>
                                <td style="text-align:right;font-family:monospace;">TSh <?= number_format($bill_paid, 0) ?></td>
                                <td></td>
                            </tr>
                            
                            <!-- Balance Row -->
                            <tr class="balance-row <?= $bill_balance > 0 ? '' : 'no-balance' ?>">
                                <td colspan="5" style="text-align:right;">BALANCE:</td>
                                <td style="text-align:right;font-family:monospace;">TSh <?= number_format($bill_balance, 0) ?></td>
                                <td></td>
                            </tr>
                            
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:10px;color:#94A3B8;font-style:italic;">
                                    No items in this bill
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php endforeach; ?>
        <?php else: ?>
            <div class="pdf-table"><div class="empty-cell">No bills found</div></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="pdf-footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Patient Medical Record v5.0
            <span style="margin:0 8px;">|</span>
            Generated: <?= date('M d, Y H:i:s A') ?>
        </p>
        <p style="margin-top:4px;">
            This is a computer-generated document. No signature is required.
        </p>
        <p style="margin-top:4px;">
            &copy; <?= date('Y') ?> Braick Dispensary. All rights reserved.
        </p>
    </div>

</div>

<!-- ================================================================ -->
<!-- AUTO-PRINT (Optional) -->
<!-- ================================================================ -->
<script>
    // ✅ Auto-print kama URL ina ?auto_print=1
    window.addEventListener('DOMContentLoaded', function() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            setTimeout(function() {
                window.print();
            }, 800);
        }
    });
    
    // ✅ Keyboard shortcut: Ctrl+P inafanya kazi naturally
    console.log('%c🏥 Braick Dispensary - Patient PDF v5.0', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ FIXED: Paid card inatumia paid_amount column', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Pending card inatumia balance column', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Bills: <?= $total_bills ?> | Subtotal: TSh <?= number_format($total_subtotal, 0) ?> | Total: TSh <?= number_format($total_bill_amount, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Paid: TSh <?= number_format($paid_bill_amount, 0) ?> | Pending: TSh <?= number_format($pending_bill_amount, 0) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>