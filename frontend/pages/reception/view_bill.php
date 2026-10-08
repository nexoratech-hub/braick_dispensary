<?php
// ================================================================
// FILE: frontend/pages/reception/view_bill.php
// RECEPTION - VIEW BILL DETAILS
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
$user_id          = $_SESSION['user_id'];
$user_full_name   = $_SESSION['full_name']   ?? 'User';
$user_role        = $_SESSION['role']        ?? 'reception';
$user_branch_id   = $_SESSION['branch_id']   ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username    = $_SESSION['username']    ?? '';
$profile_pic      = $_SESSION['profile_pic'] ?? '';

// ================================================================
// GET BILL ID
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bill_id <= 0) {
    header('Location: bills.php?error=invalid_bill');
    exit;
}

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$bill             = null;
$bill_items       = [];
$payment_history  = [];

// ================================================================
// GET BILL
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT
            b.id, b.bill_number, b.patient_id, b.visit_id, b.branch_id, b.created_by,
            b.subtotal, b.discount_percent, b.discount_amount,
            b.total_amount, b.paid_amount, b.balance, b.status,
            b.payment_method, b.notes, b.created_at, b.updated_at,
            p.full_name AS patient_name,
            p.patient_id AS patient_code,
            p.phone, p.gender, p.date_of_birth, p.address, p.email,
            u.full_name AS created_by_name,
            v.visit_number, v.visit_type, v.created_at AS visit_date,
            v.diagnosis, v.symptoms
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u    ON b.created_by = u.id
        LEFT JOIN visits v   ON b.visit_id   = v.id
        WHERE b.id = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: bills.php?error=bill_not_found');
        exit;
    }
} catch (Exception $e) {
    header('Location: bills.php?error=database_error');
    exit;
}

// ================================================================
// GET BILL ITEMS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT
            bi.*,
            CASE
                WHEN bi.item_type = 'consultation' THEN 'Consultation'
                WHEN bi.item_type = 'lab_test'     THEN 'Lab Test'
                WHEN bi.item_type = 'medication'   THEN 'Medication'
                WHEN bi.item_type = 'procedure'    THEN 'Procedure'
                WHEN bi.item_type = 'equipment'    THEN 'Equipment'
                WHEN bi.item_type = 'tool'         THEN 'Tool'
                WHEN bi.item_type = 'registration' THEN 'Registration'
                ELSE bi.item_type
            END AS item_type_label
        FROM bill_items bi
        WHERE bi.bill_id = ? AND bi.status != 'cancelled'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bill_items = [];
}

// ================================================================
// GET PAYMENT HISTORY
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name AS received_by_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ?
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$bill_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payment_history = [];
}

// ================================================================
// TOTALS
// ================================================================
$total_items    = count($bill_items);
$total_amount   = $bill['total_amount'] ?? 0;
$paid_amount    = $bill['paid_amount']  ?? 0;
$balance        = $bill['balance']      ?? 0;
$pending_amount = max(0, $total_amount - $paid_amount);

// ================================================================
// HELPERS (guarded)
// ================================================================
if (!function_exists('calculateAge')) {
    function calculateAge($dob) {
        if (empty($dob)) return 'N/A';
        try {
            $birthDate = new DateTime($dob);
            $today     = new DateTime('today');
            return $birthDate->diff($today)->y;
        } catch (Exception $e) {
            return 'N/A';
        }
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $map = [
            'paid'      => 'badge-success',
            'pending'   => 'badge-warning',
            'partial'   => 'badge-info',
            'cancelled' => 'badge-danger'
        ];
        return $map[$status] ?? 'badge-warning';
    }
}

if (!function_exists('getStatusLabel')) {
    function getStatusLabel($status) {
        $map = [
            'paid'      => '✅ Paid',
            'pending'   => '⏳ Pending',
            'partial'   => '🔄 Partial',
            'cancelled' => '❌ Cancelled'
        ];
        return $map[$status] ?? ucfirst($status);
    }
}

if (!function_exists('getPaymentStatusBadge')) {
    function getPaymentStatusBadge($status) {
        $map = [
            'paid'      => 'badge-success',
            'pending'   => 'badge-warning',
            'cancelled' => 'badge-danger',
            'refunded'  => 'badge-info'
        ];
        return $map[$status] ?? 'badge-warning';
    }
}

if (!function_exists('getPaymentStatusLabel')) {
    function getPaymentStatusLabel($status) {
        $map = [
            'paid'      => '✅ Paid',
            'pending'   => '⏳ Pending',
            'cancelled' => '❌ Cancelled',
            'refunded'  => '🔄 Refunded'
        ];
        return $map[$status] ?? ucfirst($status);
    }
}

if (!function_exists('getUserColor')) {
    function getUserColor($name) {
        $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
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
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = (int)($stmt->fetch()['total'] ?? 0);
} catch (Exception $e) {}

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
    <title>View Bill - Braick Dispensary</title>

    <link rel="icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           ================================================================ */

        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 18px;
            padding: 24px 32px;
            margin-bottom: 22px;
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
            top: -50%; right: -20%;
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

        .header-pill {
            background: rgba(255,255,255,0.16);
            color: #fff;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.62rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.18);
            backdrop-filter: blur(6px);
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

        /* CARD */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all .3s ease;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            margin-bottom: 18px;
        }
        .card:hover {
            border-color: rgba(11, 94, 215, 0.35);
            box-shadow: 0 8px 24px rgba(11, 94, 215, 0.08);
        }
        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-title i { color: var(--primary); }

        /* PATIENT INFO ROW */
        .patient-info-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 14px 18px;
            background: var(--primary-bg);
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid rgba(11, 94, 215, 0.15);
        }
        [data-theme="dark"] .patient-info-row {
            background: #1E3A5F;
            border-color: rgba(110, 168, 254, 0.2);
        }
        .patient-avatar-sm {
            width: 52px; height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* DETAILS */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 24px;
        }
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 130px;
            flex-shrink: 0;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* BADGES */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: .3px;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info    { background: var(--primary-bg); color: var(--primary); }
        .badge-danger  { background: var(--danger-bg);  color: var(--danger); }
        .badge-purple  { background: #EDE9FE; color: #7C3AED; }

        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .badge-info    { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .badge-danger  { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .badge-purple  { background: #2D1B5F; color: #A78BFA; }

        /* SUMMARY BOXES */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 18px;
        }
        .summary-box {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
        }
        .summary-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        .summary-box.total::before    { background: linear-gradient(90deg, #0B5ED7, #1A7AFF); }
        .summary-box.paid::before     { background: linear-gradient(90deg, #059669, #34D399); }
        .summary-box.pending::before  { background: linear-gradient(90deg, #D97706, #FBBF24); }
        .summary-box.balance::before  { background: linear-gradient(90deg, #DC2626, #F87171); }
        .summary-box.balance.ok::before { background: linear-gradient(90deg, #059669, #34D399); }

        .summary-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(11, 94, 215, 0.12);
            border-color: rgba(11, 94, 215, 0.3);
        }
        .summary-box .total-label {
            font-size: 0.68rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 4px;
        }
        .summary-box .total-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .summary-box .total-value.primary { color: var(--primary); }
        .summary-box .total-value.success { color: var(--success); }
        .summary-box .total-value.warning { color: var(--warning); }
        .summary-box .total-value.danger  { color: var(--danger); }

        /* TABLE */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .table-container thead th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            background: var(--primary);
            color: #fff;
            white-space: nowrap;
        }
        .table-container thead th i { margin-right: 5px; opacity: .9; }
        .table-container tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .table-container tbody tr:nth-child(even) td {
            background: rgba(241, 245, 249, 0.5);
        }
        [data-theme="dark"] .table-container tbody tr:nth-child(even) td {
            background: rgba(15, 23, 42, 0.5);
        }
        .table-container tbody tr:hover td {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .table-container tbody tr:hover td {
            background: #1E3A5F;
        }
        .table-container tfoot td {
            padding: 14px 16px;
            font-weight: 700;
            background: var(--bg-body);
            border-top: 2px solid var(--border-color);
        }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all .25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
            min-height: 42px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 700; }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.25rem; }
            .details-grid { grid-template-columns: 1fr; }
            .detail-row { flex-direction: column; gap: 2px; }
            .detail-label { width: 100%; }
            .patient-info-row { flex-direction: column; text-align: center; }
            .summary-grid { grid-template-columns: 1fr; }
        }

        /* PRINT */
        @media print {
            .btn-outline-light, .btn, .footer { display: none !important; }
            .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-container thead th { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .card { break-inside: avoid; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ============================================================
         PAGE HEADER
         ============================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Bill Details
                <span class="role-badge-display">RECEPTION</span>
                <span class="header-pill">
                    <i class="fas fa-database"></i> New DB
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span style="opacity:.5;">|</span>
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong>
                <span style="opacity:.5;">|</span>
                <i class="fas fa-calendar"></i>
                <?= date('d/m/Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ============================================================
         PATIENT INFORMATION
         ============================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-user-circle"></i> Patient Information
        </h3>

        <div class="patient-info-row">
            <div class="patient-avatar-sm"
                 style="background: <?= getUserColor($bill['patient_name'] ?? 'Unknown') ?>;">
                <?= strtoupper(substr($bill['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:1.05rem;color:var(--text-primary);">
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </div>
                <div style="font-size:0.78rem;color:var(--text-secondary);margin-top:3px;display:flex;gap:10px;flex-wrap:wrap;">
                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-calendar"></i> <?= calculateAge($bill['date_of_birth'] ?? '') ?> years</span>
                    <span><i class="fas <?= ($bill['gender'] ?? '') === 'Male' ? 'fa-mars' : 'fa-venus' ?>"></i> <?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></span>
                </div>
            </div>
            <div style="margin-left:auto;">
                <span class="badge <?= getStatusBadge($bill['status'] ?? 'pending') ?>" style="font-size:.78rem;padding:6px 18px;">
                    <?= getStatusLabel($bill['status'] ?? 'pending') ?>
                </span>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-row">
                <span class="detail-label">Visit Number</span>
                <span class="detail-value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit Type</span>
                <span class="detail-value"><?= ucfirst($bill['visit_type'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created By</span>
                <span class="detail-value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created At</span>
                <span class="detail-value"><?= date('d/m/Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></span>
            </div>
            <?php if (!empty($bill['diagnosis'])): ?>
                <div class="detail-row" style="grid-column: span 2;">
                    <span class="detail-label">Diagnosis</span>
                    <span class="detail-value"><?= htmlspecialchars($bill['diagnosis']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($bill['notes'])): ?>
                <div class="detail-row" style="grid-column: span 2;">
                    <span class="detail-label">Notes</span>
                    <span class="detail-value"><?= htmlspecialchars($bill['notes']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         SUMMARY BOXES
         ============================================================ -->
    <div class="summary-grid">
        <div class="summary-box total">
            <div class="total-label">Total Amount</div>
            <div class="total-value primary">TSh <?= number_format($total_amount, 0) ?></div>
        </div>
        <div class="summary-box paid">
            <div class="total-label">Paid Amount</div>
            <div class="total-value success">TSh <?= number_format($paid_amount, 0) ?></div>
        </div>
        <div class="summary-box pending">
            <div class="total-label">Pending Amount</div>
            <div class="total-value warning">TSh <?= number_format($pending_amount, 0) ?></div>
        </div>
        <div class="summary-box balance <?= $balance <= 0 ? 'ok' : '' ?>">
            <div class="total-label">Balance</div>
            <div class="total-value <?= $balance > 0 ? 'danger' : 'success' ?>">
                TSh <?= number_format($balance, 0) ?>
            </div>
        </div>
    </div>

    <!-- ============================================================
         BILL ITEMS
         ============================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-list"></i> Bill Items
            <span class="badge badge-info" style="margin-left:auto;"><?= $total_items ?> items</span>
        </h3>

        <?php if (count($bill_items) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;"><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-tag"></i> Item</th>
                            <th><i class="fas fa-cube"></i> Type</th>
                            <th style="text-align:center;"><i class="fas fa-calculator"></i> Qty</th>
                            <th style="text-align:right;"><i class="fas fa-coins"></i> Unit Price</th>
                            <th style="text-align:right;"><i class="fas fa-money-bill"></i> Total</th>
                            <th style="text-align:center;"><i class="fas fa-circle"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $index => $item): ?>
                            <tr>
                                <td style="text-align:center;font-weight:600;color:var(--text-secondary);"><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                                <td>
                                    <span class="badge badge-purple"><?= htmlspecialchars($item['item_type_label'] ?? ucfirst($item['item_type'] ?? 'N/A')) ?></span>
                                </td>
                                <td style="text-align:center;font-weight:600;"><?= $item['quantity'] ?? 1 ?></td>
                                <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="text-align:right;font-weight:700;color:var(--primary);">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="badge <?= getPaymentStatusBadge($item['status'] ?? 'pending') ?>">
                                        <?= getPaymentStatusLabel($item['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align:right;">TOTAL</td>
                            <td style="text-align:right;color:var(--primary);font-size:1.1rem;">
                                TSh <?= number_format($total_amount, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No items found on this bill.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         PAYMENT HISTORY
         ============================================================ -->
    <?php if (count($payment_history) > 0): ?>
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Payment History
                <span class="badge badge-info" style="margin-left:auto;"><?= count($payment_history) ?> payments</span>
            </h3>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;"><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-coins"></i> Amount</th>
                            <th><i class="fas fa-credit-card"></i> Method</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-user"></i> Received By</th>
                            <th><i class="fas fa-receipt"></i> Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $index => $payment): ?>
                            <tr>
                                <td style="text-align:center;font-weight:600;color:var(--text-secondary);"><?= $index + 1 ?></td>
                                <td style="font-weight:700;color:var(--success);">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($payment['payment_method'] ?? 'Cash') ?></span></td>
                                <td><?= date('d/m/Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? $payment['received_by'] ?? 'N/A') ?></td>
                                <td><span style="font-family:'Courier New',monospace;font-size:.75rem;"><?= htmlspecialchars($payment['reference_number'] ?? '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         ACTIONS
         ============================================================ -->
    <?php if (($bill['status'] ?? '') !== 'paid' && ($bill['status'] ?? '') !== 'cancelled'): ?>
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-cog"></i> Actions
            </h3>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="process_payment.php?bill_id=<?= $bill_id ?>" class="btn btn-success">
                    <i class="fas fa-credit-card"></i> Process Payment
                </a>
                <a href="bills.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;opacity:.4;">|</span>
            Bill Details
            <span style="margin:0 6px;opacity:.4;">|</span>
            <span style="color:var(--success);font-weight:600;">
                <i class="fas fa-database"></i> New DB
            </span>
            <span style="margin:0 6px;opacity:.4;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // FOOTER CLOCK (header handles its own)
    // ================================================================
    console.log('%c🧾 View Bill - Reception (Shared Header)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Inatumia shared header + sidebar', 'font-size:13px;color:#059669;font-weight:bold;');
    console.log('%c📋 Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px;color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:13px;color:#059669;');
    console.log('%c💰 Total: TSh <?= number_format($total_amount, 0) ?> | Paid: TSh <?= number_format($paid_amount, 0) ?> | Balance: TSh <?= number_format($balance, 0) ?>', 'font-size:13px;color:#0B5ED7;');
    console.log('%c📊 Items: <?= $total_items ?>', 'font-size:13px;color:#7C3AED;');
</script>

</body>
</html>