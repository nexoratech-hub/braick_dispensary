<?php
// ================================================================
// FILE: frontend/pages/admin/export_patient_pdf.php
// ADMIN - EXPORT PATIENT PDF
// ✅ A4 Preview (210mm x 297mm) - sio full screen
// ✅ Print/Close buttons (zinaonekana screen tu)
// ✅ Syntax errors zote fixed
// ✅ 7 Vital Signs + SpO2
// ✅ Bills Cards 3 tu
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
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
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// PARAMETERS
// ================================================================
$patient_id = 0;
if (isset($_GET['patient_id']) && (int)$_GET['patient_id'] > 0) {
    $patient_id = (int)$_GET['patient_id'];
} elseif (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $patient_id = (int)$_GET['id'];
}

if ($patient_id <= 0) {
    die('Invalid patient ID. Please provide ?id=X or ?patient_id=X');
}

// ================================================================
// CHECK SpO2 COLUMN
// ================================================================
$has_oxygen_column = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM vital_signs LIKE 'oxygen_saturation'");
    $has_oxygen_column = $stmt->rowCount() > 0;
} catch (Exception $e) {}

// ================================================================
// GET BRANCH INFO
// ================================================================
$branch_location = '';
$branch_phone = '';
$branch_email = '';
try {
    $stmt = $db->prepare("SELECT name, location, phone, email FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $user_branch_name = $branch['name'] ?? $user_branch_name;
        $branch_location = $branch['location'] ?? '';
        $branch_phone = $branch['phone'] ?? '';
        $branch_email = $branch['email'] ?? '';
    }
} catch (Exception $e) {}

// ================================================================
// LOGO BASE64
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_base64 = '';
$logo_absolute = $_SERVER['DOCUMENT_ROOT'] . $logo_path;
if (file_exists($logo_absolute)) {
    $logo_data = file_get_contents($logo_absolute);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// FETCH PATIENT
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as receptionist_name, b.name as branch_name,
           doc.full_name as assigned_doctor_name
    FROM patients p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN users doc ON p.assigned_doctor_id = doc.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient_data) {
    die('Patient not found (ID: ' . $patient_id . ')');
}

// ================================================================
// FETCH VISITS WITH ALL DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$patient_id]);
$patient_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// BILLS SUMMARY
// ================================================================
$bills_total = 0;
$bills_discount = 0;
$bills_paid = 0;
$total_bills_count = 0;

foreach ($patient_visits as &$visit) {
    $visit_id = $visit['id'];

    // VITAL SIGNS
    $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $visit['vital_signs'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // LAB TESTS
    $stmt = $db->prepare("SELECT * FROM lab_tests WHERE visit_id = ? ORDER BY created_at DESC");
    $stmt->execute([$visit_id]);
    $visit['lab_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PRESCRIPTIONS
    $stmt = $db->prepare("
        SELECT 
            p.id as prescription_id,
            p.prescription_number,
            p.status as prescription_status,
            p.created_at as prescription_date,
            pi.id as item_id,
            pi.medication_name,
            pi.dosage,
            pi.frequency,
            pi.duration,
            pi.quantity,
            pi.instructions as item_instructions
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC, pi.id ASC
    ");
    $stmt->execute([$visit_id]);
    $visit['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PROCEDURES + EQUIPMENT
    $stmt = $db->prepare("
        SELECT bi.*, b.bill_number
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.visit_id = ?
        AND bi.item_type IN ('procedure', 'tool', 'equipment')
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $visit['procedures_equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // BILLS
    $stmt = $db->prepare("SELECT * FROM bills WHERE visit_id = ? ORDER BY created_at DESC");
    $stmt->execute([$visit_id]);
    $visit['bills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($visit);

foreach ($patient_visits as $visit) {
    foreach ($visit['bills'] as $bill) {
        $total_bills_count++;
        $bills_total += $bill['total_amount'] ?? 0;
        $bills_discount += ($bill['pharmacy_discount'] ?? 0) + ($bill['cashier_discount'] ?? 0) + ($bill['discount_amount'] ?? 0);
        $bills_paid += $bill['paid_amount'] ?? 0;
    }
}

// ================================================================
// COUNTS
// ================================================================
$total_visits = count($patient_visits);
$total_bills = $total_bills_count;
$total_prescriptions = 0;
$total_lab_tests = 0;
$total_vital_signs = 0;

foreach ($patient_visits as $v) {
    $total_prescriptions += count($v['prescriptions'] ?? []);
    $total_lab_tests += count($v['lab_tests'] ?? []);
    if (!empty($v['vital_signs'])) $total_vital_signs++;
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pending', 'paid' => 'Paid', 'partial' => 'Partial',
        'cancelled' => 'Cancelled', 'completed' => 'Completed',
        'confirmed' => 'Confirmed', 'dispensed' => 'Dispensed',
        'in_progress' => 'In Progress', 'scheduled' => 'Scheduled',
        'assigned' => 'Assigned', 'with_doctor' => 'With Doctor',
        'lab_test' => 'Lab Test', 'lab_completed' => 'Lab Completed',
        'prescribed' => 'Prescribed', 'active' => 'Active', 'inactive' => 'Inactive'
    ];
    return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
}

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
           PAGE SETUP - A4
           ================================================================ */
        @page {
            size: A4;
            margin: 10mm 8mm 12mm 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ================================================================
           BODY - GRAY BACKGROUND (kama PDF viewer)
           ================================================================ */
        body {
            font-family: Arial, 'Segoe UI', Helvetica, sans-serif;
            font-size: 10px;
            color: #1E293B;
            line-height: 1.5;
            background: #525659;
            padding: 20px 10px 80px 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            min-height: 100vh;
        }

        /* ================================================================
           ACTION BAR - FIXED AT TOP (zinaonekana screen tu)
           ================================================================ */
        .action-bar {
            position: fixed;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            gap: 10px;
            background: rgba(255, 255, 255, 0.98);
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-print-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
        }

        .btn-primary-action {
            background: #0B5ED7;
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.35);
        }

        .btn-primary-action:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.45);
        }

        .btn-secondary-action {
            background: #FFFFFF;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
        }

        .btn-secondary-action:hover {
            background: #F8FAFC;
            border-color: #0B5ED7;
            color: #0B5ED7;
            transform: translateY(-2px);
        }

        /* ================================================================
           A4 PAPER - Kama karatasi halisi
           ================================================================ */
        .a4-paper {
            width: 210mm;
            min-height: 297mm;
            margin: 60px auto 30px auto;
            padding: 12mm 10mm;
            background: white;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
            border-radius: 2px;
            position: relative;
            overflow: hidden;
        }

        .report-container {
            max-width: 100%;
        }

        /* ================================================================
           HEADER
           ================================================================ */
        .header {
            text-align: center;
            border-bottom: 3px solid #0B5ED7;
            padding-bottom: 12px;
            margin-bottom: 14px;
            position: relative;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .header .logo-img {
            max-height: 55px;
            margin-bottom: 6px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .header .logo-title {
            font-size: 22px;
            font-weight: 700;
            color: #0B5ED7;
            letter-spacing: 1px;
        }

        .header .logo-sub {
            font-size: 11px;
            color: #64748B;
            margin-top: 2px;
            font-style: italic;
        }

        .header .branch-info {
            font-size: 9px;
            color: #64748B;
            margin-top: 4px;
        }

        .header .report-number {
            font-size: 10px;
            color: #64748B;
            font-weight: 600;
            position: absolute;
            right: 0;
            top: 2px;
        }

        /* PAGE TITLE */
        .page-title {
            font-size: 15px;
            font-weight: 700;
            color: #0B5ED7;
            text-align: center;
            margin: 4px 0 2px 0;
            page-break-after: avoid;
            break-after: avoid;
        }

        .page-subtitle {
            font-size: 10px;
            color: #64748B;
            text-align: center;
            margin-bottom: 10px;
            page-break-after: avoid;
            break-after: avoid;
        }

        /* SECTION TITLES */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 4px;
            margin: 14px 0 10px 0;
            page-break-after: avoid;
            break-after: avoid;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* GRIDS */
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row-3col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .row-4col { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; }

        /* INFO CARDS */
        .info-card {
            background: #F8FAFC;
            border-radius: 4px;
            padding: 8px 12px;
            border: 1px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .info-card .label {
            font-size: 7px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }

        .info-card .value {
            font-size: 10px;
            font-weight: 600;
            color: #1E293B;
            display: block;
            margin-top: 1px;
        }

        .info-card.blue { border-left: 3px solid #0B5ED7; }
        .info-card.green { border-left: 3px solid #059669; }
        .info-card.purple { border-left: 3px solid #7C3AED; }
        .info-card.orange { border-left: 3px solid #D97706; }
        .info-card.red { border-left: 3px solid #DC2626; }
        .info-card.sky { border-left: 3px solid #0EA5E9; }

        /* BILLS CARDS */
        .bills-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .bills-card {
            background: #F8FAFC;
            border-radius: 6px;
            padding: 12px 14px;
            text-align: center;
            border: 2px solid #E2E8F0;
            border-top-width: 4px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .bills-card.total { background: #EFF6FF; border-color: #93C5FD; border-top-color: #0B5ED7; }
        .bills-card.discount { background: #FFFBEB; border-color: #FCD34D; border-top-color: #D97706; }
        .bills-card.paid { background: #F0FDF4; border-color: #86EFAC; border-top-color: #059669; }

        .bills-card .bc-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            margin: 0 auto 6px auto;
            font-weight: 700;
        }

        .bills-card.total .bc-icon { background: #0B5ED7; }
        .bills-card.discount .bc-icon { background: #D97706; }
        .bills-card.paid .bc-icon { background: #059669; }

        .bills-card .bc-label {
            font-size: 8px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: block;
            margin-bottom: 3px;
        }

        .bills-card .bc-value {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.1;
            display: block;
        }

        .bills-card.total .bc-value { color: #0A4CA8; }
        .bills-card.discount .bc-value { color: #92400E; }
        .bills-card.paid .bc-value { color: #065F46; }

        .bills-card .bc-sub {
            font-size: 7px;
            color: #94A3B8;
            font-weight: 600;
            margin-top: 4px;
            display: block;
        }

        /* VISIT CARD */
        .visit-card {
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            margin-bottom: 14px;
            overflow: hidden;
            background: white;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .visit-header {
            background: linear-gradient(90deg, #EFF6FF, #DBEAFE);
            padding: 8px 12px;
            border-bottom: 2px solid #93C5FD;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .visit-header .number {
            font-weight: 700;
            font-size: 11px;
            color: #0A4CA8;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .visit-header .meta {
            font-size: 9px;
            color: #64748B;
            font-weight: 600;
        }

        .visit-body {
            padding: 10px 12px;
        }

        /* VITAL SIGNS */
        .vital-signs-wrapper {
            background: #F0FDF4;
            border: 1.5px solid #86EFAC;
            border-radius: 6px;
            padding: 10px 12px;
            margin: 8px 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .vital-signs-wrapper .vital-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #86EFAC;
            flex-wrap: wrap;
            gap: 6px;
        }

        .vital-signs-wrapper .vital-header-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            color: #059669;
            text-transform: uppercase;
        }

        .vital-signs-wrapper .vital-header-time {
            font-size: 8px;
            color: #64748B;
            font-weight: 600;
        }

        .vitals-grid-7 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }

        .vital-card {
            background: white;
            border-radius: 5px;
            padding: 6px 4px;
            text-align: center;
            border: 1.5px solid #E2E8F0;
            border-top-width: 3px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .vital-card.temp { border-top-color: #DC2626; border-color: #FCA5A5; border-top-width: 3px; }
        .vital-card.bp { border-top-color: #059669; border-color: #86EFAC; border-top-width: 3px; }
        .vital-card.pulse { border-top-color: #7C3AED; border-color: #C4B5FD; border-top-width: 3px; }
        .vital-card.weight { border-top-color: #D97706; border-color: #FCD34D; border-top-width: 3px; }
        .vital-card.height { border-top-color: #0D9488; border-color: #5EEAD4; border-top-width: 3px; }
        .vital-card.bmi { border-top-color: #DB2777; border-color: #F9A8D4; border-top-width: 3px; }

        .vital-card.spo2 {
            border-top-color: #0891B2;
            border-color: #67E8F9;
            border-top-width: 3px;
            background: #ECFEFF;
        }
        .vital-card.spo2.spo2-normal { border-top-color: #059669; border-color: #86EFAC; background: #F0FDF4; }
        .vital-card.spo2.spo2-normal .vital-value { color: #059669; }
        .vital-card.spo2.spo2-low { border-top-color: #D97706; border-color: #FCD34D; background: #FFFBEB; }
        .vital-card.spo2.spo2-low .vital-value { color: #D97706; }
        .vital-card.spo2.spo2-critical { border-top-color: #DC2626; border-color: #FCA5A5; background: #FEF2F2; }
        .vital-card.spo2.spo2-critical .vital-value { color: #DC2626; }
        .vital-card.spo2.spo2-na { border-top-color: #64748B; border-color: #CBD5E1; background: #F8FAFC; }
        .vital-card.spo2.spo2-na .vital-value { color: #64748B; }

        .vital-card .vital-label {
            font-size: 7px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 700;
            display: block;
            margin-bottom: 3px;
        }

        .vital-card .vital-value {
            font-size: 13px;
            font-weight: 800;
            color: #1E293B;
            display: block;
            line-height: 1.1;
        }

        .vital-card .vital-unit {
            font-size: 7px;
            color: #94A3B8;
            font-weight: 500;
            margin-left: 1px;
        }

        .vital-card .vital-status {
            font-size: 7px;
            font-weight: 700;
            display: inline-block;
            padding: 1px 5px;
            border-radius: 6px;
            margin-top: 2px;
            color: white;
        }

        .vital-card.spo2-normal .vital-status { background: #059669; }
        .vital-card.spo2-low .vital-status { background: #D97706; }
        .vital-card.spo2-critical .vital-status { background: #DC2626; }
        .vital-card.spo2-na .vital-status { background: #64748B; }

        /* SUBSECTION HEADERS */
        .subsection-header {
            background: #EFF6FF;
            border-left: 3px solid #0B5ED7;
            border-radius: 0 4px 4px 0;
            padding: 5px 10px;
            margin: 10px 0 6px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            flex-wrap: wrap;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: avoid;
            break-after: avoid;
        }

        .subsection-header .subsection-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            color: #0A4CA8;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .subsection-header .subsection-count {
            background: #0B5ED7;
            color: white;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: 700;
        }

        /* DIAGNOSIS BOX */
        .diagnosis-box {
            background: #EFF6FF;
            padding: 8px 12px;
            border-left: 4px solid #0B5ED7;
            border-radius: 4px;
            margin: 6px 0;
            border: 1px solid #93C5FD;
            border-left-width: 4px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .diagnosis-box .label {
            font-weight: 700;
            font-size: 8px;
            color: #0A4CA8;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }

        .diagnosis-box .text {
            font-weight: 700;
            font-size: 11px;
            color: #1E293B;
        }

        /* COMPLAINT BOX */
        .complaint-box {
            background: #F8FAFC;
            padding: 5px 10px;
            border: 1px dashed #CBD5E1;
            margin: 4px 0;
            border-radius: 4px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .complaint-box .label {
            font-weight: 700;
            font-size: 7px;
            color: #64748B;
            text-transform: uppercase;
            display: block;
            margin-bottom: 1px;
        }

        .complaint-box .text {
            font-size: 9px;
            color: #1E293B;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: 700;
            color: white;
        }

        .badge-success { background: #059669; }
        .badge-warning { background: #D97706; }
        .badge-danger { background: #DC2626; }
        .badge-info { background: #0B5ED7; }
        .badge-purple { background: #7C3AED; }
        .badge-secondary { background: #64748B; }
        .badge-sky { background: #0284C7; }

        /* TABLES */
        .table-wrap {
            overflow-x: auto;
            margin-bottom: 8px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-top: 4px;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table thead th {
            background: #0B5ED7;
            color: white;
            font-weight: 700;
            padding: 5px 8px;
            text-align: left;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .data-table thead th.spo2-header {
            background: #0284C7;
        }

        .data-table tbody tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .data-table tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid #E2E8F0;
            color: #1E293B;
            vertical-align: middle;
            font-size: 8px;
        }

        .data-table tbody td.spo2-value {
            background: #E0F2FE;
            font-weight: 700;
            color: #0284C7;
        }

        .data-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }

        /* FOOTER */
        .footer-section {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .footer-left {
            flex: 1;
            min-width: 200px;
        }

        .footer-left .doctor-name {
            font-size: 10px;
            font-weight: 700;
            color: #1E293B;
        }

        .footer-left .doctor-details {
            font-size: 8px;
            color: #64748B;
            margin-top: 1px;
        }

        .signature-area {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }

        .signature-area .sig-item {
            text-align: center;
        }

        .signature-area .sig-line {
            width: 80px;
            border-bottom: 1px solid #1E293B;
            height: 16px;
            margin: 0 auto 1px auto;
        }

        .signature-area .sig-label {
            font-size: 7px;
            color: #64748B;
        }

        .stamp-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            min-width: 150px;
        }

        .stamp {
            border: 2px solid #0B5ED7;
            border-radius: 4px;
            padding: 6px 14px;
            text-align: center;
            background: #F8FAFC;
            width: 140px;
        }

        .stamp .stamp-title {
            font-size: 7px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stamp .stamp-name {
            font-size: 12px;
            font-weight: 700;
            color: #0B5ED7;
            margin-top: 1px;
        }

        .stamp .stamp-line {
            border-top: 1px dashed #CBD5E1;
            margin: 3px 0;
        }

        .stamp .stamp-doctor {
            font-size: 8px;
            color: #0B5ED7;
            font-weight: 600;
        }

        .stamp .stamp-signature {
            font-size: 8px;
            color: #1E293B;
        }

        .stamp .stamp-date {
            font-size: 7px;
            color: #94A3B8;
            margin-top: 1px;
        }

        .footer-note {
            text-align: center;
            font-size: 7px;
            color: #94A3B8;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .footer-note .brand {
            color: #0B5ED7;
            font-weight: 700;
        }

        .footer-note .slogan {
            font-size: 8px;
            color: #0B5ED7;
            font-weight: 700;
            margin-top: 2px;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .a4-paper {
                width: 100%;
                max-width: 210mm;
                padding: 15px;
            }
            .row-2col, .row-3col, .row-4col { grid-template-columns: 1fr 1fr; }
            .vitals-grid-7 { grid-template-columns: repeat(2, 1fr); }
            .bills-cards-grid { grid-template-columns: 1fr; }
            .footer-section { flex-direction: column; }
            .stamp-container { justify-content: flex-start; }
        }

        /* ================================================================
           PRINT - HIDE SCREEN-ONLY ELEMENTS
           ================================================================ */
        @media print {
            @page {
                size: A4;
                margin: 10mm 8mm 12mm 8mm;
            }

            body {
                background: white !important;
                padding: 0 !important;
                min-height: auto !important;
            }

            /* Hide action bar on print */
            .action-bar,
            .no-print {
                display: none !important;
            }

            /* A4 paper becomes plain white on print */
            .a4-paper {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            /* Page break control */
            .visit-card,
            .vital-signs-wrapper,
            .bills-card,
            .bills-cards-grid,
            .footer-section,
            .diagnosis-box,
            .complaint-box,
            .info-card,
            .subsection-header,
            .data-table tbody tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                -webkit-column-break-inside: avoid !important;
            }

            .section-title,
            .page-title,
            .page-subtitle {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            .data-table thead {
                display: table-header-group;
            }

            .header {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            /* Ensure colors print */
            .data-table thead th,
            .data-table thead th.spo2-header,
            .data-table tbody td.spo2-value,
            .badge,
            .bills-card,
            .bills-card.total,
            .bills-card.discount,
            .bills-card.paid,
            .info-card,
            .info-card.blue,
            .info-card.green,
            .info-card.purple,
            .info-card.orange,
            .info-card.red,
            .info-card.sky,
            .vital-card,
            .vital-card.spo2.spo2-normal,
            .vital-card.spo2.spo2-low,
            .vital-card.spo2.spo2-critical,
            .vital-card.spo2.spo2-na,
            .vital-signs-wrapper,
            .subsection-header,
            .diagnosis-box,
            .complaint-box,
            .stamp {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            p, li {
                orphans: 3;
                widows: 3;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ACTION BAR - FIXED AT TOP (zinaonekana screen tu) -->
<!-- ================================================================ -->
<div class="action-bar no-print">
    <button onclick="window.print()" class="btn-print-action btn-primary-action">
        <i class="fas fa-file-pdf"></i> Save as PDF / Print
    </button>
    <button onclick="window.close()" class="btn-print-action btn-secondary-action">
        <i class="fas fa-times"></i> Close
    </button>
</div>

<!-- ================================================================ -->
<!-- A4 PAPER - Kama karatasi halisi -->
<!-- ================================================================ -->
<div class="a4-paper">
    <div class="report-container">

        <!-- HEADER -->
        <div class="header">
            <?php if ($logo_base64) { ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Dispensary" class="logo-img">
            <?php } ?>
            <div class="logo-title">BRAICK DISPENSARY</div>
            <div class="logo-sub">Tunajali Afya Yako</div>
            <div class="branch-info">
                <?= htmlspecialchars($user_branch_name) ?>
                <?php if (!empty($branch_location)) { echo ' | ' . htmlspecialchars($branch_location); } ?>
                <?php if (!empty($branch_phone)) { echo ' | Tel: ' . htmlspecialchars($branch_phone); } ?>
                <?php if (!empty($branch_email)) { echo ' | Email: ' . htmlspecialchars($branch_email); } ?>
            </div>
            <div class="report-number">Report #: PAT-<?= str_pad($patient_id, 4, '0', STR_PAD_LEFT) ?></div>
        </div>

        <!-- PAGE TITLE -->
        <div class="page-title">PATIENT MEDICAL REPORT</div>
        <div class="page-subtitle">Complete Patient Health Summary</div>

        <!-- BILLS CARDS -->
        <div class="bills-cards-grid">
            <div class="bills-card total">
                <div class="bc-icon">💰</div>
                <span class="bc-label">Total Amount</span>
                <span class="bc-value">TSh <?= number_format($bills_total, 0) ?></span>
                <span class="bc-sub"><?= number_format($total_bills_count) ?> bills</span>
            </div>

            <div class="bills-card discount">
                <div class="bc-icon">%</div>
                <span class="bc-label">Total Discount</span>
                <span class="bc-value">TSh <?= number_format($bills_discount, 0) ?></span>
                <span class="bc-sub">Saved amount</span>
            </div>

            <div class="bills-card paid">
                <div class="bc-icon">✓</div>
                <span class="bc-label">Total Paid</span>
                <span class="bc-value">TSh <?= number_format($bills_paid, 0) ?></span>
                <span class="bc-sub">Amount alizo lipia</span>
            </div>
        </div>

        <!-- 1. PATIENT INFORMATION -->
        <div class="section-title">1. Patient Information</div>
        <div class="row-2col">
            <div>
                <div class="info-card blue">
                    <span class="label">Full Name</span>
                    <span class="value"><?= htmlspecialchars($patient_data['full_name']) ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Patient ID</span>
                    <span class="value" style="font-family:monospace;"><?= htmlspecialchars($patient_data['patient_id']) ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Gender</span>
                    <span class="value"><?= htmlspecialchars($patient_data['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?= !empty($patient_data['date_of_birth']) ? date('d M Y', strtotime($patient_data['date_of_birth'])) : 'N/A' ?></span>
                </div>
            </div>
            <div>
                <div class="info-card green">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($patient_data['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Blood Group</span>
                    <span class="value"><?= htmlspecialchars($patient_data['blood_group'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Address</span>
                    <span class="value" style="font-weight:400;font-size:9px;"><?= htmlspecialchars($patient_data['address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card red" style="margin-top:4px;">
                    <span class="label">Allergies</span>
                    <span class="value" style="color:#DC2626;"><?= htmlspecialchars($patient_data['allergies'] ?? 'None reported') ?></span>
                </div>
            </div>
        </div>

        <!-- 2. VISIT HISTORY -->
        <div class="section-title">2. Visit History & Medical Records (<?= $total_visits ?> visits)</div>

        <?php if (count($patient_visits) > 0) { ?>
            <?php foreach ($patient_visits as $visit) { 
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
                        📋 <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                        <span class="badge <?= $status_badge ?>"><?= getStatusLabel($visit['status'] ?? 'pending') ?></span>
                        <?php if ($has_diagnosis) { ?>
                            <span class="badge badge-purple">🩺 Diagnosed</span>
                        <?php } ?>
                    </span>
                    <span class="meta">
                        <?= date('d M Y h:i A', strtotime($visit['visit_date'] ?? $visit['created_at'])) ?>
                        &nbsp; | &nbsp;#<?= $visit['id'] ?>
                    </span>
                </div>

                <div class="visit-body">

                    <div class="row-2col">
                        <div class="info-card blue">
                            <span class="label">Doctor</span>
                            <span class="value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-card">
                            <span class="label">Visit Type</span>
                            <span class="value"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <?php if ($has_symptoms) { ?>
                    <div class="complaint-box" style="margin-top:6px;">
                        <span class="label">🩺 Symptoms</span>
                        <div class="text"><?= htmlspecialchars($visit['symptoms']) ?></div>
                    </div>
                    <?php } ?>

                    <?php if ($has_complaint) { ?>
                    <div class="complaint-box">
                        <span class="label">❓ Reason for Visit</span>
                        <div class="text"><?= htmlspecialchars($visit['complaint']) ?></div>
                    </div>
                    <?php } ?>

                    <!-- 3. VITAL SIGNS -->
                    <?php 
                    $vs = isset($visit['vital_signs']) ? $visit['vital_signs'] : null;
                    $has_any_vital = false;
                    
                    if ($vs) {
                        $has_any_vital = !empty($vs['temperature']) || !empty($vs['blood_pressure_systolic']) ||
                                         !empty($vs['pulse_rate']) || !empty($vs['weight']) ||
                                         !empty($vs['height']) || !empty($vs['bmi']) ||
                                         ($has_oxygen_column && !empty($vs['oxygen_saturation']));
                    }

                    if ($has_any_vital) {
                        $spo2_value = null;
                        if ($has_oxygen_column && isset($vs['oxygen_saturation']) && $vs['oxygen_saturation'] !== null && $vs['oxygen_saturation'] !== '') {
                            $spo2_value = (int)$vs['oxygen_saturation'];
                        }
                        
                        $spo2_class = 'spo2-na';
                        $spo2_label = 'N/A';
                        
                        if ($spo2_value !== null) {
                            if ($spo2_value < 90) { $spo2_class = 'spo2-critical'; $spo2_label = 'CRITICAL'; }
                            elseif ($spo2_value < 95) { $spo2_class = 'spo2-low'; $spo2_label = 'LOW'; }
                            else { $spo2_class = 'spo2-normal'; $spo2_label = 'NORMAL'; }
                        }
                    ?>
                    <div class="subsection-header">
                        <div class="subsection-title">❤️ 3. Vital Signs (7 Measurements)</div>
                        <div class="subsection-count">7 Signs</div>
                    </div>

                    <div class="vital-signs-wrapper">
                        <?php if (!empty($vs['recorded_at'])) { ?>
                        <div class="vital-header">
                            <div class="vital-header-title">❤️ Vital Signs Recorded</div>
                            <div class="vital-header-time"><?= date('d M Y h:i A', strtotime($vs['recorded_at'])) ?></div>
                        </div>
                        <?php } ?>

                        <div class="vitals-grid-7">
                            <?php if (!empty($vs['temperature'])) { ?>
                            <div class="vital-card temp">
                                <span class="vital-label">🌡️ Temp</span>
                                <span class="vital-value"><?= htmlspecialchars($vs['temperature']) ?><span class="vital-unit">°C</span></span>
                            </div>
                            <?php } ?>

                            <?php if (!empty($vs['blood_pressure_systolic']) && !empty($vs['blood_pressure_diastolic'])) { ?>
                            <div class="vital-card bp">
                                <span class="vital-label">💓 BP</span>
                                <span class="vital-value"><?= htmlspecialchars($vs['blood_pressure_systolic']) ?>/<?= htmlspecialchars($vs['blood_pressure_diastolic']) ?><span class="vital-unit">mmHg</span></span>
                            </div>
                            <?php } ?>

                            <?php if (!empty($vs['pulse_rate'])) { ?>
                            <div class="vital-card pulse">
                                <span class="vital-label">❤️ Pulse</span>
                                <span class="vital-value"><?= htmlspecialchars($vs['pulse_rate']) ?><span class="vital-unit">bpm</span></span>
                            </div>
                            <?php } ?>

                            <?php if (!empty($vs['weight'])) { ?>
                            <div class="vital-card weight">
                                <span class="vital-label">⚖️ Weight</span>
                                <span class="vital-value"><?= htmlspecialchars($vs['weight']) ?><span class="vital-unit">kg</span></span>
                            </div>
                            <?php } ?>

                            <?php if (!empty($vs['height'])) { ?>
                            <div class="vital-card height">
                                <span class="vital-label">📏 Height</span>
                                <span class="vital-value"><?= htmlspecialchars($vs['height']) ?><span class="vital-unit">cm</span></span>
                            </div>
                            <?php } ?>

                            <?php if (!empty($vs['bmi'])) { ?>
                            <div class="vital-card bmi">
                                <span class="vital-label">📊 BMI</span>
                                <span class="vital-value"><?= htmlspecialchars($vs['bmi']) ?><span class="vital-unit">kg/m²</span></span>
                            </div>
                            <?php } ?>

                            <div class="vital-card spo2 <?= $spo2_class ?>">
                                <span class="vital-label">🫁 SpO2</span>
                                <span class="vital-value">
                                    <?= $spo2_value !== null ? $spo2_value : 'N/A' ?>
                                    <?php if ($spo2_value !== null) { ?><span class="vital-unit">%</span><?php } ?>
                                </span>
                                <span class="vital-status"><?= $spo2_label ?></span>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- 4. LAB TESTS -->
                    <?php if (!empty($visit['lab_tests'])) { ?>
                    <div class="subsection-header">
                        <div class="subsection-title">🧪 4. Lab Tests & Results</div>
                        <div class="subsection-count"><?= count($visit['lab_tests']) ?> Tests</div>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Result</th>
                                    <th>Reference Range</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visit['lab_tests'] as $test) {
                                    $badge_test = 'badge-info';
                                    if ($test['status'] === 'completed') $badge_test = 'badge-success';
                                    elseif ($test['status'] === 'pending') $badge_test = 'badge-warning';
                                    elseif ($test['status'] === 'cancelled') $badge_test = 'badge-danger';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($test['test_name']) ?></strong></td>
                                    <td style="color:#059669;font-weight:700;"><?= htmlspecialchars($test['results'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($test['reference_range'] ?? '-') ?></td>
                                    <td><span class="badge <?= $badge_test ?>"><?= getStatusLabel($test['status'] ?? 'pending') ?></span></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>

                    <!-- 5. DIAGNOSIS -->
                    <?php if ($has_diagnosis) { ?>
                    <div class="subsection-header">
                        <div class="subsection-title">🩺 5. Diagnosis / Impression</div>
                        <div class="subsection-count">Confirmed</div>
                    </div>

                    <div class="diagnosis-box">
                        <span class="label">✅ Doctor's Diagnosis</span>
                        <div class="text"><?= htmlspecialchars($visit['diagnosis']) ?></div>
                    </div>
                    <?php } ?>

                    <!-- 6. PRESCRIPTIONS -->
                    <?php if (!empty($visit['prescriptions'])) { ?>
                    <div class="subsection-header">
                        <div class="subsection-title">💊 6. Prescriptions</div>
                        <div class="subsection-count"><?= count($visit['prescriptions']) ?> Items</div>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Prescription #</th>
                                    <th>Medication</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Duration</th>
                                    <th style="text-align:right;">Qty</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visit['prescriptions'] as $p) { 
                                    $badge_presc = 'badge-info';
                                    if (($p['prescription_status'] ?? '') === 'dispensed') $badge_presc = 'badge-success';
                                    elseif (($p['prescription_status'] ?? '') === 'pending') $badge_presc = 'badge-warning';
                                    elseif (($p['prescription_status'] ?? '') === 'cancelled') $badge_presc = 'badge-danger';
                                ?>
                                <tr>
                                    <td style="font-family:monospace;font-size:7px;color:#7C3AED;font-weight:700;">
                                        <?= htmlspecialchars($p['prescription_number'] ?? 'N/A') ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($p['medication_name'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars($p['dosage'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['frequency'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['duration'] ?? '-') ?></td>
                                    <td style="text-align:right;font-weight:700;"><?= (int)($p['quantity'] ?? 0) ?></td>
                                    <td><span class="badge <?= $badge_presc ?>"><?= getStatusLabel($p['prescription_status'] ?? 'pending') ?></span></td>
                                </tr>
                                <?php if (!empty($p['item_instructions'])) { ?>
                                <tr>
                                    <td colspan="7" style="background:#FEF9C3;font-size:8px;color:#92400E;padding:3px 8px;">
                                        📌 <strong>Instructions:</strong> <?= htmlspecialchars($p['item_instructions']) ?>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>

                    <!-- 7. PROCEDURES + EQUIPMENT -->
                    <?php if (!empty($visit['procedures_equipment'])) { ?>
                    <div class="subsection-header">
                        <div class="subsection-title">💉 7. Procedures & Medical Equipment</div>
                        <div class="subsection-count"><?= count($visit['procedures_equipment']) ?> Items</div>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th style="text-align:right;">Qty</th>
                                    <th style="text-align:right;">Total</th>
                                    <th>Bill #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visit['procedures_equipment'] as $item) {
                                    $type_label = 'Procedure';
                                    $type_class = 'badge-warning';
                                    if ($item['item_type'] === 'tool' || $item['item_type'] === 'equipment') {
                                        $type_label = 'Equipment';
                                        $type_class = 'badge-purple';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                                    <td><span class="badge <?= $type_class ?>"><?= $type_label ?></span></td>
                                    <td style="font-size:8px;color:#64748B;"><?= htmlspecialchars($item['description'] ?? '-') ?></td>
                                    <td style="text-align:right;"><?= (int)($item['quantity'] ?? 1) ?></td>
                                    <td style="text-align:right;font-weight:700;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                    <td style="font-family:monospace;font-size:7px;"><?= htmlspecialchars($item['bill_number'] ?? '-') ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>

                </div>
            </div>
            <?php } ?>
        <?php } else { ?>
            <div style="text-align:center;padding:20px;color:#94A3B8;font-style:italic;">
                No visits found for this patient
            </div>
        <?php } ?>

        <!-- FOOTER WITH STAMP -->
        <div class="footer-section">
            <div class="footer-left">
                <div class="doctor-name">Dr. <?= htmlspecialchars($user_full_name) ?></div>
                <div class="doctor-details">
                    <?= htmlspecialchars($patient_data['assigned_doctor_name'] ?? 'Medical Doctor') ?><br>
                    <?php if (!empty($branch_phone)) { ?>
                        Tel: <?= htmlspecialchars($branch_phone) ?>
                    <?php } ?>
                    <?php if (!empty($branch_email)) { ?>
                        | Email: <?= htmlspecialchars($branch_email) ?>
                    <?php } ?>
                </div>
                
                <div class="signature-area">
                    <div class="sig-item">
                        <div class="sig-line"></div>
                        <span class="sig-label">Doctor's Signature</span>
                    </div>
                    <div class="sig-item">
                        <div class="sig-line"></div>
                        <span class="sig-label">Date</span>
                    </div>
                </div>
            </div>
            
            <div class="stamp-container">
                <div class="stamp">
                    <div class="stamp-title">Official Stamp</div>
                    <div class="stamp-name">BRAICK DISPENSARY</div>
                    <div class="stamp-line"></div>
                    <div class="stamp-doctor">Dr. <?= htmlspecialchars($user_full_name) ?></div>
                    <div class="stamp-signature">_________________________</div>
                    <div class="stamp-date">Date: <?= date('d M Y') ?></div>
                </div>
            </div>
        </div>

        <!-- FOOTER NOTE -->
        <div class="footer-note">
            <div>
                <span class="brand">Braick Dispensary</span> 
                <span style="color:#94A3B8;">|</span> 
                Patient Report: <?= htmlspecialchars($patient_data['full_name']) ?>
                <span style="color:#94A3B8;">|</span> 
                Generated: <?= date('d M Y, h:i A') ?>
                <span style="color:#94A3B8;">|</span> 
                <span style="color:#0284C7;">🫁 7 Vital Signs Tracked</span>
            </div>
            <div class="slogan">⭐ Braick Dispensary - Tunajali Afya Yako ⭐</div>
        </div>

    </div>
</div>

<script>
    // Auto print
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };

    // ESC to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.close();
        }
    });
</script>

</body>
</html>
<?php
exit;
?>