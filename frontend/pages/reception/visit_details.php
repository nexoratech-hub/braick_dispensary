<?php
// ================================================================
// FILE: frontend/pages/reception/visit_details.php
// RECEPTION - VIEW VISIT DETAILS (V2 - SHARED HEADER/SIDEBAR)
// ✅ Using shared reception_header.php & reception_sidebar.php
// ✅ PDF Generation with Official Stamp
// ✅ 7 Vital Signs (with SpO2)
// BRAICK DISPENSARY
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
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor':     header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php'); break;
        default:           header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// USER DATA
// ================================================================
$user_id     = $_SESSION['user_id'];
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error    = '';

if ($visit_id <= 0) {
    header('Location: visits.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // UNREAD NOTIFICATIONS
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }

    // ADMIN CONTACTS
    $admin_phones = [];
    try {
        $stmt = $db->prepare("
            SELECT phone FROM users
            WHERE role = 'admin' AND branch_id = ? AND status = 'active'
            ORDER BY id ASC
        ");
        $stmt->execute([$branch_id]);
        $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $admin_phones = [];
    }

    // BRANCH PHONE
    $branch_phone = '';
    try {
        $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch_phone = $stmt->fetchColumn();
    } catch (Exception $e) {
        $branch_phone = '';
    }

    // VISIT DETAILS
    $stmt = $db->prepare("
        SELECT v.*,
               p.id as patient_id,
               p.full_name as patient_name,
               p.patient_id as patient_number,
               p.phone, p.email, p.address, p.gender, p.date_of_birth,
               p.blood_group, p.allergies, p.marital_status, p.emergency_contact,
               u.id as doctor_id,
               u.full_name as doctor_name,
               u.specialty,
               u.phone as doctor_phone,
               u.profile_pic as doctor_profile_pic,
               u.is_online as doctor_is_online,
               b.name as branch_name,
               b.phone as branch_phone,
               v.consultation_fee,
               d.disease_name,
               d.disease_code as disease_code_full,
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
    }

    // PRESCRIPTIONS
    $prescriptions = [];
    $prescription_items = [];
    if ($visit) {
        $stmt = $db->prepare("SELECT * FROM prescriptions WHERE visit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($prescriptions as $pres) {
            $stmt = $db->prepare("
                SELECT pi.*, mi.medication_name as inventory_medication_name,
                       mi.batch_number, mi.unit, mi.selling_price
                FROM prescription_items pi
                LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
                WHERE pi.prescription_id = ?
                ORDER BY pi.created_at DESC
            ");
            $stmt->execute([$pres['id']]);
            $prescription_items[$pres['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // LAB TESTS
    $lab_tests = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT lt.*, u.full_name as technician_name,
                   u.profile_pic as technician_profile_pic,
                   ltc.test_code, ltc.reference_range as catalog_reference_range
            FROM lab_tests lt
            LEFT JOIN users u ON lt.technician_id = u.id
            LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // VITAL SIGNS
    $vital_signs = null;
    if ($visit) {
        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE visit_id = ? ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute([$visit_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // BILLS
    $bills = [];
    $total_amount = 0; $total_paid = 0; $total_balance = 0;
    $pending_bills = 0; $cancelled_bills = 0; $paid_bills = 0;

    if ($visit) {
        $stmt = $db->prepare("SELECT * FROM bills WHERE visit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bills as $bill) {
            $total_amount  += $bill['total_amount'] ?? 0;
            $total_paid    += $bill['paid_amount']  ?? 0;
            $total_balance += $bill['balance']      ?? 0;

            if ($bill['status'] == 'pending' || $bill['status'] == 'partial') $pending_bills++;
            elseif ($bill['status'] == 'paid')      $paid_bills++;
            elseif ($bill['status'] == 'cancelled') $cancelled_bills++;
        }
    }

    // PROCEDURES
    $procedures = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT p.*, pc.procedure_code, pc.category as procedure_category,
                   me.equipment_name, me.batch_number as equipment_batch,
                   me.quantity as equipment_quantity,
                   sm.quantity as equipment_used_quantity
            FROM procedures p
            LEFT JOIN procedures_catalog pc ON p.procedure_id = pc.id
            LEFT JOIN stock_movements sm ON sm.reference_id = p.id AND sm.reference_type = 'procedure'
            LEFT JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // EQUIPMENT USED
    $equipment_used = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT DISTINCT sm.*, me.equipment_name, me.batch_number, me.unit, me.selling_price
            FROM stock_movements sm
            JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE sm.patient_id = ?
            AND sm.reference_type IN ('prescription', 'procedure', 'lab_test')
            AND sm.movement_type = 'out'
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([$visit['patient_id']]);
        $equipment_used = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // BILL ITEMS
    $bill_items = [];
    foreach ($bills as $bill) {
        if (!empty($bill['id'])) {
            $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
            $stmt->execute([$bill['id']]);
            $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $visit = null;
    $prescriptions = [];
    $lab_tests = [];
    $vital_signs = null;
    $bills = [];
    $total_amount = 0; $total_paid = 0; $total_balance = 0;
    $pending_bills = 0; $cancelled_bills = 0; $paid_bills = 0;
    $procedures = [];
    $equipment_used = [];
    $unread_notifications = 0;
    $admin_phones = [];
    $branch_phone = '';
}

// ================================================================
// PATHS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// NOTE: time_ago() is provided by reception_header.php
// DO NOT redeclare here.
// ================================================================

include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* ================================================================
           VISIT DETAILS PAGE - SPECIFIC STYLES ONLY
           (Base styles, variables, .card, .footer, .toast-custom,
            .main-content are provided by reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083B8A 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .page-header .page-title i { font-size: 1.9rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }

        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }

        .page-header .btn-outline-light.danger {
            background: rgba(220, 38, 38, 0.3);
            border-color: rgba(220, 38, 38, 0.4);
        }

        /* ---------- BRAND HEADER (Print only) ---------- */
        .brand-header {
            text-align: center;
            padding: 12px 0 10px 0;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 20px;
        }

        .brand-header .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .brand-header .brand-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
        }

        .brand-header .brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .brand-header .brand-tagline {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .brand-header .brand-admin-row {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
            font-size: 0.68rem;
            color: var(--text-secondary);
        }

        .brand-header .brand-admin-row span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .brand-header .brand-admin-row i { color: var(--primary); }
        .brand-header .brand-admin-row .admin-phone { color: var(--primary); font-weight: 700; }

        /* ---------- DETAIL CARDS ---------- */
        .detail-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 22px 26px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        .detail-card:hover {
            border-color: var(--primary-light);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }

        .detail-card .card-title-section {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary);
            flex-wrap: wrap;
        }

        .detail-card .card-title-section h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-card .card-title-section .badge-count {
            background: #E8F0FE;
            color: var(--primary);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            border: 1px solid #6EA8FE;
        }

        [data-theme="dark"] .detail-card .card-title-section .badge-count {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: #3B82F6;
        }

        .detail-label {
            font-size: 0.68rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-word;
        }

        /* ---------- GRIDS ---------- */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px 24px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px 24px; }

        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }

        /* ---------- STATUS BADGES ---------- */
        .status-badge-visit {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 4px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-badge-visit.pending      { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.assigned     { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.with_doctor  { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed    { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled    { background: #FEE2E2; color: #DC2626; }
        .status-badge-visit.paid         { background: #D1FAE5; color: #059669; }
        .status-badge-visit.partial      { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.in_progress  { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.lab_completed{ background: #D1FAE5; color: #059669; }
        .status-badge-visit.dispensed    { background: #D1FAE5; color: #059669; }
        .status-badge-visit.confirmed    { background: #E8F0FE; color: #0B5ED7; }

        [data-theme="dark"] .status-badge-visit.pending     { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.assigned    { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-visit.completed   { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-visit.cancelled   { background: #3A1A1A; color: #F87171; }

        /* ---------- VITAL SIGNS CARDS ---------- */
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
            border-radius: 14px;
            padding: 12px 10px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
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

        .vital-card.blue::before   { background: linear-gradient(90deg, #0B5ED7, #0A4CA8); }
        .vital-card.green::before  { background: linear-gradient(90deg, #059669, #047857); }
        .vital-card.purple::before { background: linear-gradient(90deg, #7C3AED, #6D28D9); }
        .vital-card.orange::before { background: linear-gradient(90deg, #D97706, #B45309); }
        .vital-card.red::before    { background: linear-gradient(90deg, #DC2626, #B91C1C); }
        .vital-card.teal::before   { background: linear-gradient(90deg, #0D9488, #0F766E); }
        .vital-card.cyan::before   { background: linear-gradient(90deg, #0891B2, #0E7490); }

        .vital-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(11, 94, 215, 0.15);
            border-color: var(--primary-light);
        }

        .vital-card .vital-icon { font-size: 1.4rem; display: block; margin-bottom: 4px; }

        .vital-card .vital-label {
            font-size: 0.55rem;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }

        .vital-card .vital-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-top: 4px;
            letter-spacing: -0.3px;
        }

        .vital-card .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .vital-card.blue .vital-value   { color: #0B5ED7; }
        .vital-card.green .vital-value  { color: #059669; }
        .vital-card.purple .vital-value { color: #7C3AED; }
        .vital-card.orange .vital-value { color: #D97706; }
        .vital-card.red .vital-value    { color: #DC2626; }
        .vital-card.teal .vital-value   { color: #0D9488; }
        .vital-card.cyan .vital-value   { color: #0891B2; }

        /* SpO2 Category Badge */
        .spo2-category {
            display: inline-block;
            font-size: 0.5rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 8px;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .spo2-category.normal   { background: rgba(8,145,178,0.15); color: #0891B2; }
        .spo2-category.low      { background: rgba(217,119,6,0.15); color: #D97706; animation: pulse-spo2 1.5s infinite; }
        .spo2-category.critical { background: rgba(220,38,38,0.2); color: #DC2626; font-weight: 900; animation: pulse-spo2 1s infinite; }

        @keyframes pulse-spo2 {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.5; }
        }

        /* ---------- BILL SUMMARY CARDS ---------- */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .bill-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 14px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .bill-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }

        .bill-card.total::before     { background: linear-gradient(90deg, #0B5ED7, #0A4CA8); }
        .bill-card.pending::before   { background: linear-gradient(90deg, #D97706, #B45309); }
        .bill-card.paid::before      { background: linear-gradient(90deg, #059669, #047857); }
        .bill-card.cancelled::before { background: linear-gradient(90deg, #DC2626, #B91C1C); }

        .bill-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .bill-card .bill-icon { font-size: 1.8rem; display: block; margin-bottom: 4px; }

        .bill-card .bill-amount {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .bill-card .bill-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-top: 4px;
        }

        .bill-card.total .bill-amount     { color: #0B5ED7; }
        .bill-card.pending .bill-amount   { color: #D97706; }
        .bill-card.paid .bill-amount      { color: #059669; }
        .bill-card.cancelled .bill-amount { color: #DC2626; }

        /* ---------- TABLES ---------- */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            background: var(--bg-card);
        }

        .table-wrapper table th {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .table-wrapper table th i { margin-right: 6px; opacity: 0.9; }

        .table-wrapper table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .table-wrapper table tr:last-child td { border-bottom: none; }

        .table-wrapper table tr:nth-child(even) td { background: var(--bg-body); }
        .table-wrapper table tr:hover td { background: var(--primary-bg); }

        [data-theme="dark"] .table-wrapper table tr:nth-child(even) td { background: #0F172A; }
        [data-theme="dark"] .table-wrapper table tr:hover td { background: #1E3A5F; }

        .table-wrapper table .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 3px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .table-wrapper table .status-badge.pending     { background: #FEF3C7; color: #D97706; }
        .table-wrapper table .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .table-wrapper table .status-badge.completed   { background: #D1FAE5; color: #059669; }
        .table-wrapper table .status-badge.cancelled   { background: #FEE2E2; color: #DC2626; }
        .table-wrapper table .status-badge.paid        { background: #D1FAE5; color: #059669; }
        .table-wrapper table .status-badge.dispensed   { background: #D1FAE5; color: #059669; }

        [data-theme="dark"] .table-wrapper table .status-badge.pending   { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .table-wrapper table .status-badge.completed { background: #1A3A2A; color: #34D399; }

        /* ---------- MEDICATION TABLE ---------- */
        .medication-table .med-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.85rem;
        }

        .medication-table .med-dosage,
        .medication-table .med-frequency {
            font-size: 0.72rem;
            color: var(--text-primary);
            display: inline-block;
            background: var(--bg-body);
            padding: 3px 10px;
            border-radius: 10px;
            font-weight: 600;
            border: 1px solid var(--border-color);
        }

        .medication-table .med-batch {
            font-size: 0.62rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 3px;
        }

        .medication-table .med-price {
            font-weight: 800;
            color: #059669;
            font-size: 0.8rem;
            display: block;
            margin-top: 3px;
        }

        .medication-table .med-quantity {
            font-weight: 800;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .medication-table .med-instruction {
            font-size: 0.72rem;
            color: var(--text-secondary);
            font-style: italic;
            background: #FEF3C7;
            padding: 4px 10px;
            border-radius: 8px;
            border-left: 3px solid #D97706;
            display: block;
        }

        [data-theme="dark"] .medication-table .med-instruction { background: #3D2E0A; color: #FBBF24; }

        /* ---------- DOCTOR AVATAR ---------- */
        .doctor-avatar-lg {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.2);
        }

        .patient-avatar-sm {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .tech-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .tech-info .tech-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .tech-info .tech-name {
            font-weight: 700;
            color: var(--text-primary);
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 1024px) {
            .vital-grid-7 { grid-template-columns: repeat(3, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-card { padding: 16px 18px; }
            .vital-grid-7 { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .col-span-2, .col-span-3 { grid-column: span 1; }
            .brand-header .brand-name { font-size: 1.2rem; }
            .brand-header .brand-logo img { height: 40px; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }

        /* ---------- PDF MODAL ---------- */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            backdrop-filter: blur(6px);
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .pdf-modal-overlay.active { display: flex; }

        .pdf-modal {
            background: var(--bg-card);
            border-radius: 20px;
            width: 100%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            flex-wrap: wrap;
            gap: 10px;
        }

        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 800;
            color: white;
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
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.4);
            border-color: rgba(220,38,38,0.3);
        }

        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 28px;
            background: #F1F5F9;
        }

        .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: white;
            padding: 24px 28px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            line-height: 1.5;
        }

        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; margin-top: 0 !important; }
        }

        .capitalize { text-transform: capitalize; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED HEADER & SIDEBAR (INCLUDED ABOVE) -->
<!-- ================================================================ -->

<main class="main-content">

    <?php if ($error): ?>
        <div style="background:#FEE2E2;border:2px solid #DC2626;border-radius:16px;padding:30px 24px;text-align:center;max-width:600px;margin:40px auto;">
            <i class="fas fa-exclamation-circle" style="font-size:3rem;color:#DC2626;display:block;margin-bottom:14px;"></i>
            <h3 style="font-size:1.3rem;font-weight:800;color:#DC2626;margin-bottom:8px;">Error</h3>
            <p style="color:var(--text-secondary);margin:8px 0 20px;"><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;background:linear-gradient(135deg,#0B5ED7,#0A4CA8);color:white;border-radius:12px;text-decoration:none;font-weight:700;font-size:0.85rem;">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>
    <?php elseif ($visit): ?>

    <!-- ================================================================ -->
    <!-- BRAND HEADER (Print only - hidden on screen) -->
    <!-- ================================================================ -->
    <div class="brand-header no-print" style="display:none;">
        <div class="brand-logo">
            <img src="<?= $logo_path ?>" alt="Braick Logo" onerror="this.style.display='none'">
            <span class="brand-name">BRAICK DISPENSARY</span>
        </div>
        <div class="brand-tagline">Tunajali Afya Yako</div>
    </div>

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Visit Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                Complete visit information
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
                <span class="header-badge status-badge-visit <?= $visit['status'] ?>" style="background:rgba(255,255,255,0.2);color:white;">
                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                    <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="visits.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light danger">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 1. VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-info-circle" style="color:#0B5ED7;font-size:1.3rem;"></i>
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
                    <span class="status-badge-visit <?= $visit['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label">Visit Type</p>
                <p class="detail-value capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></p>
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
                <p class="detail-value" style="color:#059669;font-weight:800;">TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></p>
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

    <!-- ================================================================ -->
    <!-- 2. PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user" style="color:#0B5ED7;font-size:1.3rem;"></i>
            <h3>2. Patient Information</h3>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
            <div class="patient-avatar-sm" style="background: linear-gradient(135deg, <?= '#' . substr(md5($visit['patient_name']), 0, 6) ?>, <?= '#' . substr(md5($visit['patient_name'] . 'x'), 0, 6) ?>);">
                <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
            </div>
            <div>
                <p style="font-size:1.05rem;font-weight:800;color:var(--text-primary);"><?= htmlspecialchars($visit['patient_name']) ?></p>
                <p style="font-size:0.75rem;color:var(--text-secondary);font-family:'JetBrains Mono',monospace;">ID: <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></p>
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
                    <p class="detail-value" style="color:#DC2626;">⚠️ <?= htmlspecialchars($visit['allergies']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3. DOCTOR INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-user-md" style="color:#0B5ED7;font-size:1.3rem;"></i>
            <h3>3. Doctor Information</h3>
        </div>
        <?php if ($visit['doctor_id']): ?>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <?php if (!empty($visit['doctor_profile_pic'])): ?>
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $visit['doctor_profile_pic'] ?>"
                         alt="Doctor" class="doctor-avatar-lg"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2256%22 height=%2256%22%3E%3Crect width=%2256%22 height=%2256%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2228%22 y=%2236%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2222%22 font-weight=%22bold%22%3E<?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
                <?php else: ?>
                    <div class="doctor-avatar-lg" style="background:linear-gradient(135deg,#0B5ED7,#0A4CA8);display:flex;align-items:center;justify-content:center;color:white;font-size:1.4rem;font-weight:800;">
                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div style="flex:1;min-width:200px;">
                    <p style="font-size:1rem;font-weight:800;color:var(--text-primary);">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></p>
                    <p style="font-size:0.78rem;color:var(--text-secondary);font-weight:600;">
                        <i class="fas fa-stethoscope" style="color:#0B5ED7;"></i> <?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?>
                    </p>
                    <?php if (!empty($visit['doctor_phone'])): ?>
                        <p style="font-size:0.72rem;color:var(--text-secondary);margin-top:2px;">
                            <i class="fas fa-phone" style="color:#059669;"></i> <?= htmlspecialchars($visit['doctor_phone']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($visit['doctor_is_online'] ?? 0): ?>
                    <span class="status-badge-visit completed"><i class="fas fa-circle" style="font-size:0.45rem;"></i> Online</span>
                <?php else: ?>
                    <span class="status-badge-visit cancelled"><i class="fas fa-circle" style="font-size:0.45rem;"></i> Offline</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);font-style:italic;text-align:center;padding:12px 0;">No doctor assigned to this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 4. VITAL SIGNS (7 CARDS WITH SPO2) -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-heartbeat" style="color:#DC2626;font-size:1.3rem;"></i>
            <h3>4. Vital Signs</h3>
            <span class="badge-count">
                <i class="fas fa-clock"></i>
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
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                    <span class="vital-unit">mmHg</span>
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
            <div style="margin-top:14px;padding:10px 14px;background:var(--bg-body);border-radius:10px;font-size:0.78rem;color:var(--text-secondary);border-left:3px solid #0B5ED7;">
                <i class="fas fa-sticky-note" style="color:#0B5ED7;"></i> <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 5. CLINICAL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-file-medical-alt" style="color:#0B5ED7;font-size:1.3rem;"></i>
            <h3>5. Clinical Information</h3>
        </div>
        <div class="grid-2">
            <?php if (!empty($visit['complaint'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-exclamation-circle" style="color:#0B5ED7;"></i> Complaint</p>
                    <p class="detail-value" style="background:#E8F0FE;padding:10px 14px;border-radius:10px;border-left:4px solid #0B5ED7;">
                        <?= nl2br(htmlspecialchars($visit['complaint'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['symptoms'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-list-ul" style="color:#D97706;"></i> Symptoms</p>
                    <p class="detail-value" style="background:#FEF3C7;padding:10px 14px;border-radius:10px;border-left:4px solid #D97706;">
                        <?= nl2br(htmlspecialchars($visit['symptoms'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['hpi'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-history" style="color:#7C3AED;"></i> HPI</p>
                    <p class="detail-value" style="background:#EDE9FE;padding:10px 14px;border-radius:10px;border-left:4px solid #7C3AED;">
                        <?= nl2br(htmlspecialchars($visit['hpi'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['physical_exam'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-stethoscope" style="color:#059669;"></i> Physical Exam</p>
                    <p class="detail-value" style="background:#D1FAE5;padding:10px 14px;border-radius:10px;border-left:4px solid #059669;">
                        <?= nl2br(htmlspecialchars($visit['physical_exam'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (!empty($visit['notes'])): ?>
                <div class="col-span-2">
                    <p class="detail-label"><i class="fas fa-sticky-note" style="color:#64748B;"></i> Additional Notes</p>
                    <p class="detail-value" style="background:var(--bg-body);padding:10px 14px;border-radius:10px;border-left:4px solid #94A3B8;">
                        <?= nl2br(htmlspecialchars($visit['notes'])) ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (empty($visit['complaint']) && empty($visit['symptoms']) && empty($visit['hpi']) && empty($visit['physical_exam']) && empty($visit['notes'])): ?>
                <div class="col-span-2">
                    <p style="color:var(--text-secondary);font-style:italic;text-align:center;padding:14px;">No clinical information recorded for this visit</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 6. LAB TESTS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-flask" style="color:#7C3AED;font-size:1.3rem;"></i>
            <h3>6. Lab Tests</h3>
            <span class="badge-count"><?= count($lab_tests) ?> test(s)</span>
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
                                        <span style="font-size:0.65rem;color:var(--text-secondary);display:block;font-family:monospace;"><?= htmlspecialchars($test['test_code']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.75rem;"><?= isset($test['created_at']) ? date('M d, Y h:i A', strtotime($test['created_at'])) : 'N/A' ?></td>
                                <td>
                                    <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:#059669;font-weight:700;">✅ <?= htmlspecialchars(substr($test['results'], 0, 60)) ?><?= strlen($test['results']) > 60 ? '...' : '' ?></span>
                                        <?php if (!empty($test['reference_range'])): ?>
                                            <span style="font-size:0.65rem;color:var(--text-secondary);display:block;margin-top:2px;">Ref: <?= htmlspecialchars($test['reference_range']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-style:italic;">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['technician_name'])): ?>
                                        <div class="tech-info">
                                            <?php if (!empty($test['technician_profile_pic'])): ?>
                                                <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $test['technician_profile_pic'] ?>" alt="Tech" class="tech-avatar" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <div class="tech-avatar" style="background:linear-gradient(135deg,#0B5ED7,#0A4CA8);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:0.7rem;">
                                                    <?= strtoupper(substr($test['technician_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="tech-name"><?= htmlspecialchars($test['technician_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.72rem;font-style:italic;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px;font-style:italic;">No lab tests found for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 7. DIAGNOSIS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-stethoscope" style="color:#0B5ED7;font-size:1.3rem;"></i>
            <h3>7. Diagnosis</h3>
        </div>
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name']) || !empty($visit['treatment'])): ?>
            <div class="grid-2">
                <?php if (!empty($visit['disease_name'])): ?>
                    <div>
                        <p class="detail-label">Disease Name</p>
                        <p class="detail-value" style="font-size:1rem;color:#0B5ED7;"><?= htmlspecialchars($visit['disease_name']) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['disease_code_full']) || !empty($visit['icd_code'])): ?>
                    <div>
                        <p class="detail-label">Disease Code</p>
                        <p class="detail-value">
                            <?php if (!empty($visit['disease_code_full'])): ?>
                                <span style="background:#E8F0FE;color:#0B5ED7;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;"><?= htmlspecialchars($visit['disease_code_full']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($visit['icd_code'])): ?>
                                <span style="background:var(--bg-body);color:var(--text-secondary);padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;margin-left:6px;">ICD: <?= htmlspecialchars($visit['icd_code']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Diagnosis Description</p>
                        <p class="detail-value" style="background:#E8F0FE;padding:12px 16px;border-radius:10px;border-left:4px solid #0B5ED7;">
                            <?= nl2br(htmlspecialchars($visit['diagnosis'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label"><i class="fas fa-prescription" style="color:#059669;"></i> Treatment</p>
                        <p class="detail-value" style="background:#D1FAE5;padding:12px 16px;border-radius:10px;border-left:4px solid #059669;">
                            <?= nl2br(htmlspecialchars($visit['treatment'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px;font-style:italic;">No diagnosis recorded for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 8. MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-prescription" style="color:#059669;font-size:1.3rem;"></i>
            <h3>8. Medications</h3>
            <span class="badge-count"><?= count($prescriptions) ?> prescription(s)</span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pres):
                $items = $prescription_items[$pres['id']] ?? [];
            ?>
                <div style="margin-bottom:14px;padding:16px;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <div>
                            <strong style="color:#0B5ED7;font-size:0.95rem;">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong>
                            <?php if (!empty($pres['diagnosis'])): ?>
                                <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:10px;">
                                    <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($pres['diagnosis']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge-visit <?= $pres['status'] ?? 'pending' ?>">
                            <?= ucfirst($pres['status'] ?? 'Pending') ?>
                        </span>
                    </div>

                    <?php if (!empty($pres['instructions'])): ?>
                        <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:12px;background:#FEF3C7;padding:8px 12px;border-radius:8px;border-left:3px solid #D97706;">
                            <i class="fas fa-info-circle" style="color:#D97706;"></i> <?= nl2br(htmlspecialchars($pres['instructions'])) ?>
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
                                            <td><span class="med-dosage"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></span>
                                                <?php if (!empty($item['duration'])): ?>
                                                    <div style="font-size:0.68rem;color:var(--text-secondary);margin-top:3px;">Dur: <?= htmlspecialchars($item['duration']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="med-frequency"><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></span></td>
                                            <td>
                                                <span class="med-quantity"><?= $item['quantity'] ?? 0 ?></span>
                                                <span style="font-size:0.65rem;color:var(--text-secondary);"> units</span>
                                            </td>
                                            <td>
                                                <?php if (!empty($item['instructions'])): ?>
                                                    <div class="med-instruction"><?= htmlspecialchars($item['instructions']) ?></div>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary);font-size:0.72rem;font-style:italic;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.75rem;color:var(--text-secondary);font-style:italic;">No medication items found</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px;font-style:italic;">No medications prescribed for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 9. PROCEDURES & EQUIPMENT -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-syringe" style="color:#7C3AED;font-size:1.3rem;"></i>
            <h3>9. Procedures & Equipment Used</h3>
            <span class="badge-count"><?= count($procedures) ?> procedure(s)</span>
        </div>
        <?php if (count($procedures) > 0 || count($equipment_used) > 0): ?>
            <?php if (count($procedures) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-syringe"></i> Procedure</th>
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
                                            <span style="font-size:0.65rem;color:var(--text-secondary);display:block;font-family:monospace;"><?= htmlspecialchars($proc['procedure_code']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.72rem;"><?= isset($proc['created_at']) ? date('M d, Y h:i A', strtotime($proc['created_at'])) : 'N/A' ?></td>
                                    <td>
                                        <span class="status-badge <?= $proc['status'] ?? 'pending' ?>">
                                            <?= ucfirst($proc['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($proc['equipment_name'])): ?>
                                            <span style="font-size:0.75rem;font-weight:600;"><?= htmlspecialchars($proc['equipment_name']) ?></span>
                                            <?php if (!empty($proc['equipment_batch'])): ?>
                                                <span style="font-size:0.6rem;color:var(--text-secondary);display:block;">Batch: <?= htmlspecialchars($proc['equipment_batch']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.72rem;font-style:italic;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= $proc['equipment_used_quantity'] ?? 1 ?></strong></td>
                                    <td style="font-weight:700;color:#059669;">
                                        <?= !empty($proc['procedure_price']) ? 'TSh ' . number_format($proc['procedure_price'], 0) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (count($equipment_used) > 0): ?>
                <div style="margin-top:16px;">
                    <p style="font-size:0.72rem;font-weight:800;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">
                        <i class="fas fa-tools" style="color:#7C3AED;"></i> Medical Equipment Used
                    </p>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tools"></i> Equipment</th>
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
                                        <td><span style="font-size:0.72rem;color:var(--text-secondary);font-family:monospace;"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></span></td>
                                        <td><strong><?= $eq['quantity'] ?? 0 ?></strong></td>
                                        <td><?= htmlspecialchars($eq['unit'] ?? 'pcs') ?></td>
                                        <td>
                                            <span style="font-size:0.68rem;color:var(--text-secondary);text-transform:capitalize;font-weight:700;">
                                                <?= htmlspecialchars($eq['reference_type'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px;font-style:italic;">No procedures or equipment used for this visit</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- 10. BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title-section">
            <i class="fas fa-money-bill-wave" style="color:#059669;font-size:1.3rem;"></i>
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

            <div style="margin-top:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:0.78rem;font-weight:700;color:var(--text-secondary);">Payment Status:</span>
                <span class="status-badge-visit <?= $total_balance <= 0 ? 'paid' : 'partial' ?>">
                    <i class="fas <?= $total_balance <= 0 ? 'fa-check-circle' : 'fa-hourglass-half' ?>"></i>
                    <?= $total_balance <= 0 ? 'Fully Paid' : 'Pending / Partial' ?>
                </span>
                <?php if ($total_balance > 0): ?>
                    <span style="font-size:0.75rem;color:#DC2626;font-weight:700;">
                        Balance Due: TSh <?= number_format($total_balance, 0) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (count($bills) > 0): ?>
                <div class="table-wrapper" style="margin-top:16px;">
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
                                    <td><strong style="font-family:monospace;color:#0B5ED7;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong></td>
                                    <td style="font-weight:700;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                    <td style="font-weight:700;color:#059669;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                    <td><strong style="color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;font-weight:800;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                                            <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);text-align:center;padding:20px;font-style:italic;">No bills found for this visit</p>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
            <i class="fas fa-clinic-medical" style="font-size:3.5rem;display:block;margin-bottom:14px;opacity:0.4;"></i>
            <p style="font-size:1.1rem;font-weight:700;">Visit not found</p>
            <a href="visits.php" style="color:#0B5ED7;font-weight:700;text-decoration:none;">Back to visits</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Visit Details
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
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
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // TOAST (fallback if not defined by header)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            var toastTitle = document.getElementById('toastTitle');
            var toastMessage = document.getElementById('toastMessage');
            toast.className = 'toast-custom ' + (type || 'info');
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.style.display = 'none'; }, 400);
            }, 3500);
        };
    }

    // ================================================================
    // GENERATE PDF
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');

        var hasVitalSigns = <?= $vital_signs ? 'true' : 'false' ?>;
        var hasLabTests   = <?= count($lab_tests) > 0 ? 'true' : 'false' ?>;
        var hasPrescriptions = <?= count($prescriptions) > 0 ? 'true' : 'false' ?>;
        var hasProcedures = <?= (count($procedures) > 0 || count($equipment_used) > 0) ? 'true' : 'false' ?>;
        var hasBills      = <?= !empty($bills) ? 'true' : 'false' ?>;

        // Build vital signs HTML
        var vitalSignsHTML = '';
        if (hasVitalSigns) {
            var spo2HTML = '';
            <?php if (!empty($vital_signs['oxygen_saturation'])):
                $spo2 = (int)$vital_signs['oxygen_saturation'];
                $spo2_color = $spo2 >= 95 ? '#0891B2' : ($spo2 >= 90 ? '#D97706' : '#DC2626');
                $spo2_bg = $spo2 >= 95 ? '#CFFAFE' : ($spo2 >= 90 ? '#FEF3C7' : '#FEE2E2');
                $spo2_label = $spo2 >= 95 ? 'Normal' : ($spo2 >= 90 ? 'Low' : 'Critical');
            ?>
            spo2HTML = `<div style="background:<?= $spo2_bg ?>;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid <?= $spo2_color ?>;">
                <div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">🫁 Oxygen Sat.</div>
                <div style="font-weight:800;color:<?= $spo2_color ?>;font-size:14px;"><?= $spo2 ?> %</div>
                <div style="font-size:9px;font-weight:800;color:<?= $spo2_color ?>;text-transform:uppercase;margin-top:2px;"><?= $spo2_label ?></div>
            </div>`;
            <?php endif; ?>

            vitalSignsHTML = `
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                    <div style="background:#E8F0FE;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid #0B5ED7;"><div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">🌡️ Temperature</div><div style="font-weight:800;color:#0B5ED7;font-size:14px;"><?= $vital_signs['temperature'] ?? 'N/A' ?> °C</div></div>
                    <div style="background:#D1FAE5;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid #059669;"><div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">❤️ Blood Pressure</div><div style="font-weight:800;color:#059669;font-size:14px;"><?= !empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic']) ? $vital_signs['blood_pressure_systolic'] . '/' . $vital_signs['blood_pressure_diastolic'] . ' mmHg' : 'N/A' ?></div></div>
                    <div style="background:#EDE9FE;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid #7C3AED;"><div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">💓 Pulse Rate</div><div style="font-weight:800;color:#7C3AED;font-size:14px;"><?= $vital_signs['pulse_rate'] ?? 'N/A' ?> bpm</div></div>
                    <div style="background:#FEF3C7;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid #D97706;"><div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">⚖️ Weight</div><div style="font-weight:800;color:#D97706;font-size:14px;"><?= $vital_signs['weight'] ?? 'N/A' ?> kg</div></div>
                    <div style="background:#D1FAE5;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid #0D9488;"><div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">📏 Height</div><div style="font-weight:800;color:#0D9488;font-size:14px;"><?= $vital_signs['height'] ?? 'N/A' ?> cm</div></div>
                    <div style="background:#FEE2E2;padding:6px 8px;border-radius:8px;text-align:center;border-left:3px solid #DC2626;"><div style="font-size:0.55rem;font-weight:800;color:#64748B;text-transform:uppercase;">📊 BMI</div><div style="font-weight:800;color:#DC2626;font-size:14px;"><?= $vital_signs['bmi'] ?? 'N/A' ?> kg/m²</div></div>
                    ` + spo2HTML + `
                </div>
            `;
        } else {
            vitalSignsHTML = `<p style="color:#94A3B8;font-style:italic;text-align:center;padding:10px;font-size:13px;">No vital signs recorded for this visit</p>`;
        }

        // Build clinical info HTML
        var clinicalHTML = '';
        <?php if (!empty($visit['complaint'])): ?>
        clinicalHTML += `<div style="background:#E8F0FE;padding:8px 12px;border-radius:8px;border-left:3px solid #0B5ED7;margin-bottom:6px;font-size:13px;"><strong style="color:#64748B;">Complaint:</strong> <?= nl2br(htmlspecialchars($visit['complaint'])) ?></div>`;
        <?php endif; ?>
        <?php if (!empty($visit['symptoms'])): ?>
        clinicalHTML += `<div style="background:#FEF3C7;padding:8px 12px;border-radius:8px;border-left:3px solid #D97706;margin-bottom:6px;font-size:13px;"><strong style="color:#64748B;">Symptoms:</strong> <?= nl2br(htmlspecialchars($visit['symptoms'])) ?></div>`;
        <?php endif; ?>
        <?php if (!empty($visit['hpi'])): ?>
        clinicalHTML += `<div style="background:#EDE9FE;padding:8px 12px;border-radius:8px;border-left:3px solid #7C3AED;margin-bottom:6px;font-size:13px;"><strong style="color:#64748B;">HPI:</strong> <?= nl2br(htmlspecialchars($visit['hpi'])) ?></div>`;
        <?php endif; ?>
        <?php if (!empty($visit['physical_exam'])): ?>
        clinicalHTML += `<div style="background:#D1FAE5;padding:8px 12px;border-radius:8px;border-left:3px solid #059669;margin-bottom:6px;font-size:13px;"><strong style="color:#64748B;">Physical Exam:</strong> <?= nl2br(htmlspecialchars($visit['physical_exam'])) ?></div>`;
        <?php endif; ?>
        <?php if (!empty($visit['notes'])): ?>
        clinicalHTML += `<div style="background:#F1F5F9;padding:8px 12px;border-radius:8px;border-left:3px solid #94A3B8;margin-bottom:6px;font-size:13px;"><strong style="color:#64748B;">Notes:</strong> <?= nl2br(htmlspecialchars($visit['notes'])) ?></div>`;
        <?php endif; ?>
        if (clinicalHTML === '') {
            clinicalHTML = `<p style="color:#94A3B8;font-style:italic;text-align:center;padding:10px;font-size:13px;">No clinical information recorded for this visit</p>`;
        }

        // Build HTML
        var html = `
            <div style="text-align:center;padding-bottom:14px;border-bottom:3px solid #0B5ED7;margin-bottom:16px;">
                <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;display:block;margin:0 auto 6px;" onerror="this.style.display='none'">
                <div style="font-size:1.4rem;font-weight:800;color:#0B5ED7;letter-spacing:-0.5px;">BRAICK DISPENSARY</div>
                <div style="font-size:0.75rem;color:#64748B;">Tunajali Afya Yako</div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px solid #E2E8F0;font-size:11px;color:#64748B;">
                    <span>📞 <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?></span>
                    <span>🏢 <?= htmlspecialchars($visit['branch_name'] ?? 'Dodoma') ?></span>
                    <span>📅 <?= date('M d, Y') ?></span>
                </div>
                <div style="font-size:13px;font-weight:700;color:#0B5ED7;margin-top:8px;background:#E8F0FE;padding:6px 16px;border-radius:20px;display:inline-block;">
                    📋 Visit Report - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </div>
            </div>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:14px 0 10px 0;">1. Visit Information</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;font-size:13px;">
                <div><span style="font-weight:700;color:#64748B;">Visit Number:</span> <strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></div>
                <div><span style="font-weight:700;color:#64748B;">Status:</span> <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'N/A')) ?></div>
                <div><span style="font-weight:700;color:#64748B;">Visit Type:</span> <?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></div>
                <div><span style="font-weight:700;color:#64748B;">Date:</span> <?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></div>
                <div style="grid-column:span 2;"><span style="font-weight:700;color:#64748B;">Branch:</span> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></div>
                <div style="grid-column:span 2;"><span style="font-weight:700;color:#64748B;">Consultation Fee:</span> TSh <?= number_format($visit['consultation_fee'] ?? 0, 0) ?></div>
            </div>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">2. Patient Information</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;font-size:13px;">
                <div style="grid-column:span 2;"><span style="font-weight:700;color:#64748B;">Name:</span> <strong><?= htmlspecialchars($visit['patient_name']) ?></strong></div>
                <div><span style="font-weight:700;color:#64748B;">Patient ID:</span> <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></div>
                <div><span style="font-weight:700;color:#64748B;">Phone:</span> <?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></div>
                <div><span style="font-weight:700;color:#64748B;">Gender:</span> <?= ucfirst($visit['gender'] ?? 'N/A') ?></div>
                <div><span style="font-weight:700;color:#64748B;">Blood Group:</span> <?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></div>
                <div style="grid-column:span 2;"><span style="font-weight:700;color:#64748B;">Address:</span> <?= htmlspecialchars($visit['address'] ?? 'N/A') ?></div>
            </div>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">3. Doctor Information</div>
            <?php if ($visit['doctor_id']): ?>
            <div style="font-size:13px;">
                <div><span style="font-weight:700;color:#64748B;">Doctor:</span> <strong>Dr. <?= htmlspecialchars($visit['doctor_name']) ?></strong></div>
                <div><span style="font-weight:700;color:#64748B;">Specialty:</span> <?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></div>
                <?php if (!empty($visit['doctor_phone'])): ?>
                <div><span style="font-weight:700;color:#64748B;">Phone:</span> <?= htmlspecialchars($visit['doctor_phone']) ?></div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="color:#94A3B8;font-style:italic;font-size:13px;">No doctor assigned</p>
            <?php endif; ?>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">4. Vital Signs</div>
            ` + vitalSignsHTML + `

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">5. Clinical Information</div>
            ` + clinicalHTML + `

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">6. Lab Tests</div>
            <?php if ($hasLabTests): ?>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#059669;color:white;"><th style="padding:6px;text-align:left;">Test</th><th style="padding:6px;text-align:left;">Status</th><th style="padding:6px;text-align:left;">Results</th></tr></thead>
                <tbody>
                    <?php foreach ($lab_tests as $test): ?>
                    <tr style="border-bottom:1px solid #E2E8F0;"><td style="padding:6px;"><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td><td style="padding:6px;"><?= ucfirst($test['status'] ?? 'Pending') ?></td><td style="padding:6px;"><?= !empty($test['results']) ? htmlspecialchars(substr($test['results'], 0, 40)) : 'Pending' ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#94A3B8;font-style:italic;font-size:13px;">No lab tests</p>
            <?php endif; ?>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">7. Diagnosis</div>
            <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
            <div style="font-size:13px;">
                <?php if (!empty($visit['disease_name'])): ?>
                <div><span style="font-weight:700;color:#64748B;">Disease:</span> <strong><?= htmlspecialchars($visit['disease_name']) ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                <div style="margin-top:4px;background:#E8F0FE;padding:8px 12px;border-radius:8px;border-left:3px solid #0B5ED7;"><?= nl2br(htmlspecialchars($visit['diagnosis'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                <div style="margin-top:6px;background:#D1FAE5;padding:8px 12px;border-radius:8px;border-left:3px solid #059669;"><strong>Treatment:</strong> <?= nl2br(htmlspecialchars($visit['treatment'])) ?></div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="color:#94A3B8;font-style:italic;font-size:13px;">No diagnosis</p>
            <?php endif; ?>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">8. Medications</div>
            <?php if ($hasPrescriptions): ?>
                <?php foreach ($prescriptions as $pres):
                    $items = $prescription_items[$pres['id']] ?? [];
                ?>
                <div style="margin-bottom:8px;padding:8px 12px;background:#F8FAFC;border-radius:8px;border:1px solid #E2E8F0;font-size:13px;">
                    <div><strong>#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></strong> - <?= ucfirst($pres['status'] ?? 'Pending') ?></div>
                    <?php if (count($items) > 0): ?>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:4px;">
                        <thead><tr style="background:#059669;color:white;"><th style="padding:4px;text-align:left;">Medication</th><th style="padding:4px;text-align:left;">Dosage</th><th style="padding:4px;text-align:left;">Frequency</th><th style="padding:4px;text-align:left;">Qty</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr style="border-bottom:1px solid #E2E8F0;"><td style="padding:4px;"><strong><?= htmlspecialchars($item['medication_name'] ?? $item['inventory_medication_name'] ?? 'N/A') ?></strong></td><td style="padding:4px;"><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td><td style="padding:4px;"><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td><td style="padding:4px;"><?= $item['quantity'] ?? 0 ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <p style="color:#94A3B8;font-style:italic;font-size:13px;">No medications</p>
            <?php endif; ?>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">9. Procedures & Equipment</div>
            <?php if ($hasProcedures): ?>
            <?php if (count($procedures) > 0): ?>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#059669;color:white;"><th style="padding:6px;text-align:left;">Procedure</th><th style="padding:6px;text-align:left;">Status</th><th style="padding:6px;text-align:left;">Equipment</th></tr></thead>
                <tbody>
                    <?php foreach ($procedures as $proc): ?>
                    <tr style="border-bottom:1px solid #E2E8F0;"><td style="padding:6px;"><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td><td style="padding:6px;"><?= ucfirst($proc['status'] ?? 'Pending') ?></td><td style="padding:6px;"><?= htmlspecialchars($proc['equipment_name'] ?? 'None') ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php else: ?>
            <p style="color:#94A3B8;font-style:italic;font-size:13px;">No procedures</p>
            <?php endif; ?>

            <div style="font-size:14px;font-weight:800;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:6px;margin:16px 0 10px 0;">10. Bill Summary</div>
            <?php if ($hasBills): ?>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:10px;">
                <div style="background:#E8F0FE;padding:8px;border-radius:8px;text-align:center;border:1px solid #6EA8FE;"><div style="font-size:15px;font-weight:800;color:#0B5ED7;">TSh <?= number_format($total_amount, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:700;">💰 Total</div></div>
                <div style="background:#FEF3C7;padding:8px;border-radius:8px;text-align:center;border:1px solid #D97706;"><div style="font-size:15px;font-weight:800;color:#D97706;">TSh <?= number_format($total_amount - $total_paid, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:700;">⏳ Pending</div></div>
                <div style="background:#D1FAE5;padding:8px;border-radius:8px;text-align:center;border:1px solid #059669;"><div style="font-size:15px;font-weight:800;color:#059669;">TSh <?= number_format($total_paid, 0) ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:700;">✅ Paid</div></div>
                <div style="background:#FEE2E2;padding:8px;border-radius:8px;text-align:center;border:1px solid #DC2626;"><div style="font-size:15px;font-weight:800;color:#DC2626;"><?= $cancelled_bills ?></div><div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:700;">❌ Cancelled</div></div>
            </div>
            <?php else: ?>
            <p style="color:#94A3B8;font-style:italic;font-size:13px;">No bills</p>
            <?php endif; ?>

            <div style="margin-top:24px;padding-top:14px;border-top:2px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="font-size:13px;color:#64748B;">
                    <div>Technician: _________________</div>
                    <div style="margin-top:4px;">Date: <?= date('F d, Y') ?></div>
                </div>
                <div style="text-align:center;padding:8px 20px;border:3px solid #0B5ED7;border-radius:10px;background:#E8F0FE;">
                    <div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:1px;font-weight:800;">Official Stamp</div>
                    <div style="font-size:14px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                    <div style="font-size:11px;color:#64748B;margin-top:3px;">Approved: _________________</div>
                    <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: <?= date('F d, Y') ?></div>
                </div>
            </div>
            <div style="text-align:center;margin-top:10px;font-size:11px;color:#94A3B8;">
                Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
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
            margin: [10, 10, 10, 10],
            filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>_<?= $visit['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true
            },
            jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: 'portrait'
            },
            pagebreak: {
                mode: ['css', 'legacy']
            }
        };

        html2pdf().set(opt).from(element).save();
    }

    // ESC + click outside
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePDFModal();
    });

    document.getElementById('pdfModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePDFModal();
    });

    console.log('%c🏥 Braick - Visit Details (V2 - SHARED HEADER)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared reception_header.php & reception_sidebar.php', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c❤️ 7 Vital Signs with SpO2', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>