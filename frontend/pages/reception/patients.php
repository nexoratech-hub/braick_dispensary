<?php
// ================================================================
// FILE: frontend/pages/reception/patients.php
// RECEPTION - PATIENTS LIST (BRANCH FILTERED)
// FIXED: No redirect loop
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
$selected_branch_id = $_GET['branch'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ================================================================
// FORCE TO USER'S BRANCH IF NOT ADMIN
// ================================================================
$is_admin = ($_SESSION['role'] === 'admin');
if (!$is_admin) {
    $selected_branch_id = $user_branch_id;
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch = getBranch($selected_branch_id);
    if ($branch) {
        $branch_name = $branch['name'];
    }
} else {
    $selected_branch_id = 'all';
}

try {
    $db = getDB();
    
    // ================================================================
    // BUILD QUERY
    // ================================================================
    $query = "SELECT * FROM patients WHERE 1=1";
    $count_query = "SELECT COUNT(*) as total FROM patients WHERE 1=1";
    $params = [];
    
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $query .= " AND branch_id = ?";
        $count_query .= " AND branch_id = ?";
        $params[] = $selected_branch_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $count_query .= " AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // ================================================================
    // GET TOTAL COUNT
    // ================================================================
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_patients = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total_patients / $limit);
    
    // ================================================================
    // GET PAGINATED RESULTS
    // ================================================================
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
} catch (Exception $e) {
    $patients = [];
    $total_patients = 0;
    $total_pages = 0;
    $error_message = $e->getMessage();
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .patient-row td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .patient-row:hover td {
        background: var(--table-hover);
    }
    .patient-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.7rem;
        flex-shrink: 0;
    }
    .table-wrap { overflow-x: auto; }
    
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
        margin-top: 12px;
    }
    .pagination .page-link {
        padding: 5px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s;
    }
    .pagination .page-link:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .pagination .page-link.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .pagination .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
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
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: scale(1.05);
    }
    .btn-sm {
        padding: 3px 8px;
        font-size: 0.65rem;
        border-radius: 4px;
    }
    
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
        min-width: 700px;
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
    .page-header .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
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
    
    .filter-search {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 200px;
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        transition: all 0.3s;
        padding: 0 12px;
    }
    .filter-search:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    .filter-search .fa-search {
        color: var(--text-secondary);
        font-size: 0.85rem;
        opacity: 0.5;
    }
    .filter-input {
        border: none;
        background: transparent;
        padding: 8px 12px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    .filter-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        width: 100%;
    }
    
    @media (max-width: 768px) {
        .data-table {
            font-size: 0.7rem;
            min-width: 600px;
        }
        .data-table th,
        .data-table td {
            padding: 6px 8px;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.55rem;
        }
        .card {
            padding: 12px 14px;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-search {
            min-width: 100%;
        }
        .filter-group .btn {
            width: 100%;
            justify-content: center;
        }
        .pagination .page-link {
            padding: 3px 8px;
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 480px) {
        .data-table {
            font-size: 0.6rem;
            min-width: 500px;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
        }
        .patient-avatar-sm {
            width: 24px;
            height: 24px;
            font-size: 0.6rem;
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
            <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-users mr-2" style="color: var(--primary);"></i> Patients
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Manage all registered patients
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= $total_patients ?> patients
                </span>
                <?php if (!empty($search)): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($search) ?>"
                        <a href="patients.php?branch=<?= $selected_branch_id ?>" class="ml-2 text-yellow-600 hover:text-yellow-800">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="new_patient.php" class="btn btn-blue btn-sm">
                <i class="fas fa-user-plus"></i> Register Patient
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SEARCH -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <form method="GET" class="filter-group">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            <div class="filter-search">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="filter-input" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-blue">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="patients.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Patient List
            </h3>
            <span class="text-sm text-gray-400"><?= $total_patients ?> record(s)</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Patient</th>
                        <th>Patient ID</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Registered</th>
                        <th style="border-radius: 0 8px 0 0; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($patients) > 0): ?>
                        <?php $i = $offset + 1; foreach ($patients as $patient): ?>
                            <tr class="patient-row">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                                            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($patient['full_name']) ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A' ?></td>
                                <td>
                                    <div class="action-buttons" style="display:flex;gap:4px;justify-content:center;">
                                        <a href="view_patient.php?id=<?= $patient['id'] ?>" 
                                           class="btn btn-blue btn-sm" title="View Patient">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="new_appointment.php?patient_id=<?= $patient['id'] ?>" 
                                           class="btn btn-green btn-sm" title="New Appointment">
                                            <i class="fas fa-calendar-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-users text-3xl block mb-2"></i>
                                <?php if (!empty($search)): ?>
                                    No patients found matching "<strong><?= htmlspecialchars($search) ?></strong>"
                                <?php else: ?>
                                    No patients registered yet
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>" class="page-link">&laquo; Prev</a>
                <?php else: ?>
                    <span class="page-link disabled">&laquo; Prev</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>" class="page-link">Next &raquo;</a>
                <?php else: ?>
                    <span class="page-link disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5">
        <div class="card text-center">
            <p class="text-2xl font-bold text-primary"><?= $total_patients ?></p>
            <p class="text-sm text-gray-500">Total Patients</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-green-600"><?= count($patients) ?></p>
            <p class="text-sm text-gray-500">Showing</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-purple-600"><?= $total_pages ?></p>
            <p class="text-sm text-gray-500">Pages</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-orange-500"><?= date('M d, Y') ?></p>
            <p class="text-sm text-gray-500">Today</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patients List
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var branch = '<?= $selected_branch_id ?>';
        if (query.length > 0) {
            window.location.href = 'patients.php?search=' + encodeURIComponent(query) + '&branch=' + branch;
        }
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

    console.log('%c👥 Braick - Patients List', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= $total_patients ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Showing page <?= $page ?> of <?= $total_pages ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>