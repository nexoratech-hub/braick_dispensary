<?php
// ================================================================
// FILE: frontend/pages/admin/doctors_list.php
// SUPER ADMIN - ALL DOCTORS LIST
// BRAICK DISPENSARY
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET DOCTORS LIST
// ================================================================
if ($selected_branch_id === 'all') {
    $stmt = $db->query("
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'doctor' AND u.status = 'active'
        ORDER BY u.full_name
    ");
} else {
    $stmt = $db->prepare("
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'doctor' AND u.status = 'active' AND u.branch_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([(int)$selected_branch_id]);
}
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_doctors = count($doctors);
$online_doctors = 0;
foreach ($doctors as $doc) {
    if ($doc['is_online'] ?? 0) $online_doctors++;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// SIDEBAR STATISTICS
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       DOCTORS LIST - STYLES
       ================================================================ */
    
    .doctor-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .doctor-card:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .doctor-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .doctor-card .doctor-info {
        flex: 1;
    }
    
    .doctor-card .doctor-name {
        font-weight: 600;
        font-size: 1.05rem;
        color: var(--text-primary);
    }
    
    .doctor-card .doctor-specialty {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .doctor-card .doctor-branch {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    
    .doctor-card .doctor-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 4px;
    }
    
    .doctor-card .doctor-meta span {
        font-size: 0.7rem;
        color: var(--text-secondary);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--bg-body);
        padding: 2px 10px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }
    
    .online-dot-small {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 4px;
    }
    
    .online-dot-small.online {
        background: #059669;
        animation: pulse-dot 1.5s infinite;
    }
    
    .online-dot-small.offline {
        background: #94A3B8;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .stats-header {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .stats-header .stat-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
    }
    
    .stats-header .stat-box .number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stats-header .stat-box .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .grid-doctors {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    @media (min-width: 768px) {
        .grid-doctors {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 640px) {
        .doctor-card {
            flex-direction: column;
            text-align: center;
        }
        .doctor-card .doctor-meta {
            justify-content: center;
        }
        .stats-header {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent; width:auto;">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-white ml-3 opacity-60"></i>
            <input type="text" id="searchInput" placeholder="Search doctors...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2236%22 height=%2236%22%3E%3Crect width=%2236%22 height=%2236%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2218%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md mr-2" style="color: #0B5ED7;"></i> All Doctors
            </h1>
            <p class="page-subtitle">
                Select a doctor to view their dashboard
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-users mr-1"></i> <?= $total_doctors ?> doctors
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-circle text-[6px] text-green-500 mr-1"></i> <?= $online_doctors ?> online
                </span>
            </p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-header">
        <div class="stat-box">
            <p class="number"><?= $total_doctors ?></p>
            <p class="label">Total Doctors</p>
        </div>
        <div class="stat-box">
            <p class="number"><?= $online_doctors ?></p>
            <p class="label">Online Now</p>
        </div>
        <div class="stat-box">
            <p class="number"><?= $total_doctors - $online_doctors ?></p>
            <p class="label">Offline</p>
        </div>
        <div class="stat-box">
            <p class="number"><?= $total_branches ?></p>
            <p class="label">Branches</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTORS GRID -->
    <!-- ================================================================ -->
    <div class="grid-doctors">
        <?php if (count($doctors) > 0): ?>
            <?php foreach ($doctors as $doctor): 
                // Get patient count
                $stmt_pat = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
                $stmt_pat->execute([$doctor['id']]);
                $patient_count = $stmt_pat->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            ?>
                <div class="doctor-card animate-fade-in-up">
                    <div class="doctor-avatar" style="background: <?= getUserColor($doctor['full_name']) ?>;">
                        <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
                    </div>
                    <div class="doctor-info">
                        <div class="doctor-name"><?= htmlspecialchars($doctor['full_name']) ?></div>
                        <div class="doctor-specialty">
                            <i class="fas fa-stethoscope mr-1"></i>
                            <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                        </div>
                        <div class="doctor-branch">
                            <i class="fas fa-store-alt mr-1"></i>
                            <?= htmlspecialchars($doctor['branch_name'] ?? 'Not Assigned') ?>
                        </div>
                        <div class="doctor-meta">
                            <span>
                                <span class="online-dot-small <?= ($doctor['is_online'] ?? 0) ? 'online' : 'offline' ?>"></span>
                                <?= ($doctor['is_online'] ?? 0) ? 'Online' : 'Offline' ?>
                            </span>
                            <span>
                                <i class="fas fa-users mr-1"></i>
                                <?= $patient_count ?> patients
                            </span>
                            <span>
                                <i class="fas fa-envelope mr-1"></i>
                                <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="view_doctor.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn btn-blue" title="View Dashboard">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="edit_user.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn btn-green btn-sm" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-2 text-center py-8" style="grid-column: 1 / -1;">
                <i class="fas fa-user-md text-4xl text-gray-300 block mb-3"></i>
                <p class="text-gray-400">No doctors found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-4">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Doctors List
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    console.log('%c👨‍⚕️ All Doctors List', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Doctors: <?= $total_doctors ?>', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= $online_doctors ?>', 'font-size:13px; color:#059669;');
</script>

<?php
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#F59E0B', '#0891B2', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}
?>

</body>
</html>