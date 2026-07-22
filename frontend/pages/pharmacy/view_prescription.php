<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_prescription.php
// PHARMACY - VIEW PRESCRIPTION DETAILS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.dodoma (ID: 9)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 9;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: prescriptions.php');
    exit;
}

$db = getDB();

try {
    // ================================================================
    // GET PRESCRIPTION DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, 
               pat.full_name as patient_name, pat.patient_id, pat.phone, pat.email,
               u.full_name as doctor_name, u.specialty,
               v.visit_number, v.visit_type, v.status as visit_status
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$prescription_id, $user_branch_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescription) {
        header('Location: prescriptions.php');
        exit;
    }

    // ================================================================
    // GET PRESCRIPTION ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ?
        ORDER BY id
    ");
    $stmt->execute([$prescription_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // GET BILL FOR THIS PRESCRIPTION
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM patient_bills 
        WHERE visit_id = ? AND patient_id = ?
    ");
    $stmt->execute([$prescription['visit_id'], $prescription['patient_id']]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    // ================================================================
    // UPDATE STATUS VIA POST
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $new_status = $_POST['status'] ?? 'pending';
        $valid_statuses = ['pending', 'dispensed', 'cancelled'];
        
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE prescriptions SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $prescription_id])) {
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, action, details) 
                        VALUES (?, 'prescription_status_updated', ?)
                    ");
                    $stmt->execute([
                        $user_id,
                        "Prescription #{$prescription['prescription_number']} status changed to $new_status"
                    ]);
                } catch (Exception $e) {}
                
                $message = "Prescription status updated to: " . ucfirst($new_status);
                $message_type = 'success';
                
                // Refresh prescription data
                $stmt = $db->prepare("
                    SELECT p.*, 
                           pat.full_name as patient_name, pat.patient_id,
                           u.full_name as doctor_name
                    FROM prescriptions p
                    JOIN patients pat ON p.patient_id = pat.id
                    JOIN users u ON p.doctor_id = u.id
                    WHERE p.id = ? AND p.branch_id = ?
                ");
                $stmt->execute([$prescription_id, $user_branch_id]);
                $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $prescription = null;
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<style>
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .detail-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
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
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.dispensed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.dispensed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
    
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
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-green {
        background: #059669;
        color: white;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-red {
        background: #DC2626;
        color: white;
    }
    .btn-red:hover {
        background: #B91C1C;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
    .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .items-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
    }
    .items-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    .items-table tr:hover td {
        background: var(--table-hover);
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
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    
    @media (max-width: 768px) {
        .detail-card { padding: 16px 18px; }
        .items-table { font-size: 0.75rem; }
        .items-table th, .items-table td { padding: 6px 8px; }
        .btn { padding: 5px 12px; font-size: 0.7rem; }
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
            <input type="text" id="searchInput" placeholder="Search prescriptions, patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($prescription): ?>
    
    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2" style="color: var(--primary);"></i> Prescription Details
            </h1>
            <p class="page-subtitle">
                View and manage prescription #<?= htmlspecialchars($prescription['prescription_number']) ?>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> ID: <?= $prescription['id'] ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($prescription['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="prescriptions.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($prescription['status'] === 'pending'): ?>
                <a href="dispense_prescription.php?id=<?= $prescription['id'] ?>" class="btn btn-green btn-sm">
                    <i class="fas fa-prescription"></i> Dispense
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message) && $message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="p-4 rounded-xl mb-4 bg-red-100 text-red-700 border border-red-200">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Prescription Info -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Prescription Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Prescription Number</p>
                    <p class="detail-value font-mono"><?= htmlspecialchars($prescription['prescription_number']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $prescription['status'] ?>">
                            <?= ucfirst($prescription['status']) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Visit</p>
                    <p class="detail-value font-mono"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value capitalize"><?= htmlspecialchars($prescription['visit_type'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="detail-label">Diagnosis</p>
                    <p class="detail-value"><?= nl2br(htmlspecialchars($prescription['diagnosis'] ?? 'N/A')) ?></p>
                </div>
                <div>
                    <p class="detail-label">Created At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($prescription['created_at'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Last Updated</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($prescription['updated_at'])) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Patient & Doctor Info -->
        <div class="detail-card">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                    <i class="fas fa-user text-primary mr-2"></i> Patient
                </h3>
                <div class="space-y-2">
                    <div>
                        <p class="detail-label">Name</p>
                        <p class="detail-value">
                            <a href="view_patient.php?id=<?= $prescription['patient_id'] ?>" class="text-primary hover:underline">
                                <?= htmlspecialchars($prescription['patient_name']) ?>
                            </a>
                        </p>
                    </div>
                    <div>
                        <p class="detail-label">Patient ID</p>
                        <p class="detail-value"><?= htmlspecialchars($prescription['patient_id'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Phone</p>
                        <p class="detail-value"><?= htmlspecialchars($prescription['phone'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Email</p>
                        <p class="detail-value"><?= htmlspecialchars($prescription['email'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="border-t pt-3">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                    <i class="fas fa-user-md text-primary mr-2"></i> Doctor
                </h3>
                <div class="space-y-2">
                    <div>
                        <p class="detail-label">Name</p>
                        <p class="detail-value">Dr. <?= htmlspecialchars($prescription['doctor_name']) ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Specialty</p>
                        <p class="detail-value"><?= htmlspecialchars($prescription['specialty'] ?? 'General Practitioner') ?></p>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION ITEMS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Prescription Items
                <span class="text-sm font-normal text-gray-400">(<?= count($items) ?> items)</span>
            </h3>
            <span class="text-sm text-gray-400">Total: <strong>TSh <?= number_format(array_sum(array_column($items, 'total_price')), 2) ?></strong></span>
        </div>
        
        <?php if (count($items) > 0): ?>
            <div class="overflow-x-auto">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Quantity</th>
                            <th>Duration</th>
                            <th>Route</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($item['medication_name']) ?></strong></td>
                                <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['quantity'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['route'] ?? 'N/A') ?></td>
                                <td class="text-right">TSh <?= number_format($item['unit_price'] ?? 0, 2) ?></td>
                                <td class="text-right font-semibold">TSh <?= number_format($item['total_price'] ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="8" class="text-right font-bold">Grand Total:</td>
                            <td class="text-right font-bold text-primary">
                                TSh <?= number_format(array_sum(array_column($items, 'total_price')), 2) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription text-2xl block mb-2"></i>
                <p>No items found in this prescription</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- STATUS UPDATE -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-edit text-primary mr-2"></i> Update Status
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="update_status" value="1">
            
            <div class="flex flex-wrap items-center gap-4">
                <select name="status" class="form-control" style="width:auto;min-width:180px;">
                    <option value="pending" <?= $prescription['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                    <option value="dispensed" <?= $prescription['status'] === 'dispensed' ? 'selected' : '' ?>>✅ Dispensed</option>
                    <option value="cancelled" <?= $prescription['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                </select>
                
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-save"></i> Update Status
                </button>
                
                <?php if ($prescription['status'] === 'pending'): ?>
                    <a href="dispense_prescription.php?id=<?= $prescription['id'] ?>" class="btn btn-green">
                        <i class="fas fa-prescription"></i> Dispense Now
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-prescription text-4xl block mb-3"></i>
            <p class="text-lg">Prescription not found</p>
            <p class="text-sm mt-1">The prescription ID you requested does not exist or you don't have permission to view it.</p>
            <a href="prescriptions.php" class="text-primary hover:underline mt-3 inline-block">Back to prescriptions</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Prescription
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
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
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
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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

    console.log('%c💊 Braick - View Prescription', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= ucfirst($prescription['status'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📦 Items: <?= count($items) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>