<?php
// ================================================================
// FILE: frontend/pages/admin/view_patient_prescriptions.php
// ADMIN - PATIENT PRESCRIPTIONS (ALL IN ONE TABLE)
// BRAICK DISPENSARY - BLUE THEME
// ✅ TABLE MOJA TU
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET PATIENT
// ================================================================
$stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// FETCH ALL PRESCRIPTIONS
// ================================================================
$prescriptions = [];
try {
    $sql = "
        SELECT 
            p.id as prescription_id,
            p.prescription_number,
            p.status as prescription_status,
            p.created_at,
            p.dispensed_at,
            p.diagnosis,
            p.notes,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            v.visit_date
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE p.patient_id = ?
    ";
    $params = [$patient_id];
    
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND p.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// ================================================================
// FETCH ALL ITEMS (SINGLE QUERY)
// ================================================================
$all_items = [];
$grand_total = 0;
$total_items_count = 0;

if (count($prescriptions) > 0) {
    try {
        $prescription_ids = array_column($prescriptions, 'prescription_id');
        $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
        
        $stmt = $db->prepare("
            SELECT 
                pi.*,
                pi.prescription_id,
                mi.medication_name as inventory_name,
                mi.category,
                mi.unit,
                mi.batch_number
            FROM prescription_items pi
            LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
            WHERE pi.prescription_id IN ($placeholders)
            ORDER BY pi.prescription_id DESC, pi.id ASC
        ");
        $stmt->execute($prescription_ids);
        $items_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items_raw as $item) {
            $all_items[$item['prescription_id']][] = $item;
            $grand_total += (float)($item['total_price'] ?? 0);
            $total_items_count++;
        }
    } catch (Exception $e) {
        $all_items = [];
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'confirmed' => 'fa-check-double',
        'dispensed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'dispensed' => 'Dispensed',
        'cancelled' => 'Cancelled'
    ];
    return $labels[$status] ?? ucfirst($status);
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ✅ SHARED HEADER + SIDEBAR
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE CSS -->
<!-- ================================================================ -->
<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --primary-bg: #EFF6FF;
    --radius: 12px;
    --radius-lg: 18px;
}

[data-theme="dark"] {
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
}

/* PAGE HEADER */
.page-header {
    background: var(--primary-gradient);
    border-radius: var(--radius-lg);
    padding: 28px 36px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
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
    margin: 0;
}

.page-header .page-title i { font-size: 2rem; opacity: 0.9; }

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}

.page-header .page-subtitle strong { color: white; font-weight: 600; }

.header-badge {
    background: rgba(255,255,255,0.12);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255,255,255,0.1);
}

.btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 18px;
    border-radius: var(--radius);
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

.btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    color: white;
}

/* PATIENT CARD */
.patient-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 20px 24px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
}

.patient-avatar {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    flex-shrink: 0;
}

.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.info-item {
    padding: 10px 14px;
    background: var(--primary-bg);
    border-radius: 10px;
    border-left: 3px solid var(--primary);
}

.info-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 4px;
}

.info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* STATS */
.stats-mini {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-mini {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px 18px;
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-mini.blue-1 { border-left: 4px solid #0B5ED7; }
.stat-mini.blue-2 { border-left: 4px solid #1A73E8; }
.stat-mini.blue-3 { border-left: 4px solid #0A4CA8; }

.stat-mini .stat-mini-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
}

.stat-mini .stat-mini-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: uppercase;
}

.stat-mini .stat-mini-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--primary-bg);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* CARD */
.card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 2px solid var(--border-color);
    overflow: hidden;
    margin-bottom: 24px;
}

.card-header {
    padding: 16px 24px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

[data-theme="dark"] .card-header { background: #0F172A; }

.card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-title i { margin-right: 8px; color: var(--primary); }

/* SCROLL BUTTONS */
.scroll-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    padding: 0;
}

.scroll-btn:hover:not(:disabled) {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.scroll-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* TABLE SCROLL */
.table-scroll-container {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 700px;
    scroll-behavior: smooth;
}

.table-scroll-container::-webkit-scrollbar { height: 8px; width: 8px; }
.table-scroll-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
.table-scroll-container::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
.table-scroll-container::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

/* ================================================================
   ✅ DATA TABLE - TABLE MOJA
   ================================================================ */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
    min-width: 1400px;
}

.data-table thead th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 600;
    padding: 12px 14px;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: none;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
    text-align: left;
}

.data-table thead th:first-child { border-radius: 8px 0 0 0; }
.data-table thead th:last-child { border-radius: 0 8px 0 0; }

.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    background: var(--bg-card);
}

/* ✅ RX HEADER ROW - Prescription info */
.rx-header-row td {
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE) !important;
    color: #0A4CA8 !important;
    font-weight: 700;
    padding: 12px 14px;
    border-top: 3px solid var(--primary);
    border-bottom: 2px solid var(--primary);
    position: sticky;
    top: 44px;
    z-index: 5;
}

[data-theme="dark"] .rx-header-row td {
    background: linear-gradient(135deg, #1E3A5F, #2D4A7F) !important;
    color: #93C5FD !important;
}

/* ✅ SUBTOTAL ROW */
.subtotal-row td {
    background: linear-gradient(135deg, #FEF3C7, #FDE68A) !important;
    color: #92400E !important;
    font-weight: 700;
    font-size: 0.82rem;
    border-top: 2px solid #D97706;
    border-bottom: 2px solid #D97706;
    padding: 10px 14px;
}

[data-theme="dark"] .subtotal-row td {
    background: linear-gradient(135deg, #3D2E0A, #78350F) !important;
    color: #FCD34D !important;
}

/* ✅ GRAND TOTAL ROW */
.grand-total-row td {
    background: var(--primary-gradient) !important;
    color: white !important;
    font-weight: 700;
    font-size: 1rem;
    border-top: 3px solid #083C8A;
    border-bottom: 3px solid #083C8A;
    padding: 14px 14px;
    position: sticky;
    bottom: 0;
    z-index: 5;
}

/* Normal rows */
.data-table tbody tr:not(.rx-header-row):not(.subtotal-row):not(.grand-total-row) {
    transition: background 0.2s ease;
}

.data-table tbody tr:not(.rx-header-row):not(.subtotal-row):not(.grand-total-row):hover td {
    background: #D1FAE5 !important;
}

[data-theme="dark"] .data-table tbody tr:not(.rx-header-row):not(.subtotal-row):not(.grand-total-row):hover td {
    background: #1A3A2A !important;
}

/* Rx number */
.rx-number {
    font-family: monospace;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--primary);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* BADGES */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    color: white;
}

.badge-success { background: #059669; }
.badge-danger { background: #DC2626; }
.badge-warning { background: #D97706; color: #1E293B; }
.badge-info { background: #0B5ED7; }
.badge-secondary { background: #64748B; }

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    gap: 4px;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    color: white;
    white-space: nowrap;
}

.action-btn:hover { transform: scale(1.05); }
.action-btn.edit { background: #1A73E8; }
.action-btn.edit:hover { background: #0B5ED7; }
.action-btn.cancel { background: #D97706; }
.action-btn.cancel:hover { background: #B45309; }
.action-btn.delete { background: #DC2626; }
.action-btn.delete:hover { background: #B91C1C; }

/* EMPTY */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    color: var(--border-color);
    display: block;
    margin-bottom: 12px;
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 1024px) {
    .page-header { padding: 20px; }
    .page-header .page-title { font-size: 1.4rem; }
    .stats-mini { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .page-header {
        padding: 16px 18px;
        flex-direction: column;
        align-items: flex-start;
    }
    .page-header .page-title { font-size: 1.2rem; }
    .patient-info-grid { grid-template-columns: 1fr; }
    .stats-mini { grid-template-columns: 1fr; }
    .data-table { font-size: 0.7rem; min-width: 1200px; }
    .data-table td, .data-table th { padding: 8px 10px; }
    .scroll-btn { width: 32px; height: 32px; font-size: 0.75rem; }
    .action-btn { padding: 3px 8px; font-size: 0.6rem; }
}

@media print {
    .top-nav, .sidebar, .btn-outline-light, .scroll-btn,
    .footer, .action-buttons { display: none !important; }
    .main-content { margin: 0; padding: 20px; }
}
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Patient Prescriptions
                <span class="header-badge" style="background:rgba(255,255,255,0.2);font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-prescription"></i> <?= count($prescriptions) ?> prescription(s)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_patient.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-user"></i> View Patient
            </a>
            <a href="patients.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Patient Card -->
    <div class="patient-card">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div class="patient-avatar">
                <?= strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:200px;">
                <h2 style="font-size:1.3rem;font-weight:700;margin:0;color:var(--text-primary);">
                    <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
                </h2>
                <p style="font-size:0.85rem;color:var(--text-secondary);margin:4px 0 0 0;">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                    <span style="margin:0 8px;">|</span>
                    <i class="fas fa-user"></i> <?= ucfirst($patient['gender'] ?? 'N/A') ?>
                    <span style="margin:0 8px;">|</span>
                    <i class="fas fa-calendar"></i> <?= calculateAge($patient['date_of_birth'] ?? '') ?> years
                </p>
            </div>
        </div>
        
        <div class="patient-info-grid">
            <div class="info-item">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar"></i> Date of Birth</span>
                <span class="info-value">
                    <?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><i class="fas fa-tint"></i> Blood Group</span>
                <span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-mini">
        <div class="stat-mini blue-1">
            <div>
                <div class="stat-mini-label">Total Prescriptions</div>
                <div class="stat-mini-number"><?= count($prescriptions) ?></div>
            </div>
            <div class="stat-mini-icon"><i class="fas fa-prescription"></i></div>
        </div>
        <div class="stat-mini blue-2">
            <div>
                <div class="stat-mini-label">Total Items</div>
                <div class="stat-mini-number"><?= $total_items_count ?></div>
            </div>
            <div class="stat-mini-icon"><i class="fas fa-pills"></i></div>
        </div>
        <div class="stat-mini blue-3">
            <div>
                <div class="stat-mini-label">Overall Total</div>
                <div class="stat-mini-number" style="font-size:1.1rem;">TSh <?= number_format($grand_total, 0) ?></div>
            </div>
            <div class="stat-mini-icon"><i class="fas fa-coins"></i></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ TABLE MOJA - Prescriptions zote -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-table"></i>
                All Prescriptions (<?= count($prescriptions) ?>)
            </h3>
            <div style="display:flex;gap:6px;align-items:center;">
                <button type="button" class="scroll-btn" id="scrollBtnLeft" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" id="scrollBtnRight" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-container" id="tableScrollContainer">
            <?php if (count($prescriptions) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="min-width:180px;">Medication</th>
                            <th style="min-width:100px;">Dosage</th>
                            <th style="min-width:120px;">Frequency</th>
                            <th style="min-width:70px;">Qty</th>
                            <th style="min-width:100px;">Duration</th>
                            <th style="min-width:100px;">Route</th>
                            <th style="min-width:150px;">Instructions</th>
                            <th style="min-width:110px;">Unit Price</th>
                            <th style="min-width:110px;">Total</th>
                            <th style="min-width:220px;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $rx): 
                            $rx_id = $rx['prescription_id'];
                            $rx_items = $all_items[$rx_id] ?? [];
                            $rx_total = 0;
                            foreach ($rx_items as $it) $rx_total += (float)($it['total_price'] ?? 0);
                        ?>
                            
                            <!-- ✅ PRESCRIPTION HEADER ROW -->
                            <tr class="rx-header-row">
                                <td colspan="11" style="padding:12px 16px;">
                                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;">
                                        <span class="rx-number">
                                            <i class="fas fa-file-prescription"></i>
                                            <?= htmlspecialchars($rx['prescription_number'] ?? 'N/A') ?>
                                        </span>
                                        <span class="badge badge-<?= getStatusBadge($rx['prescription_status'] ?? 'pending') ?>">
                                            <i class="fas <?= getStatusIcon($rx['prescription_status'] ?? 'pending') ?>"></i>
                                            <?= getStatusLabel($rx['prescription_status'] ?? 'pending') ?>
                                        </span>
                                        <span style="font-size:0.75rem;color:#0A4CA8;">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($rx['doctor_name'] ?? 'N/A') ?>
                                        </span>
                                        <span style="font-size:0.75rem;color:#0A4CA8;">
                                            <i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($rx['created_at'] ?? 'now')) ?>
                                        </span>
                                        <span style="font-size:0.75rem;color:#0A4CA8;">
                                            <i class="fas fa-pills"></i> <?= count($rx_items) ?> item(s)
                                        </span>
                                        <span style="font-size:0.9rem;font-weight:700;color:#0A4CA8;margin-left:auto;">
                                            <i class="fas fa-coins"></i> TSh <?= number_format($rx_total, 0) ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- ✅ ITEMS ZA PRESCRIPTION HII -->
                            <?php if (count($rx_items) > 0): ?>
                                <?php $item_i = 1; foreach ($rx_items as $item): ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:600;color:var(--primary);">
                                            <?= $item_i++ ?>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;">
                                                <?= htmlspecialchars($item['medication_name'] ?? $item['inventory_name'] ?? 'N/A') ?>
                                            </div>
                                            <?php if (!empty($item['category'])): ?>
                                                <div style="font-size:0.68rem;color:var(--text-secondary);">
                                                    <?= htmlspecialchars($item['category']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['batch_number'])): ?>
                                                <div style="font-size:0.65rem;color:var(--text-secondary);">
                                                    <i class="fas fa-barcode"></i> <?= htmlspecialchars($item['batch_number']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight:600;color:var(--primary);">
                                            <?= htmlspecialchars($item['dosage'] ?? 'N/A') ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                        <td style="text-align:center;font-weight:600;">
                                            <?= $item['quantity'] ?? 0 ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['route'] ?? 'N/A') ?></td>
                                        <td style="font-size:0.75rem;">
                                            <?= htmlspecialchars($item['instructions'] ?? '-') ?>
                                        </td>
                                        <td style="font-family:monospace;">
                                            TSh <?= number_format($item['unit_price'] ?? 0, 0) ?>
                                        </td>
                                        <td style="font-family:monospace;font-weight:700;color:var(--primary);">
                                            TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if (($rx['prescription_status'] ?? '') === 'pending'): ?>
                                                    <a href="edit_prescription.php?id=<?= $rx_id ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                                                       class="action-btn edit" title="Edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="cancel_prescription.php?id=<?= $rx_id ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                                                       class="action-btn cancel" 
                                                       title="Cancel"
                                                       onclick="return confirm('Cancel prescription <?= htmlspecialchars(addslashes($rx['prescription_number'] ?? 'N/A')) ?>?');">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                <?php endif; ?>
                                                <a href="delete_prescription.php?id=<?= $rx_id ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                                                   class="action-btn delete" 
                                                   title="Delete"
                                                   onclick="return confirm('⚠️ DELETE prescription <?= htmlspecialchars(addslashes($rx['prescription_number'] ?? 'N/A')) ?>?\n\nThis cannot be undone!');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <!-- ✅ SUBTOTAL ROW -->
                                <tr class="subtotal-row">
                                    <td colspan="9" style="text-align:right;">
                                        <i class="fas fa-calculator"></i> Subtotal (<?= htmlspecialchars($rx['prescription_number'] ?? '') ?>):
                                    </td>
                                    <td style="font-family:monospace;">
                                        TSh <?= number_format($rx_total, 0) ?>
                                    </td>
                                    <td></td>
                                </tr>
                                
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" style="text-align:center;padding:14px;color:var(--text-secondary);">
                                        <i class="fas fa-info-circle"></i> No items in this prescription
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                        <?php endforeach; ?>
                        
                        <!-- ✅ GRAND TOTAL ROW -->
                        <tr class="grand-total-row">
                            <td colspan="9" style="text-align:right;">
                                <i class="fas fa-calculator"></i> OVERALL TOTAL (All Prescriptions):
                            </td>
                            <td style="font-family:monospace;">
                                TSh <?= number_format($grand_total, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                        
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <h4 style="font-size:1rem;color:var(--text-primary);margin-bottom:4px;">No Prescriptions</h4>
                    <p>This patient has no prescriptions yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            Patient Prescriptions - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
var scrollAmount = 300;

function scrollTable(direction) {
    var container = document.getElementById('tableScrollContainer');
    if (!container) return;
    
    if (direction === 'left') {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function updateScrollButtons() {
    var container = document.getElementById('tableScrollContainer');
    var btnLeft = document.getElementById('scrollBtnLeft');
    var btnRight = document.getElementById('scrollBtnRight');
    
    if (!container || !btnLeft || !btnRight) return;
    
    var scrollLeft = container.scrollLeft;
    var maxScroll = container.scrollWidth - container.clientWidth;
    
    btnLeft.disabled = (scrollLeft <= 5);
    btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('tableScrollContainer');
    if (container) {
        container.addEventListener('scroll', updateScrollButtons);
        setTimeout(updateScrollButtons, 200);
        window.addEventListener('resize', function() {
            setTimeout(updateScrollButtons, 200);
        });
    }
});

setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c💊 Patient Prescriptions (TABLE MOJA)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ TABLE MOJA TU - Prescriptions zote kwenye table moja', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>