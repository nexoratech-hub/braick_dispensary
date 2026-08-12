<?php
// ================================================================
// FILE: frontend/pages/admin/visit_details.php
// VISIT DETAILS - VIEW ALL VISIT INFORMATION
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($visit_id <= 0) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET VISIT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, 
           p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_number,
           p.phone as patient_phone, p.email as patient_email,
           u.id as doctor_id, u.full_name as doctor_name,
           r.id as receptionist_id, r.full_name as receptionist_name,
           b.name as branch_name,
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'assigned' THEN 'info'
               WHEN v.status = 'with_doctor' THEN 'primary'
               WHEN v.status = 'lab_test' THEN 'orange'
               WHEN v.status = 'prescribed' THEN 'purple'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM visits v
    INNER JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: visits.php?branch=' . $selected_branch_id);
    exit;
}

$patient_id = $visit['patient_id'];

// ================================================================
// GET VISIT STATISTICS
// ================================================================

// Get total visits for this patient
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_patient_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ REKEBISHWA: GET UNIQUE BILLS - NO DUPLICATES
// ================================================================
$stmt = $db->prepare("
    SELECT pb.*,
           CASE 
               WHEN pb.status = 'pending' THEN 'warning'
               WHEN pb.status = 'paid' THEN 'success'
               WHEN pb.status = 'partial' THEN 'info'
               WHEN pb.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM patient_bills pb
    WHERE pb.visit_id = ?
    ORDER BY pb.created_at ASC
");
$stmt->execute([$visit_id]);
$raw_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ FILTER DUPLICATE BILLS - Keep only one per bill_number
$unique_bills = [];
$seen_bill_numbers = [];
foreach ($raw_bills as $bill) {
    $bill_number = $bill['bill_number'];
    if (!in_array($bill_number, $seen_bill_numbers)) {
        $unique_bills[] = $bill;
        $seen_bill_numbers[] = $bill_number;
    }
}
$visit_bills = $unique_bills;

// ✅ LOG DUPLICATES FOR DEBUGGING
if (count($raw_bills) != count($unique_bills)) {
    error_log("⚠️ Duplicate bills found for visit ID: {$visit_id}. Total: " . count($raw_bills) . ", Unique: " . count($unique_bills));
}

// ================================================================
// ✅ REKEBISHWA: GET UNIQUE BILL ITEMS
// ================================================================
$all_bill_items = [];
$total_bill_amount = 0;
$total_paid_amount = 0;
$total_balance = 0;
$bill_statuses = [];

foreach ($visit_bills as $bill) {
    $bill_id = $bill['id'];
    $total_bill_amount += $bill['total_amount'] ?? 0;
    $total_paid_amount += $bill['paid_amount'] ?? 0;
    $total_balance += $bill['balance'] ?? 0;
    $bill_statuses[] = $bill['status'];
    
    // ✅ GET UNIQUE BILL ITEMS - Group by unique item combination
    $stmt = $db->prepare("
        SELECT 
            bi.*
        FROM bill_items bi
        WHERE bi.bill_id = ?
        GROUP BY bi.item_name, bi.item_type, bi.unit_price, bi.quantity
        ORDER BY bi.created_at ASC
    ");
    $stmt->execute([$bill_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $all_bill_items[$bill_id] = $items;
}

// Determine overall payment status
$overall_status = 'pending';
if (count($visit_bills) > 0) {
    if (in_array('cancelled', $bill_statuses) && count($visit_bills) == 1 && $bill_statuses[0] == 'cancelled') {
        $overall_status = 'cancelled';
    } elseif (in_array('pending', $bill_statuses)) {
        $overall_status = 'pending';
    } elseif (in_array('partial', $bill_statuses)) {
        $overall_status = 'partial';
    } elseif (array_diff($bill_statuses, ['paid', 'cancelled']) === []) {
        $has_paid = in_array('paid', $bill_statuses);
        $has_cancelled = in_array('cancelled', $bill_statuses);
        if ($has_paid && !$has_cancelled) {
            $overall_status = 'paid';
        } elseif ($has_paid && $has_cancelled) {
            $overall_status = 'partial';
        } else {
            $overall_status = 'cancelled';
        }
    }
}

// Get lab tests for this visit
$stmt = $db->prepare("
    SELECT lt.*,
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM lab_tests lt
    WHERE lt.visit_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$visit_id]);
$visit_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescriptions for this visit
$stmt = $db->prepare("
    SELECT p.*,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    WHERE p.visit_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$visit_id]);
$visit_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescription items
$prescription_items = [];
foreach ($visit_prescriptions as $prescription) {
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ?
    ");
    $stmt->execute([$prescription['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $prescription_items[$prescription['id']] = $items;
}

// Get vital signs for this visit
$stmt = $db->prepare("
    SELECT vs.*, u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.visit_id = ?
    ORDER BY vs.recorded_at DESC
    LIMIT 1
");
$stmt->execute([$visit_id]);
$vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES
       ================================================================ */
    
    /* Status Badges */
    .status-badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.primary { background: #DBEAFE; color: #2563EB; }
    .status-badge.orange { background: #FED7AA; color: #EA580C; }
    .status-badge.purple { background: #E9D5FF; color: #7B2FBE; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.primary { background: #1A2A4A; color: #60A5FA; }
    [data-theme="dark"] .status-badge.orange { background: #3A2A1A; color: #FB923C; }
    [data-theme="dark"] .status-badge.purple { background: #2A1A3A; color: #A78BFA; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    /* Visit Header */
    .visit-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .visit-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .visit-header .visit-number {
        font-size: 1.4rem;
        font-weight: 700;
        font-family: monospace;
    }
    
    .visit-header .visit-meta {
        font-size: 0.85rem;
        opacity: 0.85;
    }
    
    /* Stat Cards */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    .stat-card-mini .stat-number.blue { color: #0B5ED7; }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    .title-purple { color: #7B2FBE; }
    .title-orange { color: #F59E0B; }
    .title-red { color: #EF4444; }
    .title-pink { color: #EC4899; }
    
    /* Info Row */
    .info-row {
        display: flex;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row .info-label {
        width: 140px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.82rem;
        flex-shrink: 0;
    }
    
    .info-row .info-value {
        flex: 1;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    /* ================================================================
       VITAL SIGNS CARDS - MODERN DESIGN (6 CARDS)
       ================================================================ */
    .vital-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 12px;
        text-align: center;
        border: 2px solid var(--border-color);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        min-height: 100px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .vital-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: 14px 14px 0 0;
    }
    
    .vital-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    
    .vital-card .vital-icon {
        font-size: 1.8rem;
        margin-bottom: 6px;
    }
    
    .vital-card .vital-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .vital-card .vital-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.04em;
        margin-top: 2px;
    }
    
    .vital-card .vital-unit {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-weight: 400;
        margin-left: 2px;
    }
    
    /* Card Colors - 6 Colors */
    .vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
    .vital-card.blue .vital-icon { color: #0B5ED7; }
    .vital-card.blue .vital-value { color: #0B5ED7; }
    
    .vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
    .vital-card.red .vital-icon { color: #EF4444; }
    .vital-card.red .vital-value { color: #EF4444; }
    
    .vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
    .vital-card.pink .vital-icon { color: #EC4899; }
    .vital-card.pink .vital-value { color: #EC4899; }
    
    .vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
    .vital-card.purple .vital-icon { color: #7B2FBE; }
    .vital-card.purple .vital-value { color: #7B2FBE; }
    
    .vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
    .vital-card.green .vital-icon { color: #059669; }
    .vital-card.green .vital-value { color: #059669; }
    
    .vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
    .vital-card.indigo .vital-icon { color: #4F46E5; }
    .vital-card.indigo .vital-value { color: #4F46E5; }
    
    /* Dark mode vital cards */
    [data-theme="dark"] .vital-card {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .vital-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    }
    
    [data-theme="dark"] .vital-card .vital-value {
        color: #F1F5F9;
    }
    
    [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
    [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
    [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
    [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
    [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
    [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
    
    /* Bill Card Styles */
    .bill-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px 18px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .bill-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 15px rgba(11, 94, 215, 0.08);
    }
    
    .bill-card .bill-number {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-primary);
    }
    
    .bill-card .bill-number i {
        color: #0B5ED7;
        margin-right: 6px;
    }
    
    .bill-card .bill-meta {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    /* Duplicate Warning */
    .duplicate-warning {
        background: #FEF3C7;
        border: 1px solid #F59E0B;
        color: #92400E;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    [data-theme="dark"] .duplicate-warning {
        background: #3A2A1A;
        border-color: #F59E0B;
        color: #FBBF24;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .visit-header {
            padding: 16px 18px;
        }
        .visit-header .visit-number {
            font-size: 1rem;
        }
        .info-row {
            flex-direction: column;
            gap: 2px;
        }
        .info-row .info-label {
            width: 100%;
            font-size: 0.75rem;
        }
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
        .table-blue tbody td {
            font-size: 0.7rem;
            padding: 6px 10px !important;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .vital-card {
            min-height: 80px;
            padding: 12px 8px;
        }
        .vital-card .vital-value {
            font-size: 1.2rem;
        }
        .vital-card .vital-icon {
            font-size: 1.4rem;
        }
        .grid-cols-2.sm\:grid-cols-3.md\:grid-cols-6 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

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
            <form method="GET" action="visits.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search visits..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- VISIT HEADER -->
    <!-- ================================================================ -->
    <div class="visit-header mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3" style="position:relative;z-index:1;">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="visit-number">
                        <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                    </span>
                    <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="visit-meta mt-1">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="visits.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ STATISTICS CARDS - WITH UNIQUE BILLS COUNT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_patient_visits ?></p>
            <p class="stat-label">Patient Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🧾</div>
            <p class="stat-number blue"><?= count($visit_bills) ?></p>
            <p class="stat-label">Total Bills</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💰</div>
            <p class="stat-number green">TSh <?= number_format($total_bill_amount) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">✅</div>
            <p class="stat-number green">TSh <?= number_format($total_paid_amount) ?></p>
            <p class="stat-label">Total Paid</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📊</div>
            <p class="stat-number orange">TSh <?= number_format($total_balance) ?></p>
            <p class="stat-label">Total Balance</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION & PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        
        <!-- Visit Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle title-blue mr-2"></i> Visit Information
                </h3>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Visit Number</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['visit_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Date</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Type</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge <?= $visit['status_color'] ?? 'secondary' ?>">
                            <?= ucfirst($visit['status'] ?? 'N/A') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor</span>
                    <span class="info-value">
                        <?php if ($visit['doctor_name']): ?>
                            <i class="fas fa-user-md text-blue-600"></i> 
                            <?= htmlspecialchars($visit['doctor_name']) ?>
                        <?php else: ?>
                            <span class="text-gray-400">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Receptionist</span>
                    <span class="info-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span>
                </div>
                <?php if ($visit['follow_up_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Follow-up Date</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($visit['follow_up_date'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user title-green mr-2"></i> Patient Information
                </h3>
                <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i> View Patient
                </a>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($visit['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($visit['patient_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($visit['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($visit['patient_email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Visits</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= $total_patient_visits ?></span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Complaint</span>
                    <span class="info-value"><?= htmlspecialchars($visit['complaint'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Symptoms</span>
                    <span class="info-value"><?= htmlspecialchars($visit['symptoms'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Diagnosis</span>
                    <span class="info-value"><?= htmlspecialchars($visit['diagnosis'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Treatment</span>
                    <span class="info-value"><?= htmlspecialchars($visit['treatment'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS - MODERN DESIGN (6 CARDS) -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat title-pink mr-2"></i> Vital Signs
                <span class="badge-count">(<?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>)</span>
            </h3>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            
            <!-- 1. Temperature -->
            <div class="vital-card blue">
                <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-value">
                    <?php 
                        $temp = $vital_signs['temperature'] ?? null;
                        echo $temp !== null ? $temp : '-';
                    ?>
                    <span class="vital-unit">°C</span>
                </div>
                <div class="vital-label">Temperature</div>
            </div>
            
            <!-- 2. Blood Pressure -->
            <div class="vital-card red">
                <div class="vital-icon"><i class="fas fa-heart"></i></div>
                <div class="vital-value">
                    <?php 
                        $systolic = $vital_signs['blood_pressure_systolic'] ?? null;
                        $diastolic = $vital_signs['blood_pressure_diastolic'] ?? null;
                        
                        if ($systolic !== null && $diastolic !== null) {
                            echo $systolic . '/' . $diastolic;
                        } elseif ($systolic !== null) {
                            echo $systolic;
                        } else {
                            echo '-';
                        }
                    ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <div class="vital-label">Blood Pressure</div>
            </div>
            
            <!-- 3. Pulse Rate -->
            <div class="vital-card pink">
                <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-value">
                    <?php 
                        $pulse = $vital_signs['pulse_rate'] ?? null;
                        echo $pulse !== null ? $pulse : '-';
                    ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <div class="vital-label">Pulse Rate</div>
            </div>
            
            <!-- 4. Weight -->
            <div class="vital-card purple">
                <div class="vital-icon"><i class="fas fa-weight"></i></div>
                <div class="vital-value">
                    <?php 
                        $weight = $vital_signs['weight'] ?? null;
                        echo $weight !== null ? $weight : '-';
                    ?>
                    <span class="vital-unit">kg</span>
                </div>
                <div class="vital-label">Weight</div>
            </div>
            
            <!-- 5. Height -->
            <div class="vital-card green">
                <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                <div class="vital-value">
                    <?php 
                        $height = $vital_signs['height'] ?? null;
                        echo $height !== null ? $height : '-';
                    ?>
                    <span class="vital-unit">cm</span>
                </div>
                <div class="vital-label">Height</div>
            </div>
            
            <!-- 6. BMI -->
            <div class="vital-card indigo">
                <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                <div class="vital-value">
                    <?php 
                        $bmi = $vital_signs['bmi'] ?? null;
                        echo $bmi !== null ? $bmi : '-';
                    ?>
                </div>
                <div class="vital-label">BMI</div>
            </div>
            
        </div>
        
        <?php if ($vital_signs['notes']): ?>
        <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-xs text-gray-500">📝 Notes</p>
            <p class="text-sm"><?= htmlspecialchars($vital_signs['notes']) ?></p>
        </div>
        <?php endif; ?>
        
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
        </p>
    </div>
    <?php else: ?>
    <!-- No Vital Signs Message -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat title-pink mr-2"></i> Vital Signs
            </h3>
        </div>
        <div class="text-center py-6 text-gray-400">
            <i class="fas fa-heartbeat text-3xl block mb-2" style="color: #EC4899;"></i>
            <p>No vital signs recorded for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ✅ REKEBISHWA: ALL UNIQUE BILLS - NO DUPLICATES -->
    <!-- ================================================================ -->
    <?php if (count($visit_bills) > 0): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice title-blue mr-2"></i> All Bills (<?= count($visit_bills) ?>)
                <span class="badge-count">| Total: TSh <?= number_format($total_bill_amount) ?></span>
            </h3>
            <div class="flex items-center gap-2 flex-wrap">
                <?php 
                // Check for duplicates in raw data
                if (count($raw_bills) != count($unique_bills)): 
                ?>
                    <div class="duplicate-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Duplicate bills detected! Showing unique bills only.
                    </div>
                <?php endif; ?>
                <span class="status-badge <?= 
                    $overall_status === 'paid' ? 'success' : 
                    ($overall_status === 'partial' ? 'info' : 
                    ($overall_status === 'cancelled' ? 'danger' : 'warning')) 
                ?>" style="font-size:0.75rem;">
                    Overall: <?= ucfirst($overall_status) ?>
                </span>
            </div>
        </div>
        
        <!-- Summary Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Bills</p>
                <p class="font-bold text-lg text-blue-600"><?= count($visit_bills) ?></p>
            </div>
            <div class="text-center p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Amount</p>
                <p class="font-bold text-lg text-green-600">TSh <?= number_format($total_bill_amount) ?></p>
            </div>
            <div class="text-center p-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Paid</p>
                <p class="font-bold text-lg text-indigo-600">TSh <?= number_format($total_paid_amount) ?></p>
            </div>
            <div class="text-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Balance</p>
                <p class="font-bold text-lg text-orange-600">TSh <?= number_format($total_balance) ?></p>
            </div>
        </div>
        
        <!-- Individual Bills -->
        <?php foreach ($visit_bills as $index => $bill): ?>
            <div class="bill-card <?= $bill['status'] === 'cancelled' ? 'opacity-60' : '' ?> mb-3">
                <div class="flex flex-wrap justify-between items-start gap-2">
                    <div class="flex-1">
                        <p class="bill-number">
                            <i class="fas fa-receipt"></i> <?= htmlspecialchars($bill['bill_number']) ?>
                            <span class="badge-count ml-2">#<?= $index + 1 ?></span>
                            <?php if ($bill['prescription_id']): ?>
                                <span class="badge badge-info ml-2" style="font-size:0.6rem;">
                                    <i class="fas fa-prescription"></i> Prescription
                                </span>
                            <?php endif; ?>
                        </p>
                        <p class="bill-meta">
                            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                            TSh <?= number_format($bill['total_amount'] ?? 0) ?>
                        </span>
                        <span class="status-badge <?= $bill['status_color'] ?? 'secondary' ?>" style="font-size:0.65rem;">
                            <?= ucfirst($bill['status'] ?? 'N/A') ?>
                        </span>
                        <a href="bill_details.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Bill Items -->
                <?php if (isset($all_bill_items[$bill['id']]) && count($all_bill_items[$bill['id']]) > 0): ?>
                    <div class="mt-2 overflow-x-auto">
                        <table class="data-table table-blue w-full" style="font-size:0.7rem;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($all_bill_items[$bill['id']] as $item): ?>
                                    <tr <?= $item['payment_status'] === 'cancelled' ? 'style="opacity:0.5;"' : '' ?>>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td>
                                            <span class="badge <?= 
                                                $item['item_type'] === 'medication' ? 'badge-purple' : 
                                                ($item['item_type'] === 'lab_test' ? 'badge-orange' : 
                                                ($item['item_type'] === 'consultation' ? 'badge-blue' : 
                                                ($item['item_type'] === 'procedure' ? 'badge-red' : 
                                                ($item['item_type'] === 'tool' ? 'badge-teal' : 'badge-info')))) 
                                            ?>">
                                                <?= ucfirst(str_replace('_', ' ', $item['item_type'] ?? 'N/A')) ?>
                                            </span>
                                        </td>
                                        <td><?= $item['quantity'] ?? 1 ?></td>
                                        <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                                        <td class="font-bold">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Bill Summary -->
                    <div class="flex flex-wrap justify-end gap-3 mt-2 text-xs">
                        <span class="text-gray-500">Subtotal: TSh <?= number_format($bill['total_amount'] ?? 0) ?></span>
                        <?php if (($bill['discount_amount'] ?? 0) > 0): ?>
                            <span class="text-green-600">Discount: TSh <?= number_format($bill['discount_amount'] ?? 0) ?></span>
                        <?php endif; ?>
                        <span class="font-bold text-blue-600">Paid: TSh <?= number_format($bill['paid_amount'] ?? 0) ?></span>
                        <?php if (($bill['balance'] ?? 0) > 0): ?>
                            <span class="font-bold text-orange-600">Balance: TSh <?= number_format($bill['balance'] ?? 0) ?></span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-gray-400 mt-2">No items in this bill</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- No Bills -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice title-blue mr-2"></i> Bills
            </h3>
        </div>
        <div class="text-center py-6 text-gray-400">
            <i class="fas fa-receipt text-3xl block mb-2"></i>
            <p>No bills created for this visit</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <?php if (count($visit_lab_tests) > 0): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask title-orange mr-2"></i> Lab Tests (<?= count($visit_lab_tests) ?> tests)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($visit_lab_tests as $test): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                            <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                            <td>
                                <span class="status-badge <?= $test['status_color'] ?? 'secondary' ?>" style="font-size:0.65rem;">
                                    <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                </span>
                            </td>
                            <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                            <td>
                                <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <?php if (count($visit_prescriptions) > 0): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription title-purple mr-2"></i> Prescriptions (<?= count($visit_prescriptions) ?>)
            </h3>
        </div>
        <?php foreach ($visit_prescriptions as $prescription): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-3">
                <div class="flex justify-between items-start flex-wrap gap-2">
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($prescription['prescription_number']) ?></p>
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($prescription['created_at'])) ?>
                        </p>
                    </div>
                    <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>" style="font-size:0.65rem;">
                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                    </span>
                </div>
                
                <?php if (!empty($prescription['diagnosis'])): ?>
                    <p class="text-sm mt-1"><strong>Diagnosis:</strong> <?= htmlspecialchars($prescription['diagnosis']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($prescription['instructions'])): ?>
                    <p class="text-sm"><strong>Instructions:</strong> <?= htmlspecialchars($prescription['instructions']) ?></p>
                <?php endif; ?>
                
                <?php if (isset($prescription_items[$prescription['id']]) && count($prescription_items[$prescription['id']]) > 0): ?>
                    <div class="mt-2">
                        <p class="text-sm font-semibold text-gray-600">Medications:</p>
                        <div class="overflow-x-auto">
                            <table class="data-table table-blue w-full" style="font-size:0.75rem;">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Quantity</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescription_items[$prescription['id']] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['medication_name']) ?></td>
                                            <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                            <td><?= $item['quantity'] ?? 0 ?></td>
                                            <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                            <td>TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mt-2">
                    <a href="prescription_details.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View Prescription
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
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
    // ================================================================
    // DARK MODE
    // ================================================================
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Visit Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🧾 Total Bills: <?= count($visit_bills) ?> (Unique)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Amount: TSh <?= number_format($total_bill_amount) ?>', 'font-size:13px; color:#059669;');
    <?php if (count($raw_bills) != count($unique_bills)): ?>
    console.log('%c⚠️ Duplicate bills detected! Raw: <?= count($raw_bills) ?>, Unique: <?= count($unique_bills) ?>', 'font-size:13px; color:#F59E0B;');
    <?php endif; ?>
</script>

</body>
</html>