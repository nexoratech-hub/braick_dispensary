<?php
// ================================================================
// FILE: frontend/pages/reception/view_patient.php
// VIEW PATIENT - COMPLETE PATIENT DETAILS WITH PDF
// BRAICK DISPENSARY - TUNAJARI AFYA YAKO
// ✅ Inatumia shared header + sidebar
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
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
        default:           header('Location: ../login.php');                break;
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

$message      = '';
$message_type = '';
$patient_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_patient');
    exit;
}

// ================================================================
// INIT VARIABLES
// ================================================================
$patient              = null;
$active_visit         = null;
$visit_history        = [];
$bills                = [];
$bill_items           = [];
$procedures           = [];
$tools                = [];
$latest_vitals        = null;
$prescriptions        = [];
$lab_tests            = [];
$vital_signs          = [];
$age                  = 'N/A';
$logo_path            = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();

    // Unread notifications
    try {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = (int)($stmt->fetch()['total'] ?? 0);
    } catch (Exception $e) {
        $unread_notifications = 0;
    }

    // ================================================================
    // GET PATIENT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT
            p.*,
            u.full_name AS created_by_name,
            b.name AS branch_name,
            doc.full_name AS assigned_doctor_name,
            doc.is_online AS assigned_doctor_online
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users doc ON p.assigned_doctor_id = doc.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        header('Location: patients.php?error=patient_not_found');
        exit;
    }

    // ================================================================
    // ACTIVE VISIT
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, u.full_name AS doctor_name, u.is_online AS doctor_online
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.status IN ('pending','assigned','with_doctor','lab_test')
        ORDER BY v.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $active_visit = $stmt->fetch(PDO::FETCH_ASSOC);

    // ================================================================
    // VISIT HISTORY
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, u.full_name AS doctor_name,
               b.total_amount AS bill_amount, b.status AS bill_status, b.bill_number
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN bills b ON v.id = b.visit_id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visit_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // BILLS
    // ================================================================
    $stmt = $db->prepare("
        SELECT b.*, v.visit_number, u.full_name AS created_by_name
        FROM bills b
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bill items
    $bill_items = [];
    foreach ($bills as $bill) {
        if (!empty($bill['id'])) {
            $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
            $stmt->execute([$bill['id']]);
            $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // ================================================================
    // PROCEDURES
    // ================================================================
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at AS bill_date
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'procedure'
        ORDER BY bi.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // TOOLS
    // ================================================================
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at AS bill_date
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'equipment'
        ORDER BY bi.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // LATEST VITALS (7 SIGNS)
    // ================================================================
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name AS recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);

    // ================================================================
    // PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, u.full_name AS doctor_name
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // LAB TESTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name AS doctor_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.visit_id IN (SELECT id FROM visits WHERE patient_id = ?)
        ORDER BY lt.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Age
    if (!empty($patient['date_of_birth'])) {
        try {
            $birthDate = new DateTime($patient['date_of_birth']);
            $today     = new DateTime('today');
            $age       = $birthDate->diff($today)->y;
        } catch (Exception $e) { $age = 'N/A'; }
    }

    if (!empty($patient['branch_name'])) {
        $branch_name = $patient['branch_name'];
    }

} catch (Exception $e) {
    $message      = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patient      = null;
}

// ================================================================
// PROFILE PICTURE URL
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
    <title>View Patient - Braick Dispensary</title>

    <link rel="icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* ================================================================
           ROOT VARIABLES (local fallback)
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #1A7AFF);
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --sky: #0EA5E9;
            --sky-dark: #0284C7;
            --sky-bg: #E0F2FE;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-blue: 0 4px 20px rgba(11, 94, 215, 0.15);
            --radius: 12px;
            --radius-lg: 18px;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --purple-bg: #2D1B5F;
            --sky-bg: #0C2A3A;
        }

        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 22px 30px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.28);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 320px; height: 320px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: #fff;
            font-size: 1.55rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header .page-title i { font-size: 1.7rem; opacity: .95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.78rem;
            transition: all .25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
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

        /* ================================================================
           PROFILE HEADER CARD
           ================================================================ */
        .profile-header {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--primary-light);
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
            align-items: center;
            box-shadow: var(--shadow-blue);
            transition: all .3s ease;
        }
        .profile-header:hover {
            border-color: var(--primary);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
        }

        .profile-avatar {
            width: 92px; height: 92px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.6rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }
        .profile-avatar.avatar-male    { background: linear-gradient(135deg,#0B5ED7,#1A7AFF); }
        .profile-avatar.avatar-female  { background: linear-gradient(135deg,#DC2626,#F87171); }
        .profile-avatar.avatar-other   { background: linear-gradient(135deg,#7C3AED,#A78BFA); }
        .profile-avatar.avatar-default { background: linear-gradient(135deg,#0B5ED7,#0A4CA8); }

        .profile-info { flex: 1; min-width: 220px; }
        .profile-info .patient-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .profile-info .patient-id {
            font-size: 0.8rem;
            font-family: 'Courier New', monospace;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 3px 12px;
            border-radius: 12px;
            display: inline-block;
            margin-left: 8px;
        }
        .profile-info .patient-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 10px;
        }
        .profile-info .patient-meta .meta-item {
            font-size: 0.76rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .profile-info .patient-meta .meta-item i {
            color: var(--primary);
            width: 16px;
        }

        .profile-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ================================================================
           DETAIL CARDS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 22px 26px;
            border: 2px solid var(--border-color);
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
            transition: all .3s ease;
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-blue);
        }

        .detail-card .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-card .card-title i { color: var(--primary); font-size: 1rem; }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
        }
        .detail-grid .detail-item {
            display: flex;
            flex-direction: column;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-grid .detail-item .detail-label {
            font-size: 0.62rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .detail-grid .detail-item .detail-value {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* ================================================================
           VITAL SIGNS — 7 GRID
           ================================================================ */
        .vital-grid-7 {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        .vital-item-blue {
            background: var(--primary-bg);
            border-radius: 12px;
            padding: 14px 10px;
            border-left: 4px solid var(--primary);
            text-align: center;
            transition: all .3s ease;
        }
        .vital-item-blue:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-blue);
        }
        .vital-item-blue .vital-label {
            font-size: 0.58rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: block;
            margin-bottom: 4px;
        }
        .vital-item-blue .vital-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .vital-item-blue .vital-unit {
            font-size: 0.58rem;
            font-weight: 400;
            color: var(--text-secondary);
        }
        .vital-item-blue.green  { border-left-color: var(--success); background: var(--success-bg); }
        .vital-item-blue.green  .vital-value { color: var(--success-dark); }
        .vital-item-blue.purple { border-left-color: var(--purple); background: var(--purple-bg); }
        .vital-item-blue.purple .vital-value { color: var(--purple); }
        .vital-item-blue.orange { border-left-color: var(--warning); background: var(--warning-bg); }
        .vital-item-blue.orange .vital-value { color: var(--warning); }
        .vital-item-blue.teal   { border-left-color: #0D9488; background: rgba(13,148,136,0.08); }
        .vital-item-blue.teal   .vital-value { color: #0D9488; }
        .vital-item-blue.red    { border-left-color: var(--danger); background: var(--danger-bg); }
        .vital-item-blue.red    .vital-value { color: var(--danger); }
        .vital-item-blue.sky    { border-left-color: var(--sky); background: var(--sky-bg); }
        .vital-item-blue.sky    .vital-value { color: var(--sky-dark); }

        .bmi-label {
            display: inline-block;
            font-size: 0.5rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 4px;
        }
        .bmi-label.normal      { background: var(--success-bg); color: var(--success); }
        .bmi-label.underweight { background: var(--warning-bg); color: var(--warning); }
        .bmi-label.overweight  { background: var(--warning-bg); color: var(--warning); }
        .bmi-label.obese       { background: var(--danger-bg);  color: var(--danger); }

        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 999px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .badge-success   { background: var(--success-bg);  color: var(--success); }
        .badge-danger    { background: var(--danger-bg);   color: var(--danger); }
        .badge-warning   { background: var(--warning-bg);  color: var(--warning); }
        .badge-info      { background: var(--primary-bg);  color: var(--primary); }
        .badge-purple    { background: var(--purple-bg);   color: var(--purple); }
        .badge-pending   { background: var(--warning-bg);  color: var(--warning); }
        .badge-paid      { background: var(--success-bg);  color: var(--success); }
        .badge-partial   { background: var(--primary-bg);  color: var(--primary); }
        .badge-cancelled { background: var(--danger-bg);   color: var(--danger); }
        .badge-completed { background: var(--success-bg);  color: var(--success); }
        .badge-assigned  { background: var(--primary-bg);  color: var(--primary); }
        .badge-with_doctor { background: var(--purple-bg); color: var(--purple); }
        .badge-lab_test  { background: var(--sky-bg);      color: var(--sky-dark); }

        /* ================================================================
           TABLES
           ================================================================ */
        .table-wrapper { overflow-x: auto; }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .table-wrapper table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            background: var(--bg-body);
        }
        .table-wrapper table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .table-wrapper table tbody tr:hover {
            background: var(--bg-body);
        }

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all .25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 38px;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: #fff;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-sm { padding: 5px 12px; font-size: 0.68rem; min-height: 32px; border-radius: 8px; }
        .btn-pdf {
            background: #DC2626;
            color: #fff;
            box-shadow: 0 4px 12px rgba(220,38,38,0.3);
        }
        .btn-pdf:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220,38,38,0.4);
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 2rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 8px;
        }

        /* ================================================================
           PDF MODAL
           ================================================================ */
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
            background: var(--primary-gradient);
            border-radius: 14px 14px 0 0;
            gap: 10px;
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
            font-size: 0.76rem;
            cursor: pointer;
        }
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.3);
        }
        .pdf-modal-header .modal-actions .btn-danger-modal:hover {
            background: rgba(220,38,38,0.5);
        }

        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 22px 28px;
            background: var(--bg-body);
        }

        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 0.82rem;
            background: var(--bg-card);
            padding: 30px 36px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        /* ================================================================
           PDF CONTENT STYLES
           ================================================================ */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 18px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 22px;
        }
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 6px;
        }
        .pdf-content .pdf-header .pdf-logo img { height: 52px; width: auto; object-fit: contain; }
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.78rem;
            color: var(--text-secondary);
            letter-spacing: 0.4px;
        }
        .pdf-content .pdf-header .doc-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 6px;
            background: var(--primary-bg);
            padding: 5px 18px;
            border-radius: 20px;
            display: inline-block;
        }

        .pdf-content .section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            margin: 18px 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdf-content .pdf-row {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 150px;
            flex-shrink: 0;
            font-size: 0.72rem;
        }
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.78rem;
        }

        .pdf-content .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px 18px;
        }

        .pdf-content .pdf-vital-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 8px 0;
        }
        .pdf-content .pdf-vital-item {
            background: var(--primary-bg);
            padding: 8px 10px;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            text-align: center;
        }
        .pdf-content .pdf-vital-item.spo2 {
            background: var(--sky-bg);
            border-left-color: var(--sky);
        }
        .pdf-content .pdf-vital-item .vital-label {
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        .pdf-content .pdf-vital-item .vital-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .pdf-content .pdf-vital-item.spo2 .vital-value { color: var(--sky-dark); }
        .pdf-content .pdf-vital-item .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
        }

        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
            margin: 8px 0;
        }
        .pdf-content .pdf-table th {
            background: var(--primary);
            color: #fff;
            padding: 6px 10px;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
        .pdf-content .pdf-table td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .pdf-content .pdf-table tr:nth-child(even) td { background: var(--gray-50); }

        .pdf-content .pdf-footer {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 2px solid var(--border-color);
        }
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
        }
        .pdf-content .pdf-footer .footer-left {
            font-size: 0.72rem;
            color: var(--text-secondary);
        }
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 10px 22px;
            border: 3px solid var(--primary);
            border-radius: 10px;
            background: var(--primary-bg);
            min-width: 180px;
        }
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 0.55rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary);
        }
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 0.62rem;
            color: var(--text-secondary);
            margin-top: 3px;
        }
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 0.55rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 12px;
            font-size: 0.62rem;
            color: var(--text-secondary);
        }
        .pdf-content .pdf-footer .footer-bottom .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }

        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--primary);
            margin-top: 24px;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
        }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp .4s ease forwards;
            opacity: 0;
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .vital-grid-7 { grid-template-columns: repeat(4, 1fr); }
            .pdf-content .pdf-vital-grid { grid-template-columns: repeat(3, 1fr); }
            .pdf-content .pdf-grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.25rem; }
            .detail-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-info .patient-meta { justify-content: center; }
            .profile-actions { justify-content: center; width: 100%; }
            .vital-grid-7 { grid-template-columns: repeat(3, 1fr); }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-row { flex-direction: column; }
            .pdf-content .pdf-row .pdf-label { width: 100%; }
            .pdf-content .pdf-vital-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .vital-grid-7 { grid-template-columns: repeat(2, 1fr); }
            .pdf-modal-header { flex-direction: column; align-items: stretch; }
        }

        @media print {
            .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($patient): ?>

    <!-- PAGE HEADER -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                Patient Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                View complete patient information for <strong><?= htmlspecialchars($patient['full_name']) ?></strong>

                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    ID: <strong><?= htmlspecialchars($patient['patient_id']) ?></strong>
                </span>

                <span class="header-badge" style="background:rgba(52,211,153,0.2);">
                    <i class="fas fa-ring"></i>
                    <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- PROFILE HEADER -->
    <div class="profile-header animate-fade-in-up">
        <?php
            $gender = $patient['gender'] ?? '';
            $avatar_class = match($gender) {
                'Male'   => 'avatar-male',
                'Female' => 'avatar-female',
                'Other'  => 'avatar-other',
                default  => 'avatar-default'
            };
        ?>
        <div class="profile-avatar <?= $avatar_class ?>">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>

        <div class="profile-info">
            <div>
                <span class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></span>
                <span class="patient-id"><?= htmlspecialchars($patient['patient_id']) ?></span>
                <?php if (!empty($patient['assigned_doctor_name'])): ?>
                    <span class="badge badge-info" style="margin-left:6px;">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                        <?= $patient['assigned_doctor_online'] ? '🟢' : '⚪' ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="patient-meta">
                <span class="meta-item"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-ring"></i> <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-calendar"></i> <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                <span class="meta-item"><i class="fas fa-clock"></i> <?= $age ?> years</span>
                <span class="meta-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                <span class="meta-item">
                    <i class="fas fa-circle" style="color:<?= ($patient['status'] ?? 'active') === 'active' ? '#059669' : '#DC2626' ?>;"></i>
                    <?= ucfirst($patient['status'] ?? 'Active') ?>
                </span>
            </div>
        </div>

        <div class="profile-actions no-print">
            <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="generatePDF()" class="btn btn-pdf btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>

    <!-- 1. PERSONAL INFORMATION -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.05s;">
        <div class="card-title"><i class="fas fa-user"></i> Personal Information</div>
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Full Name</span><span class="detail-value"><?= htmlspecialchars($patient['full_name']) ?></span></div>
            <div class="detail-item"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($patient['patient_id']) ?></span></div>
            <div class="detail-item"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Marital Status</span><span class="detail-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span></div>
            <div class="detail-item">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value">
                    <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>
                    <?php if ($age !== 'N/A'): ?><span style="font-size:.7rem;color:var(--text-secondary);">(<?= $age ?> years)</span><?php endif; ?>
                </span>
            </div>
            <div class="detail-item"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Branch</span><span class="detail-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Registered By</span><span class="detail-value"><?= htmlspecialchars($patient['created_by_name'] ?? 'System') ?></span></div>
            <div class="detail-item" style="grid-column:1/-1;"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span></div>
        </div>
    </div>

    <!-- 2. ASSIGNED DOCTOR & ACTIVE VISIT -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.1s;">
        <div class="card-title"><i class="fas fa-user-md"></i> Assigned Doctor & Active Visit</div>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Assigned Doctor</span>
                <span class="detail-value">
                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                        <span class="badge badge-info">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                            <?= $patient['assigned_doctor_online'] ? '🟢 Online' : '⚪ Offline' ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-secondary);">No doctor assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Active Visit</span>
                <span class="detail-value">
                    <?php if ($active_visit): ?>
                        <span class="badge badge-<?= htmlspecialchars($active_visit['status'] ?? 'pending') ?>">
                            <?= ucfirst(str_replace('_', ' ', $active_visit['status'] ?? 'Pending')) ?>
                        </span>
                        <span style="font-size:.7rem;color:var(--text-secondary);margin-left:6px;">#<?= htmlspecialchars($active_visit['visit_number'] ?? '') ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-secondary);">No active visit</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- 3. LATEST VITAL SIGNS (7) -->
    <?php if ($latest_vitals): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:.15s;">
        <div class="card-title">
            <i class="fas fa-heartbeat" style="color:#DC2626;"></i>
            Latest Vital Signs
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">
                (7 Signs - <?= date('d M Y h:i A', strtotime($latest_vitals['recorded_at'])) ?>)
            </span>
        </div>
        <div class="vital-grid-7">
            <div class="vital-item-blue">
                <span class="vital-label">🌡️ Temperature</span>
                <span class="vital-value"><?= $latest_vitals['temperature'] ?? 'N/A' ?> <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-item-blue green">
                <span class="vital-label">❤️ Blood Pressure</span>
                <span class="vital-value">
                    <?php if (!empty($latest_vitals['blood_pressure_systolic']) && !empty($latest_vitals['blood_pressure_diastolic'])): ?>
                        <?= $latest_vitals['blood_pressure_systolic'] ?>/<?= $latest_vitals['blood_pressure_diastolic'] ?> <span class="vital-unit">mmHg</span>
                    <?php else: ?>N/A<?php endif; ?>
                </span>
            </div>
            <div class="vital-item-blue purple">
                <span class="vital-label">💓 Pulse Rate</span>
                <span class="vital-value"><?= $latest_vitals['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-item-blue sky">
                <span class="vital-label">🫁 Oxygen (SpO₂)</span>
                <span class="vital-value"><?= $latest_vitals['oxygen_saturation'] ?? 'N/A' ?> <span class="vital-unit">%</span></span>
            </div>
            <div class="vital-item-blue orange">
                <span class="vital-label">⚖️ Weight</span>
                <span class="vital-value"><?= $latest_vitals['weight'] ?? 'N/A' ?> <span class="vital-unit">kg</span></span>
            </div>
            <div class="vital-item-blue teal">
                <span class="vital-label">📏 Height</span>
                <span class="vital-value"><?= $latest_vitals['height'] ?? 'N/A' ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-item-blue red">
                <span class="vital-label">📊 BMI</span>
                <span class="vital-value">
                    <?= $latest_vitals['bmi'] ?? 'N/A' ?> <span class="vital-unit">kg/m²</span>
                    <?php if (!empty($latest_vitals['bmi'])):
                        $bmi = (float)$latest_vitals['bmi'];
                        if ($bmi < 18.5) { $bmi_label = 'Underweight'; $bmi_class = 'underweight'; }
                        elseif ($bmi < 25) { $bmi_label = 'Normal'; $bmi_class = 'normal'; }
                        elseif ($bmi < 30) { $bmi_label = 'Overweight'; $bmi_class = 'overweight'; }
                        else { $bmi_label = 'Obese'; $bmi_class = 'obese'; }
                    ?>
                        <span class="bmi-label <?= $bmi_class ?>"><?= $bmi_label ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php if (!empty($latest_vitals['notes'])): ?>
            <div style="margin-top:10px;font-size:.78rem;color:var(--text-secondary);">
                <i class="fas fa-sticky-note" style="color:var(--primary);"></i>
                <strong>Notes:</strong> <?= htmlspecialchars($latest_vitals['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 4. VISIT HISTORY -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.2s;">
        <div class="card-title">
            <i class="fas fa-clock"></i> Visit History
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">(Last 10 visits)</span>
        </div>
        <?php if (count($visit_history) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Bill</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visit_history as $visit): ?>
                            <tr>
                                <td><span style="font-family:monospace;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($visit['created_at'])) ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($visit['status'] ?? 'pending') ?>"><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?></span></td>
                                <td>
                                    <?php if (!empty($visit['bill_amount'])): ?>
                                        <span style="font-weight:600;">TSh <?= number_format($visit['bill_amount'], 0) ?></span>
                                        <span class="badge badge-<?= $visit['bill_status'] ?? 'pending' ?>"><?= ucfirst($visit['bill_status'] ?? 'Pending') ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-clock"></i><p>No visit history found</p></div>
        <?php endif; ?>
    </div>

    <!-- 5. SYMPTOMS -->
    <?php if ($active_visit && !empty($active_visit['symptoms'])): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:.25s;">
        <div class="card-title"><i class="fas fa-notes-medical"></i> Symptoms & Complaint</div>
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Symptoms</span><span class="detail-value"><?= htmlspecialchars($active_visit['symptoms'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Complaint</span><span class="detail-value"><?= htmlspecialchars($active_visit['complaint'] ?? 'N/A') ?></span></div>
            <?php if (!empty($active_visit['notes'])): ?>
            <div class="detail-item" style="grid-column:1/-1;"><span class="detail-label">Notes</span><span class="detail-value"><?= htmlspecialchars($active_visit['notes']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6. LAB TESTS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.3s;">
        <div class="card-title">
            <i class="fas fa-flask"></i> Lab Tests
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">(Last 10)</span>
        </div>
        <?php if (!empty($lab_tests) && count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Test Name</th><th>Date</th><th>Doctor</th><th>Status</th><th>Results</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($test['status'] ?? 'pending') ?>"><?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?></span></td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:var(--success);font-weight:600;">✅ Available</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_lab.php?id=<?= $test['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests found</p></div>
        <?php endif; ?>
    </div>

    <!-- 7. DIAGNOSIS -->
    <?php if ($active_visit && !empty($active_visit['diagnosis'])): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:.35s;">
        <div class="card-title"><i class="fas fa-stethoscope"></i> Diagnosis & Treatment</div>
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Diagnosis</span><span class="detail-value"><?= htmlspecialchars($active_visit['diagnosis'] ?? 'N/A') ?></span></div>
            <div class="detail-item"><span class="detail-label">Treatment</span><span class="detail-value"><?= htmlspecialchars($active_visit['treatment'] ?? 'N/A') ?></span></div>
            <?php if (!empty($active_visit['follow_up_date'])): ?>
            <div class="detail-item"><span class="detail-label">Follow-up Date</span><span class="detail-value"><?= date('d M Y', strtotime($active_visit['follow_up_date'])) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 8. MEDICAL INFORMATION -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.4s;">
        <div class="card-title"><i class="fas fa-notes-medical"></i> Medical Information</div>
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'Not recorded') ?></span></div>
            <div class="detail-item"><span class="detail-label">Emergency Contact</span><span class="detail-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'Not recorded') ?></span></div>
            <div class="detail-item" style="grid-column:1/-1;">
                <span class="detail-label">Allergies</span>
                <span class="detail-value">
                    <?php if (!empty($patient['allergies'])):
                        $allergy_list = array_map('trim', explode(',', $patient['allergies']));
                        foreach ($allergy_list as $allergy): ?>
                            <span class="badge badge-danger" style="margin:2px;">⚠️ <?= htmlspecialchars($allergy) ?></span>
                    <?php endforeach; else: ?>
                        <span style="color:var(--text-secondary);">No known allergies</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- 9. PRESCRIPTIONS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.45s;">
        <div class="card-title">
            <i class="fas fa-prescription"></i> Prescriptions
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">(Last 10)</span>
        </div>
        <?php if (!empty($prescriptions) && count($prescriptions) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Prescription #</th><th>Date</th><th>Doctor</th><th>Medication</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><span style="font-family:monospace;"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($prescription['created_at'])) ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($prescription['status'] ?? 'pending') ?>"><?= ucfirst($prescription['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions found</p></div>
        <?php endif; ?>
    </div>

    <!-- 10. PROCEDURES -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.5s;">
        <div class="card-title">
            <i class="fas fa-syringe" style="color:#7C3AED;"></i> Procedures
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">(Last 10)</span>
        </div>
        <?php if (!empty($procedures) && count($procedures) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Procedure Name</th><th>Date</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Bill #</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $procedure): ?>
                            <tr>
                                <td><?= htmlspecialchars($procedure['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($procedure['created_at'])) ?></td>
                                <td><?= $procedure['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($procedure['unit_price'] ?? 0, 0) ?></td>
                                <td style="font-weight:600;">TSh <?= number_format($procedure['total_price'] ?? 0, 0) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($procedure['status'] ?? 'pending') ?>"><?= ucfirst($procedure['status'] ?? 'Pending') ?></span></td>
                                <td><span style="font-family:monospace;"><?= htmlspecialchars($procedure['bill_number'] ?? 'N/A') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-syringe"></i><p>No procedures found</p></div>
        <?php endif; ?>
    </div>

    <!-- 11. TOOLS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.55s;">
        <div class="card-title">
            <i class="fas fa-tools" style="color:#D97706;"></i> Tools / Equipment
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">(Last 10)</span>
        </div>
        <?php if (!empty($tools) && count($tools) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Tool Name</th><th>Date</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Bill #</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($tool['created_at'])) ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($tool['unit_price'] ?? 0, 0) ?></td>
                                <td style="font-weight:600;">TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($tool['status'] ?? 'pending') ?>"><?= ucfirst($tool['status'] ?? 'Pending') ?></span></td>
                                <td><span style="font-family:monospace;"><?= htmlspecialchars($tool['bill_number'] ?? 'N/A') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-tools"></i><p>No tools found</p></div>
        <?php endif; ?>
    </div>

    <!-- 12. BILLS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:.6s;">
        <div class="card-title">
            <i class="fas fa-receipt"></i> Bills
            <span style="font-size:.7rem;font-weight:400;color:var(--text-secondary);">(Last 10 bills)</span>
        </div>
        <?php if (!empty($bills) && count($bills) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Bill #</th><th>Date</th><th>Visit</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><span style="font-family:monospace;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                                <td><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                                <td style="font-weight:600;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td>TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($bill['status'] ?? 'pending') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-receipt"></i><p>No bills found</p></div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer no-print">
        <div style="text-align:center;">
            <div style="font-size:1.15rem;font-weight:800;color:var(--primary);letter-spacing:1px;">
                BRAICK DISPENSARY
            </div>
            <div style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);margin-top:4px;">
                <i class="fas fa-heart" style="color:#DC2626;"></i>
                TUNAJARI AFYA YAKO
                <i class="fas fa-heart" style="color:#DC2626;"></i>
            </div>
            <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:8px;">
                <span style="color:var(--primary);font-weight:700;">Braick Dispensary</span> Management System
                <span style="margin:0 6px;opacity:.4;">|</span>
                View Patient
                <span style="margin:0 6px;opacity:.4;">|</span>
                <?= htmlspecialchars($patient['full_name']) ?>
                <span style="margin:0 6px;opacity:.4;">|</span>
                <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            </div>
        </div>
    </footer>

    <?php else: ?>

    <div class="detail-card">
        <div class="empty-state">
            <i class="fas fa-user-slash" style="font-size:3rem;"></i>
            <h3 style="margin-top:12px;">Patient Not Found</h3>
            <p style="color:var(--text-secondary);">The patient you are looking for does not exist.</p>
            <a href="patients.php" class="btn btn-primary" style="margin-top:12px;">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <?php endif; ?>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf"></i>
                Patient PDF Preview - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm" id="downloadPdfBtn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // ================================================================
    // FOOTER CLOCK (header clock handled by shared header)
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var el = document.getElementById('footerTimestamp');
        if (el) el.textContent = 'Last updated: ' + timeStr;
    }, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    function performSearch() {
        var q = searchInput.value.trim();
        if (q.length > 0) window.location.href = 'search.php?q=' + encodeURIComponent(q);
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) { if (e.key === 'Enter') performSearch(); });

    // ================================================================
    // TOAST (uses header's showToast if available)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            var t = document.getElementById('toastTitle');
            var m = document.getElementById('toastMessage');
            if (!toast) return;
            toast.className = 'toast-custom ' + (type || 'info');
            if (t) t.textContent = title;
            if (m) m.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(() => toast.style.display = 'none', 400);
            }, 3500);
        };
    }

    // ================================================================
    // GENERATE PDF
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');

        var patientData = {
            id: '<?= $patient['id'] ?? 0 ?>',
            patient_id: '<?= addslashes($patient['patient_id'] ?? 'N/A') ?>',
            full_name: '<?= addslashes($patient['full_name'] ?? 'N/A') ?>',
            gender: '<?= addslashes($patient['gender'] ?? 'N/A') ?>',
            marital_status: '<?= addslashes($patient['marital_status'] ?? 'N/A') ?>',
            date_of_birth: '<?= !empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>',
            age: '<?= $age ?>',
            phone: '<?= addslashes($patient['phone'] ?? 'N/A') ?>',
            email: '<?= addslashes($patient['email'] ?? 'N/A') ?>',
            address: '<?= addslashes($patient['address'] ?? 'N/A') ?>',
            blood_group: '<?= addslashes($patient['blood_group'] ?? 'N/A') ?>',
            allergies: '<?= addslashes($patient['allergies'] ?? 'None') ?>',
            emergency_contact: '<?= addslashes($patient['emergency_contact'] ?? 'N/A') ?>',
            branch_name: '<?= addslashes($patient['branch_name'] ?? $branch_name) ?>',
            assigned_doctor: '<?= addslashes($patient['assigned_doctor_name'] ?? 'Not Assigned') ?>',
            created_at: '<?= date('d/m/Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?>',
            created_by: '<?= addslashes($patient['created_by_name'] ?? 'System') ?>'
        };

        var vitals         = <?= $latest_vitals ? json_encode($latest_vitals) : 'null' ?>;
        var visitHistory   = <?= json_encode($visit_history) ?>;
        var bills          = <?= json_encode($bills) ?>;
        var procedures     = <?= json_encode($procedures) ?>;
        var tools          = <?= json_encode($tools) ?>;
        var prescriptions  = <?= json_encode($prescriptions) ?>;
        var labTests       = <?= json_encode($lab_tests) ?>;
        var activeVisit    = <?= $active_visit ? json_encode($active_visit) : 'null' ?>;

        var now = new Date();
        var reportDate = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var reportTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

        // Vitals HTML
        var vitalsHtml = '';
        if (vitals) {
            vitalsHtml = `
                <div class="pdf-vital-grid">
                    <div class="pdf-vital-item"><div class="vital-label">🌡️ Temperature</div><div class="vital-value">${vitals.temperature || 'N/A'} <span class="vital-unit">°C</span></div></div>
                    <div class="pdf-vital-item"><div class="vital-label">❤️ Blood Pressure</div><div class="vital-value">${vitals.blood_pressure_systolic && vitals.blood_pressure_diastolic ? vitals.blood_pressure_systolic + ' / ' + vitals.blood_pressure_diastolic + ' <span class="vital-unit">mmHg</span>' : 'N/A'}</div></div>
                    <div class="pdf-vital-item"><div class="vital-label">💓 Pulse Rate</div><div class="vital-value">${vitals.pulse_rate || 'N/A'} <span class="vital-unit">bpm</span></div></div>
                    <div class="pdf-vital-item spo2"><div class="vital-label">🫁 Oxygen (SpO₂)</div><div class="vital-value">${vitals.oxygen_saturation || 'N/A'} <span class="vital-unit">%</span></div></div>
                    <div class="pdf-vital-item"><div class="vital-label">⚖️ Weight</div><div class="vital-value">${vitals.weight || 'N/A'} <span class="vital-unit">kg</span></div></div>
                    <div class="pdf-vital-item"><div class="vital-label">📏 Height</div><div class="vital-value">${vitals.height || 'N/A'} <span class="vital-unit">cm</span></div></div>
                    <div class="pdf-vital-item"><div class="vital-label">📊 BMI</div><div class="vital-value">${vitals.bmi || 'N/A'} <span class="vital-unit">kg/m²</span></div></div>
                </div>
                ${vitals.notes ? `<div style="margin-top:6px;font-size:0.75rem;color:var(--text-secondary);"><strong>Notes:</strong> ${vitals.notes}</div>` : ''}
            `;
        } else {
            vitalsHtml = `<p style="color:var(--text-secondary);">No vital signs recorded</p>`;
        }

        // Visit history
        var visitHtml = (visitHistory && visitHistory.length > 0)
            ? `<table class="pdf-table"><thead><tr><th>Visit #</th><th>Date</th><th>Type</th><th>Doctor</th><th>Status</th><th>Bill</th></tr></thead><tbody>
                ${visitHistory.map(function(v){ return `<tr><td>${v.visit_number||'N/A'}</td><td>${new Date(v.created_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'})}</td><td>${v.visit_type||'N/A'}</td><td>${v.doctor_name||'N/A'}</td><td>${v.status||'N/A'}</td><td>${v.bill_amount ? 'TSh '+Number(v.bill_amount).toLocaleString() : 'N/A'}</td></tr>`; }).join('')}
                </tbody></table>`
            : `<p style="color:var(--text-secondary);">No visit history</p>`;

        // Symptoms
        var symptomsHtml = activeVisit
            ? `<div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Symptoms</span><span class="pdf-value">${activeVisit.symptoms || 'N/A'}</span></div>
                <div class="pdf-row"><span class="pdf-label">Complaint</span><span class="pdf-value">${activeVisit.complaint || 'N/A'}</span></div>
                ${activeVisit.notes ? `<div class="pdf-row" style="grid-column:1/-1;"><span class="pdf-label">Notes</span><span class="pdf-value">${activeVisit.notes}</span></div>` : ''}
                </div>`
            : `<p style="color:var(--text-secondary);">No active visit symptoms</p>`;

        // Diagnosis
        var diagnosisHtml = (activeVisit && (activeVisit.diagnosis || activeVisit.treatment))
            ? `<div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Diagnosis</span><span class="pdf-value">${activeVisit.diagnosis || 'N/A'}</span></div>
                <div class="pdf-row"><span class="pdf-label">Treatment</span><span class="pdf-value">${activeVisit.treatment || 'N/A'}</span></div>
                </div>`
            : `<p style="color:var(--text-secondary);">No diagnosis recorded</p>`;

        // Bills
        var billsHtml = (bills && bills.length > 0)
            ? `<table class="pdf-table"><thead><tr><th>Bill #</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>
                ${bills.map(function(b){ return `<tr><td>${b.bill_number||'N/A'}</td><td>${new Date(b.created_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'})}</td><td>TSh ${Number(b.total_amount||0).toLocaleString()}</td><td>TSh ${Number(b.paid_amount||0).toLocaleString()}</td><td>TSh ${Number(b.balance||0).toLocaleString()}</td><td>${b.status||'N/A'}</td></tr>`; }).join('')}
                </tbody></table>`
            : `<p style="color:var(--text-secondary);">No bills found</p>`;

        // Prescriptions
        var prescriptionsHtml = (prescriptions && prescriptions.length > 0)
            ? `<table class="pdf-table"><thead><tr><th>Prescription #</th><th>Date</th><th>Doctor</th><th>Medication</th><th>Status</th></tr></thead><tbody>
                ${prescriptions.map(function(p){ return `<tr><td>${p.prescription_number||'N/A'}</td><td>${new Date(p.created_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'})}</td><td>${p.doctor_name||'N/A'}</td><td>${p.medication||'N/A'}</td><td>${p.status||'N/A'}</td></tr>`; }).join('')}
                </tbody></table>`
            : `<p style="color:var(--text-secondary);">No prescriptions found</p>`;

        // Lab tests
        var labTestsHtml = (labTests && labTests.length > 0)
            ? `<table class="pdf-table"><thead><tr><th>Test Name</th><th>Date</th><th>Doctor</th><th>Status</th><th>Results</th></tr></thead><tbody>
                ${labTests.map(function(lt){ return `<tr><td>${lt.test_name||'N/A'}</td><td>${new Date(lt.created_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'})}</td><td>${lt.doctor_name||'N/A'}</td><td>${lt.status||'N/A'}</td><td>${lt.results ? '✅ Available' : '⏳ Pending'}</td></tr>`; }).join('')}
                </tbody></table>`
            : `<p style="color:var(--text-secondary);">No lab tests found</p>`;

        // Procedures
        var proceduresHtml = (procedures && procedures.length > 0)
            ? `<table class="pdf-table"><thead><tr><th>Procedure</th><th>Date</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead><tbody>
                ${procedures.map(function(p){ return `<tr><td>${p.item_name||'N/A'}</td><td>${new Date(p.created_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'})}</td><td>${p.quantity||1}</td><td>TSh ${Number(p.total_price||0).toLocaleString()}</td><td>${p.status||'N/A'}</td></tr>`; }).join('')}
                </tbody></table>`
            : `<p style="color:var(--text-secondary);">No procedures found</p>`;

        // Tools
        var toolsHtml = (tools && tools.length > 0)
            ? `<table class="pdf-table"><thead><tr><th>Tool Name</th><th>Date</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead><tbody>
                ${tools.map(function(t){ return `<tr><td>${t.item_name||'N/A'}</td><td>${new Date(t.created_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'})}</td><td>${t.quantity||1}</td><td>TSh ${Number(t.total_price||0).toLocaleString()}</td><td>${t.status||'N/A'}</td></tr>`; }).join('')}
                </tbody></table>`
            : `<p style="color:var(--text-secondary);">No tools found</p>`;

        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name">BRAICK DISPENSARY</span>
                </div>
                <div class="clinic-sub">Quality Healthcare Services • ${patientData.branch_name}</div>
                <div class="doc-title">📋 Patient Medical Record</div>
                <div style="font-size:0.72rem;color:var(--text-secondary);margin-top:4px;">Report Generated: ${reportDate} • ${reportTime}</div>
            </div>

            <div class="section-title">👤 Personal Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value"><strong>${patientData.full_name}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value">${patientData.patient_id}</span></div>
                <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value">${patientData.gender}</span></div>
                <div class="pdf-row"><span class="pdf-label">Marital Status</span><span class="pdf-value">${patientData.marital_status}</span></div>
                <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value">${patientData.date_of_birth} (${patientData.age} years)</span></div>
                <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value">${patientData.phone}</span></div>
                <div class="pdf-row"><span class="pdf-label">Email</span><span class="pdf-value">${patientData.email}</span></div>
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value">${patientData.blood_group}</span></div>
                <div class="pdf-row" style="grid-column:1/-1;"><span class="pdf-label">Address</span><span class="pdf-value">${patientData.address}</span></div>
                <div class="pdf-row"><span class="pdf-label">Registered</span><span class="pdf-value">${patientData.created_at} by ${patientData.created_by}</span></div>
                <div class="pdf-row"><span class="pdf-label">Branch</span><span class="pdf-value">${patientData.branch_name}</span></div>
            </div>

            <div class="section-title">👨‍⚕️ Assigned Doctor & Active Visit</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Assigned Doctor</span><span class="pdf-value">${patientData.assigned_doctor}</span></div>
                <div class="pdf-row"><span class="pdf-label">Active Visit</span><span class="pdf-value">${activeVisit ? activeVisit.visit_number + ' (' + activeVisit.status + ')' : 'No active visit'}</span></div>
            </div>

            <div class="section-title">❤️ Vital Signs (7 Signs)</div>
            ${vitalsHtml}

            <div class="section-title">📋 Visit History (Last 10)</div>
            ${visitHtml}

            <div class="section-title">🩺 Symptoms & Complaint</div>
            ${symptomsHtml}

            <div class="section-title">🧪 Lab Tests (Last 10)</div>
            ${labTestsHtml}

            <div class="section-title">📋 Diagnosis & Treatment</div>
            ${diagnosisHtml}

            <div class="section-title">🏥 Medical Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value">${patientData.blood_group}</span></div>
                <div class="pdf-row"><span class="pdf-label">Emergency Contact</span><span class="pdf-value">${patientData.emergency_contact}</span></div>
                <div class="pdf-row" style="grid-column:1/-1;"><span class="pdf-label">Allergies</span><span class="pdf-value">${patientData.allergies}</span></div>
            </div>

            <div class="section-title">💊 Prescriptions (Last 10)</div>
            ${prescriptionsHtml}

            <div class="section-title">💉 Procedures (Last 10)</div>
            ${proceduresHtml}

            <div class="section-title">🔧 Tools / Equipment (Last 10)</div>
            ${toolsHtml}

            <div class="section-title">💰 Bills (Last 10)</div>
            ${billsHtml}

            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Technician: _________________</span>
                        <span style="margin-left:20px;">Date: ${reportDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: ${reportDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <span class="footer-brand">Braick Dispensary</span> •
                    <span style="font-weight:600;color:#DC2626;">❤️ TUNAJARI AFYA YAKO</span> •
                    Generated on ${reportDate} at ${reportTime}
                </div>
            </div>
        `;

        content.innerHTML = html;
        modal.classList.add('active');
    }

    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }

    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Patient_<?= htmlspecialchars($patient['full_name'] ?? 'patient') ?>_<?= $patient['id'] ?? 0 ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: 'avoid-all' }
        };
        html2pdf().set(opt).from(element).save();
    }

    // ESC to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePDFModal();
    });

    // Click outside to close
    document.getElementById('pdfModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePDFModal();
    });

    // Toast for PDF
    document.getElementById('downloadPdfBtn')?.addEventListener('click', function() {
        showToast('📄 PDF Download', 'Downloading patient PDF...', 'info');
    });

    console.log('%c👤 Braick - View Patient (Shared Header)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Inatumia shared header + sidebar', 'font-size:13px;color:#059669;font-weight:bold;');
    console.log('%c❤️ 7 Vital Signs: Temp, BP, Pulse, SpO₂, Weight, Height, BMI', 'font-size:13px;color:#DC2626;');
</script>

</body>
</html>