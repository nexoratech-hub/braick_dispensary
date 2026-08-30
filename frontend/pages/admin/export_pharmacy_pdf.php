<?php
// ================================================================
// FILE: frontend/pages/admin/export_pharmacy_pdf.php
// EXPORT PHARMACY REPORT TO PDF - HTML FALLBACK VERSION
// BRAICK DISPENSARY - PURPLE THEME
// FIXED: Uses bills table and medications_inventory
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// WITH OFFICIAL STAMP & ADMIN CONTACTS
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database
require_once '../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET ADMIN CONTACT NUMBERS
// ================================================================
$admin_phones = [];
try {
    $stmt = $db->prepare("
        SELECT phone FROM users 
        WHERE role = 'admin' AND branch_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute([$user_branch_id]);
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $admin_phones = [];
}

// ================================================================
// GET BRANCH PHONE
// ================================================================
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

$admin_phones_display = !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001');

// ================================================================
// GET PARAMETERS
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><rect width="60" height="60" rx="12" fill="#7C3AED"/><text x="30" y="38" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="Arial">B</text></svg>');

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
}

// ================================================================
// BUILD DATE FILTER
// ================================================================
$date_filter = "";
if (!empty($date_from) && !empty($date_to)) {
    $date_filter = " AND b.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
} elseif (!empty($date_from)) {
    $date_filter = " AND b.created_at >= '$date_from 00:00:00'";
} elseif (!empty($date_to)) {
    $date_filter = " AND b.created_at <= '$date_to 23:59:59'";
}

// ================================================================
// BRANCH FILTER
// ================================================================
$branch_filter = "";
if ($branch_id > 0) {
    $branch_filter = " AND b.branch_id = $branch_id";
}

// ================================================================
// FETCH PHARMACY DATA - USING bills AND medications_inventory
// ================================================================

// Get prescription bills (medication items) - USING bills table
$stmt = $db->query("
    SELECT 
        b.id as bill_id,
        b.bill_number,
        b.patient_id,
        b.total_amount,
        b.paid_amount,
        b.balance,
        b.status as bill_status,
        b.payment_method,
        b.created_at as bill_date,
        b.branch_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as cashier_name,
        br.name as branch_name,
        (
            SELECT COUNT(*) 
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
        ) as medication_count,
        (
            SELECT COALESCE(SUM(bi.total_price), 0)
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
        ) as medication_total
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE EXISTS (
        SELECT 1 
        FROM bill_items bi 
        WHERE bi.bill_id = b.id 
        AND bi.item_type = 'medication'
        AND bi.status != 'cancelled'
    )
    AND b.status = 'paid'
    $branch_filter $date_filter
    ORDER BY b.created_at DESC
");
$prescription_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get medication items - FROM bill_items and medications_inventory
$stmt = $db->query("
    SELECT 
        bi.id,
        bi.bill_id,
        bi.item_name as medication_name,
        bi.quantity,
        bi.unit_price,
        bi.total_price,
        bi.status as item_status,
        bi.created_at,
        b.bill_number,
        b.patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        b.branch_id,
        br.name as branch_name,
        mi.batch_number,
        mi.category,
        mi.unit,
        mi.expiry_date
    FROM bill_items bi
    LEFT JOIN bills b ON bi.bill_id = b.id
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN branches br ON b.branch_id = br.id
    LEFT JOIN medications_inventory mi ON mi.medication_name = bi.item_name AND mi.branch_id = b.branch_id
    WHERE bi.item_type = 'medication'
    AND bi.status != 'cancelled'
    AND b.status = 'paid'
    $branch_filter $date_filter
    ORDER BY bi.created_at DESC
    LIMIT 200
");
$medication_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_prescription_bills = count($prescription_bills);
$total_prescription_amount = 0;
$total_medication_items = 0;
$total_medication_quantity = 0;
$total_medication_value = 0;

foreach ($prescription_bills as $bill) {
    $total_prescription_amount += $bill['medication_total'] ?? 0;
}

foreach ($medication_items as $item) {
    $total_medication_items++;
    $total_medication_quantity += $item['quantity'] ?? 0;
    $total_medication_value += $item['total_price'] ?? 0;
}

// Get OTC sales - USING otc_sales table (exists in database)
$stmt = $db->query("
    SELECT os.*, u.full_name as cashier_name, br.name as branch_name
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches br ON os.branch_id = br.id
    WHERE os.payment_status = 'paid'
    $branch_filter $date_filter
    ORDER BY os.created_at DESC
");
$otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_otc_sales = count($otc_sales);
$total_otc_amount = 0;
$total_otc_items_sold = 0;

foreach ($otc_sales as $sale) {
    $total_otc_amount += $sale['total_amount'] ?? 0;
}

// Get OTC items
$stmt = $db->query("
    SELECT oi.*, os.sale_number, os.customer_name, os.created_at as sale_date,
           br.name as branch_name
    FROM otc_sale_items oi
    LEFT JOIN otc_sales os ON oi.sale_id = os.id
    LEFT JOIN branches br ON os.branch_id = br.id
    WHERE os.payment_status = 'paid'
    $branch_filter $date_filter
    ORDER BY oi.created_at DESC
    LIMIT 200
");
$otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($otc_items as $item) {
    $total_otc_items_sold += $item['quantity'] ?? 0;
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
        'assigned' => 'Assigned'
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
    <title>Pharmacy Report - <?= htmlspecialchars($branch_name) ?></title>
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
            max-width: 1100px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px 35px;
        }
        
        /* ================================================================
           HEADER WITH LOGO - PURPLE THEME LIKE EXPENSES
           ================================================================ */
        .report-header {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            color: white;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .report-header .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
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
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
            color: white;
        }
        
        .report-header .brand .logo-text p {
            font-size: 12px;
            opacity: 0.85;
            margin: 2px 0 0 0;
            color: rgba(255,255,255,0.85);
        }
        
        .report-header .meta-info {
            text-align: right;
            font-size: 12px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .report-header .meta-info .badge-print {
            background: rgba(255,255,255,0.2);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
            color: white;
        }
        
        /* Admin Contact Line */
        .admin-contact-line {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 10px;
            color: rgba(255,255,255,0.7);
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid rgba(255,255,255,0.1);
            position: relative;
            z-index: 1;
        }
        
        .admin-contact-line span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .admin-contact-line i {
            color: rgba(255,255,255,0.6);
        }
        
        /* ================================================================
           SUMMARY CARDS - PURPLE THEME
           ================================================================ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 14px 12px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .summary-card .number {
            font-size: 20px;
            font-weight: 800;
        }
        
        .summary-card .number.purple { color: #7C3AED; }
        .summary-card .number.orange { color: #D97706; }
        .summary-card .number.blue { color: #0B5ED7; }
        .summary-card .number.green { color: #059669; }
        
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
            border-left: 4px solid #7C3AED;
            margin: 16px 0 10px 0;
            border-radius: 0 4px 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: #7C3AED;
        }
        
        /* ================================================================
           FILTER INFO
           ================================================================ */
        .filter-info {
            background: #F8FAFC;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 11px;
            color: #64748B;
            margin-bottom: 12px;
            border: 1px solid #E2E8F0;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .filter-info span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .filter-info i {
            color: #7C3AED;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 9px;
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
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        
        .data-table th {
            background: #7C3AED;
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid #6D28D9;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .data-table td {
            padding: 5px 10px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover td {
            background: #F8FAFC;
        }
        
        .text-right { text-align: right; }
        .text-green { color: #059669; }
        .text-red { color: #DC2626; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: 700; }
        
        /* ================================================================
           OFFICIAL STAMP - LIKE EXPENSES PDF
           ================================================================ */
        .official-stamp {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .official-stamp .stamp-left {
            font-size: 12px;
            color: #64748B;
        }
        
        .official-stamp .stamp-left strong {
            color: #1E293B;
        }
        
        .official-stamp .stamp-box {
            text-align: center;
            padding: 8px 20px;
            border: 3px solid #7C3AED;
            border-radius: 10px;
            background: #EDE9FE;
            min-width: 160px;
        }
        
        .official-stamp .stamp-box .stamp-title {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .official-stamp .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #7C3AED;
        }
        
        .official-stamp .stamp-box .stamp-line {
            font-size: 11px;
            color: #64748B;
            margin-top: 2px;
        }
        
        .official-stamp .stamp-box .stamp-date {
            font-size: 9px;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        /* ================================================================
           NO DATA
           ================================================================ */
        .no-data {
            text-align: center;
            color: #94A3B8;
            padding: 30px 0;
            font-style: italic;
        }
        
        .no-data i {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
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
            background: #7C3AED;
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
            background: #6D28D9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
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
            .report-header { flex-direction: column; text-align: center; }
            .report-header .brand { flex-direction: column; }
            .report-header .meta-info { text-align: center; }
            .data-table { font-size: 8px; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .official-stamp { flex-direction: column; text-align: center; }
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
            .data-table th {
                background: #7C3AED !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .summary-card {
                border-color: #ddd !important;
            }
            .official-stamp .stamp-box {
                background: #EDE9FE !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-color: #7C3AED !important;
            }
            .admin-contact-line {
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
    <!-- HEADER WITH LOGO - PURPLE THEME LIKE EXPENSES -->
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
                <p>Tunajali Afya Yako</p>
            </div>
        </div>
        <div class="meta-info">
            <div><strong>Pharmacy Report</strong></div>
            <div>Generated: <?= date('M d, Y h:i A') ?></div>
            <span class="badge-print">💊 Pharmacy Report</span>
        </div>
    </div>
    
    <!-- Admin Contact Line -->
    <div class="admin-contact-line">
        <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($user_branch_name) ?> Branch</span>
        <span><i class="fas fa-user"></i> Generated by: <?= htmlspecialchars($user_full_name) ?></span>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER INFO -->
    <!-- ================================================================ -->
    <div class="filter-info">
        <span><i class="fas fa-store"></i> Branch: <strong><?= htmlspecialchars($branch_name) ?></strong></span>
        <?php if (!empty($date_from) || !empty($date_to)): ?>
            <span><i class="fas fa-calendar"></i> Period: 
                <strong>
                    <?= !empty($date_from) ? date('M d, Y', strtotime($date_from)) : 'Start' ?>
                    -
                    <?= !empty($date_to) ? date('M d, Y', strtotime($date_to)) : 'End' ?>
                </strong>
            </span>
        <?php else: ?>
            <span><i class="fas fa-calendar"></i> Period: <strong>All Time</strong></span>
        <?php endif; ?>
        <span><i class="fas fa-prescription"></i> Prescriptions: <strong><?= number_format($total_prescription_bills) ?></strong></span>
        <span><i class="fas fa-shopping-cart"></i> OTC Sales: <strong><?= number_format($total_otc_sales) ?></strong></span>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="number purple">TSh <?= number_format($total_prescription_amount, 0) ?></div>
            <div class="label">Prescription Revenue</div>
            <div class="sub-label"><?= number_format($total_prescription_bills) ?> transactions</div>
        </div>
        <div class="summary-card">
            <div class="number orange">TSh <?= number_format($total_otc_amount, 0) ?></div>
            <div class="label">OTC Revenue</div>
            <div class="sub-label"><?= number_format($total_otc_sales) ?> transactions</div>
        </div>
        <div class="summary-card">
            <div class="number blue">TSh <?= number_format($total_prescription_amount + $total_otc_amount, 0) ?></div>
            <div class="label">Total Pharmacy Revenue</div>
            <div class="sub-label">Prescription + OTC</div>
        </div>
        <div class="summary-card">
            <div class="number green"><?= number_format($total_medication_quantity + $total_otc_items_sold) ?></div>
            <div class="label">Total Items Sold</div>
            <div class="sub-label"><?= number_format($total_medication_quantity) ?> Rx · <?= number_format($total_otc_items_sold) ?> OTC</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION BILLS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-prescription"></i> Prescription Bills (<?= count($prescription_bills) ?>)
    </div>

    <?php if (!empty($prescription_bills)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Patient</th>
                    <th style="text-align:right;">Items</th>
                    <th style="text-align:right;">Medication Total</th>
                    <th>Branch</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $pb_total = 0;
                $pb_items = 0;
                
                foreach ($prescription_bills as $bill):
                    $pb_total += $bill['medication_total'] ?? 0;
                    $pb_items += $bill['medication_count'] ?? 0;
                ?>
                    <tr>
                        <td class="font-mono" style="font-size:9px;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                        <td style="text-align:right;"><?= number_format($bill['medication_count'] ?? 0) ?></td>
                        <td style="text-align:right;font-weight:bold;color:#7C3AED;">TSh <?= number_format($bill['medication_total'] ?? 0, 0) ?></td>
                        <td><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                        <td style="font-size:9px;"><?= date('M d, Y', strtotime($bill['bill_date'] ?? 'now')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:700;border-top:2px solid #7C3AED;">
                    <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                    <td style="text-align:right;"><?= number_format($pb_items) ?></td>
                    <td style="text-align:right;color:#7C3AED;">TSh <?= number_format($pb_total, 0) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-prescription"></i>
            No prescription bills found
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MEDICATION ITEMS -->
    <!-- ================================================================ -->
    <div class="section-title" style="margin-top:24px;">
        <i class="fas fa-pills"></i> Medication Items (<?= count($medication_items) ?>)
    </div>

    <?php if (!empty($medication_items)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Patient</th>
                    <th>Medication</th>
                    <th>Batch</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $mi_total = 0;
                $mi_qty = 0;
                
                foreach ($medication_items as $item):
                    $mi_total += $item['total_price'] ?? 0;
                    $mi_qty += $item['quantity'] ?? 0;
                ?>
                    <tr>
                        <td style="font-size:8px;"><?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?></td>
                        <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                        <td style="font-size:8px;color:#94A3B8;"><?= htmlspecialchars($item['batch_number'] ?? '-') ?></td>
                        <td style="text-align:right;"><?= number_format($item['quantity']) ?></td>
                        <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                        <td style="text-align:right;font-weight:bold;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:700;border-top:2px solid #7C3AED;">
                    <td colspan="4" style="text-align:right;">GRAND TOTAL</td>
                    <td style="text-align:right;"><?= number_format($mi_qty) ?></td>
                    <td></td>
                    <td style="text-align:right;">TSh <?= number_format($mi_total, 0) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-pills"></i>
            No medication items found
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OTC SALES -->
    <!-- ================================================================ -->
    <div class="section-title" style="margin-top:24px;">
        <i class="fas fa-shopping-cart"></i> OTC Sales (<?= count($otc_sales) ?>)
    </div>

    <?php if (!empty($otc_sales)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sale #</th>
                    <th>Customer</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Discount</th>
                    <th style="text-align:right;">Net</th>
                    <th>Payment</th>
                    <th>Sold By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $otc_total = 0;
                $otc_discount = 0;
                $otc_net = 0;
                
                foreach ($otc_sales as $sale):
                    $otc_total += $sale['total_amount'] ?? 0;
                    $otc_discount += $sale['discount_amount'] ?? 0;
                    $otc_net += $sale['net_amount'] ?? 0;
                ?>
                    <tr>
                        <td class="font-mono" style="font-size:9px;"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                        <td style="text-align:right;font-weight:bold;">TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                        <td style="text-align:right;">TSh <?= number_format($sale['discount_amount'] ?? 0, 0) ?></td>
                        <td style="text-align:right;color:#059669;font-weight:bold;">TSh <?= number_format($sale['net_amount'] ?? 0, 0) ?></td>
                        <td><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></td>
                        <td style="font-size:9px;"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:700;border-top:2px solid #7C3AED;">
                    <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                    <td style="text-align:right;">TSh <?= number_format($otc_total, 0) ?></td>
                    <td style="text-align:right;">TSh <?= number_format($otc_discount, 0) ?></td>
                    <td style="text-align:right;color:#059669;">TSh <?= number_format($otc_net, 0) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-shopping-cart"></i>
            No OTC sales found
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OTC ITEMS -->
    <!-- ================================================================ -->
    <div class="section-title" style="margin-top:24px;">
        <i class="fas fa-boxes"></i> OTC Items (<?= count($otc_items) ?>)
    </div>

    <?php if (!empty($otc_items)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sale #</th>
                    <th>Customer</th>
                    <th>Medicine</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $oi_total = 0;
                $oi_qty = 0;
                
                foreach ($otc_items as $item):
                    $oi_total += $item['total_price'] ?? 0;
                    $oi_qty += $item['quantity'] ?? 0;
                ?>
                    <tr>
                        <td style="font-size:8px;"><?= htmlspecialchars($item['sale_number'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['customer_name'] ?? 'Walk-in') ?></td>
                        <td><strong><?= htmlspecialchars($item['medicine_name']) ?></strong></td>
                        <td style="text-align:right;"><?= number_format($item['quantity']) ?></td>
                        <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                        <td style="text-align:right;font-weight:bold;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                        <td style="font-size:9px;"><?= date('M d, Y', strtotime($item['sale_date'] ?? 'now')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:700;border-top:2px solid #7C3AED;">
                    <td colspan="3" style="text-align:right;">GRAND TOTAL</td>
                    <td style="text-align:right;"><?= number_format($oi_qty) ?></td>
                    <td></td>
                    <td style="text-align:right;">TSh <?= number_format($oi_total, 0) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-boxes"></i>
            No OTC items found
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OFFICIAL STAMP - LIKE EXPENSES PDF -->
    <!-- ================================================================ -->
    <div class="official-stamp">
        <div class="stamp-left">
            <span>Generated by: <strong><?= htmlspecialchars($user_full_name) ?></strong></span>
            <span style="margin-left:14px;">Date: <strong><?= date('F d, Y') ?></strong></span>
            <span style="margin-left:14px;display:block;font-size:10px;color:#94A3B8;margin-top:4px;">
                <i class="fas fa-print"></i> Printed: <?= date('h:i A') ?>
            </span>
        </div>
        <div class="stamp-box">
            <div class="stamp-title">Official Stamp</div>
            <div class="stamp-name">BRAICK DISPENSARY</div>
            <div class="stamp-line">Approved By: _________________</div>
            <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <strong>Braick Dispensary</strong> Management System 
        <span style="margin:0 8px;color:#CBD5E1;">|</span>
        Pharmacy Report 
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
    
    console.log('%c💊 Braick Dispensary - Pharmacy Report (WITH LOGIN SESSION)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ USING: bills table + medications_inventory', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Design like expenses with logo & official stamp', 'font-size:13px; color:#34D399;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Prescription Revenue: TSh <?= number_format($total_prescription_amount, 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Revenue: TSh <?= number_format($total_otc_amount, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📞 Admin Contacts: <?= htmlspecialchars($admin_phones_display) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>
<?php
exit;
?>