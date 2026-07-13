<?php
// ================================================================
// FILE: frontend/pages/reception/online_doctors.php
// RECEPTION - ONLINE DOCTORS LIST (BRANCH FILTERED)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$search = $_GET['search'] ?? '';

try {
    $db = getDB();
    
    // Build query - only online doctors in this branch
    $query = "
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'doctor' 
        AND u.status = 'active' 
        AND u.branch_id = ?
        ORDER BY u.is_online DESC, u.full_name
    ";
    $params = [$selected_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.specialty LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
    
    // Count online doctors
    $online_count = 0;
    foreach ($doctors as $doc) {
        if ($doc['is_online'] ?? 0) $online_count++;
    }
    
} catch (Exception $e) {
    $doctors = [];
    $online_count = 0;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .doctor-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 12px;
    }
    .doctor-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .doctor-card.online {
        border-left: 4px solid #059669;
    }
    .doctor-card.offline {
        border-left: 4px solid #94A3B8;
        opacity: 0.7;
    }
    .doctor-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
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
        font-size: 1rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .doctor-card .doctor-specialty {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    .doctor-card .doctor-branch {
        font-size: 0.7rem;
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
    .online-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }
    .online-dot.online {
        background: #059669;
        animation: pulse-dot 1.5s infinite;
    }
    .online-dot.offline {
        background: #94A3B8;
    }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .status-badge {
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .status-badge.online {
        background: #D1FAE5;
        color: #059669;
    }
    .status-badge.offline {
        background: #F1F5F9;
        color: #94A3B8;
    }
    [data-theme="dark"] .status-badge.online {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .status-badge.offline {
        background: #1E293B;
        color: #64748B;
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
    
    .stats-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .stats-box:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .stats-box .number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stats-box .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
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
            <input type="text" id="searchInput" placeholder="Search doctors by name, specialty or email..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-user-md mr-2" style="color: var(--primary);"></i> Online Doctors
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View all doctors in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-circle text-[6px] text-green-500 mr-1"></i> <?= $online_count ?> online
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-md mr-1"></i> <?= count($doctors) ?> total doctors
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="stats-box">
            <p class="number"><?= count($doctors) ?></p>
            <p class="label">Total Doctors</p>
        </div>
        <div class="stats-box">
            <p class="number" style="color: #059669;"><?= $online_count ?></p>
            <p class="label">Online Now</p>
        </div>
        <div class="stats-box">
            <p class="number" style="color: #94A3B8;"><?= count($doctors) - $online_count ?></p>
            <p class="label">Offline</p>
        </div>
        <div class="stats-box">
            <p class="number" style="color: #D97706;">0</p>
            <p class="label">Busy</p>
        </div>
    </div>

    <!-- Search Results Info -->
    <?php if (!empty($search)): ?>
        <div class="mb-4">
            <span class="inline-flex bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs border border-yellow-200">
                <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($search) ?>"
                <a href="online_doctors.php" class="ml-2 text-yellow-600 hover:text-yellow-800">
                    <i class="fas fa-times"></i>
                </a>
            </span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- DOCTORS LIST -->
    <!-- ================================================================ -->
    <?php if (count($doctors) > 0): ?>
        <?php foreach ($doctors as $doctor): 
            $is_online = $doctor['is_online'] ?? 0;
            $color = getUserColor($doctor['full_name']);
        ?>
            <div class="doctor-card <?= $is_online ? 'online' : 'offline' ?>">
                <div class="doctor-avatar" style="background: <?= $color ?>;">
                    <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
                </div>
                <div class="doctor-info">
                    <div class="doctor-name">
                        <?= htmlspecialchars($doctor['full_name']) ?>
                        <span class="status-badge <?= $is_online ? 'online' : 'offline' ?>">
                            <?php if ($is_online): ?>
                                <span class="online-dot online" style="width:6px;height:6px;display:inline-block;"></span>
                            <?php else: ?>
                                <span class="online-dot offline" style="width:6px;height:6px;display:inline-block;"></span>
                            <?php endif; ?>
                            <?= $is_online ? 'Online' : 'Offline' ?>
                        </span>
                    </div>
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
                            <i class="fas fa-envelope mr-1"></i>
                            <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                        </span>
                        <?php if (!empty($doctor['phone'])): ?>
                            <span>
                                <i class="fas fa-phone mr-1"></i>
                                <?= htmlspecialchars($doctor['phone']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($doctor['last_online'])): ?>
                            <span>
                                <i class="fas fa-clock mr-1"></i>
                                Last seen: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <?php if ($is_online): ?>
                        <a href="assign_doctor.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-green btn-sm" title="Assign this doctor">
                            <i class="fas fa-user-md"></i> Assign
                        </a>
                    <?php endif; ?>
                    <a href="view_doctor.php?id=<?= $doctor['id'] ?>" class="btn btn-blue btn-sm" title="View Doctor Profile">
                        <i class="fas fa-eye"></i> View
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card text-center py-8">
            <i class="fas fa-user-md text-4xl text-gray-300 block mb-3"></i>
            <?php if (!empty($search)): ?>
                <p class="text-gray-400">No doctors found matching "<strong><?= htmlspecialchars($search) ?></strong>"</p>
            <?php else: ?>
                <p class="text-gray-400">No doctors available in <?= htmlspecialchars($branch_name) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Online Doctors
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
            window.location.href = 'online_doctors.php?search=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    // ================================================================
    // HELPER: Get user color
    // ================================================================
    function getUserColor(name) {
        var colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
        var index = 0;
        for (var i = 0; i < name.length; i++) {
            index = (index + name.charCodeAt(i)) % colors.length;
        }
        return colors[index];
    }

    console.log('%c👨‍⚕️ Braick - Online Doctors', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= $online_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Total: <?= count($doctors) ?>', 'font-size:13px; color:#64748B;');
</script>

<?php
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}
?>

</body>
</html>