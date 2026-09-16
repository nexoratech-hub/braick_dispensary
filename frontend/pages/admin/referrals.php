<?php
// ================================================================
// FILE: frontend/pages/admin/referrals.php
// SUPER ADMIN - REFERRALS MANAGEMENT
// ✅ BLUE THEME ONLY
// ✅ Dark mode inafanya kazi kwa page YOTE
// ✅ View, Edit, Delete, PDF buttons
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$selected_branch_id = $_GET['branch'] ?? 'all';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $current_branch_name_display = $branch_data['name'];
} else {
    $selected_branch_id = 'all';
}

$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DELETE
// ================================================================
$message = '';
$message_type = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $ref_id = (int)$_GET['delete'];
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM referrals WHERE id = ?");
        $stmt->execute([$ref_id]);
        
        try {
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'referral_deleted', ?, NOW())");
            $log_stmt->execute([$user_id, $user_branch_id, "Deleted referral #$ref_id"]);
        } catch (Exception $e) {}
        
        $db->commit();
        $message = "✅ Referral deleted successfully!";
        $message_type = 'success';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "❌ Failed to delete: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// FILTERS
// ================================================================
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// ================================================================
// FETCH REFERRALS
// ================================================================
$sql = "
    SELECT 
        r.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        u_from.full_name as from_doctor_name,
        u_from.specialty as from_doctor_specialty,
        u_to.full_name as to_doctor_name,
        u_to.specialty as to_doctor_specialty,
        v.visit_number,
        b.name as branch_name
    FROM referrals r
    LEFT JOIN patients p ON r.patient_id = p.id
    LEFT JOIN visits v ON r.visit_id = v.id
    LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
    LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
    LEFT JOIN branches b ON r.branch_id = b.id
    WHERE 1=1
";
$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND r.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if ($type_filter !== 'all') {
    $sql .= " AND r.referral_type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR u_to.full_name LIKE ? OR r.referral_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY r.created_at DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STATS
$total = count($referrals);
$pending = 0; $accepted = 0; $completed = 0; $cancelled = 0;
$internal = 0; $external = 0;

foreach ($referrals as $r) {
    switch ($r['status'] ?? 'pending') {
        case 'pending': case 'referred': $pending++; break;
        case 'accepted': $accepted++; break;
        case 'completed': $completed++; break;
        case 'cancelled': $cancelled++; break;
    }
    if (($r['referral_type'] ?? '') === 'internal') $internal++;
    else $external++;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================
   BLUE THEME ONLY — DARK MODE FULL PAGE
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #334155;
}

/* ================================================================
   ✅ FORCE DARK MODE ON EVERYTHING
   ================================================================ */
html[data-theme="dark"],
html[data-theme="dark"] body {
    background: #0F172A !important;
    color: #F1F5F9 !important;
}

html[data-theme="dark"] .main-content {
    background: #0F172A !important;
}

html[data-theme="dark"] .card {
    background: #1E293B !important;
    border-color: #334155 !important;
    color: #F1F5F9 !important;
}

html[data-theme="dark"] .stat-card {
    color: white !important;
}

html[data-theme="dark"] .data-table {
    background: #1E293B !important;
}

html[data-theme="dark"] .data-table td {
    color: #F1F5F9 !important;
    border-bottom-color: #334155 !important;
}

html[data-theme="dark"] .data-table tbody tr:nth-child(even) {
    background: #0F172A !important;
}

html[data-theme="dark"] .data-table tbody tr:nth-child(odd) {
    background: #1E293B !important;
}

html[data-theme="dark"] .data-table tbody tr:hover td {
    background: #1E40AF !important;
}

html[data-theme="dark"] .filter-form input,
html[data-theme="dark"] .filter-form select {
    background: #1E293B !important;
    color: #F1F5F9 !important;
    border-color: #334155 !important;
}

html[data-theme="dark"] h3 {
    color: #F1F5F9 !important;
}

html[data-theme="dark"] .footer {
    background: transparent !important;
    color: #94A3B8 !important;
}

/* ================================================================
   PAGE HEADER
   ================================================================ */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
}

.page-header-custom .page-title i {
    color: rgba(255,255,255,0.85);
}

.page-header-custom .badge-branch {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.page-header-custom .btn-new-referral {
    background: white;
    color: #0B5ED7;
    border: none;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.page-header-custom .btn-new-referral:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    background: #F0F7FF;
}

/* ================================================================
   STAT CARDS - ALL BLUE
   ================================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}

.stat-card {
    border-radius: 12px;
    padding: 14px 16px;
    color: white;
    min-height: 85px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s;
    text-decoration: none;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* ALL BLUE VARIANTS */
.stat-card.blue-1 { background: #0B5ED7; }
.stat-card.blue-2 { background: #0A4CA8; }
.stat-card.blue-3 { background: #1A73E8; }
.stat-card.blue-4 { background: #0B3D8A; }
.stat-card.blue-5 { background: #1E40AF; }
.stat-card.blue-6 { background: #1E3A8A; }

.stat-card .stat-number {
    font-size: 1.4rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-card .stat-label {
    font-size: 0.65rem;
    opacity: 0.9;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.stat-card .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

/* ================================================================
   CARD
   ================================================================ */
.card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 20px 24px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    transition: all 0.3s;
}

.card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
}

/* ================================================================
   FILTERS
   ================================================================ */
.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-form input,
.filter-form select {
    padding: 8px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
}

.filter-form input:focus,
.filter-form select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
}

/* ================================================================
   BUTTONS
   ================================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    min-height: 38px;
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
    border-color: var(--primary);
    color: var(--primary);
}

/* Action buttons - ALL BLUE */
.btn-view {
    background: #0B5ED7;
    color: white;
    padding: 5px 12px;
    font-size: 0.75rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-view:hover {
    background: #0A4CA8;
    transform: scale(1.05);
}

.btn-edit {
    background: #1A73E8;
    color: white;
    padding: 5px 12px;
    font-size: 0.75rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-edit:hover {
    background: #0B5ED7;
    transform: scale(1.05);
}

.btn-pdf {
    background: #0A4CA8;
    color: white;
    padding: 5px 10px;
    font-size: 0.75rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-pdf:hover {
    background: #0B3D8A;
    transform: scale(1.05);
}

.btn-delete {
    background: #1E40AF;
    color: white;
    padding: 5px 12px;
    font-size: 0.75rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-delete:hover {
    background: #1E3A8A;
    transform: scale(1.05);
}

/* ================================================================
   TABLE - BLUE HEADERS
   ================================================================ */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.data-table thead th {
    background: #0B5ED7;
    color: white;
    padding: 12px 14px;
    font-size: 0.68rem;
    text-transform: uppercase;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.data-table thead th:first-child { border-radius: 10px 0 0 0; }
.data-table thead th:last-child { border-radius: 0 10px 0 0; }

.data-table tbody tr:nth-child(even) { background: #E8F0FE; }
.data-table tbody tr:nth-child(odd) { background: #FFFFFF; }
.data-table tbody tr:hover td { background: #DBEAFE; }

[data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #0F172A; }
[data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
[data-theme="dark"] .data-table tbody tr:hover td { background: #1E40AF; }

.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

/* ================================================================
   BADGES - ALL BLUE
   ================================================================ */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 12px;
    border-radius: 12px;
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: capitalize;
}

.badge-blue-1 { background: #E8F0FE; color: #0B5ED7; }
.badge-blue-2 { background: #DBEAFE; color: #0A4CA8; }
.badge-blue-3 { background: #E0F2FE; color: #0B3D8A; }
.badge-blue-4 { background: #EDE9FE; color: #1A73E8; }
.badge-blue-5 { background: #C7D2FE; color: #1E40AF; }
.badge-blue-6 { background: #BFDBFE; color: #1E3A8A; }

[data-theme="dark"] .badge-blue-1 { background: #1E3A5F; color: #6EA8FE; }
[data-theme="dark"] .badge-blue-2 { background: #1E40AF; color: #DBEAFE; }
[data-theme="dark"] .badge-blue-3 { background: #0C2A3A; color: #93C5FD; }
[data-theme="dark"] .badge-blue-4 { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .badge-blue-5 { background: #1E3A5F; color: #C7D2FE; }
[data-theme="dark"] .badge-blue-6 { background: #1E3A8A; color: #BFDBFE; }

/* ================================================================
   ACTION BUTTONS
   ================================================================ */
.action-buttons {
    display: flex;
    gap: 4px;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
}

/* ================================================================
   MESSAGE BOX
   ================================================================ */
.message-box {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.message-box.success {
    background: #DBEAFE;
    color: #0B5ED7;
    border: 2px solid #6EA8FE;
}

.message-box.error {
    background: #FEE2E2;
    color: #DC2626;
    border: 2px solid #FCA5A5;
}

[data-theme="dark"] .message-box.success {
    background: #1E3A5F;
    color: #6EA8FE;
    border-color: #3B82F6;
}

[data-theme="dark"] .message-box.error {
    background: #3A1A1A;
    color: #F87171;
    border-color: #F87171;
}

/* ================================================================
   FOOTER
   ================================================================ */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* ================================================================
   EMPTY STATE
   ================================================================ */
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

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .data-table { font-size: 0.7rem; }
    .data-table th, .data-table td { padding: 6px 8px; }
    .action-buttons { flex-wrap: wrap; }
    .page-header-custom { padding: 18px 20px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-share-square"></i> Referrals Management
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                Manage all patient referrals
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="add_referral.php?branch=<?= $selected_branch_id ?>" class="btn-new-referral">
                <i class="fas fa-plus-circle"></i> New Referral
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATS - ALL BLUE -->
    <div class="stats-grid">
        <a href="referrals.php?branch=<?= $selected_branch_id ?>" class="stat-card blue-1">
            <div>
                <div class="stat-label">Total</div>
                <div class="stat-number"><?= $total ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-share-square"></i></div>
        </a>
        <a href="referrals.php?status=pending&branch=<?= $selected_branch_id ?>" class="stat-card blue-2">
            <div>
                <div class="stat-label">Pending</div>
                <div class="stat-number"><?= $pending ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        <a href="referrals.php?type=internal&branch=<?= $selected_branch_id ?>" class="stat-card blue-3">
            <div>
                <div class="stat-label">Internal</div>
                <div class="stat-number"><?= $internal ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-hospital"></i></div>
        </a>
        <a href="referrals.php?type=external&branch=<?= $selected_branch_id ?>" class="stat-card blue-4">
            <div>
                <div class="stat-label">External</div>
                <div class="stat-number"><?= $external ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-globe-africa"></i></div>
        </a>
        <a href="referrals.php?status=completed&branch=<?= $selected_branch_id ?>" class="stat-card blue-5">
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-number"><?= $completed ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        </a>
        <a href="referrals.php?status=accepted&branch=<?= $selected_branch_id ?>" class="stat-card blue-6">
            <div>
                <div class="stat-label">Accepted</div>
                <div class="stat-number"><?= $accepted ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <div style="flex:1;min-width:200px;">
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Search</label>
                <input type="text" name="search" placeholder="Patient, referral #, doctor..." value="<?= htmlspecialchars($search) ?>" style="width:100%;">
            </div>
            
            <div>
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Type</label>
                <select name="type">
                    <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="internal" <?= $type_filter === 'internal' ? 'selected' : '' ?>>Internal</option>
                    <option value="external" <?= $type_filter === 'external' ? 'selected' : '' ?>>External</option>
                </select>
            </div>
            
            <div>
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Status</label>
                <select name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="referred" <?= $status_filter === 'referred' ? 'selected' : '' ?>>Referred</option>
                    <option value="accepted" <?= $status_filter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="referrals.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--text-primary);">
            <i class="fas fa-list" style="color:#0B5ED7;"></i> All Referrals
            <span style="font-weight:400;color:var(--text-secondary);font-size:0.85rem;">(<?= $total ?> records)</span>
        </h3>
        
        <?php if ($total > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Referral #</th>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>From Doctor</th>
                            <th>To (Doctor/Hospital)</th>
                            <th>Visit #</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align:center;width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($referrals as $ref): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span style="font-family:monospace;font-size:0.75rem;font-weight:600;color:#0B5ED7;">
                                        <?= htmlspecialchars($ref['referral_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($ref['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);"><?= htmlspecialchars($ref['patient_code'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if (($ref['referral_type'] ?? '') === 'internal'): ?>
                                        <span class="badge badge-blue-1"><i class="fas fa-hospital"></i> Internal</span>
                                    <?php else: ?>
                                        <span class="badge badge-blue-3"><i class="fas fa-globe-africa"></i> External</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:500;">Dr. <?= htmlspecialchars($ref['from_doctor_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);"><?= htmlspecialchars($ref['from_doctor_specialty'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if (($ref['referral_type'] ?? '') === 'external'): ?>
                                        <div style="font-weight:500;">🏥 <?= htmlspecialchars($ref['to_hospital_name'] ?? 'N/A') ?></div>
                                    <?php else: ?>
                                        <div style="font-weight:500;">Dr. <?= htmlspecialchars($ref['to_doctor_name'] ?? 'N/A') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($ref['visit_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $status_class = 'badge-blue-2';
                                        $status_label = ucfirst($ref['status'] ?? 'pending');
                                        if (($ref['status'] ?? '') === 'accepted') $status_class = 'badge-blue-1';
                                        elseif (($ref['status'] ?? '') === 'completed') $status_class = 'badge-blue-3';
                                        elseif (($ref['status'] ?? '') === 'cancelled') $status_class = 'badge-blue-6';
                                        elseif (($ref['status'] ?? '') === 'referred') $status_class = 'badge-blue-5';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= $status_label ?></span>
                                </td>
                                <td>
                                    <div style="font-size:0.75rem;font-weight:500;"><?= date('M d, Y', strtotime($ref['created_at'] ?? 'now')) ?></div>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);"><?= date('h:i A', strtotime($ref['created_at'] ?? 'now')) ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_referral.php?id=<?= $ref['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_referral.php?id=<?= $ref['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (($ref['referral_type'] ?? '') === 'external'): ?>
                                            <a href="../doctor/refer_patient_pdf.php?patient_id=<?= $ref['patient_id'] ?>" 
                                               target="_blank"
                                               class="btn-pdf" 
                                               title="Print PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $ref['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-delete" 
                                           title="Delete"
                                           onclick="return confirm('⚠️ Delete this referral?\n\nReferral #: <?= htmlspecialchars(addslashes($ref['referral_number'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($ref['patient_name'] ?? 'N/A')) ?>\n\nThis action cannot be undone!');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-share-square"></i>
                <p style="font-size:1rem;font-weight:500;">No referrals found</p>
                <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Try changing filters or create a new referral</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Referrals Management
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c📋 Admin - Referrals (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Dark mode inafanya kazi kwa page YOTE', 'font-size:12px;color:#34D399;');
console.log('%c✅ Blue theme everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ View, Edit, Delete, PDF buttons included', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>