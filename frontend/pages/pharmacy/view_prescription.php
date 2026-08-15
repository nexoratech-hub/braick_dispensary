<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_prescription.php
// PHARMACY - VIEW PRESCRIPTION DETAILS
// READ-ONLY MODE - No status update buttons
// NO VIEW BILL BUTTON
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();
$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: pending_prescriptions.php?error=invalid_id');
    exit;
}

// ================================================================
// GET PRESCRIPTION DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        p.*,
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone,
        pat.email,
        pat.date_of_birth,
        pat.gender,
        pat.address,
        pat.blood_group,
        pat.allergies,
        pat.emergency_contact,
        u.full_name as doctor_name,
        u.specialty,
        u.email as doctor_email,
        u.phone as doctor_phone,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        v.symptoms,
        v.notes as visit_notes,
        ph.full_name as pharmacy_name,
        (
            SELECT id FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_id,
        (
            SELECT bill_number FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_number,
        (
            SELECT total_amount FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_total,
        (
            SELECT discount_amount FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_discount,
        (
            SELECT balance FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_balance,
        (
            SELECT status FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_status
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN visits v ON p.visit_id = v.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    WHERE p.id = ? AND p.branch_id = ?
");
$stmt->execute([$prescription_id, $user_branch_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: pending_prescriptions.php?error=not_found');
    exit;
}

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM prescription_items 
    WHERE prescription_id = ? 
    ORDER BY id ASC
");
$stmt->execute([$prescription_id]);
$prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no items, create from main prescription
if (empty($prescription_items) && !empty($prescription['medication'])) {
    $prescription_items = [[
        'id' => 0,
        'prescription_id' => $prescription_id,
        'medication_name' => $prescription['medication'],
        'dosage' => $prescription['dosage'] ?? '',
        'frequency' => $prescription['frequency'] ?? '',
        'quantity' => $prescription['quantity'] ?? 1,
        'duration' => $prescription['duration'] ?? '',
        'route' => $prescription['route'] ?? '',
        'instructions' => $prescription['instructions'] ?? '',
        'unit_price' => 0,
        'total_price' => 0
    ]];
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_quantity = 0;
$total_price = 0;
foreach ($prescription_items as $item) {
    $total_quantity += (int)$item['quantity'];
    $total_price += (float)($item['total_price'] ?? $item['unit_price'] * $item['quantity']);
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'confirmed' => '✅ Confirmed - Awaiting Payment',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getBillStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending Payment',
        'partial' => '🔶 Partial',
        'paid' => '✅ Paid',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getBillStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'partial' => 'badge-warning',
        'paid' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

// ================================================================
// INCLUDE PHARMACY HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .page-header .badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .badge-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* Items Table */
        .table-wrap {
            overflow-x: auto;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        .items-table thead th:last-child { text-align: right; }
        .items-table tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-table tbody td:last-child { text-align: right; font-weight: 600; font-family: monospace; }
        .items-table tbody tr:hover td { background: var(--primary-bg); }
        .items-table .total-row td {
            font-weight: 700;
            border-top: 2px solid var(--primary);
            background: var(--primary-bg);
            padding: 8px 12px;
        }
        .items-table .total-row td:last-child {
            color: var(--primary);
            font-size: 1rem;
        }
        
        /* Bill Summary */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .bill-summary-item {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 10px 14px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .bill-summary-item .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .bill-summary-item .value {
            font-size: 1.2rem;
            font-weight: 700;
            font-family: monospace;
            margin-top: 2px;
        }
        
        .bill-summary-item .value.green { color: var(--success); }
        .bill-summary-item .value.red { color: var(--danger); }
        .bill-summary-item .value.blue { color: var(--primary); }
        .bill-summary-item .value.orange { color: var(--warning); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        
        .btn-success-custom {
            background: var(--success);
            color: white;
        }
        
        .btn-success-custom:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        /* Read Only Badge */
        .read-only-badge {
            display: inline-block;
            background: var(--gray-500);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
            .items-table { font-size: 0.7rem; }
            .card { padding: 16px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search prescriptions...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescription Details
                <span class="badge-display"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
                <span class="badge-status <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>" style="background:rgba(255,255,255,0.2);color:white;border-color:rgba(255,255,255,0.3);">
                    <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></strong>
                <span class="text-xs text-gray-400 ml-2">
                    (<?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?>)
                </span>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-stethoscope"></i> Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'Not Assigned') ?>
                </span>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($prescription['created_at'])) ?>
                </span>
                <?php if ($prescription['status'] === 'dispensed'): ?>
                    <span class="text-xs text-green-400 ml-2">
                        <i class="fas fa-check-circle"></i> Dispensed: <?= formatDate($prescription['dispensed_at']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if ($prescription['status'] === 'dispensed'): ?>
                <span class="read-only-badge">
                    <i class="fas fa-lock"></i> Read Only
                </span>
            <?php endif; ?>
            <a href="<?= $prescription['status'] === 'dispensed' ? 'dispensed_prescriptions.php' : 'pending_prescriptions.php' ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-user-circle"></i>
            Patient Information
            <span class="badge-count">
                <?= ($prescription['gender'] ?? '') === 'Female' ? '👩' : '👨' ?>
                <?= $prescription['gender'] ?? 'N/A' ?>
                <?= !empty($prescription['date_of_birth']) ? '• ' . calculateAge($prescription['date_of_birth']) . ' yrs' : '' ?>
            </span>
        </h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($prescription['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($prescription['date_of_birth']) ? date('d/m/Y', strtotime($prescription['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($prescription['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($prescription['email'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($prescription['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($prescription['allergies'] ?? 'None') ?></span></div>
            <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($prescription['address'] ?? 'N/A') ?></span></div>
            <?php if (!empty($prescription['emergency_contact'])): ?>
                <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Emergency Contact</span><span class="detail-value"><?= htmlspecialchars($prescription['emergency_contact']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION INFORMATION -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-prescription"></i>
            Prescription Information
            <span class="badge-count"><?= count($prescription_items) ?> item(s)</span>
            <?php if ($prescription['status'] === 'dispensed'): ?>
                <span class="badge-status badge-success">✅ Dispensed</span>
            <?php endif; ?>
        </h3>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;">
            <div class="detail-row"><span class="detail-label">Prescription #</span><span class="detail-value"><strong><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Visit #</span><span class="detail-value"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Visit Type</span><span class="detail-value"><?= ucfirst(htmlspecialchars($prescription['visit_type'] ?? 'N/A')) ?></span></div>
            <div class="detail-row"><span class="detail-label">Doctor</span><span class="detail-value"><?= htmlspecialchars($prescription['doctor_name'] ?? 'Not Assigned') ?></span></div>
            <?php if (!empty($prescription['doctor_email'])): ?>
                <div class="detail-row"><span class="detail-label">Doctor Email</span><span class="detail-value"><?= htmlspecialchars($prescription['doctor_email']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($prescription['doctor_phone'])): ?>
                <div class="detail-row"><span class="detail-label">Doctor Phone</span><span class="detail-value"><?= htmlspecialchars($prescription['doctor_phone']) ?></span></div>
            <?php endif; ?>
            <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value"><?= formatDate($prescription['created_at'] ?? '') ?></span></div>
            <?php if ($prescription['status'] === 'dispensed'): ?>
                <div class="detail-row"><span class="detail-label">Dispensed</span><span class="detail-value"><?= formatDate($prescription['dispensed_at'] ?? '') ?></span></div>
                <?php if (!empty($prescription['pharmacy_name'])): ?>
                    <div class="detail-row"><span class="detail-label">Dispensed By</span><span class="detail-value"><?= htmlspecialchars($prescription['pharmacy_name']) ?></span></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($prescription['diagnosis'])): ?>
            <div class="detail-row" style="margin-top:10px;border-top:2px solid var(--border-color);padding-top:10px;">
                <span class="detail-label">Diagnosis</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($prescription['diagnosis'])) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($prescription['symptoms'])): ?>
            <div class="detail-row"><span class="detail-label">Symptoms</span><span class="detail-value"><?= nl2br(htmlspecialchars($prescription['symptoms'])) ?></span></div>
        <?php endif; ?>
        
        <?php if (!empty($prescription['visit_notes'])): ?>
            <div class="detail-row"><span class="detail-label">Visit Notes</span><span class="detail-value"><?= nl2br(htmlspecialchars($prescription['visit_notes'])) ?></span></div>
        <?php endif; ?>
        
        <?php if (!empty($prescription['instructions'])): ?>
            <div class="detail-row"><span class="detail-label">Prescription Instructions</span><span class="detail-value"><?= nl2br(htmlspecialchars($prescription['instructions'])) ?></span></div>
        <?php endif; ?>
        
        <?php if (!empty($prescription['notes'])): ?>
            <div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value"><?= nl2br(htmlspecialchars($prescription['notes'])) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION ITEMS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-pills"></i>
            Prescription Items
            <span class="badge-count"><?= count($prescription_items) ?> items</span>
            <span class="badge-count" style="background:var(--success);">Total Qty: <?= $total_quantity ?></span>
        </h3>
        
        <div class="table-wrap">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:35%;">Medication</th>
                        <th style="width:10%; text-align:center;">Qty</th>
                        <th style="width:12%; text-align:center;">Dosage</th>
                        <th style="width:15%; text-align:center;">Frequency</th>
                        <th style="width:10%; text-align:center;">Duration</th>
                        <th style="width:18%; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescription_items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong>
                                <?php if (!empty($item['route'])): ?>
                                    <br><span class="text-xs text-gray-400">Route: <?= htmlspecialchars($item['route']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['instructions'])): ?>
                                    <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['instructions']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= $item['quantity'] ?? 0 ?></td>
                            <td style="text-align:center;"><?= htmlspecialchars($item['dosage'] ?? '') ?></td>
                            <td style="text-align:center;"><?= htmlspecialchars($item['frequency'] ?? '') ?></td>
                            <td style="text-align:center;"><?= !empty($item['duration']) ? $item['duration'] . ' days' : '' ?></td>
                            <td style="text-align:right;font-family:monospace;font-weight:600;color:var(--primary);">
                                <?= number_format($item['total_price'] ?? $item['unit_price'] * $item['quantity'] ?? 0, 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="5" style="text-align:right;font-weight:700;font-size:0.9rem;">
                            <i class="fas fa-receipt"></i> GRAND TOTAL:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-size:1.1rem;color:var(--primary);">
                            <?= number_format($total_price, 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL INFORMATION - NO VIEW BILL BUTTON -->
    <!-- ================================================================ -->
    <?php if ($prescription['bill_id'] > 0): ?>
    <div class="card" style="border-color:<?= ($prescription['bill_status'] ?? '') === 'paid' ? 'var(--success)' : 'var(--warning)' ?>;border-left:4px solid <?= ($prescription['bill_status'] ?? '') === 'paid' ? 'var(--success)' : 'var(--warning)' ?>;">
        <h3 class="card-title">
            <i class="fas fa-receipt" style="color:<?= ($prescription['bill_status'] ?? '') === 'paid' ? 'var(--success)' : 'var(--warning)' ?>;"></i>
            Bill Information
            <span class="badge-status <?= getBillStatusBadgeClass($prescription['bill_status'] ?? 'pending') ?>">
                <?= getBillStatusLabel($prescription['bill_status'] ?? 'pending') ?>
            </span>
            <?php if ($prescription['bill_number']): ?>
            <span class="badge-count">#<?= htmlspecialchars($prescription['bill_number']) ?></span>
            <?php endif; ?>
            <span class="badge-count" style="background:var(--gray-500);">
                <i class="fas fa-info-circle"></i> Prescription Bill Only
            </span>
        </h3>
        
        <div class="bill-summary-grid">
            <div class="bill-summary-item">
                <div class="label">Total Amount</div>
                <div class="value blue"><?= number_format($prescription['bill_total'] ?? 0, 2) ?></div>
            </div>
            <div class="bill-summary-item" style="border-color:var(--warning);">
                <div class="label">Discount</div>
                <div class="value orange"><?= number_format($prescription['bill_discount'] ?? 0, 2) ?></div>
            </div>
            <div class="bill-summary-item" style="border-color:<?= ($prescription['bill_status'] ?? '') === 'paid' ? 'var(--success)' : 'var(--danger)' ?>;">
                <div class="label">Balance</div>
                <div class="value <?= ($prescription['bill_status'] ?? '') === 'paid' ? 'green' : 'red' ?>">
                    <?= number_format($prescription['bill_balance'] ?? 0, 2) ?>
                </div>
            </div>
            <div class="bill-summary-item" style="border-color:<?= ($prescription['bill_status'] ?? '') === 'paid' ? 'var(--success)' : 'var(--warning)' ?>;">
                <div class="label">Status</div>
                <div class="value <?= ($prescription['bill_status'] ?? '') === 'paid' ? 'green' : 'orange' ?>">
                    <?= ($prescription['bill_status'] ?? '') === 'paid' ? '✅ PAID' : '⏳ PENDING' ?>
                </div>
            </div>
        </div>
        
        <!-- ✅ NO VIEW BILL BUTTON HERE -->
        <div style="margin-top:12px;font-size:0.75rem;color:var(--text-secondary);">
            <i class="fas fa-info-circle"></i> 
            Bill details are for reference only. Payment is handled by Cashier.
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS - READ ONLY MODE -->
    <!-- ================================================================ -->
    <div class="card" style="border-color:var(--gray-300);background:var(--gray-50);">
        <h3 class="card-title">
            <i class="fas fa-info-circle" style="color:var(--gray-500);"></i>
            Prescription Status
            <span class="badge-status <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
            </span>
            <?php if ($prescription['status'] === 'dispensed'): ?>
                <span class="read-only-badge">
                    <i class="fas fa-lock"></i> Read Only - No Updates Allowed
                </span>
            <?php endif; ?>
        </h3>
        
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
            <?php if ($prescription['status'] === 'dispensed'): ?>
                <div style="padding:12px 16px;background:var(--success-bg);border-radius:var(--radius);border:1px solid var(--success);flex:1;">
                    <i class="fas fa-check-circle" style="color:var(--success);"></i>
                    <span style="color:var(--success-dark);font-weight:600;">
                        This prescription has been dispensed on <?= formatDate($prescription['dispensed_at']) ?>
                    </span>
                    <?php if (!empty($prescription['pharmacy_name'])): ?>
                        <span style="color:var(--text-secondary);font-size:0.8rem;display:block;margin-top:4px;">
                            Dispensed by: <?= htmlspecialchars($prescription['pharmacy_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding:12px 16px;background:var(--warning-bg);border-radius:var(--radius);border:1px solid var(--warning);flex:1;">
                    <i class="fas fa-clock" style="color:var(--warning);"></i>
                    <span style="color:var(--warning-dark);font-weight:600;">
                        This prescription is <?= strtolower(getStatusLabel($prescription['status'] ?? 'pending')) ?>
                    </span>
                    <span style="color:var(--text-secondary);font-size:0.8rem;display:block;margin-top:4px;">
                        Created on: <?= formatDate($prescription['created_at'] ?? '') ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <!-- ✅ ONLY BACK BUTTON - NO VIEW BILL -->
            <a href="<?= $prescription['status'] === 'dispensed' ? 'dispensed_prescriptions.php' : 'pending_prescriptions.php' ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            
            <?php if ($prescription['status'] !== 'dispensed' && $prescription['status'] !== 'cancelled'): ?>
                <a href="dispense.php?id=<?= $prescription['id'] ?>" class="btn btn-success-custom">
                    <i class="fas fa-prescription-bottle"></i> Go to Dispense
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Prescription
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c💊 Braick - View Prescription (READ ONLY - NO VIEW BILL)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active - redirects to login if not authenticated', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💊 Status: <?= $prescription['status'] ?? 'pending' ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 READ ONLY - No status update buttons', 'font-size:13px; color:#DC2626;');
    console.log('%c🚫 NO VIEW BILL BUTTON', 'font-size:13px; color:#DC2626;');
    <?php if ($prescription['status'] === 'dispensed'): ?>
        console.log('%c✅ Dispensed on: <?= formatDate($prescription['dispensed_at']) ?>', 'font-size:13px; color:#059669;');
    <?php endif; ?>
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>