<?php
// ================================================================
// FILE: frontend/pages/admin/prescription_details.php
// PRESCRIPTION DETAILS - VIEW ALL PRESCRIPTION INFORMATION
// BRAICK DISPENSARY - WITH LOGIN SESSION
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
// INCLUDE DATABASE AND HELPERS
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// VARIABLES
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($prescription_id <= 0) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// GET PRESCRIPTION DATA
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, 
           pat.id as patient_id, pat.full_name as patient_name, pat.patient_id as patient_number,
           pat.phone as patient_phone, pat.email as patient_email,
           d.id as doctor_id, d.full_name as doctor_name,
           ph.id as pharmacy_id, ph.full_name as pharmacy_name,
           v.visit_number,
           b.name as branch_name,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    INNER JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users d ON p.doctor_id = d.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    LEFT JOIN visits v ON p.visit_id = v.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$prescription_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id));
    exit;
}

$patient_id = $prescription['patient_id'];

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT pi.*, mi.medication_name as inventory_medication_name
    FROM prescription_items pi
    LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
    WHERE pi.prescription_id = ?
    ORDER BY pi.created_at ASC
");
$stmt->execute([$prescription_id]);
$prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTION SALE (if dispensed)
// ================================================================
$stmt = $db->prepare("
    SELECT ps.*, u.full_name as dispensed_by_name
    FROM prescription_sales ps
    LEFT JOIN users u ON ps.dispensed_by = u.id
    WHERE ps.prescription_id = ?
    ORDER BY ps.created_at DESC
    LIMIT 1
");
$stmt->execute([$prescription_id]);
$prescription_sale = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL FOR THIS PRESCRIPTION
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
    WHERE pb.prescription_id = ?
    ORDER BY pb.created_at DESC
    LIMIT 1
");
$stmt->execute([$prescription_id]);
$prescription_bill = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET PATIENT STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE patient_id = ? AND status != 'cancelled'");
$stmt->execute([$patient_id]);
$total_patient_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$total_patient_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
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
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    /* Prescription Header */
    .prescription-header {
        background: linear-gradient(135deg, #7B2FBE, #6B21A8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .prescription-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .prescription-header .prescription-number {
        font-size: 1.4rem;
        font-weight: 700;
        font-family: monospace;
    }
    
    .prescription-header .prescription-meta {
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
        border-color: #7B2FBE;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #7B2FBE;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
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
        border-color: #7B2FBE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #A78BFA;
    }
    
    /* Table Header - Purple Theme */
    .table-purple thead th {
        background: linear-gradient(135deg, #7B2FBE, #6B21A8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #6B21A8 !important;
        white-space: nowrap !important;
    }
    
    .table-purple thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-purple thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-purple tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    .table-purple tbody tr:hover td {
        background: #F3E8FF !important;
    }
    
    [data-theme="dark"] .table-purple tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-purple tbody tr:hover td {
        background: #2A1A3A !important;
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
        border-color: #7B2FBE;
        box-shadow: 0 4px 12px rgba(123, 47, 190, 0.05);
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
    
    .title-purple { color: #7B2FBE; }
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    
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
    
    /* Medication Item Card */
    .medication-item {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .medication-item:hover {
        border-color: #7B2FBE;
        box-shadow: 0 2px 8px rgba(123, 47, 190, 0.08);
    }
    
    [data-theme="dark"] .medication-item {
        background: #0F172A;
    }
    
    /* Dispensing Info Card - Special Highlight */
    .dispensing-card {
        border-left: 4px solid #059669 !important;
        background: var(--bg-card);
    }
    
    .dispensing-card .card-title {
        color: #059669;
    }
    
    [data-theme="dark"] .dispensing-card {
        border-left-color: #34D399 !important;
    }
    
    /* Toast */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    /* Responsive */
    @media (max-width: 640px) {
        .prescription-header {
            padding: 16px 18px;
        }
        .prescription-header .prescription-number {
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
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
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
            <form method="GET" action="prescriptions.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search prescriptions..." 
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PRESCRIPTION HEADER -->
    <!-- ================================================================ -->
    <div class="prescription-header mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3" style="position:relative;z-index:1;">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="prescription-number">
                        <i class="fas fa-prescription"></i> <?= htmlspecialchars($prescription['prescription_number']) ?>
                    </span>
                    <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>">
                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="prescription-meta mt-1">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($prescription['patient_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($prescription['patient_number']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($prescription['created_at'])) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($prescription['branch_name'] ?? 'N/A') ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <?php if ($prescription['status'] === 'pending'): ?>
                    <a href="edit_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                <?php endif; ?>
                <?php if ($prescription['status'] === 'pending' || $prescription['status'] === 'confirmed'): ?>
                    <a href="dispense_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary btn-sm" style="background:rgba(52,211,153,0.3);color:white;border:1px solid rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Dispense
                    </a>
                <?php endif; ?>
                <a href="prescriptions.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number"><?= count($prescription_items) ?></p>
            <p class="stat-label">Medications</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number blue"><?= $total_patient_prescriptions ?></p>
            <p class="stat-label">Patient Prescriptions</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number green"><?= $total_patient_visits ?></p>
            <p class="stat-label">Patient Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💰</div>
            <p class="stat-number orange">TSh <?= number_format($prescription_bill['total_amount'] ?? 0) ?></p>
            <p class="stat-label">Bill Amount</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DISPENSING INFORMATION - PLACED HERE (JUU) -->
    <!-- ================================================================ -->
    <?php if ($prescription_sale): ?>
    <div class="card dispensing-card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-check-circle title-green mr-2"></i> Dispensing Information
            </h3>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Sale Number</p>
                <p class="font-bold text-lg"><?= htmlspecialchars($prescription_sale['sale_number']) ?></p>
            </div>
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Amount</p>
                <p class="font-bold text-lg text-blue-600">TSh <?= number_format($prescription_sale['total_amount'] ?? 0) ?></p>
            </div>
            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Payment Method</p>
                <p class="font-bold text-lg"><?= ucfirst($prescription_sale['payment_method'] ?? 'N/A') ?></p>
            </div>
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Payment Status</p>
                <span class="status-badge <?= $prescription_sale['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                    <?= ucfirst($prescription_sale['payment_status'] ?? 'N/A') ?>
                </span>
            </div>
        </div>
        <div class="mt-3 text-sm text-gray-500">
            <i class="fas fa-user"></i> Dispensed by: <?= htmlspecialchars($prescription_sale['dispensed_by_name'] ?? 'N/A') ?>
            <span class="mx-2">|</span>
            <i class="fas fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($prescription_sale['dispensed_at'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION INFORMATION -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        
        <!-- Prescription Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle title-purple mr-2"></i> Prescription Details
                </h3>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Prescription #</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($prescription['prescription_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit</span>
                    <span class="info-value">
                        <?php if ($prescription['visit_number']): ?>
                            <a href="visit_details.php?id=<?= $prescription['visit_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="text-blue-600 hover:underline">
                                <?= htmlspecialchars($prescription['visit_number']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-400">N/A</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Doctor</span>
                    <span class="info-value">
                        <?php if ($prescription['doctor_name']): ?>
                            <i class="fas fa-user-md text-purple-600"></i> 
                            <?= htmlspecialchars($prescription['doctor_name']) ?>
                        <?php else: ?>
                            <span class="text-gray-400">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Pharmacy</span>
                    <span class="info-value"><?= htmlspecialchars($prescription['pharmacy_name'] ?? 'Not dispensed') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>">
                            <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= htmlspecialchars($prescription['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($prescription['created_at'])) ?></span>
                </div>
                <?php if ($prescription['dispensed_at']): ?>
                    <div class="info-row">
                        <span class="info-label">Dispensed</span>
                        <span class="info-value"><?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user title-blue mr-2"></i> Patient Information
                </h3>
                <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i> View Patient
                </a>
            </div>
            <div>
                <div class="info-row">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($prescription['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($prescription['patient_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($prescription['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($prescription['patient_email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Prescriptions</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= $total_patient_prescriptions ?></span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Visits</span>
                    <span class="info-value">
                        <span class="badge badge-info"><?= $total_patient_visits ?></span>
                    </span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- MEDICATIONS LIST -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-pills title-purple mr-2"></i> Medications
                <span class="badge-count">(<?= count($prescription_items) ?> items)</span>
            </h3>
            <?php if ($prescription['status'] !== 'dispensed'): ?>
                <a href="edit_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Medication
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (count($prescription_items) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table table-purple w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication Name</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Quantity</th>
                            <th>Duration</th>
                            <th>Route</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($prescription_items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                    <?php if ($item['inventory_medication_name']): ?>
                                        <br><span class="text-xs text-gray-400">(<?= htmlspecialchars($item['inventory_medication_name']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                <td><?= $item['quantity'] ?? 0 ?></td>
                                <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['route'] ?? 'N/A') ?></td>
                                <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                                <td class="font-bold">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8" class="text-right font-bold">Total:</td>
                            <td class="font-bold text-purple-600">
                                TSh <?= number_format(array_sum(array_column($prescription_items, 'total_price'))) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if ($prescription['instructions']): ?>
                <div class="mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                    <p class="text-sm font-semibold text-purple-600">📋 Instructions:</p>
                    <p class="text-sm"><?= htmlspecialchars($prescription['instructions']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($prescription['notes']): ?>
                <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm font-semibold text-gray-600">📝 Notes:</p>
                    <p class="text-sm"><?= htmlspecialchars($prescription['notes']) ?></p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-pills text-3xl block mb-2"></i>
                <p>No medications added to this prescription</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILL INFORMATION -->
    <!-- ================================================================ -->
    <?php if ($prescription_bill): ?>
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice title-blue mr-2"></i> Bill Information
                <span class="badge-count">(<?= $prescription_bill['bill_number'] ?>)</span>
            </h3>
            <a href="bill_details.php?id=<?= $prescription_bill['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-external-link-alt"></i> View Bill
            </a>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Total Amount</p>
                <p class="font-bold text-lg text-blue-600">TSh <?= number_format($prescription_bill['total_amount'] ?? 0) ?></p>
            </div>
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Paid Amount</p>
                <p class="font-bold text-lg text-green-600">TSh <?= number_format($prescription_bill['paid_amount'] ?? 0) ?></p>
            </div>
            <div class="text-center p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Balance</p>
                <p class="font-bold text-lg text-orange-600">TSh <?= number_format($prescription_bill['balance'] ?? 0) ?></p>
            </div>
            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                <p class="text-xs text-gray-500">Status</p>
                <span class="status-badge <?= $prescription_bill['status_color'] ?? 'secondary' ?>" style="font-size:0.8rem;">
                    <?= ucfirst($prescription_bill['status'] ?? 'N/A') ?>
                </span>
            </div>
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
            Prescription Details
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

    console.log('%c🏥 Braick Dispensary - Prescription Details (WITH LOGIN SESSION)', 'font-size:18px; font-weight:bold; color:#7B2FBE;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💊 Prescription: <?= htmlspecialchars($prescription['prescription_number']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= ucfirst($prescription['status'] ?? 'N/A') ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c💊 Medications: <?= count($prescription_items) ?> items', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📦 Dispensing Info: ' + (<?= $prescription_sale ? 'true' : 'false' ?> ? 'Available' : 'Not dispensed'), 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>