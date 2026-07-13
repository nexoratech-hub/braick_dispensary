<?php
// ================================================================
// FILE: frontend/pages/reception/patients.php
// RECEPTION - PATIENTS LIST (BRANCH FILTERED)
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
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $db = getDB();
    
    // Build query with branch filter
    $query = "SELECT * FROM patients WHERE branch_id = ?";
    $count_query = "SELECT COUNT(*) as total FROM patients WHERE branch_id = ?";
    $params = [$selected_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $count_query .= " AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_patients = $stmt->fetch()['total'] ?? 0;
    
    // Get paginated results
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
    
    $total_pages = ceil($total_patients / $limit);
    
} catch (Exception $e) {
    $patients = [];
    $total_patients = 0;
    $total_pages = 0;
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
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
        margin-top: 12px;
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
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name ?? 'Dodoma') ?>
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
                Manage all registered patients in <?= htmlspecialchars($branch_name ?? 'Dodoma') ?>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= $total_patients ?> patients
                </span>
                <?php if (!empty($search)): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($search) ?>"
                        <a href="patients.php" class="ml-2 text-yellow-600 hover:text-yellow-800">
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
                        <th>Patient ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Registered</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($patients) > 0): ?>
                        <?php $i = $offset + 1; foreach ($patients as $patient): ?>
                            <tr class="patient-row">
                                <td><?= $i++ ?></td>
                                <td><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name']) ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= isset($patient['created_at']) ? time_ago($patient['created_at']) : 'N/A' ?></td>
                                <td>
                                    <div class="flex gap-1">
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
                                    No patients registered in <?= htmlspecialchars($branch_name ?? 'Dodoma') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="page-link">&laquo; Prev</a>
                <?php else: ?>
                    <span class="page-link disabled">&laquo; Prev</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="page-link">Next &raquo;</a>
                <?php else: ?>
                    <span class="page-link disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
    // SEARCH - Filtered by branch
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        window.location.href = 'patients.php?search=' + encodeURIComponent(query);
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

    console.log('%c👥 Braick - Patients List (Branch Filtered)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name ?? 'Dodoma') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= $total_patients ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>