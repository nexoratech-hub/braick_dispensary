<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispensed_prescriptions.php
// PHARMACY - DISPENSED PRESCRIPTIONS (BLUE THEME)
// Shows all prescriptions that have been dispensed
// NO AMOUNT CARDS - Only Total Dispensed
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Pharmacy
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 9;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Dodoma';

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
// GET FILTER PARAMETERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY FOR DISPENSED PRESCRIPTIONS
// ================================================================
$conditions = ["p.branch_id = ?", "p.status = 'dispensed'"];
$params = [$user_branch_id];

if (!empty($search)) {
    $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR p.medication LIKE ? OR pi.medication_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $conditions[] = "DATE(p.dispensed_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(p.dispensed_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET DISPENSED PRESCRIPTIONS WITH ITEMS
// ================================================================
$sql = "
    SELECT 
        p.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone,
        pat.email,
        pat.gender,
        pat.date_of_birth,
        u.full_name as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        pi.medication_name as item_medication,
        pi.dosage as item_dosage,
        pi.frequency as item_frequency,
        pi.quantity as item_quantity,
        pi.duration as item_duration,
        pi.route as item_route,
        pi.unit_price as item_unit_price,
        pi.total_price as item_total_price,
        (
            SELECT id FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_id,
        (
            SELECT bill_number FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_number
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN visits v ON p.visit_id = v.id
    LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.dispensed_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTION ITEMS FOR EACH
// ================================================================
foreach ($prescriptions as &$pres) {
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$pres['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no items, create from main prescription
    if (empty($items) && !empty($pres['medication'])) {
        $items = [[
            'id' => 0,
            'prescription_id' => $pres['id'],
            'medication_name' => $pres['medication'],
            'dosage' => $pres['dosage'] ?? '',
            'frequency' => $pres['frequency'] ?? '',
            'quantity' => $pres['quantity'] ?? 1,
            'duration' => $pres['duration'] ?? '',
            'route' => $pres['route'] ?? '',
            'instructions' => $pres['instructions'] ?? '',
            'unit_price' => 0,
            'total_price' => 0
        ]];
    }
    $pres['items'] = $items;
    $pres['total_quantity'] = array_sum(array_column($items, 'quantity'));
    $pres['total_price'] = array_sum(array_column($items, 'total_price'));
}

// ================================================================
// GET STATISTICS - ONLY TOTAL DISPENSED
// ================================================================
$total_dispensed = count($prescriptions);

// ================================================================
// GET DAILY DISPENSED COUNT
// ================================================================
$stmt = $db->prepare("
    SELECT DATE(dispensed_at) as date, COUNT(*) as count
    FROM prescriptions
    WHERE branch_id = ? AND status = 'dispensed'
    GROUP BY DATE(dispensed_at)
    ORDER BY DATE(dispensed_at) DESC
    LIMIT 7
");
$stmt->execute([$user_branch_id]);
$daily_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HELPER FUNCTIONS
// ================================================================
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
    <title>Dispensed Prescriptions - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-bg: #1E3A5F;
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
            background: var(--primary-gradient);
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
        
        /* ================================================================
           PAGE HEADER - BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 25px rgba(11, 94, 215, 0.3);
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
        
        /* ================================================================
           STATS ROW - ONLY TOTAL DISPENSED
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 24px;
            max-width: 300px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            text-align: center;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }
        
        .stat-card .stat-icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-input {
            padding: 7px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .filter-input[type="date"] {
            width: 150px;
        }
        
        .btn-search {
            padding: 7px 18px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        /* ================================================================
           TABLE - BLUE THEME
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary-gradient);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 5px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        .badge-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 2px 10px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
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
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
            border-radius: 4px;
        }
        
        .btn-view {
            background: var(--primary-gradient);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-bill {
            background: var(--warning);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.55rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-bill:hover {
            background: #B45309;
            transform: translateY(-1px);
        }
        
        .table-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-row { max-width: 100%; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .filter-input[type="date"] { width: 100%; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .stats-row { max-width: 100%; }
            .page-title { font-size: 1.1rem; }
            .btn { padding: 3px 8px; font-size: 0.55rem; }
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
            <input type="text" id="searchInput" placeholder="Search dispensed prescriptions..." value="<?= htmlspecialchars($search) ?>">
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
    <!-- PAGE HEADER - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Dispensed Prescriptions
                <span class="badge-display"><?= $total_dispensed ?> Total</span>
                <span class="badge-display" style="background:rgba(255,255,255,0.12);">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-check-circle"></i>
                All prescriptions that have been dispensed in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="text-xs text-white/60 ml-2">
                    <i class="fas fa-info-circle"></i> 
                    Showing all dispensed medications
                </span>
                <span class="text-xs text-white/50 ml-2">
                    <i class="fas fa-clock"></i> Updated: <?= date('H:i:s') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS - ONLY TOTAL DISPENSED -->
    <!-- ================================================================ -->
    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-icon">💊</span>
            <p class="stat-number"><?= $total_dispensed ?></p>
            <p class="stat-label">Total Dispensed</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <form method="GET" class="filter-row" style="flex:1;gap:8px;">
            <input type="text" name="search" class="filter-input" placeholder="Search patient, medication..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
            <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
            <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
                <a href="dispensed_prescriptions.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE - BLUE THEME (NO BILL BUTTON) -->
    <!-- ================================================================ -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-receipt"></i> Prescription</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th><i class="fas fa-cubes"></i> Qty</th>
                        <th><i class="fas fa-calendar-check"></i> Dispensed</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($prescriptions as $pres): 
                            $medication = !empty($pres['item_medication']) ? $pres['item_medication'] : $pres['medication'];
                            $quantity = !empty($pres['item_quantity']) ? $pres['item_quantity'] : $pres['quantity'];
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:var(--primary);">
                                        <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <span class="text-xs text-gray-400 block">Visit: <?= htmlspecialchars($pres['visit_number']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['doctor_name'])): ?>
                                        <span class="text-xs text-gray-400 block">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($pres['doctor_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($pres['patient_code'] ?? 'N/A') ?></div>
                                    <?php if (!empty($pres['phone'])): ?>
                                        <div class="text-xs text-gray-400">📱 <?= htmlspecialchars($pres['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['gender'])): ?>
                                        <div class="text-xs text-gray-400"><?= $pres['gender'] ?> • <?= calculateAge($pres['date_of_birth'] ?? '') ?> yrs</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm font-medium"><?= htmlspecialchars($medication) ?></span>
                                    <?php if (!empty($pres['dosage'])): ?>
                                        <span class="text-xs text-gray-400 block">💊 <?= htmlspecialchars($pres['dosage']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['frequency'])): ?>
                                        <span class="text-xs text-gray-400 block">🕐 <?= htmlspecialchars($pres['frequency']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['duration'])): ?>
                                        <span class="text-xs text-gray-400 block">📅 <?= $pres['duration'] ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm font-semibold"><?= $quantity ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="text-xs"><?= formatDate($pres['dispensed_at'] ?? '') ?></span>
                                    <?php if (!empty($pres['dispensed_at'])): ?>
                                        <span class="text-xs text-gray-400 block">
                                            <?= date('d/m/Y', strtotime($pres['dispensed_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($pres['pharmacy_id'] == $user_id): ?>
                                        <span class="text-xs text-primary font-semibold block">✅ By You</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <span class="badge-status badge-success" style="font-size:0.55rem;">
                                            ✅ Dispensed
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription-bottle text-3xl block mb-2"></i>
                                    <p>No dispensed prescriptions found</p>
                                    <p class="text-sm mt-1">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif (!empty($date_from) || !empty($date_to)): ?>
                                            No prescriptions dispensed in this date range
                                        <?php else: ?>
                                            No prescriptions have been dispensed yet
                                        <?php endif; ?>
                                    </p>
                                    <a href="pending_prescriptions.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-clock"></i> Go to Pending Prescriptions
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($prescriptions) ?></strong> dispensed prescriptions
                <span class="text-xs text-gray-400 ml-2">🏥 <?= htmlspecialchars($user_branch_name) ?></span>
            </span>
            <span>
                <span class="count-badge"><?= $total_dispensed ?></span> Total dispensed
                <?php if (!empty($search)): ?>
                    <span class="text-xs text-gray-400 ml-2">(Filtered by: "<?= htmlspecialchars($search) ?>")</span>
                <?php endif; ?>
                <?php if (!empty($date_from) || !empty($date_to)): ?>
                    <span class="text-xs text-gray-400 ml-2">
                        (<?= !empty($date_from) ? 'From: ' . date('d/m/Y', strtotime($date_from)) : '' ?>
                        <?= !empty($date_to) ? ' To: ' . date('d/m/Y', strtotime($date_to)) : '' ?>)
                    </span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DAILY DISPENSED CHART -->
    <!-- ================================================================ -->
    <?php if (count($daily_counts) > 0): ?>
    <div class="mt-4 p-4 bg-card rounded-lg border border-border-color" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);">
        <h4 class="text-sm font-semibold mb-3" style="color:var(--text-primary);">
            <i class="fas fa-chart-bar mr-2" style="color:var(--primary);"></i>
            Daily Dispensed (Last 7 Days)
        </h4>
        <div class="flex gap-3 flex-wrap">
            <?php foreach ($daily_counts as $day): ?>
                <div class="text-center" style="flex:1;min-width:50px;">
                    <div style="background:var(--primary-bg);border-radius:6px;padding:6px 0;min-height:60px;display:flex;align-items:flex-end;justify-content:center;">
                        <div style="width:100%;max-width:30px;background:var(--primary);border-radius:4px 4px 0 0;min-height:8px;height:<?= max(8, min(60, $day['count'] * 15)) ?>px;transition:height 0.3s ease;"></div>
                    </div>
                    <div class="text-xs mt-1" style="color:var(--text-secondary);"><?= date('d M', strtotime($day['date'])) ?></div>
                    <div class="text-xs font-bold" style="color:var(--primary);"><?= $day['count'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Dispensed Prescriptions
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

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var date_from = document.querySelector('input[name="date_from"]')?.value || '';
        var date_to = document.querySelector('input[name="date_to"]')?.value || '';
        var params = [];
        if (query) params.push('search=' + encodeURIComponent(query));
        if (date_from) params.push('date_from=' + date_from);
        if (date_to) params.push('date_to=' + date_to);
        window.location.href = 'dispensed_prescriptions.php' + (params.length > 0 ? '?' + params.join('&') : '');
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    console.log('%c🔵 Braick - Dispensed Prescriptions (Blue Theme - No Amount Cards)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Total Dispensed: <?= $total_dispensed ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c❌ Removed: Amount, Discount, Balance cards', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Only Total Dispensed card remains', 'font-size:13px; color:#34D399;');
    console.log('%c💙 Blue theme applied', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>