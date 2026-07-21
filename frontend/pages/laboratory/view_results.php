<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_results.php
// LABORATORY - VIEW RESULTS FOR A SPECIFIC REQUEST
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE LAB.DODOMA (ID: 8) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET REQUEST ID
// ================================================================
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($request_id <= 0) {
    header('Location: in_progress.php');
    exit;
}

// ================================================================
// GET REQUEST DETAILS
// ================================================================
$query = "
    SELECT lr.*, 
           p.full_name as patient_name, p.patient_id, p.phone, p.email,
           COALESCE(u.full_name, 'Not Assigned') as doctor_name,
           u.specialty,
           v.visit_number,
           b.name as branch_name,
           lab.full_name as lab_technician_name
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN branches b ON lr.branch_id = b.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.id = ? AND lr.branch_id = ?
";

$stmt = $db->prepare($query);
$stmt->execute([$request_id, $user_branch_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: in_progress.php');
    exit;
}

// ================================================================
// GET REQUEST ITEMS WITH RESULTS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM lab_request_items 
    WHERE request_id = ? 
    ORDER BY id
");
$stmt->execute([$request_id]);
$test_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<style>
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .detail-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
    }
    .detail-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .detail-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .status-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .result-item {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    .result-item:hover {
        border-color: var(--primary);
    }
    .result-item.completed {
        border-color: #059669;
        background: rgba(5, 150, 105, 0.05);
    }
    .result-item.in_progress {
        border-color: #0B5ED7;
        background: rgba(11, 94, 215, 0.05);
    }
    .result-item.pending {
        border-color: #D97706;
        background: rgba(217, 119, 6, 0.05);
    }
    
    .result-status-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .result-status-badge.pending { background: #FEF3C7; color: #D97706; }
    .result-status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .result-status-badge.completed { background: #D1FAE5; color: #059669; }
    .result-status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .result-display {
        background: var(--bg-card);
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        font-family: monospace;
        font-size: 0.85rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        margin-top: 8px;
    }
    .result-display.empty {
        color: var(--text-secondary);
        font-style: italic;
        font-family: inherit;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    .btn-green { background: #059669; color: white; }
    .btn-green:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
    .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .print-button {
        background: #0B5ED7;
        color: white;
        padding: 8px 20px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    .print-button:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .print-button, .no-print { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .detail-card, .result-item { border: 1px solid #ddd !important; box-shadow: none !important; }
        .status-badge, .result-status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($request): ?>
    
    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2" style="color: var(--primary);"></i> Lab Results
            </h1>
            <p class="page-subtitle">
                <span class="font-mono font-semibold text-blue-600"><?= htmlspecialchars($request['request_number']) ?></span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($request['patient_name']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-flask mr-1"></i> <?= count($test_items) ?> tests
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <a href="in_progress.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="print-button btn-sm">
                <i class="fas fa-print"></i> Print Results
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REQUEST OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Request Information
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <p class="detail-label">Request Number</p>
                    <p class="detail-value font-mono text-sm"><?= htmlspecialchars($request['request_number']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $request['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Doctor</p>
                    <p class="detail-value"><?= htmlspecialchars($request['doctor_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Requested</p>
                    <p class="detail-value"><?= date('M d, Y h:i A', strtotime($request['requested_at'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($request['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Tests</p>
                    <p class="detail-value"><?= count($test_items) ?></p>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient Information
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value"><?= htmlspecialchars($request['patient_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($request['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($request['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($request['email'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULTS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-flask text-purple-600 mr-2"></i> Test Results
        </h3>
        
        <?php if (count($test_items) > 0): ?>
            <?php foreach ($test_items as $index => $item): 
                $status = $item['status'] ?? 'pending';
                $has_result = !empty($item['result']);
            ?>
                <div class="result-item <?= $status ?>">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-semibold">#<?= $index + 1 ?></span>
                                <span class="font-medium"><?= htmlspecialchars($item['test_name']) ?></span>
                                <span class="result-status-badge <?= $status ?>">
                                    <?php if ($status === 'pending'): ?>
                                        ⏳ Pending
                                    <?php elseif ($status === 'in_progress'): ?>
                                        🔬 In Progress
                                    <?php elseif ($status === 'completed'): ?>
                                        ✅ Completed
                                    <?php else: ?>
                                        <?= ucfirst($status) ?>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($item['price']) && $item['price'] > 0): ?>
                                    <span class="text-xs text-gray-500">TSh <?= number_format($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['reference_range'])): ?>
                                <p class="text-xs text-gray-400 mt-1">
                                    Reference Range: <?= htmlspecialchars($item['reference_range']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?php if ($item['completed_at']): ?>
                                Completed: <?= date('M d, Y h:i A', strtotime($item['completed_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Result Display -->
                    <div class="result-display <?= $has_result ? '' : 'empty' ?>">
                        <?php if ($has_result): ?>
                            <?= nl2br(htmlspecialchars($item['result'])) ?>
                            <?php if (!empty($item['comments'])): ?>
                                <div class="text-xs text-gray-400 mt-2">
                                    <strong>Notes:</strong> <?= htmlspecialchars($item['comments']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            No result available yet
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-flask text-3xl block mb-2"></i>
                <p>No test items found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY SECTION -->
    <!-- ================================================================ -->
    <?php
    $total_tests = count($test_items);
    $completed_tests = 0;
    $pending_tests = 0;
    $in_progress_tests = 0;
    
    foreach ($test_items as $item) {
        $status = $item['status'] ?? 'pending';
        if ($status === 'completed') $completed_tests++;
        elseif ($status === 'in_progress') $in_progress_tests++;
        else $pending_tests++;
    }
    ?>
    
    <div class="detail-card mt-5">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-chart-pie text-blue-600 mr-2"></i> Summary
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                <p class="text-2xl font-bold text-green-600"><?= $completed_tests ?></p>
                <p class="text-xs text-gray-500">✅ Completed</p>
            </div>
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-2xl font-bold text-blue-600"><?= $in_progress_tests ?></p>
                <p class="text-xs text-gray-500">🔬 In Progress</p>
            </div>
            <div class="text-center p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <p class="text-2xl font-bold text-yellow-600"><?= $pending_tests ?></p>
                <p class="text-xs text-gray-500">⏳ Pending</p>
            </div>
            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                <p class="text-2xl font-bold text-purple-600"><?= $total_tests ?></p>
                <p class="text-xs text-gray-500">📊 Total Tests</p>
            </div>
        </div>
        
        <?php if ($request['status'] === 'completed' && $completed_tests === $total_tests && $total_tests > 0): ?>
            <div class="mt-4 p-3 bg-green-100 dark:bg-green-900/30 rounded-lg border border-green-300 dark:border-green-700 text-center">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                <span class="text-green-700 dark:text-green-400 font-medium">All tests are complete. Results have been sent to the doctor.</span>
            </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-flask text-4xl block mb-3"></i>
            <p class="text-lg">Request not found</p>
            <a href="in_progress.php" class="text-blue-600 hover:underline">Back to requests</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Results
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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

    console.log('%c🧪 Braick - View Results', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Request: <?= htmlspecialchars($request['request_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($request['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Tests: <?= count($test_items) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Completed: <?= $completed_tests ?? 0 ?> | 🔬 In Progress: <?= $in_progress_tests ?? 0 ?> | ⏳ Pending: <?= $pending_tests ?? 0 ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>