<?php
// ================================================================
// FILE: frontend/pages/admin/bill_details.php
// SUPER ADMIN - BILL DETAILS
// ✅ Inatumia SHARED HEADER & SIDEBAR pekee
// ✅ Imeondoa top-nav, search, dark mode, datetime, bell, avatar
// ✅ Imeondoa DOCTYPE, html, head, body
// ✅ BLUE THEME
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id));
    exit;
}

$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// FETCH BILL DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        u.full_name as created_by_name,
        u.username as created_by_username,
        br.name as branch_name,
        br.location as branch_location,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as total_items,
        (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as total_payments
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// FETCH BILL ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT id, item_type, item_name, description, quantity, unit_price, total_price, status, created_at
    FROM bill_items
    WHERE bill_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$bill_id]);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH PAYMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        id, receipt_number, amount, payment_method, reference_number, notes, received_by, received_at,
        (SELECT full_name FROM users WHERE id = payments.received_by) as received_by_name
    FROM payments
    WHERE bill_id = ?
    ORDER BY received_at DESC
");
$stmt->execute([$bill_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH VISIT
// ================================================================
$visit = null;
if (!empty($bill['visit_id'])) {
    $stmt = $db->prepare("
        SELECT v.*, d.full_name as doctor_name, r.full_name as receptionist_name
        FROM visits v
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        WHERE v.id = ?
    ");
    $stmt->execute([$bill['visit_id']]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// FETCH PRESCRIPTION
// ================================================================
$prescription = null;
if (!empty($bill['visit_id'])) {
    $stmt = $db->prepare("
        SELECT p.*, d.full_name as doctor_name, pat.full_name as patient_name
        FROM prescriptions p
        LEFT JOIN users d ON p.doctor_id = d.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$bill['visit_id']]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_items_amount = 0;
foreach ($bill_items as $item) {
    $total_items_amount += $item['total_price'];
}

$total_paid = $bill['paid_amount'] ?? 0;
$balance = $bill['balance'] ?? 0;
$total_amount = $bill['total_amount'] ?? 0;
$discount_amount = $bill['discount_amount'] ?? 0;
$discount_percent = $bill['discount_percent'] ?? 0;

function getStatusBadge($status) {
    $classes = [
        'paid' => 'success',
        'pending' => 'warning',
        'partial' => 'info',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'paid' => 'fa-check-circle',
        'pending' => 'fa-clock',
        'partial' => 'fa-hourglass-half',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

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

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-bg: #EFF6FF;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7B2FBE;
    --bg-card: #FFFFFF;
    --bg-body: #F8FAFC;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --card-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --card-shadow-hover: 0 4px 20px rgba(0,0,0,0.08);
    --card-radius: 12px;
}

[data-theme="dark"] {
    --bg-card: #1E293B;
    --bg-body: #0F172A;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --card-shadow-hover: 0 4px 20px rgba(0,0,0,0.3);
}

/* ================================================================
   PAGE HEADER - BLUE
   ================================================================ */
.page-header-custom {
    background: var(--primary-gradient);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
}

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.page-header-custom .branch-tag,
.page-header-custom .date-badge {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 18px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.8rem;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* ================================================================
   MODERN CARD
   ================================================================ */
.card-modern {
    background: var(--bg-card);
    border-radius: var(--card-radius);
    border: 2px solid var(--border-color);
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
    overflow: hidden;
    margin-bottom: 20px;
}

.card-modern:hover {
    box-shadow: var(--card-shadow-hover);
    border-color: var(--primary);
}

.card-modern-header {
    padding: 16px 20px;
    background: var(--bg-card);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.card-modern-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-modern-body { padding: 20px; }
.card-modern-body.p-0 { padding: 0; }

.badge-count {
    background: var(--primary-bg);
    color: var(--primary);
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 500;
    margin-left: 4px;
}

/* ================================================================
   BILL SUMMARY GRID
   ================================================================ */
.bill-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px 24px;
}

.summary-item .label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: block;
    font-weight: 600;
}

.summary-item .value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    display: block;
    margin-top: 2px;
}

.summary-item .sub {
    font-size: 0.7rem;
    color: var(--text-secondary);
    display: block;
}

/* ================================================================
   FINANCIAL CARDS - ALL BLUE
   ================================================================ */
.financial-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.financial-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border: 2px solid var(--border-color);
    border-radius: var(--card-radius);
    background: var(--bg-card);
    transition: all 0.3s ease;
    box-shadow: var(--card-shadow);
}

.financial-card:hover {
    border-color: var(--primary);
    box-shadow: var(--card-shadow-hover);
    transform: translateY(-2px);
}

.financial-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.financial-icon.blue-1 { background: #0B5ED7; color: white; }
.financial-icon.blue-2 { background: #1A73E8; color: white; }
.financial-icon.blue-3 { background: #0A4CA8; color: white; }
.financial-icon.blue-4 { background: #1E40AF; color: white; }

.financial-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0;
}

.financial-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* ================================================================
   DATA TABLE - BLUE
   ================================================================ */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.data-table thead th {
    background: var(--primary-gradient) !important;
    color: white !important;
    font-weight: 600;
    padding: 10px 14px;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: none !important;
    white-space: nowrap;
    text-align: left;
}

.data-table thead th:first-child { border-radius: 0; }
.data-table thead th:last-child { border-radius: 0; }

.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    font-size: 0.78rem;
    vertical-align: middle;
}

.data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
.data-table tbody tr:hover td { background: #D1FAE5; }

[data-theme="dark"] .data-table tbody tr:hover td { background: #1A3A2A; }

.data-table tfoot td {
    padding: 10px 14px;
    border-top: 2px solid var(--border-color);
    font-weight: 600;
    background: var(--bg-body);
}

.data-table .total-row td {
    border-top: 3px solid var(--primary);
    font-size: 1rem;
    background: var(--primary-bg) !important;
}

/* ================================================================
   BADGES - BLUE VARIANTS
   ================================================================ */
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

.badge-success { background: var(--success); }
.badge-warning { background: var(--warning); color: #1E293B; }
.badge-info { background: var(--primary); }
.badge-danger { background: var(--danger); }
.badge-secondary { background: #64748B; }

[data-theme="dark"] .badge-warning { color: #1E293B; }

/* ================================================================
   ITEM TYPE BADGES - BLUE VARIANTS
   ================================================================ */
.item-type-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 500;
}

.item-consultation { background: #E8F0FE; color: #0B5ED7; }
.item-lab_test { background: #DBEAFE; color: #0A4CA8; }
.item-medication { background: #E0F2FE; color: #0B3D8A; }
.item-procedure { background: #EDE9FE; color: #1A73E8; }
.item-equipment { background: #C7D2FE; color: #1E40AF; }
.item-tool { background: #F1F5F9; color: #64748B; }
.item-other { background: #F1F5F9; color: #64748B; }
.item-registration { background: #BFDBFE; color: #1E3A8A; }

[data-theme="dark"] .item-consultation { background: #1E3A5F; color: #6EA8FE; }
[data-theme="dark"] .item-lab_test { background: #1E40AF; color: #DBEAFE; }
[data-theme="dark"] .item-medication { background: #0C2A3A; color: #93C5FD; }
[data-theme="dark"] .item-procedure { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .item-equipment { background: #1E3A5F; color: #C7D2FE; }
[data-theme="dark"] .item-tool { background: #334155; color: #94A3B8; }
[data-theme="dark"] .item-other { background: #334155; color: #94A3B8; }
[data-theme="dark"] .item-registration { background: #1E3A8A; color: #BFDBFE; }

/* ================================================================
   PAYMENTS
   ================================================================ */
.payment-item {
    padding: 12px 16px;
    background: var(--bg-body);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    margin-bottom: 12px;
}

.payment-item:last-child { margin-bottom: 0; }

.payment-item:hover { border-color: var(--primary); }

.payment-info .payment-amount {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}

.payment-info .amount {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
}

.payment-info .payment-details {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.payment-info .payment-reference {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin-top: 4px;
    padding: 2px 8px;
    background: var(--bg-card);
    border-radius: 4px;
    display: inline-block;
    border: 1px solid var(--border-color);
}

.payment-info .payment-notes {
    font-size: 0.7rem;
    color: var(--text-secondary);
    margin-top: 4px;
    font-style: italic;
}

/* ================================================================
   RELATED INFORMATION
   ================================================================ */
.related-item {
    padding: 12px 16px;
    background: var(--bg-body);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    margin-bottom: 12px;
}

.related-item:last-child { margin-bottom: 0; }

.related-item h4 {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.related-item h4 i { color: var(--primary); }

.related-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 20px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid var(--border-color);
}

.detail-row:last-child { border-bottom: none; }
.detail-row.full { grid-column: 1 / -1; }

.detail-row .label {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.detail-row .value {
    font-size: 0.75rem;
    color: var(--text-primary);
    font-weight: 500;
    text-align: right;
}

/* ================================================================
   GRID LAYOUT
   ================================================================ */
.grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

/* ================================================================
   UTILITIES
   ================================================================ */
.text-blue-500 { color: var(--primary); }
.text-green-500 { color: var(--success); }
.text-purple-500 { color: var(--purple); }
.text-red-500 { color: var(--danger); }
.text-gray-400 { color: var(--text-secondary); }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.text-lg { font-size: 1.1rem; }
.text-sm { font-size: 0.8rem; }
.text-xs { font-size: 0.7rem; }
.block { display: block; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-5 { margin-bottom: 20px; }
.mt-3 { margin-top: 12px; }
.p-0 { padding: 0; }
.py-5 { padding-top: 20px; padding-bottom: 20px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.flex { display: flex; }
.flex-wrap { flex-wrap: wrap; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.overflow-x-auto { overflow-x: auto; }

/* ================================================================
   FOOTER
   ================================================================ */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer-brand { color: var(--primary); font-weight: 600; }

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (max-width: 1024px) {
    .grid-2col { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .bill-summary-grid { grid-template-columns: 1fr 1fr; }
    .financial-summary-grid { grid-template-columns: 1fr 1fr; }
    .related-details { grid-template-columns: 1fr; }
    .detail-row { flex-direction: column; align-items: flex-start; gap: 2px; }
    .detail-row .value { text-align: left; }
    .data-table { font-size: 0.7rem; }
    .data-table td, .data-table th { padding: 6px 8px; }
    .card-modern-header { padding: 12px 16px; }
    .card-modern-body { padding: 12px 16px; }
    .financial-card { padding: 12px 16px; }
}

@media (max-width: 480px) {
    .bill-summary-grid { grid-template-columns: 1fr; }
    .financial-summary-grid { grid-template-columns: 1fr; }
    .page-header-custom { flex-direction: column; align-items: stretch; text-align: center; }
}
</style>

<main class="main-content">

    <!-- Page Header - BLUE -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i> Bill Details
            </h1>
            <p class="page-subtitle">
                View complete bill information
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="date-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="bills.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
    </div>

    <!-- BILL SUMMARY -->
    <div class="card-modern">
        <div class="card-modern-header">
            <div class="card-modern-title">
                <i class="fas fa-file-invoice" style="color:#0B5ED7;"></i>
                Bill Summary
            </div>
            <div>
                <span class="badge badge-<?= getStatusBadge($bill['status']) ?>">
                    <i class="fas <?= getStatusIcon($bill['status']) ?>"></i>
                    <?= ucfirst($bill['status']) ?>
                </span>
            </div>
        </div>
        <div class="card-modern-body">
            <div class="bill-summary-grid">
                <div class="summary-item">
                    <span class="label">Bill Number</span>
                    <span class="value"><?= htmlspecialchars($bill['bill_number']) ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Patient</span>
                    <span class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
                    <span class="sub">ID: <?= htmlspecialchars($bill['patient_id_number'] ?? 'N/A') ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Created By</span>
                    <span class="value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                    <span class="sub">@<?= htmlspecialchars($bill['created_by_username'] ?? '') ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Date Created</span>
                    <span class="value"><?= date('M d, Y', strtotime($bill['created_at'])) ?></span>
                    <span class="sub"><?= date('h:i:s A', strtotime($bill['created_at'])) ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Branch</span>
                    <span class="value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></span>
                    <span class="sub"><?= htmlspecialchars($bill['branch_location'] ?? '') ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Total Items</span>
                    <span class="value"><?= number_format($bill['total_items'] ?? 0) ?></span>
                    <span class="sub">items in this bill</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FINANCIAL CARDS - ALL BLUE -->
    <div class="financial-summary-grid">
        <div class="financial-card">
            <div class="financial-icon blue-1"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="financial-label">Total Amount</p>
                <p class="financial-value">TSh <?= number_format($total_amount, 2) ?></p>
            </div>
        </div>
        <div class="financial-card">
            <div class="financial-icon blue-2"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="financial-label">Paid Amount</p>
                <p class="financial-value">TSh <?= number_format($total_paid, 2) ?></p>
            </div>
        </div>
        <div class="financial-card">
            <div class="financial-icon blue-3"><i class="fas fa-clock"></i></div>
            <div>
                <p class="financial-label">Balance</p>
                <p class="financial-value" style="color: <?= $balance > 0 ? '#EF4444' : '#059669' ?>;">
                    TSh <?= number_format($balance, 2) ?>
                </p>
            </div>
        </div>
        <div class="financial-card">
            <div class="financial-icon blue-4"><i class="fas fa-list"></i></div>
            <div>
                <p class="financial-label">Total Items</p>
                <p class="financial-value"><?= number_format($bill['total_items'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <!-- BILL ITEMS TABLE -->
    <div class="card-modern">
        <div class="card-modern-header">
            <div class="card-modern-title">
                <i class="fas fa-list-ul" style="color:#0B5ED7;"></i>
                Bill Items
                <span class="badge-count"><?= count($bill_items) ?> items</span>
            </div>
        </div>
        <div class="card-modern-body p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Total Price</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bill_items) > 0): ?>
                            <?php $i = 1; foreach ($bill_items as $item): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="font-medium"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <?php if (!empty($item['description'])): ?>
                                            <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['description']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="item-type-badge item-<?= $item['item_type'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;"><?= $item['quantity'] ?? 1 ?></td>
                                    <td style="text-align:right;">TSh <?= number_format($item['unit_price'], 2) ?></td>
                                    <td style="text-align:right;" class="font-semibold">TSh <?= number_format($item['total_price'], 2) ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-<?= $item['status'] === 'paid' ? 'success' : 'warning' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                            <?= ucfirst($item['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:30px;">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                    No items found for this bill
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align:right;font-weight:700;">Subtotal</td>
                            <td style="text-align:right;font-weight:700;">TSh <?= number_format($total_items_amount, 2) ?></td>
                            <td></td>
                        </tr>
                        <?php if ($discount_amount > 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:right;color:#EF4444;">Discount (<?= $discount_percent ?>%)</td>
                                <td style="text-align:right;color:#EF4444;">- TSh <?= number_format($discount_amount, 2) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td colspan="5" style="text-align:right;font-weight:700;font-size:1.05rem;">Total</td>
                            <td style="text-align:right;font-weight:700;font-size:1.05rem;color:var(--primary);">TSh <?= number_format($total_amount, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- PAYMENTS & RELATED -->
    <div class="grid-2col">
        
        <!-- Payments -->
        <div class="card-modern">
            <div class="card-modern-header">
                <div class="card-modern-title">
                    <i class="fas fa-credit-card" style="color:#059669;"></i>
                    Payment History
                    <span class="badge-count"><?= count($payments) ?> payments</span>
                </div>
            </div>
            <div class="card-modern-body">
                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-info">
                                <div class="payment-amount">
                                    <span class="amount">TSh <?= number_format($payment['amount'], 2) ?></span>
                                    <span class="badge badge-<?= $payment['payment_method'] === 'cash' ? 'success' : 'info' ?>" style="font-size:0.6rem;">
                                        <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                    </span>
                                </div>
                                <div class="payment-details">
                                    <span>Receipt: <?= htmlspecialchars($payment['receipt_number']) ?></span>
                                    <span>By: <?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></span>
                                    <span><?= date('M d, Y h:i A', strtotime($payment['received_at'])) ?></span>
                                </div>
                                <?php if (!empty($payment['reference_number'])): ?>
                                    <div class="payment-reference">Ref: <?= htmlspecialchars($payment['reference_number']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($payment['notes'])): ?>
                                    <div class="payment-notes"><?= htmlspecialchars($payment['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:20px;">
                        <i class="fas fa-credit-card" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        No payments recorded for this bill
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Related Information -->
        <div class="card-modern">
            <div class="card-modern-header">
                <div class="card-modern-title">
                    <i class="fas fa-link" style="color:#7B2FBE;"></i>
                    Related Information
                </div>
            </div>
            <div class="card-modern-body">
                <?php if ($visit): ?>
                    <div class="related-item">
                        <h4><i class="fas fa-stethoscope"></i> Visit</h4>
                        <div class="related-details">
                            <div class="detail-row">
                                <span class="label">Visit #</span>
                                <span class="value"><?= htmlspecialchars($visit['visit_number']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Doctor</span>
                                <span class="value"><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Status</span>
                                <span class="value">
                                    <span class="badge badge-<?= $visit['status'] === 'completed' ? 'success' : 'warning' ?>" style="font-size:0.6rem;">
                                        <?= ucfirst($visit['status']) ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Date</span>
                                <span class="value"><?= date('M d, Y', strtotime($visit['visit_date'])) ?></span>
                            </div>
                            <?php if (!empty($visit['diagnosis'])): ?>
                                <div class="detail-row full">
                                    <span class="label">Diagnosis</span>
                                    <span class="value"><?= htmlspecialchars($visit['diagnosis']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($prescription): ?>
                    <div class="related-item">
                        <h4><i class="fas fa-prescription-bottle"></i> Prescription</h4>
                        <div class="related-details">
                            <div class="detail-row">
                                <span class="label">Prescription #</span>
                                <span class="value"><?= htmlspecialchars($prescription['prescription_number']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Doctor</span>
                                <span class="value"><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Status</span>
                                <span class="value">
                                    <span class="badge badge-<?= $prescription['status'] === 'dispensed' ? 'success' : 'warning' ?>" style="font-size:0.6rem;">
                                        <?= ucfirst($prescription['status']) ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Created</span>
                                <span class="value"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!$visit && !$prescription): ?>
                    <div style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:20px;">
                        <i class="fas fa-info-circle" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        No related visit or prescription found
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            Bill Details
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE JAVASCRIPT - PEKEE -->
<!-- ================================================================ -->
<script>
// Footer time update only (header ina yake)
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c📄 Braick - Bill Details (Shared Header/Sidebar)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Inatumia SHARED HEADER & SIDEBAR', 'font-size:12px;color:#34D399;');
console.log('%c✅ BLUE THEME applied', 'font-size:12px;color:#34D399;');
console.log('%c🏷️ Bill: <?= htmlspecialchars($bill['bill_number']) ?>', 'font-size:12px;color:#0B5ED7;');
console.log('%c💰 Total: TSh <?= number_format($total_amount, 2) ?>', 'font-size:12px;color:#059669;');
</script>

</body>
</html>