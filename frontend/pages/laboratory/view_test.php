<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_test.php
// LABORATORY - VIEW & UPDATE SINGLE TEST (WITH QUICK RESULTS)
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
// GET TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($test_id <= 0) {
    header('Location: pending_requests.php');
    exit;
}

// ================================================================
// HANDLE FORM SUBMISSION - Update test
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_test'])) {
    $status = $_POST['status'] ?? 'pending';
    $results = trim($_POST['results'] ?? '');
    $quick_result = $_POST['quick_result'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Combine quick result with custom result
    if (!empty($quick_result) && empty($results)) {
        $results = $quick_result;
    } elseif (!empty($quick_result) && !empty($results)) {
        $results = $quick_result . "\n\n" . $results;
    }
    
    $stmt = $db->prepare("
        UPDATE lab_tests 
        SET status = ?, results = ?, notes = ?,
            completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END,
            lab_technician_id = ?
        WHERE id = ? AND branch_id = ?
    ");
    
    if ($stmt->execute([$status, $results, $notes, $status, $user_id, $test_id, $user_branch_id])) {
        // If completed, create bill
        if ($status === 'completed') {
            createTestBill($db, $test_id, $user_id, $user_branch_id);
        }
        
        $_SESSION['success_message'] = 'Test updated successfully!';
        header('Location: view_test.php?id=' . $test_id);
        exit;
    } else {
        $_SESSION['error_message'] = 'Failed to update test!';
        header('Location: view_test.php?id=' . $test_id);
        exit;
    }
}

// ================================================================
// HANDLE ACTIONS (GET parameters)
// ================================================================
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$message_type = '';

if ($action === 'start') {
    $stmt = $db->prepare("
        UPDATE lab_tests 
        SET status = 'in_progress', lab_technician_id = ? 
        WHERE id = ? AND branch_id = ?
    ");
    if ($stmt->execute([$user_id, $test_id, $user_branch_id])) {
        $_SESSION['success_message'] = 'Test started successfully!';
        header('Location: view_test.php?id=' . $test_id);
        exit;
    }
} elseif ($action === 'complete') {
    $stmt = $db->prepare("
        UPDATE lab_tests 
        SET status = 'completed', completed_at = NOW(), lab_technician_id = ? 
        WHERE id = ? AND branch_id = ?
    ");
    if ($stmt->execute([$user_id, $test_id, $user_branch_id])) {
        // Create bill
        createTestBill($db, $test_id, $user_id, $user_branch_id);
        $_SESSION['success_message'] = 'Test completed successfully! Results sent to doctor.';
        header('Location: view_test.php?id=' . $test_id);
        exit;
    }
} elseif ($action === 'cancel') {
    $stmt = $db->prepare("
        UPDATE lab_tests 
        SET status = 'cancelled' 
        WHERE id = ? AND branch_id = ?
    ");
    if ($stmt->execute([$test_id, $user_branch_id])) {
        $_SESSION['success_message'] = 'Test cancelled!';
        header('Location: view_test.php?id=' . $test_id);
        exit;
    }
}

// ================================================================
// GET TEST DETAILS
// ================================================================
$query = "
    SELECT lt.*, 
           p.full_name as patient_name, p.patient_id, p.phone, p.email,
           COALESCE(u.full_name, 'Not Assigned') as doctor_name,
           u.specialty,
           v.visit_number,
           b.name as branch_name,
           lab.full_name as lab_technician_name
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.id = ? AND lt.branch_id = ?
";

$stmt = $db->prepare($query);
$stmt->execute([$test_id, $user_branch_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: pending_requests.php');
    exit;
}

// ================================================================
// FUNCTION TO CREATE BILL
// ================================================================
function createTestBill($db, $test_id, $user_id, $branch_id) {
    try {
        // Get test details
        $stmt = $db->prepare("
            SELECT lt.*, v.patient_id 
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            WHERE lt.id = ?
        ");
        $stmt->execute([$test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) return;
        
        $patient_id = $test['patient_id'];
        $visit_id = $test['visit_id'];
        $test_name = $test['test_name'];
        
        // Get test price from catalog
        $stmt = $db->prepare("
            SELECT price FROM lab_tests_catalog 
            WHERE test_name = ? 
            LIMIT 1
        ");
        $stmt->execute([$test_name]);
        $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
        $price = $catalog['price'] ?? 0;
        
        if ($price <= 0) return;
        
        // Check if bill exists
        $stmt = $db->prepare("
            SELECT id FROM patient_bills 
            WHERE patient_id = ? AND visit_id = ? AND status != 'paid'
        ");
        $stmt->execute([$patient_id, $visit_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill) {
            $bill_id = $bill['id'];
            
            // Check if already added
            $stmt = $db->prepare("
                SELECT id FROM bill_items 
                WHERE bill_id = ? AND item_name = ? AND item_type = 'lab_test'
            ");
            $stmt->execute([$bill_id, $test_name]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price, department)
                    VALUES (?, 'lab_test', ?, 1, ?, ?, 'Laboratory')
                ");
                $stmt->execute([$bill_id, $test_name, $price, $price]);
                
                // Update patient_bills
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = subtotal + ?,
                        total_amount = total_amount + ?,
                        balance = balance + ?
                    WHERE id = ?
                ");
                $stmt->execute([$price, $price, $price, $bill_id]);
            }
        } else {
            // Create new bill
            $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO patient_bills (
                    bill_number, patient_id, visit_id, subtotal, total_amount, balance,
                    status, created_by, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$bill_number, $patient_id, $visit_id, $price, $price, $price, $user_id, $branch_id]);
            $bill_id = $db->lastInsertId();
            
            // Add to bill_items
            $stmt = $db->prepare("
                INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price, department)
                VALUES (?, 'lab_test', ?, 1, ?, ?, 'Laboratory')
            ");
            $stmt->execute([$bill_id, $test_name, $price, $price]);
        }
        
    } catch (Exception $e) {
        error_log("Bill creation error: " . $e->getMessage());
    }
}

// ================================================================
// GET SESSION MESSAGES
// ================================================================
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']);
}

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
        font-size: 0.8rem;
        font-weight: 600;
        padding: 4px 16px;
        border-radius: 20px;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
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
    .btn-orange { background: #D97706; color: white; }
    .btn-orange:hover { background: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
    .btn-red { background: #DC2626; color: white; }
    .btn-red:hover { background: #B91C1C; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
    .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 16px 20px;
        background: var(--bg-body);
        border-radius: 12px;
        border: 2px solid var(--border-color);
    }
    
    .result-display {
        background: var(--bg-body);
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        font-family: monospace;
        font-size: 0.85rem;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .quick-result-btn {
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .quick-result-btn:hover {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }
    .quick-result-btn.active {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }
    
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
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    .quick-results-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($test): ?>
    
    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2" style="color: var(--primary);"></i> Test Details
            </h1>
            <p class="page-subtitle">
                <span class="font-medium"><?= htmlspecialchars($test['test_name']) ?></span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> ID: <?= $test['id'] ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($test['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="pending_requests.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="javascript:window.print()" class="btn btn-blue btn-sm">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUCCESS/ERROR MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200' : 'bg-red-100 text-red-700 border border-red-200') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- TEST DETAILS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Test Info -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Test Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Test Name</p>
                    <p class="detail-value"><?= htmlspecialchars($test['test_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $test['status'] ?? 'pending' ?>">
                            <?= ucfirst($test['status'] ?? 'Pending') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Test Type</p>
                    <p class="detail-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Sample Type</p>
                    <p class="detail-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Created At</p>
                    <p class="detail-value"><?= date('M d, Y h:i A', strtotime($test['created_at'])) ?></p>
                </div>
                <?php if ($test['completed_at']): ?>
                    <div>
                        <p class="detail-label">Completed At</p>
                        <p class="detail-value"><?= date('M d, Y h:i A', strtotime($test['completed_at'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($test['lab_technician_name']): ?>
                    <div>
                        <p class="detail-label">Lab Technician</p>
                        <p class="detail-value"><?= htmlspecialchars($test['lab_technician_name']) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($test['results']): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Results</p>
                        <div class="result-display"><?= nl2br(htmlspecialchars($test['results'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($test['notes']): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Notes</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($test['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient & Doctor Info -->
        <div class="detail-card">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-user text-primary mr-2"></i> Patient
                </h3>
                <div class="mt-2 space-y-2">
                    <div>
                        <p class="detail-label">Name</p>
                        <p class="detail-value"><?= htmlspecialchars($test['patient_name']) ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Patient ID</p>
                        <p class="detail-value"><?= htmlspecialchars($test['patient_id'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Phone</p>
                        <p class="detail-value"><?= htmlspecialchars($test['phone'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Email</p>
                        <p class="detail-value"><?= htmlspecialchars($test['email'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
            
            <hr class="border-gray-200 dark:border-gray-700 my-3">
            
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-user-md text-primary mr-2"></i> Doctor
                </h3>
                <div class="mt-2 space-y-2">
                    <div>
                        <p class="detail-label">Name</p>
                        <p class="detail-value"><?= htmlspecialchars($test['doctor_name']) ?></p>
                    </div>
                    <div>
                        <p class="detail-label">Specialty</p>
                        <p class="detail-value"><?= htmlspecialchars($test['specialty'] ?? 'General') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="quick-actions mb-5">
        <?php if (($test['status'] ?? 'pending') === 'pending' || $test['status'] === '' || $test['status'] === null): ?>
            <a href="view_test.php?id=<?= $test['id'] ?>&action=start" class="btn btn-orange">
                <i class="fas fa-play"></i> Start Test
            </a>
        <?php endif; ?>
        
        <?php if (($test['status'] ?? '') === 'in_progress'): ?>
            <a href="view_test.php?id=<?= $test['id'] ?>&action=complete" class="btn btn-green" onclick="return confirm('Complete this test? Results will be sent to the doctor.')">
                <i class="fas fa-check-circle"></i> Complete Test
            </a>
        <?php endif; ?>
        
        <?php if (($test['status'] ?? '') !== 'completed' && ($test['status'] ?? '') !== 'cancelled'): ?>
            <a href="view_test.php?id=<?= $test['id'] ?>&action=cancel" class="btn btn-red" onclick="return confirm('Cancel this test?')">
                <i class="fas fa-times"></i> Cancel Test
            </a>
        <?php endif; ?>
        
        <?php if (($test['status'] ?? '') === 'completed'): ?>
            <span class="btn btn-outline" style="border-color:#059669;color:#059669;">
                <i class="fas fa-check-circle"></i> Completed
            </span>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- UPDATE FORM -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-edit text-blue-600 mr-2"></i> Update Test
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="update_test" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="pending" <?= ($test['status'] ?? 'pending') === 'pending' || $test['status'] === '' || $test['status'] === null ? 'selected' : '' ?>>⏳ Pending</option>
                        <option value="in_progress" <?= ($test['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>🔬 In Progress</option>
                        <option value="completed" <?= ($test['status'] ?? '') === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                        <option value="cancelled" <?= ($test['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Lab Technician</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_full_name) ?>" disabled>
                </div>
                
                <!-- Quick Results -->
                <div class="md:col-span-2">
                    <label class="form-label">Quick Results</label>
                    <div class="quick-results-grid">
                        <button type="button" class="quick-result-btn" onclick="setResult('Negative')">❌ Negative</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Positive')">✅ Positive</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Normal')">✅ Normal</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Abnormal')">⚠️ Abnormal</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Reactive')">🔄 Reactive</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Non-Reactive')">⛔ Non-Reactive</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Indeterminate')">❓ Indeterminate</button>
                        <button type="button" class="quick-result-btn" onclick="setResult('Pending Review')">⏳ Pending Review</button>
                    </div>
                    <input type="hidden" id="quick_result" name="quick_result" value="">
                </div>
                
                <!-- Results -->
                <div class="md:col-span-2">
                    <label class="form-label">Results <span class="text-xs text-gray-400">(or type below)</span></label>
                    <textarea name="results" id="resultsTextarea" class="form-control" rows="4" placeholder="Enter test results..."><?= htmlspecialchars($test['results'] ?? '') ?></textarea>
                </div>
                
                <!-- Notes -->
                <div class="md:col-span-2">
                    <label class="form-label">Notes / Comments</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."><?= htmlspecialchars($test['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-3 mt-4">
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-save"></i> Update Test
                </button>
                <?php if (($test['status'] ?? '') !== 'completed' && ($test['status'] ?? '') !== 'cancelled'): ?>
                    <a href="view_test.php?id=<?= $test['id'] ?>&action=start" class="btn btn-orange">
                        <i class="fas fa-play"></i> Start
                    </a>
                    <a href="view_test.php?id=<?= $test['id'] ?>&action=complete" class="btn btn-green" onclick="return confirm('Complete this test? Results will be sent to the doctor.')">
                        <i class="fas fa-check"></i> Complete
                    </a>
                    <a href="view_test.php?id=<?= $test['id'] ?>&action=cancel" class="btn btn-red" onclick="return confirm('Are you sure you want to cancel this test?')">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-flask text-4xl block mb-3"></i>
            <p class="text-lg">Test not found</p>
            <a href="pending_requests.php" class="text-blue-600 hover:underline">Back to tests</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Test
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
    // QUICK RESULT FUNCTION
    // ================================================================
    function setResult(value) {
        // Set hidden input
        document.getElementById('quick_result').value = value;
        
        // Set textarea
        var textarea = document.getElementById('resultsTextarea');
        var current = textarea.value.trim();
        
        // If textarea has content, append with newline
        if (current.length > 0) {
            // Check if the value is already in the textarea
            if (!current.includes(value)) {
                textarea.value = current + '\n' + value;
            }
        } else {
            textarea.value = value;
        }
        
        // Highlight selected button
        var buttons = document.querySelectorAll('.quick-result-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.textContent.trim().includes(value) || btn.textContent.trim() === value) {
                btn.classList.add('active');
            }
        });
        
        // Trigger input event for any listeners
        var event = new Event('input', { bubbles: true });
        textarea.dispatchEvent(event);
    }

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
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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
        }, 5000);
    }

    // ================================================================
    // CHECK FOR SESSION MESSAGES
    // ================================================================
    <?php if ($message): ?>
    setTimeout(function() {
        showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Warning' : '❌ Error') ?>', 
                  '<?= addslashes($message) ?>', 
                  '<?= $message_type ?>');
    }, 500);
    <?php endif; ?>

    console.log('%c🧪 Braick - View Test (With Quick Results)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Test: <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= ucfirst($test['status'] ?? 'Pending') ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>