<?php
// ================================================================
// FILE: frontend/pages/admin/appointments.php
// SUPER ADMIN - APPOINTMENTS MANAGEMENT
// ✅ BLUE THEME ONLY
// ✅ View, Edit, Delete buttons
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
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
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DELETE APPOINTMENT
// ================================================================
$message = '';
$message_type = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $appt_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$appt_id]);
        $message = "✅ Appointment deleted successfully!";
        $message_type = 'success';
        
        try {
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'appointment_deleted', ?, NOW())");
            $log_stmt->execute([$user_id, $user_branch_id, "Deleted appointment #$appt_id"]);
        } catch (Exception $e) {}
    } catch (Exception $e) {
        $message = "❌ Failed to delete: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// GET FILTERS
// ================================================================
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// ================================================================
// FETCH APPOINTMENTS
// ================================================================
$sql = "
    SELECT 
        a.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        b.name as branch_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    LEFT JOIN branches b ON a.branch_id = b.id
    WHERE 1=1
";
$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND a.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY a.appointment_date DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STATS
$total = count($appointments);
$scheduled = 0; $confirmed = 0; $completed = 0; $cancelled = 0;
foreach ($appointments as $a) {
    switch ($a['status'] ?? 'scheduled') {
        case 'scheduled': $scheduled++; break;
        case 'confirmed': $confirmed++; break;
        case 'completed': $completed++; break;
        case 'cancelled': $cancelled++; break;
    }
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
   BLUE THEME ONLY
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
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
}

/* ================================================================
   PAGE HEADER - BLUE GRADIENT
   ================================================================ */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 28px;
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
}

/* ================================================================
   STATS GRID - ALL BLUE VARIANTS
   ================================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    border-radius: 12px;
    padding: 16px 20px;
    color: white;
    min-height: 90px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* ALL BLUE VARIANTS */
.stat-card.blue-1 { background: #0B5ED7; }
.stat-card.blue-2 { background: #0A4CA8; }
.stat-card.blue-3 { background: #1A73E8; }
.stat-card.blue-4 { background: #0B3D8A; }
.stat-card.blue-5 { background: #1E40AF; }

.stat-card .stat-number {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-card .stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
    font-weight: 500;
}

.stat-card .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
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
}

.card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
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

.filter-form input, .filter-form select {
    padding: 8px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
}

.filter-form input:focus, .filter-form select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
}

/* ================================================================
   BUTTONS - BLUE VARIANTS
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

.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }

.btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }

.btn-view { background: #0B5ED7; color: white; padding: 5px 12px; font-size: 0.75rem; }
.btn-view:hover { background: #0A4CA8; transform: scale(1.05); }

.btn-edit { background: #1A73E8; color: white; padding: 5px 12px; font-size: 0.75rem; }
.btn-edit:hover { background: #0B5ED7; transform: scale(1.05); }

.btn-delete { background: #DC2626; color: white; padding: 5px 12px; font-size: 0.75rem; }
.btn-delete:hover { background: #B91C1C; transform: scale(1.05); }

.btn-sm { padding: 5px 12px; font-size: 0.75rem; }

/* ================================================================
   TABLE - BLUE HEADER
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
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.data-table thead th:first-child { border-radius: 10px 0 0 0; }
.data-table thead th:last-child { border-radius: 0 10px 0 0; }

.data-table tbody tr:nth-child(even) { background: #E8F0FE; }
[data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E3A5F; }
.data-table tbody tr:hover { background: #D1FAE5; }
[data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }

.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

/* ================================================================
   BADGES - BLUE VARIANTS
   ================================================================ */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 12px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-blue-1 { background: #E8F0FE; color: #0B5ED7; }
.badge-blue-2 { background: #DBEAFE; color: #0A4CA8; }
.badge-blue-3 { background: #E0F2FE; color: #0B3D8A; }
.badge-blue-4 { background: #EDE9FE; color: #1A73E8; }
.badge-blue-5 { background: #C7D2FE; color: #1E40AF; }

[data-theme="dark"] .badge-blue-1 { background: #1E3A5F; color: #6EA8FE; }
[data-theme="dark"] .badge-blue-2 { background: #1E40AF; color: #DBEAFE; }
[data-theme="dark"] .badge-blue-3 { background: #0C2A3A; color: #93C5FD; }
[data-theme="dark"] .badge-blue-4 { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .badge-blue-5 { background: #1E3A5F; color: #C7D2FE; }

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

.message-box.success { background: #D1FAE5; color: #065F46; border: 2px solid #6EE7B7; }
.message-box.error { background: #FEE2E2; color: #991B1B; border: 2px solid #FCA5A5; }

[data-theme="dark"] .message-box.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
[data-theme="dark"] .message-box.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

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
   RESPONSIVE
   ================================================================ */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .action-buttons { flex-wrap: wrap; }
    .data-table { font-size: 0.7rem; }
    .data-table th, .data-table td { padding: 6px 8px; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER - BLUE -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i> Appointments Management
                <span class="badge-branch"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?></span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                Manage all appointments across branches
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="add_appointment.php?branch=<?= $selected_branch_id ?>" class="btn" style="background:white;color:#0B5ED7;font-weight:700;padding:10px 24px;">
                <i class="fas fa-plus-circle"></i> New Appointment
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
        <div class="stat-card blue-1">
            <div>
                <div class="stat-label">Total</div>
                <div class="stat-number"><?= $total ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="stat-card blue-2">
            <div>
                <div class="stat-label">Scheduled</div>
                <div class="stat-number"><?= $scheduled ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card blue-3">
            <div>
                <div class="stat-label">Confirmed</div>
                <div class="stat-number"><?= $confirmed ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card blue-4">
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-number"><?= $completed ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        </div>
        <div class="stat-card blue-5">
            <div>
                <div class="stat-label">Cancelled</div>
                <div class="stat-number"><?= $cancelled ?></div>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <div style="flex:1;min-width:200px;">
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Search</label>
                <input type="text" name="search" placeholder="Patient name, ID, doctor..." value="<?= htmlspecialchars($search) ?>" style="width:100%;">
            </div>
            
            <div>
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Status</label>
                <select name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div>
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="appointments.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--text-primary);">
            <i class="fas fa-list" style="color:#0B5ED7;"></i> All Appointments 
            <span style="font-weight:400;color:var(--text-secondary);font-size:0.85rem;">(<?= $total ?> records)</span>
        </h3>
        
        <?php if ($total > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th style="text-align:center;width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($appointments as $appt): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($appt['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);"><?= htmlspecialchars($appt['patient_code'] ?? '') ?></div>
                                    <?php if (!empty($appt['patient_phone'])): ?>
                                        <div style="font-size:0.7rem;color:var(--text-secondary);">📞 <?= htmlspecialchars($appt['patient_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:500;">Dr. <?= htmlspecialchars($appt['doctor_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);"><?= htmlspecialchars($appt['doctor_specialty'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600;"><?= date('M d, Y', strtotime($appt['appointment_date'] ?? 'now')) ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-secondary);"><?= date('h:i A', strtotime($appt['appointment_date'] ?? 'now')) ?></div>
                                </td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars(substr($appt['purpose'] ?? 'N/A', 0, 40)) ?><?= strlen($appt['purpose'] ?? '') > 40 ? '...' : '' ?></td>
                                <td>
                                    <span class="badge badge-blue-1">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($appt['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $status_class = 'badge-blue-2'; // Scheduled = dark blue
                                        if (($appt['status'] ?? '') === 'confirmed') $status_class = 'badge-blue-1'; // Confirmed = bright blue
                                        elseif (($appt['status'] ?? '') === 'completed') $status_class = 'badge-blue-3'; // Completed = deep blue
                                        elseif (($appt['status'] ?? '') === 'cancelled') $status_class = 'badge-blue-5'; // Cancelled = gray blue
                                    ?>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($appt['status'] ?? 'scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_appointment.php?id=<?= $appt['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_appointment.php?id=<?= $appt['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?= $appt['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-delete" 
                                           title="Delete"
                                           onclick="return confirm('⚠️ Delete this appointment?\n\nPatient: <?= htmlspecialchars(addslashes($appt['patient_name'] ?? 'N/A')) ?>\nDate: <?= date('M d, Y', strtotime($appt['appointment_date'] ?? 'now')) ?>\n\nThis action cannot be undone!');">
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
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fas fa-calendar-check" style="font-size:3rem;color:var(--border-color);display:block;margin-bottom:12px;"></i>
                <p style="font-size:1rem;font-weight:500;">No appointments found</p>
                <p style="font-size:0.85rem;">Try changing filters or create a new appointment</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Appointments Management
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

console.log('%c📅 Admin - Appointments (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Blue theme applied everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ View, Edit, Delete buttons included', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>