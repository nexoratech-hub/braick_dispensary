<?php
// ================================================================
// FILE: frontend/pages/laboratory/update_test_status.php
// LABORATORY - UPDATE TEST STATUS
// ✅ USING NEW DATABASE: dispensary_db
// ✅ ONLY ONE TABLE: lab_tests (NO lab_requests)
// WITH FULL LOGIN SESSION PROTECTION
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET LAB TECHNICIAN INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_specialty = $_SESSION['specialty'] ?? 'Laboratory';
$user_username = $_SESSION['username'] ?? 'lab.technician';

// ================================================================
// IF ADMIN VIEWING LAB PAGE, USE THEIR BRANCH
// ================================================================
if ($_SESSION['role'] === 'admin') {
    $user_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;
}

// ================================================================
// INCLUDE DATABASE - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Location: pending_requests.php?success=0&message=' . urlencode('Database connection error'));
    exit;
}

// ================================================================
// GET PARAMETERS
// ================================================================
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// POST data
$result = isset($_POST['result']) ? trim($_POST['result']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

$response = [
    'success' => false,
    'message' => 'Invalid action',
    'redirect' => ''
];

// ================================================================
// ACTION: CONFIRM_TEST - Confirm a single test from lab_tests
// ================================================================
if ($action === 'confirm_test' && $id > 0) {
    try {
        // Check if test exists in lab_tests
        $stmt = $db->prepare("SELECT * FROM lab_tests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            header('Location: pending_requests.php?success=0&message=' . urlencode('Test not found'));
            exit;
        }
        
        // Update test status to 'in_progress'
        $stmt = $db->prepare("
            UPDATE lab_tests 
            SET status = 'in_progress', lab_technician_id = ?, updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$user_id, $id, $user_branch_id]);
        
        // Update visit status to 'lab_test'
        if ($test['visit_id']) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'lab_test', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$test['visit_id']]);
        }
        
        // Create bill for this test (if price exists)
        createTestBill($db, $id, $user_id, $user_branch_id);
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'test_confirmed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Test confirmed: " . $test['test_name'] . " (ID: " . $id . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: in_progress_tests.php?success=1&message=' . urlencode('Test confirmed successfully!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: CANCEL_TEST - Cancel a single test from lab_tests
// ================================================================
if ($action === 'cancel_test' && $id > 0) {
    try {
        // Check if test exists
        $stmt = $db->prepare("SELECT test_name FROM lab_tests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update test status to 'cancelled'
        $stmt = $db->prepare("
            UPDATE lab_tests 
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'test_cancelled', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Test cancelled: " . ($test['test_name'] ?? 'Unknown') . " (ID: " . $id . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: pending_requests.php?success=1&message=' . urlencode('Test cancelled successfully!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: COMPLETE_TEST - Complete a single test
// ================================================================
if ($action === 'complete_test' && $id > 0) {
    try {
        // Check if test exists
        $stmt = $db->prepare("SELECT test_name, visit_id FROM lab_tests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            header('Location: in_progress_tests.php?success=0&message=' . urlencode('Test not found'));
            exit;
        }
        
        // Update test status to 'completed'
        $stmt = $db->prepare("
            UPDATE lab_tests 
            SET status = 'completed', completed_at = NOW(), updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        // Update visit status to 'lab_test' so doctor can continue
        if ($test['visit_id']) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'lab_test', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$test['visit_id']]);
        }
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'test_completed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Test completed: " . $test['test_name'] . " (ID: " . $id . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: completed_tests.php?success=1&message=' . urlencode('Test completed successfully!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: in_progress_tests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: UPDATE_TEST_STATUS - Update test status and results
// ================================================================
if ($action === 'update_test_status' && $id > 0) {
    try {
        // Get test details
        $stmt = $db->prepare("SELECT test_name, visit_id FROM lab_tests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            header('Location: in_progress_tests.php?success=0&message=' . urlencode('Test not found'));
            exit;
        }
        
        // Build update query
        $update_fields = [];
        $params = [];
        
        if (!empty($status)) {
            $update_fields[] = "status = ?";
            $params[] = $status;
        }
        
        if (!empty($result)) {
            $update_fields[] = "results = ?";
            $params[] = $result;
        }
        
        if (!empty($notes)) {
            $update_fields[] = "notes = ?";
            $params[] = $notes;
        }
        
        if ($status === 'completed') {
            $update_fields[] = "completed_at = NOW()";
        }
        
        $update_fields[] = "updated_at = NOW()";
        
        if (empty($update_fields)) {
            header('Location: in_progress_tests.php?success=0&message=' . urlencode('No fields to update'));
            exit;
        }
        
        $params[] = $id;
        $params[] = $user_branch_id;
        
        $query = "UPDATE lab_tests SET " . implode(', ', $update_fields) . " WHERE id = ? AND branch_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // If completed, update visit status
        if ($status === 'completed' && $test['visit_id']) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'lab_test', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$test['visit_id']]);
        }
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'test_updated', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Test updated: " . $test['test_name'] . " (ID: " . $id . ") -> Status: " . ($status ?? 'unchanged')
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        // Redirect based on status
        if ($status === 'completed') {
            header('Location: completed_tests.php?success=1&message=' . urlencode('Test updated and completed!'));
        } else {
            header('Location: in_progress_tests.php?success=1&message=' . urlencode('Test updated successfully!'));
        }
        exit;
        
    } catch (Exception $e) {
        header('Location: in_progress_tests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================

function createTestBill($db, $test_id, $user_id, $branch_id) {
    try {
        // Get test details
        $stmt = $db->prepare("
            SELECT lt.*, v.patient_id, v.id as visit_id
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            WHERE lt.id = ?
        ");
        $stmt->execute([$test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) return null;
        
        $patient_id = $test['patient_id'];
        $visit_id = $test['visit_id'];
        $test_name = $test['test_name'];
        $test_price = $test['test_price'] ?? 0;
        
        // If price is 0, try to get from catalog
        if ($test_price <= 0) {
            $stmt = $db->prepare("
                SELECT price FROM lab_tests_catalog 
                WHERE test_name = ? 
                LIMIT 1
            ");
            $stmt->execute([$test_name]);
            $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
            $test_price = $catalog['price'] ?? 0;
        }
        
        if ($test_price <= 0) return null;
        
        // Check if bill exists
        $stmt = $db->prepare("
            SELECT id FROM bills 
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
                    INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, created_at)
                    VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, NOW())
                ");
                $stmt->execute([$bill_id, $patient_id, $branch_id, $test_name, $test_price, $test_price]);
                
                // Update bills
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET subtotal = subtotal + ?,
                        total_amount = total_amount + ?,
                        balance = balance + ?
                    WHERE id = ?
                ");
                $stmt->execute([$test_price, $test_price, $test_price, $bill_id]);
            }
        } else {
            // Create new bill
            $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO bills (
                    bill_number, patient_id, visit_id, branch_id, created_by,
                    subtotal, total_amount, balance, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$bill_number, $patient_id, $visit_id, $branch_id, $user_id, $test_price, $test_price, $test_price]);
            $bill_id = $db->lastInsertId();
            
            // Add to bill_items
            $stmt = $db->prepare("
                INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, created_at)
                VALUES (?, ?, ?, 'lab_test', ?, 1, ?, ?, NOW())
            ");
            $stmt->execute([$bill_id, $patient_id, $branch_id, $test_name, $test_price, $test_price]);
        }
        
        return $bill_id;
        
    } catch (Exception $e) {
        error_log("Bill creation error: " . $e->getMessage());
        return null;
    }
}

// ================================================================
// IF ACTION NOT RECOGNIZED
// ================================================================
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'pending_requests.php';
header('Location: ' . $referer . '?success=0&message=' . urlencode('Invalid action: ' . $action));
exit;
?>