<?php
// ================================================================
// FILE: frontend/pages/reception/view_visit.php
// RECEPTION - VIEW VISIT DETAILS (BRANCH FILTERED)
// ✅ Inatumia shared header + sidebar
// PDF with 7 Vital Signs (SpO2) + Official Stamp
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// ACCESS CONTROL
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    switch ($_SESSION['role']) {
        case 'doctor':     header('Location: ../doctor/dashboard.php');     break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php');   break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php');    break;
        default:           header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id'];
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error    = '';

if ($visit_id <= 0) {
    header('Location: visits.php');
    exit;
}

// Defaults
$visit               = null;
$prescriptions       = [];
$prescription_items  = [];
$lab_tests           = [];
$vital_signs         = null;
$bills               = [];
$bill_items          = [];
$procedures          = [];
$equipment_used      = [];
$total_amount        = 0;
$total_paid          = 0;
$total_balance       = 0;
$pending_bills       = 0;
$cancelled_bills     = 0;
$paid_bills          = 0;
$admin_phones        = [];
$branch_phone        = '';
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();

    // Notifications
    try {
        $stmt = $db->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}

    // Admin phones
    try {
        $stmt = $db->prepare("
            SELECT phone FROM users
            WHERE role = 'admin' AND branch_id = ? AND status = 'active'
            ORDER BY id ASC
        ");
        $stmt->execute([$branch_id]);
        $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    // Branch phone
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch_phone = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {}

    // ================================================================
    // GET VISIT
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*,
               p.id AS patient_id,
               p.full_name AS patient_name,
               p.patient_id AS patient_number,
               p.phone, p.email, p.address, p.gender, p.date_of_birth,
               p.blood_group, p.allergies, p.marital_status, p.emergency_contact,
               u.id AS doctor_id,
               u.full_name AS doctor_name,
               u.specialty, u.phone AS doctor_phone,
               u.profile_pic AS doctor_profile_pic,
               u.is_online AS doctor_is_online,
               b.name AS branch_name,
               b.phone AS branch_phone,
               v.consultation_fee,
               d.disease_name,
               d.disease_code AS disease_code_full,
               d.icd_code
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$visit_id, $branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit) {
        $error = "Visit not found or you don't have permission to view it.";
    } else {
        // Prescriptions
        $stmt = $db->prepare("SELECT * FROM prescriptions WHERE visit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($prescriptions as $pres) {
            $stmt = $db->prepare("
                SELECT pi.*, mi.medication_name AS inventory_medication_name,
                       mi.batch_number, mi.unit, mi.selling_price
                FROM prescription_items pi
                LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
                WHERE pi.prescription_id = ?
                ORDER BY pi.created_at DESC
            ");
            $stmt->execute([$pres['id']]);
            $prescription_items[$pres['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Lab tests
        $stmt = $db->prepare("
            SELECT lt.*,
                   u.full_name AS technician_name,
                   u.profile_pic AS technician_profile_pic,
                   ltc.test_code,
                   ltc.reference_range AS catalog_reference_range
            FROM lab_tests lt
            LEFT JOIN users u ON lt.technician_id = u.id
            LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vitals
        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute([$visit_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

        // Bills
        $stmt = $db->prepare("SELECT * FROM bills WHERE visit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bills as $bill) {
            $total_amount  += $bill['total_amount'] ?? 0;
            $total_paid    += $bill['paid_amount']  ?? 0;
            $total_balance += $bill['balance']      ?? 0;

            if (in_array($bill['status'], ['pending', 'partial'])) $pending_bills++;
            elseif ($bill['status'] === 'paid')                    $paid_bills++;
            elseif ($bill['status'] === 'cancelled')               $cancelled_bills++;

            if (!empty($bill['id'])) {
                $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
                $stmt->execute([$bill['id']]);
                $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Procedures
        $stmt = $db->prepare("
            SELECT p.*, pc.procedure_code, pc.category AS procedure_category,
                   me.equipment_name, me.batch_number AS equipment_batch,
                   me.quantity AS equipment_quantity,
                   sm.quantity AS equipment_used_quantity
            FROM procedures p
            LEFT JOIN procedures_catalog pc ON p.procedure_id = pc.id
            LEFT JOIN stock_movements sm ON sm.reference_id = p.id AND sm.reference_type = 'procedure'
            LEFT JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Equipment used
        $stmt = $db->prepare("
            SELECT DISTINCT sm.*, me.equipment_name, me.batch_number, me.unit, me.selling_price
            FROM stock_movements sm
            JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE sm.patient_id = ?
              AND sm.reference_type IN ('prescription','procedure','lab_test')
              AND sm.movement_type = 'out'
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([$visit['patient_id']]);
        $equipment_used = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// ================================================================
// HELPERS (guarded)
// ================================================================
if (!function_exists('vv_getUserColor')) {
    function vv_getUserColor($name) {
        $colors = ['#0B5ED7','#059669','#7C3AED','#DC2626','#D97706','#0D9488','#DB2777'];
        $hash = 0;
        $name = $name ?: 'Unknown';
        for ($i = 0; $i < strlen($name); $i++) {
            $hash = ord($name[$i]) + (($hash << 5) - $hash);
        }
        return $colors[abs($hash) % count($colors)];
    }
}

// ================================================================
// PROFILE URLS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - Braick Dispensary</title>

    <link rel="icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           ================================================================ */

        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 18px;
            padding: 24px 32px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.28);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 320px; height: 320px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header .page-title {
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header .page-title i { font-size: 1.85rem; opacity: .9; }
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

        .role-badge-display {
            background: rgba(255,255,255,0.22);
            color: #fff;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .btn-outline-light {
            background: rgba(255,255,255,0.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.22);
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all .25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.26);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        /* DETAIL CARD */
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
            margin-bottom: 14px;
            box-shadow: 0 2px 6px rgba(15,23,42,0.03);
        }
        .detail-card:hover {
            border-color: var(--primary-light);
            box-shadow: 0 8px 24px rgba(11, 94, 215, 0.08);
        }
        .card-title-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary);
        }
        .card-title-section h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-title-section .badge-count {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            margin-left: auto;
        }

        .detail-label {
            font-size: 0.62rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 3px;
        }
        .detail-value {
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* GRIDS */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 22px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px 22px; }
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }

        /* STATUS BADGES */
        .status-badge-visit {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .status-badge-visit.pending       { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.assigned      { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.with_doctor   { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed     { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled     { background: #FEE2E2; color: #DC2626; }
        .status-badge-visit.paid          { background: #D1FAE5; color: #059669; }
        .status-badge-visit.partial       { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.in_progress   { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.lab_completed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.dispensed     { background: #D1FAE5; color: #059669; }
        .status-badge-visit.confirmed     { background: #E8F0FE; color: #0B5ED7; }

        /* VITAL SIGNS CARDS */
        .vital-grid-7 {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        @media (max-width: 1400px) { .vital-grid-7 { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1024px) { .vital-grid-7 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px)  { .vital-grid-7 { grid-template-columns: repeat(2, 1fr); } }

        .vital-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px 10px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
            min-height: 105px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        .vital-card.blue::before   { background: linear-gradient(90deg, #0B5ED7, #1A7AFF); }
        .vital-card.green::before  { background: linear-gradient(90deg, #059669, #34D399); }
        .vital-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
        .vital-card.orange::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
        .vital-card.red::before    { background: linear-gradient(90deg, #DC2626, #F87171); }
        .vital-card.teal::before   { background: linear-gradient(90deg, #0D9488, #14B8A6); }
        .vital-card.cyan::before   { background: linear-gradient(90deg, #0891B2, #06B6D4); }

        .vital-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(11, 94, 215, 0.12);
            border-color: var(--primary-light);
        }
        .vital-card .vital-icon {
            font-size: 1.35rem;
            display: block;
            margin-bottom: 3px;
        }
        .vital-card .vital-label {
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .4px;
            display: block;
        }
        .vital-card .vital-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 3px;
        }
        .vital-card .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .vital-card.blue .vital-value   { color: var(--primary); }
        .vital-card.green .vital-value  { color: var(--success); }
        .vital-card.purple .vital-value { color: var(--purple); }
        .vital-card.orange .vital-value { color: var(--warning); }
        .vital-card.red .vital-value    { color: var(--danger); }
        .vital-card.teal .vital-value   { color: #0D9488; }
        .vital-card.cyan .vital-value   { color: var(--cyan); }

        .spo2-category {
            display: inline-block;
            font-size: 0.5rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 8px;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .spo2-category.normal   { background: rgba(8,145,178,0.15); color: var(--cyan); }
        .spo2-category.low      { background: rgba(217,119,6,0.15); color: var(--warning); }
        .spo2-category.critical { background: rgba(220,38,38,0.2); color: var(--danger); animation: pulse-spo2 1s infinite; }

        @keyframes pulse-spo2 {
            0%, 100% { opacity: 1; }
            50%      { opacity: .5; }
        }

        /* BILL SUMMARY CARDS */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .bill-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 14px 16px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
        }
        .bill-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        .bill-card.total::before     { background: linear-gradient(90deg, #0B5ED7, #1A7AFF); }
        .bill-card.pending::before   { background: linear-gradient(90deg, #D97706, #FBBF24); }
        .bill-card.paid::before      { background: linear-gradient(90deg, #059669, #34D399); }
        .bill-card.cancelled::before { background: linear-gradient(90deg, #DC2626, #F87171); }

        .bill-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(11, 94, 215, 0.12);
        }
        .bill-card .bill-icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 4px;
        }
        .bill-card .bill-amount {
            font-size: 1.15rem;
            font-weight: 800;
        }
        .bill-card.total .bill-amount     { color: var(--primary); }
        .bill-card.pending .bill-amount   { color: var(--warning); }
        .bill-card.paid .bill-amount      { color: var(--success); }
        .bill-card.cancelled .bill-amount { color: var(--danger); }
        .bill-card .bill-label {
            font-size: 0.62rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: .4px;
            margin-top: 4px;
        }

        /* TABLES — GREEN HEADER */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 8px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .table-wrapper thead th {
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .table-wrapper thead th i { margin-right: 5px; opacity: .9; }
        .table-wrapper tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .table-wrapper tbody tr:nth-child(even) td { background: rgba(241,245,249,0.5); }
        [data-theme="dark"] .table-wrapper tbody tr:nth-child(even) td { background: rgba(15,23,42,0.5); }
        .table-wrapper tbody tr:hover td { background: var(--primary-bg); }
        [data-theme="dark"] .table-wrapper tbody tr:hover td { background: #1E3A5F; }

        /* STATUS BADGES (small) */
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .status-badge.pending     { background: #FEF3C7; color: #D97706; }
        .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.completed   { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled   { background: #FEE2E2; color: #DC2626; }
        .status-badge.paid        { background: #D1FAE5; color: #059669; }
        .status-badge.partial     { background: #FEF3C7; color: #D97706; }
        .status-badge.dispensed   { background: #D1FAE5; color: #059669; }

        /* MEDICATION TABLE ELEMENTS */
        .medication-table .med-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.85rem;
        }
        .medication-table .med-batch,
        .medication-table .med-price {
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: block;
        }
        .medication-table .med-price { color: var(--success); font-weight: 700; }
        .medication-table .med-dosage,
        .medication-table .med-frequency {
            font-size: 0.72rem;
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-weight: 600;
        }
        .medication-table .med-dosage    { background: var(--bg-body); color: var(--text-secondary); }
        .medication-table .med-frequency { background: var(--primary-bg); color: var(--primary); }
        .medication-table .med-instruction {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-style: italic;
            display: block;
            margin-top: 3px;
            padding: 3px 8px;
            border-radius: 4px;
            border-left: 3px solid var(--warning);
            background: var(--bg-body);
        }
        .medication-table .med-quantity { font-weight: 700; color: var(--text-primary); font-size: .85rem; }

        /* TECH INFO */
        .tech-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .75rem;
            color: var(--text-secondary);
        }
        .tech-info .tech-avatar {
            width: 24px; height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .65rem;
            color: #fff;
        }
        .tech-info .tech-name { font-weight: 500; color: var(--text-primary); }

        /* AVATARS */
        .doctor-avatar-lg {
            width: 52px; height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
        }
        .patient-avatar-sm {
            width: 46px; height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 700; }

        /* ERROR BOX */
        .error-box {
            background: var(--danger-bg);
            border: 2px solid var(--danger);
            border-radius: 14px;
            padding: 24px 28px;
            text-align: center;
            max-width: 600px;
            margin: 40px auto;
        }
        [data-theme="dark"] .error-box { background: #3A1A1A; }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp .4s ease forwards;
            opacity: 0;
        }

        /* PDF MODAL */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .pdf-modal-overlay.active { display: flex; }

        .pdf-modal {
            background: var(--bg-card);
            border-radius: 14px;
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            animation: slideUp .3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .pdf-modal-header {
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 14px 14px 0 0;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .25s;
        }
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }

        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 22px 28px;
            background: var(--bg-body);
        }
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: #fff;
            color: #1E293B;
            padding: 24px 28px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            line-height: 1.5;
        }
        [data-theme="dark"] .pdf-modal-body .pdf-content { background: #fff; color: #1E293B; }

        .pdf-content .pdf-section {
            page-break-inside: avoid;
            break-inside: avoid;
            margin: 6px 0;
        }
        .pdf-content .text-wrap-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 3em;
            line-height: 1.5em;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .vital-grid-7 { grid-template-columns: repeat(3, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .col-span-2, .col-span-3 { grid-column: span 1; }
        }
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.25rem; }
            .vital-grid-7 { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .detail-card { padding: 14px 16px; }
        }

        @media print {
            .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <?php if ($error): ?>

        <div class="error-box">
            <i class="fas fa-exclamation-circle" style="font-size:3rem;color:var(--danger);display:block;margin-bottom:12px;"></i>
            <h3 style="font-size:1.2rem;font-weight:700;color:var(--danger);">Error</h3>
            <p style="color:var(--text-secondary);margin:10px 0 16px;"><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" class="btn-outline-light" style="background:var(--primary);color:#fff;border:none;padding:9px 20px;">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>

    <?php elseif ($visit): ?>

    <!-- ============================================================
         PAGE HEADER
         ============================================================ -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Visit Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View complete visit information
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
                <?php if (($visit['is_completed'] ?? 0) == 1): ?>
                    <span class="header-badge" style="background:rgba(5,150,105,0.25);border-color:rgba(5,150,105,0.35);">
                        <i class="fas fa-check-circle"></i> Completed
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="visits.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.25);border-color:rgba(220,38,38,0.35);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- ============================================================
         1. VISIT INFORMATION
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-info-circle" style="color:var(--primary);font-size:1.15rem;"></i>
            <h3>1. Visit Information</h3>
        </div>
        <div class="grid-2">
            <div>
                <p class="detail-label">Visit Number</p>
                <p class="detail-value"><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></p>
            </div>
            <div>
                <p class="detail-label">Status</p>
                <p class="detail-value">
                    <span class="status-badge-visit <?= htmlspecialchars($visit['status'] ?? 'pending') ?>">
                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label">Visit Type</p>
                <p class="detail-value"><?= htmlspecialchars(ucfirst($visit['visit_type'] ?? 'N/A')) ?></p>
            </div>
            <div>
                <p class="detail-label">Date & Time</p>
                <p class="detail-value"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Branch</p>
                <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Consultation Fee</p>
                <p class="detail-value">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></p>
            </div>
            <?php if (!empty($visit['follow_up_date'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Follow-up Date</p>
                    <p class="detail-value"><?= date('F d, Y', strtotime($visit['follow_up_date'])) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['notes'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Notes</p>
                    <p class="detail-value"><?= nl2br(htmlspecialchars($visit['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         2. PATIENT INFORMATION
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user" style="color:var(--primary);font-size:1.15rem;"></i>
            <h3>2. Patient Information</h3>
        </div>

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 18px;background:var(--primary-bg);border-radius:12px;margin-bottom:14px;border:1px solid rgba(11,94,215,0.15);">
            <div class="patient-avatar-sm" style="background:<?= vv_getUserColor($visit['patient_name']) ?>;">
                <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
            </div>
            <div>
                <p style="font-weight:700;font-size:1.05rem;color:var(--text-primary);margin:0;"><?= htmlspecialchars($visit['patient_name']) ?></p>
                <p style="font-size:0.75rem;color:var(--text-secondary);margin:3px 0 0 0;">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?>
                </p>
            </div>
        </div>

        <div class="grid-3">
            <div>
                <p class="detail-label">Phone</p>
                <p class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Email</p>
                <p class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Emergency Contact</p>
                <p class="detail-value"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Gender</p>
                <p class="detail-value"><?= ucfirst(htmlspecialchars($visit['gender'] ?? 'N/A')) ?></p>
            </div>
            <div>
                <p class="detail-label">Marital Status</p>
                <p class="detail-value"><?= htmlspecialchars($visit['marital_status'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label">Date of Birth</p>
                <p class="detail-value"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
            </div>
            <div>
                <p class="detail-label">Blood Group</p>
                <p class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></p>
            </div>
            <div class="col-span-2">
                <p class="detail-label">Address</p>
                <p class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></p>
            </div>
            <?php if (!empty($visit['allergies'])): ?>
                <div class="col-span-3">
                    <p class="detail-label">Allergies</p>
                    <p class="detail-value" style="color:var(--danger);font-weight:600;"><?= htmlspecialchars($visit['allergies']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         3. DOCTOR INFORMATION
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user-md" style="color:var(--primary);font-size:1.15rem;"></i>
            <h3>3. Doctor Information</h3>
        </div>
        <?php if (!empty($visit['doctor_id'])): ?>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <?php if (!empty($visit['doctor_profile_pic'])): ?>
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= htmlspecialchars($visit['doctor_profile_pic']) ?>"
                         alt="Doctor" class="doctor-avatar-lg"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="doctor-avatar-lg" style="background:var(--primary);display:none;">
                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                    </div>
                <?php else: ?>
                    <div class="doctor-avatar-lg" style="background:var(--primary);">
                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <p style="font-weight:700;font-size:1rem;color:var(--text-primary);margin:0;">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></p>
                    <p style="font-size:0.75rem;color:var(--text-secondary);margin:3px 0 0 0;"><?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></p>
                </div>
                <?php if (!empty($visit['doctor_phone'])): ?>
                    <span style="font-size:0.78rem;color:var(--text-secondary);">
                        <i class="fas fa-phone"></i> <?= htmlspecialchars($visit['doctor_phone']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($visit['consultation_fee'])): ?>
                    <span style="font-size:0.78rem;color:var(--text-secondary);">
                        <i class="fas fa-money-bill-wave"></i> Fee: TSh <?= number_format($visit['consultation_fee'], 0) ?>
                    </span>
                <?php endif; ?>
                <?php if (($visit['doctor_is_online'] ?? 0)): ?>
                    <span class="status-badge completed" style="margin-left:auto;">
                        <i class="fas fa-circle" style="font-size:.4rem;"></i> Online
                    </span>
                <?php else: ?>
                    <span class="status-badge cancelled" style="margin-left:auto;">
                        <i class="fas fa-circle" style="font-size:.4rem;"></i> Offline
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);font-style:italic;">No doctor assigned to this visit</p>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         4. VITAL SIGNS (7 CARDS)
         ============================================================ -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-heartbeat" style="color:#DC2626;font-size:1.15rem;"></i>
            <h3>4. Vital Signs</h3>
            <span class="badge-count">
                <?= isset($vital_signs['recorded_at']) ? date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) : 'N/A' ?>
            </span>
        </div>
        <div class="vital-grid-7">
            <div class="vital-card blue">
                <span class="vital-icon">🌡️</span>
                <span class="vital-label">Temperature</span>
                <span class="vital-value"><?= $vital_signs['temperature'] ?? 'N/A' ?> <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-card green">
                <span class="vital-icon">❤️</span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-value">
                    <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
                        <?= $vital_signs['blood_pressure_systolic'] ?>/<?= $vital_signs['blood_pressure_diastolic'] ?>
                        <span class="vital-unit">mmHg</span>
                    <?php else: ?>N/A<?php endif; ?>
                </span>
            </div>
            <div class="vital-card purple">
                <span class="vital-icon">💓</span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-card orange">
                <span class="vital-icon">⚖️</span>
                <span class="vital-label">Weight</span>
                <span class="vital-value"><?= $vital_signs['weight'] ?? 'N/A' ?> <span class="vital-unit">kg</span></span>
            </div>
            <div class="vital-card teal">
                <span class="vital-icon">📏</span>
                <span class="vital-label">Height</span>
                <span class="vital-value"><?= $vital_signs['height'] ?? 'N/A' ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-card red">
                <span class="vital-icon">📊</span>
                <span class="vital-label">BMI</span>
                <span class="vital-value"><?= $vital_signs['bmi'] ?? 'N/A' ?> <span class="vital-unit">kg/m²</span></span>
            </div>
            <?php if (!empty($vital_signs['oxygen_saturation'])):
                $spo2 = (int)$vital_signs['oxygen_saturation'];
                $spo2_class = $spo2 >= 95 ? 'normal' : ($spo2 >= 90 ? 'low' : 'critical');
                $spo2_label = $spo2 >= 95 ? 'Normal' : ($spo2 >= 90 ? 'Low' : 'Critical');
            ?>
            <div class="vital-card cyan">
                <span class="vital-icon">🫁</span>
                <span class="vital-label">Oxygen Sat.</span>
                <span class="vital-value"><?= $spo2 ?> <span class="vital-unit">%</span></span>
                <span class="spo2-category <?= $spo2_class ?>"><?= $spo2_label ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($vital_signs['notes'])): ?>
            <div style="margin-top:10px;font-size:.78rem;color:var(--text-secondary);">
                <i class="fas fa-sticky-note" style="color:var(--primary);"></i> <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         5. CLINICAL INFORMATION
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-file-medical-alt" style="color:var(--primary);font-size:1.15rem;"></i>
            <h3>5. Clinical Information</h3>
        </div>
        <div class="grid-2">
            <?php if (!empty($visit['complaint'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-exclamation-circle"></i> Complaint</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:10px;border-left:4px solid var(--primary);">
                        <?= nl2br(htmlspecialchars($visit['complaint'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['symptoms'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-list-ul"></i> Symptoms</p>
                    <p class="detail-value" style="background:var(--warning-bg);padding:10px 14px;border-radius:10px;border-left:4px solid var(--warning);">
                        <?= nl2br(htmlspecialchars($visit['symptoms'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['hpi'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-history"></i> HPI (History of Presenting Illness)</p>
                    <p class="detail-value" style="background:var(--purple-bg);padding:10px 14px;border-radius:10px;border-left:4px solid var(--purple);">
                        <?= nl2br(htmlspecialchars($visit['hpi'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['physical_exam'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-stethoscope"></i> Physical Examination</p>
                    <p class="detail-value" style="background:var(--success-bg);padding:10px 14px;border-radius:10px;border-left:4px solid var(--success);">
                        <?= nl2br(htmlspecialchars($visit['physical_exam'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         6. LAB TESTS
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-flask" style="color:var(--purple);font-size:1.15rem;"></i>
            <h3>6. Lab Tests</h3>
            <span class="badge-count"><?= count($lab_tests) ?></span>
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-vial"></i> Test Name</th>
                            <th><i class="fas fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-flask"></i> Results</th>
                            <th><i class="fas fa-user"></i> Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($test['test_code'])): ?>
                                        <span style="font-size:.6rem;color:var(--text-secondary);display:block;"><?= htmlspecialchars($test['test_code']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= isset($test['created_at']) ? date('M d, Y h:i A', strtotime($test['created_at'])) : 'N/A' ?></td>
                                <td><span class="status-badge <?= htmlspecialchars($test['status'] ?? 'pending') ?>"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:var(--success);font-weight:600;">✅ <?= htmlspecialchars(substr($test['results'], 0, 50)) . (strlen($test['results']) > 50 ? '...' : '') ?></span>
                                        <?php if (!empty($test['reference_range'])): ?>
                                            <span style="font-size:.6rem;color:var(--text-secondary);display:block;">Ref: <?= htmlspecialchars($test['reference_range']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['technician_name'])): ?>
                                        <div class="tech-info">
                                            <?php if (!empty($test['technician_profile_pic'])): ?>
                                                <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= htmlspecialchars($test['technician_profile_pic']) ?>" alt="Tech" class="tech-avatar" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <div class="tech-avatar" style="background:var(--primary);"><?= strtoupper(substr($test['technician_name'], 0, 1)) ?></div>
                                            <?php endif; ?>
                                            <span class="tech-name"><?= htmlspecialchars($test['technician_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:.7rem;font-style:italic;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No lab tests found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         7. DIAGNOSIS
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-stethoscope" style="color:var(--primary);font-size:1.15rem;"></i>
            <h3>7. Diagnosis</h3>
        </div>
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])): ?>
        <div class="grid-2">
            <?php if (!empty($visit['disease_name'])): ?>
                <div>
                    <p class="detail-label">Disease Name</p>
                    <p class="detail-value" style="font-size:1rem;font-weight:700;color:var(--primary);"><?= htmlspecialchars($visit['disease_name']) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['disease_code_full']) || !empty($visit['icd_code'])): ?>
                <div>
                    <p class="detail-label">Disease Code</p>
                    <p class="detail-value">
                        <?php if (!empty($visit['disease_code_full'])): ?>
                            <span class="status-badge" style="background:var(--primary-bg);color:var(--primary);"><?= htmlspecialchars($visit['disease_code_full']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($visit['icd_code'])): ?>
                            <span class="status-badge" style="background:var(--bg-body);color:var(--text-secondary);margin-left:4px;">ICD: <?= htmlspecialchars($visit['icd_code']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['diagnosis'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Diagnosis Description</p>
                    <p class="detail-value" style="background:var(--primary-bg);padding:10px 14px;border-radius:10px;border-left:4px solid var(--primary);">
                        <?= nl2br(htmlspecialchars($visit['diagnosis'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['treatment'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-prescription"></i> Treatment</p>
                    <p class="detail-value" style="background:var(--success-bg);padding:10px 14px;border-radius:10px;border-left:4px solid var(--success);">
                        <?= nl2br(htmlspecialchars($visit['treatment'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);font-style:italic;text-align:center;padding:16px 0;">No diagnosis recorded for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         8. MEDICATIONS
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-prescription" style="color:var(--success);font-size:1.15rem;"></i>
            <h3>8. Medications</h3>
            <span class="badge-count"><?= count($prescriptions) ?> prescription(s)</span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pres):
                $items = $prescription_items[$pres['id']] ?? [];
            ?>
                <div style="margin-bottom:12px;padding:14px 16px;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <div>
                            <strong style="color:var(--primary);font-size:.95rem;">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong>
                            <?php if (!empty($pres['diagnosis'])): ?>
                                <span style="font-size:.75rem;color:var(--text-secondary);margin-left:8px;">
                                    <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($pres['diagnosis']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge <?= htmlspecialchars($pres['status'] ?? 'pending') ?>">
                            <?= ucfirst($pres['status'] ?? 'Pending') ?>
                        </span>
                    </div>

                    <?php if (!empty($pres['instructions'])): ?>
                        <div style="font-size:.75rem;color:var(--text-secondary);margin-bottom:10px;background:var(--bg-card);padding:6px 12px;border-radius:8px;border-left:3px solid var(--warning);">
                            <i class="fas fa-info-circle"></i> <?= nl2br(htmlspecialchars($pres['instructions'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($items) > 0): ?>
                        <div class="table-wrapper">
                            <table class="medication-table">
                                <thead>
                                    <tr>
                                        <th style="width:30%;"><i class="fas fa-capsules"></i> Medication</th>
                                        <th style="width:15%;"><i class="fas fa-weight"></i> Dosage</th>
                                        <th style="width:15%;"><i class="fas fa-clock"></i> Frequency</th>
                                        <th style="width:15%;"><i class="fas fa-cubes"></i> Quantity</th>
                                        <th style="width:25%;"><i class="fas fa-info-circle"></i> Instructions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="med-name"><?= htmlspecialchars($item['medication_name'] ?? $item['inventory_medication_name'] ?? 'N/A') ?></div>
                                                <?php if (!empty($item['batch_number'])): ?>
                                                    <div class="med-batch">Batch: <?= htmlspecialchars($item['batch_number']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['total_price'])): ?>
                                                    <div class="med-price">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="med-dosage"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></span></td>
                                            <td><span class="med-frequency"><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></span></td>
                                            <td><span class="med-quantity"><?= $item['quantity'] ?? 0 ?></span> <span style="font-size:.6rem;color:var(--text-secondary);">units</span></td>
                                            <td>
                                                <?php if (!empty($item['instructions'])): ?>
                                                    <div class="med-instruction"><?= htmlspecialchars($item['instructions']) ?></div>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);font-size:.7rem;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="font-size:.75rem;color:var(--text-secondary);font-style:italic;">No medication items found</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No medications prescribed for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         9. PROCEDURES & EQUIPMENT
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-syringe" style="color:var(--purple);font-size:1.15rem;"></i>
            <h3>9. Procedures & Equipment Used</h3>
            <span class="badge-count"><?= count($procedures) ?> procedure(s)</span>
        </div>
        <?php if (count($procedures) > 0 || count($equipment_used) > 0): ?>
            <?php if (count($procedures) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-syringe"></i> Procedure Name</th>
                                <th><i class="fas fa-calendar-alt"></i> Date</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-tools"></i> Equipment</th>
                                <th><i class="fas fa-cubes"></i> Qty</th>
                                <th><i class="fas fa-money-bill-wave"></i> Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($procedures as $proc): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong>
                                        <?php if (!empty($proc['procedure_code'])): ?>
                                            <span style="font-size:.6rem;color:var(--text-secondary);display:block;"><?= htmlspecialchars($proc['procedure_code']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= isset($proc['created_at']) ? date('M d, Y h:i A', strtotime($proc['created_at'])) : 'N/A' ?></td>
                                    <td><span class="status-badge <?= htmlspecialchars($proc['status'] ?? 'pending') ?>"><?= ucfirst($proc['status'] ?? 'Pending') ?></span></td>
                                    <td>
                                        <?php if (!empty($proc['equipment_name'])): ?>
                                            <span style="font-size:.78rem;"><?= htmlspecialchars($proc['equipment_name']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:.7rem;font-style:italic;">No equipment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $proc['equipment_used_quantity'] ?? 1 ?></td>
                                    <td><?= !empty($proc['procedure_price']) ? 'TSh ' . number_format($proc['procedure_price'], 0) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (count($equipment_used) > 0): ?>
                <div style="margin-top:14px;">
                    <p style="font-size:.72rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
                        <i class="fas fa-tools"></i> Medical Equipment Used
                    </p>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tools"></i> Equipment Name</th>
                                    <th><i class="fas fa-barcode"></i> Batch Number</th>
                                    <th><i class="fas fa-cubes"></i> Quantity</th>
                                    <th><i class="fas fa-ruler"></i> Unit</th>
                                    <th><i class="fas fa-tag"></i> Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_used as $eq): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($eq['equipment_name'] ?? 'N/A') ?></strong></td>
                                        <td><span style="font-size:.72rem;color:var(--text-secondary);"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></span></td>
                                        <td><strong><?= $eq['quantity'] ?? 0 ?></strong></td>
                                        <td><?= htmlspecialchars($eq['unit'] ?? 'pcs') ?></td>
                                        <td><span style="font-size:.65rem;color:var(--text-secondary);text-transform:capitalize;"><?= htmlspecialchars($eq['reference_type'] ?? 'N/A') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No procedures or equipment used for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         10. BILL SUMMARY
         ============================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-money-bill-wave" style="color:var(--success);font-size:1.15rem;"></i>
            <h3>10. Bill Summary</h3>
            <span class="badge-count"><?= count($bills) ?> bill(s)</span>
        </div>
        <?php if (!empty($bills)): ?>
            <div class="bill-summary-grid">
                <div class="bill-card total">
                    <span class="bill-icon">💰</span>
                    <div class="bill-amount">TSh <?= number_format($total_amount, 0) ?></div>
                    <div class="bill-label">Total Amount</div>
                </div>
                <div class="bill-card pending">
                    <span class="bill-icon">⏳</span>
                    <div class="bill-amount">TSh <?= number_format($total_amount - $total_paid, 0) ?></div>
                    <div class="bill-label">Pending Balance</div>
                </div>
                <div class="bill-card paid">
                    <span class="bill-icon">✅</span>
                    <div class="bill-amount">TSh <?= number_format($total_paid, 0) ?></div>
                    <div class="bill-label">Paid Amount</div>
                </div>
                <div class="bill-card cancelled">
                    <span class="bill-icon">❌</span>
                    <div class="bill-amount"><?= $cancelled_bills ?></div>
                    <div class="bill-label">Cancelled Bills</div>
                </div>
            </div>

            <div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:.78rem;font-weight:600;">Payment Status:</span>
                <span class="status-badge <?= $total_balance <= 0 ? 'paid' : 'partial' ?>">
                    <?= $total_balance <= 0 ? '✅ Fully Paid' : '⏳ Pending / Partial' ?>
                </span>
                <?php if ($total_balance > 0): ?>
                    <span style="font-size:.75rem;color:var(--text-secondary);">
                        Balance Due: <strong style="color:var(--danger);">TSh <?= number_format($total_balance, 0) ?></strong>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (count($bills) > 0): ?>
                <div class="table-wrapper" style="margin-top:14px;">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-file-invoice"></i> Bill Number</th>
                                <th><i class="fas fa-money-bill"></i> Total</th>
                                <th><i class="fas fa-check-circle"></i> Paid</th>
                                <th><i class="fas fa-balance-scale"></i> Balance</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                    <td>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                    <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                    <td><strong style="color:<?= ($bill['balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></strong></td>
                                    <td><span class="status-badge <?= htmlspecialchars($bill['status'] ?? 'pending') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px 0;font-style:italic;">No bills found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;opacity:.4;">|</span>
            Visit Details
            <span style="margin:0 6px;opacity:.4;">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span style="margin:0 6px;opacity:.4;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
        </p>
    </footer>

    <?php endif; ?>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf"></i>
                Visit PDF Preview - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

<script>
    // ================================================================
    // FOOTER CLOCK (header handles its own)
    // ================================================================
    setInterval(function () {
        var now = new Date();
        var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var el = document.getElementById('footerTimestamp');
        if (el) el.textContent = 'Last updated: ' + t;
    }, 1000);

    // ================================================================
    // TOAST — Inatumia ya header kama ipo
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function (title, message, type) {
            console.log('[' + type + '] ' + title + ': ' + message);
        };
    }

    // ================================================================
    // GENERATE PDF
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');

        // Build HTML using template literals with PHP data
        var html = `
            <div style="text-align:center;padding-bottom:12px;border-bottom:3px solid #0B5ED7;margin-bottom:16px;">
                <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png"
                     alt="Braick Logo" style="height:55px;display:block;margin:0 auto 4px auto;" onerror="this.style.display='none'">
                <div style="font-size:1.4rem;font-weight:800;color:#0B5ED7;letter-spacing:-0.5px;">BRAICK DISPENSARY</div>
                <div style="font-size:.75rem;color:#64748B;">Tunajali Afya Yako</div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:6px;padding-top:6px;border-top:1px solid #E2E8F0;font-size:.65rem;color:#64748B;">
                    <span>📞 Admin: <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : htmlspecialchars($branch_phone) ?></span>
                    <span>🏢 <?= htmlspecialchars($visit['branch_name'] ?? 'Dodoma') ?></span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div style="font-size:.8rem;font-weight:600;color:#0B5ED7;margin-top:6px;background:#E8F0FE;padding:5px 16px;border-radius:20px;display:inline-block;">
                    📋 Visit Details Report - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </div>
            </div>

            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:8px 0 6px 0;">1. Visit Information</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Visit Number</span><span><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Status</span><span><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Visit Type</span><span><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Date & Time</span><span><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Branch</span><span><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Consultation Fee</span><span>TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></span></div>
                </div>
            </div>

            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:8px 0 6px 0;">2. Patient Information</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;font-size:14px;">
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;grid-column:span 2;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Full Name</span><span><strong><?= htmlspecialchars($visit['patient_name']) ?></strong></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Patient ID</span><span><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Phone</span><span><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Gender</span><span><?= ucfirst($visit['gender'] ?? 'N/A') ?></span></div>
                    <div style="display:flex;padding:3px 0;border-bottom:1px solid #E2E8F0;"><span style="font-weight:600;color:#64748B;width:130px;flex-shrink:0;">Blood Group</span><span><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                </div>
            </div>

            <?php if ($vital_signs): ?>
            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:8px 0 6px 0;">3. Vital Signs</div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                    <div style="background:#E8F0FE;padding:6px;border-radius:6px;text-align:center;border-left:3px solid #0B5ED7;"><div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">🌡️ Temp</div><div style="font-weight:700;color:#0B5ED7;"><?= $vital_signs['temperature'] ?? 'N/A' ?> °C</div></div>
                    <div style="background:#D1FAE5;padding:6px;border-radius:6px;text-align:center;border-left:3px solid #059669;"><div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">❤️ BP</div><div style="font-weight:700;color:#059669;"><?= !empty($vital_signs['blood_pressure_systolic']) ? $vital_signs['blood_pressure_systolic'] . '/' . $vital_signs['blood_pressure_diastolic'] . ' mmHg' : 'N/A' ?></div></div>
                    <div style="background:#EDE9FE;padding:6px;border-radius:6px;text-align:center;border-left:3px solid #7C3AED;"><div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">💓 Pulse</div><div style="font-weight:700;color:#7C3AED;"><?= $vital_signs['pulse_rate'] ?? 'N/A' ?> bpm</div></div>
                    <div style="background:#FEF3C7;padding:6px;border-radius:6px;text-align:center;border-left:3px solid #D97706;"><div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">⚖️ Weight</div><div style="font-weight:700;color:#D97706;"><?= $vital_signs['weight'] ?? 'N/A' ?> kg</div></div>
                    <div style="background:#D1FAE5;padding:6px;border-radius:6px;text-align:center;border-left:3px solid #0D9488;"><div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">📏 Height</div><div style="font-weight:700;color:#0D9488;"><?= $vital_signs['height'] ?? 'N/A' ?> cm</div></div>
                    <div style="background:#FEE2E2;padding:6px;border-radius:6px;text-align:center;border-left:3px solid #DC2626;"><div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">📊 BMI</div><div style="font-weight:700;color:#DC2626;"><?= $vital_signs['bmi'] ?? 'N/A' ?></div></div>
                    <?php if (!empty($vital_signs['oxygen_saturation'])): 
                        $spo2 = (int)$vital_signs['oxygen_saturation'];
                        $spo2_color = $spo2 >= 95 ? '#0891B2' : ($spo2 >= 90 ? '#D97706' : '#DC2626');
                        $spo2_bg    = $spo2 >= 95 ? '#CFFAFE' : ($spo2 >= 90 ? '#FEF3C7' : '#FEE2E2');
                        $spo2_label = $spo2 >= 95 ? 'Normal' : ($spo2 >= 90 ? 'Low' : 'Critical');
                    ?>
                    <div style="background:<?= $spo2_bg ?>;padding:6px;border-radius:6px;text-align:center;border-left:3px solid <?= $spo2_color ?>;">
                        <div style="font-size:10px;font-weight:600;color:#64748B;text-transform:uppercase;">🫁 SpO₂</div>
                        <div style="font-weight:700;color:<?= $spo2_color ?>;"><?= $spo2 ?> %</div>
                        <div style="font-size:9px;font-weight:700;color:<?= $spo2_color ?>;text-transform:uppercase;"><?= $spo2_label ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:8px 0 6px 0;">4. Diagnosis</div>
                <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])): ?>
                <div style="padding:4px 8px;background:#E8F0FE;border-radius:6px;border-left:3px solid #0B5ED7;margin-bottom:4px;">
                    <strong>Diagnosis:</strong> <?= htmlspecialchars($visit['diagnosis'] ?? $visit['disease_name'] ?? 'N/A') ?>
                </div>
                <?php if (!empty($visit['treatment'])): ?>
                <div style="padding:4px 8px;background:#D1FAE5;border-radius:6px;border-left:3px solid #059669;">
                    <strong>Treatment:</strong> <?= htmlspecialchars($visit['treatment']) ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <p style="color:#94A3B8;font-style:italic;font-size:13px;padding:6px 0;">No diagnosis recorded</p>
                <?php endif; ?>
            </div>

            <div class="pdf-section">
                <div style="font-size:14px;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin:8px 0 6px 0;">5. Bill Summary</div>
                <?php if (!empty($bills)): ?>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:6px;">
                    <div style="background:#E8F0FE;padding:6px;border-radius:6px;text-align:center;border:1px solid #6EA8FE;"><div style="font-size:15px;font-weight:700;color:#0B5ED7;">TSh <?= number_format($total_amount, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">💰 Total</div></div>
                    <div style="background:#D1FAE5;padding:6px;border-radius:6px;text-align:center;border:1px solid #059669;"><div style="font-size:15px;font-weight:700;color:#059669;">TSh <?= number_format($total_paid, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">✅ Paid</div></div>
                    <div style="background:#FEF3C7;padding:6px;border-radius:6px;text-align:center;border:1px solid #D97706;"><div style="font-size:15px;font-weight:700;color:#D97706;">TSh <?= number_format($total_balance, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;">⏳ Balance</div></div>
                </div>
                <?php else: ?>
                <p style="color:#94A3B8;font-style:italic;font-size:13px;padding:6px 0;">No bills found for this visit</p>
                <?php endif; ?>
            </div>

            <div style="margin-top:20px;padding-top:14px;border-top:2px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
                    <div style="font-size:13px;color:#64748B;">
                        <span>Technician: _________________</span>
                        <span style="margin-left:16px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div style="text-align:center;padding:8px 18px;border:3px solid #0B5ED7;border-radius:10px;background:#E8F0FE;min-width:170px;">
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Official Stamp</div>
                        <div style="font-size:14px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                        <div style="font-size:11px;color:#64748B;margin-top:3px;">Approved By: ______________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:3px;">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div style="text-align:center;margin-top:8px;font-size:12px;color:#94A3B8;">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?>
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
            filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>_<?= $visit['id'] ?? 0 ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'] }
        };
        html2pdf().set(opt).from(element).save();
    }

    // ESC / click outside to close modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePDFModal();
    });
    document.getElementById('pdfModal')?.addEventListener('click', function (e) {
        if (e.target === this) closePDFModal();
    });

    console.log('%c🏥 Braick - View Visit (Shared Header)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Inatumia shared header + sidebar', 'font-size:13px;color:#059669;font-weight:bold;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px;color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px;color:#64748B;');
</script>

</body>
</html>