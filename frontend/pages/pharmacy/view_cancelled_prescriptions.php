<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_cancelled_prescriptions.php
// V2 - FULL CANCELLED VIEW WITH CANCELLED BY NAME + QUANTITIES
// ================================================================
// ✅ Inaonyesha jina la aliye cancel (cancelled_by_name)
// ✅ Inaonyesha cancelled_quantity (sio quantity=0)
// ✅ Inaonyesha original_quantity
// ✅ Inaonyesha cancelled bill amount = cancelled_quantity × unit_price
// ✅ Inaonyesha tarehe/saa ya kucancel
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id   = isset($_GET['visit_id'])   ? (int)$_GET['visit_id']   : 0;

if (!$patient_id || !$visit_id) {
    die("Missing patient_id or visit_id");
}

$currency = 'TSh';
$message = '';
$message_type = '';

try {
    // Currency
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ============================================================
    // PATIENT INFO
    // ============================================================
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ? AND branch_id = ? LIMIT 1");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        die("Patient not found");
    }

    // ============================================================
    // VISIT INFO
    // ============================================================
    $stmt = $db->prepare("SELECT * FROM visits WHERE id = ? AND patient_id = ? LIMIT 1");
    $stmt->execute([$visit_id, $patient_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    // ============================================================
    // CANCELLED ITEMS — FULL DETAILS with cancelled_by_name
    // ============================================================
    $sql = "SELECT
                pi.id,
                pi.prescription_id,
                pi.patient_id,
                pi.inventory_id,
                pi.medication_name,
                pi.dosage,
                pi.frequency,
                pi.route,
                pi.instructions,
                pi.unit_price,
                pi.quantity,
                pi.original_quantity,
                pi.cancelled_quantity,
                pi.total_price,
                pi.original_total_price,
                pi.original_bill_amount,
                pi.cancelled_at,
                pi.cancelled_by,
                pi.cancel_reason,
                pi.cancellation_reason,
                pi.dispensed_at,
                pi.dispensed_by,
                pi.confirmed_by,
                pi.confirmed_at,
                u_cancel.full_name  AS cancelled_by_name,
                u_cancel.username   AS cancelled_by_username,
                u_confirm.full_name AS confirmed_by_name,
                u_dispense.full_name AS dispensed_by_name,
                p.prescription_number,
                p.status AS prescription_status,
                p.created_at AS prescription_created_at
            FROM prescription_items pi
            LEFT JOIN users u_cancel   ON u_cancel.id   = pi.cancelled_by
            LEFT JOIN users u_confirm  ON u_confirm.id  = pi.confirmed_by
            LEFT JOIN users u_dispense ON u_dispense.id = pi.dispensed_by
            LEFT JOIN prescriptions p  ON p.id          = pi.prescription_id
            WHERE pi.patient_id = :patient_id
              AND p.branch_id   = :branch_id
              AND p.visit_id    = :visit_id
              AND pi.cancelled_at IS NOT NULL
            ORDER BY pi.cancelled_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':patient_id' => $patient_id,
        ':branch_id'  => $user_branch_id,
        ':visit_id'   => $visit_id
    ]);
    $cancelled_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // TOTALS
    // ============================================================
    $total_original_qty     = 0;
    $total_cancelled_qty    = 0;
    $total_original_amount  = 0;
    $total_cancelled_amount = 0;

    foreach ($cancelled_items as &$item) {
        $unit_price = (float)($item['unit_price'] ?? 0);

        // Cancelled qty
        $cqty = (int)($item['cancelled_quantity'] ?? 0);
        if ($cqty <= 0 && !empty($item['original_quantity'])) {
            $cqty = (int)$item['original_quantity'];
        }
        if ($cqty <= 0 && !empty($item['total_price']) && $unit_price > 0) {
            $cqty = (int)round((float)$item['total_price'] / $unit_price);
        }

        // Original qty
        $oqty = (int)($item['original_quantity'] ?? 0);
        if ($oqty <= 0) {
            $oqty = (int)($item['quantity'] ?? 0) + $cqty;
        }

        // Amounts
        $orig_amount = $oqty * $unit_price;
        if (!empty($item['original_total_price'])) {
            $orig_amount = (float)$item['original_total_price'];
        } elseif (!empty($item['original_bill_amount'])) {
            $orig_amount = (float)$item['original_bill_amount'];
        }

        $canc_amount = $cqty * $unit_price;
        if ($canc_amount <= 0 && !empty($item['total_price'])) {
            $canc_amount = (float)$item['total_price'];
        }

        $item['_original_qty']     = $oqty;
        $item['_cancelled_qty']    = $cqty;
        $item['_original_amount']  = $orig_amount;
        $item['_cancelled_amount'] = $canc_amount;

        $total_original_qty     += $oqty;
        $total_cancelled_qty    += $cqty;
        $total_original_amount  += $orig_amount;
        $total_cancelled_amount += $canc_amount;
    }
    unset($item);

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $cancelled_items = [];
    $patient = [];
    $visit = [];
    $total_original_qty = 0;
    $total_cancelled_qty = 0;
    $total_original_amount = 0;
    $total_cancelled_amount = 0;
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelled Prescriptions - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-primary: 'Inter', -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --danger-light: #FCA5A5;
            --success: #059669;
            --success-bg: #D1FAE5;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1A1A2E;
            --gray-100: #1E293B;
            --danger-bg: #3A1A1A;
            --primary-bg: #1E3A5F;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-primary);
            background: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        .mono, .money, .qty, .date-mono {
            font-family: var(--font-mono) !important;
            font-feature-settings: 'tnum';
            font-variant-numeric: tabular-nums;
        }

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }

        /* PAGE HEADER - RED THEME */
        .page-header {
            background: linear-gradient(135deg, #DC2626, #B91C1C, #991B1B);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .page-header .page-title i { font-size: 1.7rem; opacity: 0.9; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }

        .page-header .visit-badge {
            background: rgba(255,255,255,0.25);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            font-family: var(--font-mono);
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 7px 14px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.78rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        /* ALERT */
        .alert-danger {
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            border-left: 5px solid var(--danger);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            color: #991B1B;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow);
        }

        [data-theme="dark"] .alert-danger {
            background: linear-gradient(135deg, #3A1A1A, #2A1010);
            color: #FCA5A5;
        }

        .alert-danger i {
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow);
        }

        .patient-card .patient-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            font-family: var(--font-mono);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .patient-card .patient-info h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .patient-card .patient-details {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .patient-card .patient-details span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .patient-card .patient-details i {
            color: var(--danger);
            font-size: 0.85rem;
        }

        /* SUMMARY CARDS */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .summary-card.danger-theme {
            border-color: var(--danger);
            background: linear-gradient(135deg, var(--bg-card), rgba(220, 38, 38, 0.04));
        }

        .summary-card .summary-label {
            font-size: 0.62rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .summary-card .summary-value {
            font-size: 1.6rem;
            font-weight: 800;
            font-family: var(--font-mono);
            color: var(--danger);
            line-height: 1.1;
        }

        .summary-card .summary-value.original {
            color: var(--text-muted);
            text-decoration: line-through;
            font-size: 1.3rem;
        }

        .summary-card .summary-value.success {
            color: var(--success);
        }

        /* ITEMS TABLE */
        .items-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .items-card-header {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            padding: 14px 22px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .items-card-header .title {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .items-card-header .count-badge {
            background: rgba(255,255,255,0.25);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            font-family: var(--font-mono);
        }

        .table-scroll { overflow-x: auto; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }

        .data-table thead th {
            text-align: left;
            padding: 12px 12px;
            font-weight: 700;
            font-size: 0.62rem;
            text-transform: uppercase;
            color: white;
            background: var(--danger);
            border-bottom: 3px solid var(--danger-dark);
            white-space: nowrap;
        }

        .data-table thead th i { margin-right: 5px; opacity: 0.7; }

        .data-table tbody td {
            padding: 12px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-size: 0.78rem;
        }

        .data-table tbody tr:hover td { background: var(--danger-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }

        [data-theme="dark"] .data-table tbody tr:hover td { background: #3A1A1A; }

        .badge-cancelled {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--danger);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-family: var(--font-mono);
        }

        .qty-cancelled {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--danger-bg);
            color: var(--danger);
            padding: 3px 10px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            font-family: var(--font-mono);
            border: 1px solid var(--danger);
        }

        .qty-original {
            display: inline-block;
            color: var(--text-muted);
            text-decoration: line-through;
            font-size: 0.8rem;
            font-family: var(--font-mono);
        }

        .amount-cancelled {
            color: var(--danger);
            font-weight: 800;
            font-family: var(--font-mono);
            font-size: 0.85rem;
        }

        .amount-original {
            color: var(--text-muted);
            text-decoration: line-through;
            font-family: var(--font-mono);
            font-size: 0.75rem;
        }

        .cancelled-by-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cancelled-by-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
            font-family: var(--font-mono);
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
        }

        .cancelled-by-info .name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.78rem;
        }

        .cancelled-by-info .meta {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-family: var(--font-mono);
        }

        .total-row {
            background: linear-gradient(135deg, #FEE2E2, #FECACA) !important;
            font-weight: 800;
        }

        [data-theme="dark"] .total-row {
            background: linear-gradient(135deg, #3A1A1A, #2A1010) !important;
        }

        .total-row td {
            border-top: 2px solid var(--danger);
            border-bottom: none !important;
            padding-top: 14px !important;
            padding-bottom: 14px !important;
            font-size: 0.85rem;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--success);
            display: block;
            margin-bottom: 16px;
            opacity: 0.7;
        }

        .empty-state p {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .empty-state .sub {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .footer .footer-brand { color: var(--primary); font-weight: 600; }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }

        @media (max-width: 768px) {
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.15rem; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .patient-card { flex-direction: column; text-align: center; }
            .patient-card .patient-details { justify-content: center; }
            .data-table { font-size: 0.68rem; }
            .data-table thead th, .data-table tbody td { padding: 8px 6px; }
        }

        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-ban"></i>
                Cancelled Prescriptions
                <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:20px;font-size:0.55rem;font-weight:600;text-transform:uppercase;">
                    FULL VIEW
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                Cancelled medications with full audit trail — original qty, cancelled qty & amounts
                <?php if (!empty($visit)): ?>
                    <span class="visit-badge">
                        <i class="fas fa-calendar-check"></i>
                        <?= htmlspecialchars($visit['visit_number'] ?? 'VIS-' . $visit_id) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php?status=cancelled" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- ALERT -->
    <div class="alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Cancelled Items Alert:</strong> Medications listed below were cancelled and stock has been returned to inventory.
            These items are <strong>NOT</strong> included in the patient's current bill.
        </div>
    </div>

    <!-- PATIENT CARD -->
    <?php if ($patient): ?>
    <div class="patient-card">
        <div class="patient-avatar">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="patient-info" style="flex:1;">
            <h2><?= htmlspecialchars($patient['full_name']) ?></h2>
            <div class="patient-details">
                <span><i class="fas fa-id-card"></i> ID: <span class="mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></span>
                <span><i class="fas fa-venus-mars"></i> <?= ucfirst($patient['gender'] ?? 'N/A') ?></span>
                <?php if (!empty($patient['date_of_birth'])):
                    $age = (new DateTime($patient['date_of_birth']))->diff(new DateTime('today'))->y;
                ?>
                    <span><i class="fas fa-calendar-alt"></i> <span class="mono"><?= $age ?></span> yrs</span>
                <?php endif; ?>
                <span><i class="fas fa-phone"></i> <span class="mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></span>
                <?php if (!empty($patient['blood_group'])): ?>
                    <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">
        <div class="summary-card danger-theme">
            <div class="summary-label">📋 Cancelled Items</div>
            <div class="summary-value"><?= count($cancelled_items) ?></div>
        </div>
        <div class="summary-card danger-theme">
            <div class="summary-label">📦 Original Qty</div>
            <div class="summary-value original"><?= $total_original_qty ?></div>
        </div>
        <div class="summary-card danger-theme">
            <div class="summary-label">❌ Cancelled Qty</div>
            <div class="summary-value"><?= $total_cancelled_qty ?></div>
        </div>
        <div class="summary-card danger-theme">
            <div class="summary-label">💰 Cancelled Amount</div>
            <div class="summary-value">-<?= $currency ?> <?= number_format($total_cancelled_amount, 0) ?></div>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <?php if (count($cancelled_items) > 0): ?>
    <div class="items-card">
        <div class="items-card-header">
            <div class="title">
                <i class="fas fa-list"></i>
                Cancelled Medications Details
            </div>
            <span class="count-badge"><?= count($cancelled_items) ?> items</span>
        </div>

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th style="text-align:center;"><i class="fas fa-ban"></i> Status</th>
                        <th style="text-align:center;"><i class="fas fa-sort-numeric-up"></i> Original Qty</th>
                        <th style="text-align:center;"><i class="fas fa-times-circle"></i> Cancelled Qty</th>
                        <th style="text-align:right;"><i class="fas fa-coins"></i> Unit Price</th>
                        <th style="text-align:right;"><i class="fas fa-money-bill"></i> Original Amount</th>
                        <th style="text-align:right;"><i class="fas fa-money-bill-wave"></i> Cancelled Amount</th>
                        <th><i class="fas fa-prescription"></i> Rx #</th>
                        <th><i class="fas fa-user-times"></i> Cancelled By</th>
                        <th><i class="fas fa-clock"></i> Cancelled At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($cancelled_items as $item):
                        $rx_num = $item['prescription_number'] ?? ('PRES-' . $item['prescription_id']);
                        $cancel_name = $item['cancelled_by_name'] ?? 'System';
                        $cancel_initials = strtoupper(substr($cancel_name, 0, 1));
                    ?>
                        <tr>
                            <td style="text-align:center;font-family:var(--font-mono);font-weight:700;color:var(--text-secondary);"><?= $i++ ?></td>
                            <td>
                                <div style="font-weight:700;font-size:0.82rem;color:var(--danger);text-decoration:line-through;">
                                    <?= htmlspecialchars($item['medication_name']) ?>
                                </div>
                                <?php if (!empty($item['dosage'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;">
                                        Dosage: <?= htmlspecialchars($item['dosage']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge-cancelled">
                                    <i class="fas fa-ban"></i> Cancelled
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="qty-original"><?= $item['_original_qty'] ?></span>
                            </td>
                            <td style="text-align:center;">
                                <span class="qty-cancelled">
                                    <i class="fas fa-times"></i> <?= $item['_cancelled_qty'] ?>
                                </span>
                            </td>
                            <td style="text-align:right;font-family:var(--font-mono);font-size:0.78rem;color:var(--text-secondary);">
                                <?= $currency ?> <?= number_format((float)$item['unit_price'], 0) ?>
                            </td>
                            <td style="text-align:right;">
                                <span class="amount-original"><?= $currency ?> <?= number_format($item['_original_amount'], 0) ?></span>
                            </td>
                            <td style="text-align:right;">
                                <div class="amount-cancelled">
                                    <?= $item['_cancelled_qty'] ?> × <?= $currency ?> <?= number_format((float)$item['unit_price'], 0) ?>
                                </div>
                                <div class="amount-cancelled" style="margin-top:2px;">
                                    -<?= $currency ?> <?= number_format($item['_cancelled_amount'], 0) ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-family:var(--font-mono);font-size:0.68rem;background:var(--primary-bg);color:var(--primary);padding:3px 8px;border-radius:5px;font-weight:600;">
                                    <?= htmlspecialchars($rx_num) ?>
                                </span>
                            </td>
                            <td>
                                <div class="cancelled-by-cell">
                                    <div class="cancelled-by-avatar"><?= $cancel_initials ?></div>
                                    <div class="cancelled-by-info">
                                        <div class="name"><?= htmlspecialchars($cancel_name) ?></div>
                                        <?php if (!empty($item['cancelled_by_username'])): ?>
                                            <div class="meta">@<?= htmlspecialchars($item['cancelled_by_username']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="date-mono" style="font-size:0.7rem;color:var(--text-secondary);">
                                    <?php if (!empty($item['cancelled_at'])): ?>
                                        <div style="font-weight:700;color:var(--text-primary);">
                                            <?= date('d M Y', strtotime($item['cancelled_at'])) ?>
                                        </div>
                                        <div style="font-size:0.65rem;">
                                            <?= date('H:i:s', strtotime($item['cancelled_at'])) ?>
                                        </div>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- TOTAL ROW -->
                    <tr class="total-row">
                        <td colspan="3" style="text-align:right;color:var(--danger);">
                            <i class="fas fa-calculator"></i> TOTAL:
                        </td>
                        <td style="text-align:center;color:var(--text-muted);text-decoration:line-through;font-family:var(--font-mono);">
                            <?= $total_original_qty ?>
                        </td>
                        <td style="text-align:center;color:var(--danger);font-family:var(--font-mono);font-size:1rem;">
                            <?= $total_cancelled_qty ?>
                        </td>
                        <td></td>
                        <td style="text-align:right;color:var(--text-muted);text-decoration:line-through;font-family:var(--font-mono);">
                            <?= $currency ?> <?= number_format($total_original_amount, 0) ?>
                        </td>
                        <td style="text-align:right;color:var(--danger);font-family:var(--font-mono);font-size:1rem;">
                            -<?= $currency ?> <?= number_format($total_cancelled_amount, 0) ?>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>


    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No cancelled items for this visit</p>
            <p class="sub">All medications for this visit are active ✅</p>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Cancelled Prescriptions V2 (Full Audit)
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

</body>
</html>