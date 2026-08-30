<?php
// ================================================================
// FILE: frontend/pages/admin/export_cashier_pdf.php
// EXPORT CASHIER REPORT TO PDF - HTML FALLBACK VERSION
// BRAICK DISPENSARY - GREEN THEME - WITH LOGIN SESSION
// WITH OFFICIAL STAMP & ADMIN CONTACTS
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($username) && !empty($username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $profile_pic = $user['profile_pic'];
            }
        } catch (Exception $e) {
            // Fallback to session values
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

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
$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><rect width="60" height="60" rx="12" fill="#059669"/><text x="30" y="38" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="Arial">B</text></svg>');

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
    $date_filter = " AND pb.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
} elseif (!empty($date_from)) {
    $date_filter = " AND pb.created_at >= '$date_from 00:00:00'";
} elseif (!empty($date_to)) {
    $date_filter = " AND pb.created_at <= '$date_to 23:59:59'";
}

// ================================================================
// BRANCH FILTER - Using visits table to get branch
// ================================================================
$branch_filter = "";
if ($branch_id > 0) {
    $branch_filter = " AND v.branch_id = $branch_id";
}

// ================================================================
// FETCH CASHIER DATA - Using bills table
// ================================================================

// Total Revenue
$stmt = $db->query("
    SELECT COALESCE(SUM(b.total_amount), 0) as total 
    FROM bills b
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE b.status = 'paid' $branch_filter $date_filter
");
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Expenses (discounts)
$stmt = $db->query("
    SELECT COALESCE(SUM(b.total_discount), 0) as total 
    FROM bills b
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE b.status = 'paid' $branch_filter $date_filter
");
$total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total Profit = Revenue - Expenses
$total_profit = $total_revenue - $total_expenses;

// All bills with details - USING bills table
$stmt = $db->query("
    SELECT b.*, p.full_name as patient_name, u.full_name as cashier_name,
           v.branch_id, br.name as branch_name
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN branches br ON v.branch_id = br.id
    WHERE 1=1 $branch_filter $date_filter
    ORDER BY b.created_at DESC
");
$cashier_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Patient totals
$stmt = $db->query("
    SELECT 
        p.id, p.full_name, p.patient_id,
        COUNT(b.id) as bill_count,
        COALESCE(SUM(b.total_amount), 0) as total_paid,
        COALESCE(SUM(b.total_discount), 0) as total_discount
    FROM patients p
    LEFT JOIN bills b ON p.id = b.patient_id AND b.status = 'paid'
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE 1=1 $branch_filter $date_filter
    GROUP BY p.id
    HAVING bill_count > 0
    ORDER BY total_paid DESC
    LIMIT 20
");
$patient_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count bills by status
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN b.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN b.status = 'partial' THEN 1 ELSE 0 END) as partial_count,
        SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        COUNT(*) as total_count
    FROM bills b
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE 1=1 $branch_filter $date_filter
");
$bill_stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
// FORMAT CURRENCY
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
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
    <title>Cashier Report - <?= htmlspecialchars($branch_name) ?></title>
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
            position: relative;
        }
        
        /* ================================================================
           HEADER WITH LOGO - GREEN THEME LIKE EXPENSES
           ================================================================ */
        .report-header {
            background: linear-gradient(135deg, #059669, #047857);
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
           SUMMARY CARDS - GREEN THEME
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
        
        .summary-card .number.green { color: #059669; }
        .summary-card .number.red { color: #DC2626; }
        .summary-card .number.teal { color: #0D9488; }
        .summary-card .number.blue { color: #0B5ED7; }
        
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
            border-left: 4px solid #059669;
            margin: 16px 0 10px 0;
            border-radius: 0 4px 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: #059669;
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
            color: #059669;
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
            background: #059669;
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid #047857;
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
            border: 3px solid #059669;
            border-radius: 10px;
            background: #D1FAE5;
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
            color: #059669;
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
            background: #059669;
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
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
                background: #059669 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .summary-card {
                border-color: #ddd !important;
            }
            .official-stamp .stamp-box {
                background: #D1FAE5 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-color: #059669 !important;
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
    <!-- HEADER WITH LOGO - GREEN THEME LIKE EXPENSES -->
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
            <div><strong>Cashier Report</strong></div>
            <div>Generated: <?= date('M d, Y h:i A') ?></div>
            <span class="badge-print">💰 Financial Report</span>
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
        <span><i class="fas fa-file-invoice"></i> Total Bills: <strong><?= number_format($bill_stats['total_count'] ?? 0) ?></strong></span>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="number green"><?= formatCurrency($total_revenue) ?></div>
            <div class="label">Total Revenue</div>
            <div class="sub-label">All paid bills</div>
        </div>
        <div class="summary-card">
            <div class="number red"><?= formatCurrency($total_expenses) ?></div>
            <div class="label">Total Expenses</div>
            <div class="sub-label">Discounts given</div>
        </div>
        <div class="summary-card">
            <div class="number teal"><?= formatCurrency($total_profit) ?></div>
            <div class="label">Total Profit</div>
            <div class="sub-label">Revenue - Expenses</div>
        </div>
        <div class="summary-card">
            <div class="number blue"><?= number_format($bill_stats['total_count'] ?? 0) ?></div>
            <div class="label">Total Bills</div>
            <div class="sub-label">
                <?= number_format($bill_stats['paid_count'] ?? 0) ?> Paid · 
                <?= number_format($bill_stats['pending_count'] ?? 0) ?> Pending
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS TABLE -->
    <!-- ================================================================ -->
    <div class="section-title">
        <i class="fas fa-file-invoice"></i> All Bills (<?= count($cashier_bills) ?>)
    </div>

    <?php if (!empty($cashier_bills)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Patient</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Paid</th>
                    <th style="text-align:right;">Balance</th>
                    <th style="text-align:right;">Discount</th>
                    <th>Status</th>
                    <th>Branch</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grand_total = 0;
                $grand_paid = 0;
                $grand_balance = 0;
                $grand_discount = 0;
                
                foreach ($cashier_bills as $bill):
                    $grand_total += $bill['total_amount'] ?? 0;
                    $grand_paid += $bill['paid_amount'] ?? 0;
                    $grand_balance += $bill['balance'] ?? 0;
                    $grand_discount += $bill['total_discount'] ?? 0;
                ?>
                    <tr>
                        <td class="font-mono" style="font-size:9px;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                        <td style="text-align:right;font-weight:bold;"><?= formatCurrency($bill['total_amount'] ?? 0) ?></td>
                        <td style="text-align:right;color:#059669;"><?= formatCurrency($bill['paid_amount'] ?? 0) ?></td>
                        <td style="text-align:right;color:#DC2626;"><?= formatCurrency($bill['balance'] ?? 0) ?></td>
                        <td style="text-align:right;"><?= formatCurrency($bill['total_discount'] ?? 0) ?></td>
                        <td>
                            <span class="badge badge-<?= $bill['status'] === 'paid' ? 'success' : ($bill['status'] === 'pending' ? 'warning' : ($bill['status'] === 'partial' ? 'warning' : 'danger')) ?>">
                                <?= getStatusLabel($bill['status'] ?? 'pending') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></td>
                        <td style="font-size:9px;"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:700;border-top:2px solid #059669;">
                    <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                    <td style="text-align:right;"><?= formatCurrency($grand_total) ?></td>
                    <td style="text-align:right;color:#059669;"><?= formatCurrency($grand_paid) ?></td>
                    <td style="text-align:right;color:#DC2626;"><?= formatCurrency($grand_balance) ?></td>
                    <td style="text-align:right;"><?= formatCurrency($grand_discount) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-file-invoice"></i>
            No bills found for the selected filters
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT TOTALS -->
    <!-- ================================================================ -->
    <div class="section-title" style="margin-top:24px;">
        <i class="fas fa-users"></i> Patient Totals (<?= count($patient_totals) ?>)
    </div>

    <?php if (!empty($patient_totals)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>ID</th>
                    <th style="text-align:right;"># Bills</th>
                    <th style="text-align:right;">Total Paid</th>
                    <th style="text-align:right;">Total Discount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patient_totals as $pt): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pt['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($pt['patient_id']) ?></td>
                        <td style="text-align:right;"><?= number_format($pt['bill_count']) ?></td>
                        <td style="text-align:right;color:#059669;font-weight:bold;"><?= formatCurrency($pt['total_paid']) ?></td>
                        <td style="text-align:right;"><?= formatCurrency($pt['total_discount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-users"></i>
            No patient data found
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
        Cashier Report 
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
    
    console.log('%c💰 Braick Dispensary - Export Cashier Report (WITH LOGIN SESSION)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💵 Total Revenue: <?= formatCurrency($total_revenue) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Total Expenses: <?= formatCurrency($total_expenses) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📈 Total Profit: <?= formatCurrency($total_profit) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c📄 Total Bills: <?= number_format($bill_stats['total_count'] ?? 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Using: bills table (NOT patient_bills)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Design like expenses with logo & official stamp', 'font-size:13px; color:#34D399;');
    console.log('%c📞 Admin Contacts: <?= htmlspecialchars($admin_phones_display) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>
<?php
exit;
?>