<?php
// ================================================================
// FILE: frontend/pages/reception/appointments.php
// RECEPTION - APPOINTMENTS LIST (BRANCH FILTERED)
// WITH VIEW BUTTON ONLY
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
$_SESSION['user_id'] = 6;
$_SESSION['full_name'] = 'Rose Mwangi';
$_SESSION['role'] = 'reception';
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Dodoma';
$_SESSION['username'] = 'reception.rose';
$_SESSION['is_admin'] = false;

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id; // Force to user's branch
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

try {
    $db = getDB();
    
    // Build query with branch filter
    $query = "
        SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ?
    ";
    $params = [$selected_branch_id];
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $query .= " AND DATE(a.appointment_date) = ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY a.appointment_date";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Status counts for this branch
    $status_counts = [];
    $statuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled'];
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE status = ? AND branch_id = ?");
        $stmt->execute([$status, $selected_branch_id]);
        $status_counts[$status] = $stmt->fetch()['total'] ?? 0;
    }
    
} catch (Exception $e) {
    $appointments = [];
    $status_counts = [];
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .appointment-row td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .appointment-row:hover td {
        background: var(--table-hover);
    }
    .filter-btn {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    .filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    .filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .table-wrap { overflow-x: auto; }
    .role-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
    }
    
    /* ===== BUTTONS ===== */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    .btn-sm {
        padding: 3px 8px;
        font-size: 0.65rem;
        border-radius: 4px;
    }
    
    .badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    .badge-green { background: #059669; color: white; }
    .badge-yellow { background: #D97706; color: white; }
    .badge-red { background: #DC2626; color: white; }
    .badge-blue { background: #0B5ED7; color: white; }
    .badge-gray { background: #94A3B8; color: white; }
    
    .card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s;
    }
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .card-title .title-blue { color: #0B5ED7; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
        white-space: nowrap;
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .page-header {
        border-bottom: 3px solid #0B5ED7;
        padding-bottom: 12px;
    }
    .page-header .page-title {
        color: #0B3D8A;
        font-size: 1.6rem;
        font-weight: 700;
    }
    [data-theme="dark"] .page-header .page-title {
        color: #6EA8FE;
    }
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .form-control {
        padding: 4px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s;
    }
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 4px;
        justify-content: center;
    }
    
    @media (max-width: 768px) {
        .data-table {
            font-size: 0.7rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 8px;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.55rem;
        }
        .action-buttons .btn {
            flex: 1;
            justify-content: center;
        }
        .filter-btn {
            font-size: 0.6rem;
            padding: 3px 8px;
        }
        .card {
            padding: 12px 14px;
        }
        .page-header .page-title {
            font-size: 1.2rem;
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
            <input type="text" id="searchInput" placeholder="Search appointments..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-check mr-2" style="color: var(--primary);"></i> Appointments
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Manage all patient appointments in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y', strtotime($date_filter)) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="new_appointment.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus-circle"></i> New Appointment
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-600 mr-2">Status:</span>
            <a href="appointments.php?date=<?= $date_filter ?>" 
               class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">All (<?= array_sum($status_counts) ?>)</a>
            <?php foreach ($status_counts as $status => $count): ?>
                <a href="appointments.php?status=<?= $status ?>&date=<?= $date_filter ?>" 
                   class="filter-btn <?= $status_filter === $status ? 'active' : '' ?>">
                    <?= ucfirst($status) ?> (<?= $count ?>)
                </a>
            <?php endforeach; ?>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>" 
                   onchange="window.location.href='appointments.php?date='+this.value+'&status=<?= $status_filter ?>'"
                   class="form-control" style="width:auto;">
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Appointments List
            </h3>
            <span class="text-sm text-gray-400"><?= count($appointments) ?> record(s)</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php $i = 1; foreach ($appointments as $appt): ?>
                            <tr class="appointment-row">
                                <td><?= $i++ ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($appt['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                                <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                                <td><?= htmlspecialchars($appt['purpose'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $appt['status'] === 'confirmed' || $appt['status'] === 'completed' ? 'badge-green' : ($appt['status'] === 'cancelled' ? 'badge-red' : 'badge-yellow') ?>">
                                        <?= ucfirst($appt['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- VIEW BUTTON ONLY -->
                                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" 
                                           class="btn btn-blue btn-sm" title="View Appointment">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-calendar-check text-3xl block mb-2"></i>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    No appointments found matching the filters in <?= htmlspecialchars($branch_name) ?>
                                <?php else: ?>
                                    No appointments scheduled for this date in <?= htmlspecialchars($branch_name) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5">
        <?php foreach ($status_counts as $status => $count): ?>
            <div class="card text-center <?= $status === 'completed' ? 'border-green-500' : ($status === 'cancelled' ? 'border-red-500' : '') ?>">
                <p class="text-2xl font-bold <?= $status === 'completed' ? 'text-green-600' : ($status === 'cancelled' ? 'text-red-500' : 'text-blue-600') ?>">
                    <?= $count ?>
                </p>
                <p class="text-sm text-gray-500 capitalize"><?= ucfirst($status) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Appointments
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // SEARCH - Filtered by branch
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var status = '<?= $status_filter ?>';
        var date = '<?= $date_filter ?>';
        window.location.href = 'appointments.php?search=' + encodeURIComponent(query) + '&status=' + status + '&date=' + date;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c📅 Braick - Appointments List', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Appointments: <?= count($appointments) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Actions: View only', 'font-size:13px; color:#059669;');
</script>

</body>
</html>