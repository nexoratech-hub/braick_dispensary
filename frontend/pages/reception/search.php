<?php
// ================================================================
// FILE: frontend/pages/reception/search.php
// RECEPTION - SEARCH PATIENTS (BRANCH FILTERED)
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
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

$results = [];
$total_results = 0;

if (!empty($query)) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT * FROM patients 
            WHERE branch_id = ? 
            AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)
            ORDER BY full_name
            LIMIT 50
        ");
        $search_term = "%$query%";
        $stmt->execute([$selected_branch_id, $search_term, $search_term, $search_term, $search_term]);
        $results = $stmt->fetchAll();
        $total_results = count($results);
        
    } catch (Exception $e) {
        $results = [];
        $total_results = 0;
    }
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .result-item {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .result-item:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    .result-item .name {
        font-weight: 600;
        font-size: 1rem;
        color: var(--text-primary);
    }
    .result-item .details {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    .result-item .details i {
        margin-right: 4px;
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
    .highlight {
        background: #FEF08A;
        padding: 1px 4px;
        border-radius: 3px;
        font-weight: 600;
    }
    [data-theme="dark"] .highlight {
        background: #3D2E0A;
        color: #FBBF24;
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
            <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone..." value="<?= htmlspecialchars($query) ?>">
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
                <i class="fas fa-search mr-2" style="color: var(--primary);"></i> Search Patients
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Search patients in <?= htmlspecialchars($branch_name) ?>
                <?php if (!empty($query)): ?>
                    <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                        <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($query) ?>"
                    </span>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-user mr-1"></i> <?= $total_results ?> patient(s) found
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
    <!-- SEARCH RESULTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Search Results
            </h3>
            <span class="text-sm text-gray-400"><?= $total_results ?> record(s)</span>
        </div>
        
        <?php if (!empty($query)): ?>
            
            <?php if ($total_results > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($results as $patient): 
                        $name = htmlspecialchars($patient['full_name']);
                        $highlighted_name = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<span class="highlight">$1</span>', $name);
                    ?>
                        <div class="result-item">
                            <div>
                                <p class="name"><?= $highlighted_name ?></p>
                                <p class="details">
                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-calendar-alt"></i> <?= isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A' ?>
                                </p>
                            </div>
                            <div class="flex gap-1">
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn btn-blue btn-sm" title="View Patient">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="new_appointment.php?patient_id=<?= $patient['id'] ?>" class="btn btn-green btn-sm" title="New Appointment">
                                    <i class="fas fa-calendar-plus"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-search text-4xl block mb-3"></i>
                    <p class="text-lg">No patients found matching "<strong><?= htmlspecialchars($query) ?></strong>"</p>
                    <p class="text-sm mt-1">Try searching by name, patient ID, phone number or email</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-search text-4xl block mb-3"></i>
                <p class="text-lg">Enter a search term to find patients</p>
                <p class="text-sm mt-1">Search by name, patient ID, phone number or email</p>
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
            Search Patients
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
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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

    console.log('%c🔍 Braick - Search Patients (Branch Filtered)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Results: <?= $total_results ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>