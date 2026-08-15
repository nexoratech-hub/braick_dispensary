<?php
// ================================================================
// FILE: frontend/pages/laboratory/update_test_status.php
// LABORATORY - UPDATE TEST STATUS (Complete Version)
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
    // User is not logged in - redirect to login
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
    // User is not laboratory - redirect to their dashboard
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
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    // Redirect with error
    header('Location: pending_requests.php?success=0&message=' . urlencode('Database connection error'));
    exit;
}

// ================================================================
// GET PARAMETERS
// ================================================================
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

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
            SET status = 'in_progress', lab_technician_id = ? 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$user_id, $id, $user_branch_id]);
        
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
        
        header('Location: in_progress.php?success=1&message=' . urlencode('Test confirmed successfully!'));
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
            SET status = 'cancelled' 
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
// ACTION: ACCEPT - Accept a full request from lab_requests
// ================================================================
if ($action === 'accept' && $id > 0) {
    try {
        // Get request details
        $stmt = $db->prepare("SELECT * FROM lab_requests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            header('Location: pending_requests.php?success=0&message=' . urlencode('Request not found'));
            exit;
        }
        
        // Update request status
        $stmt = $db->prepare("
            UPDATE lab_requests 
            SET status = 'accepted', accepted_at = NOW(), lab_technician_id = ?
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$user_id, $id, $user_branch_id]);
        
        // Update all items to in_progress
        $stmt = $db->prepare("
            UPDATE lab_request_items 
            SET status = 'in_progress'
            WHERE request_id = ?
        ");
        $stmt->execute([$id]);
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'request_accepted', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Request accepted: " . $request['request_number'] . " (ID: " . $id . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: in_progress.php?success=1&message=' . urlencode('Request accepted successfully!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: COMPLETE - Complete entire request
// ================================================================
if ($action === 'complete' && $id > 0) {
    try {
        // Get request details
        $stmt = $db->prepare("SELECT request_number FROM lab_requests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update all items to completed
        $stmt = $db->prepare("
            UPDATE lab_request_items 
            SET status = 'completed', completed_at = NOW()
            WHERE request_id = ?
        ");
        $stmt->execute([$id]);
        
        // Update request
        $stmt = $db->prepare("
            UPDATE lab_requests 
            SET status = 'completed', completed_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'request_completed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Request completed: " . ($request['request_number'] ?? 'Unknown') . " (ID: " . $id . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: completed_requests.php?success=1&message=' . urlencode('Request completed successfully!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: in_progress.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: CANCEL - Cancel request
// ================================================================
if ($action === 'cancel' && $id > 0) {
    try {
        // Get request details
        $stmt = $db->prepare("SELECT request_number FROM lab_requests WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $user_branch_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            UPDATE lab_requests 
            SET status = 'cancelled', cancelled_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_branch_id]);
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'request_cancelled', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Request cancelled: " . ($request['request_number'] ?? 'Unknown') . " (ID: " . $id . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: pending_requests.php?success=1&message=' . urlencode('Request cancelled!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: pending_requests.php?success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
// ACTION: UPDATE_TEST - Update individual test item
// ================================================================
if ($action === 'update_test' && $id > 0 && $test_id > 0) {
    try {
        if (empty($status)) {
            header('Location: view_request.php?id=' . $id . '&success=0&message=' . urlencode('Status is required'));
            exit;
        }
        
        // Get test details
        $stmt = $db->prepare("SELECT test_name FROM lab_request_items WHERE id = ? AND request_id = ?");
        $stmt->execute([$test_id, $id]);
        $test_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            UPDATE lab_request_items 
            SET status = ?, result = ?, comments = ?, 
                completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
            WHERE id = ? AND request_id = ?
        ");
        $stmt->execute([$status, $result, $notes, $status, $test_id, $id]);
        
        // Check if all tests are completed
        $stmt_check = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM lab_request_items 
            WHERE request_id = ?
        ");
        $stmt_check->execute([$id]);
        $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        $request_status = 'in_progress';
        if ($check['total'] == $check['completed'] && $check['total'] > 0) {
            $request_status = 'completed';
        }
        
        // Update request status
        $stmt_update = $db->prepare("
            UPDATE lab_requests 
            SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
            WHERE id = ?
        ");
        $stmt_update->execute([$request_status, $request_status, $id]);
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'test_item_updated', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Test item updated: " . ($test_item['test_name'] ?? 'Unknown') . " -> " . $status
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
        
        header('Location: view_request.php?id=' . $id . '&success=1&message=' . urlencode('Test updated successfully!'));
        exit;
        
    } catch (Exception $e) {
        header('Location: view_request.php?id=' . $id . '&success=0&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// ================================================================
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
        
        // Get test price from catalog
        $stmt = $db->prepare("
            SELECT price FROM lab_tests_catalog 
            WHERE test_name = ? 
            LIMIT 1
        ");
        $stmt->execute([$test_name]);
        $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
        $price = $catalog['price'] ?? 0;
        
        if ($price <= 0) return null;
        
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