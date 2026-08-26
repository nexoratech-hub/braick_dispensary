<?php
// ================================================================
// FILE: frontend/pages/doctor/update_doctor_status.php
// UPDATES DOCTOR ONLINE STATUS IN DATABASE
// USING NEW DATABASE: dispensary_db
// Session-based login (NO BYPASS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// HEADERS - Return JSON
// ================================================================
header('Content-Type: application/json');

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - Please login first',
        'redirect' => '/dispensary_system/frontend/pages/login.php'
    ]);
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = (int)$_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, status FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor || $doctor['status'] !== 'active') {
        session_destroy();
        echo json_encode([
            'success' => false,
            'message' => 'Doctor account inactive or not found',
            'redirect' => '/dispensary_system/frontend/pages/login.php'
        ]);
        exit;
    }
    
    $doctor_name = $doctor['full_name'];
    $doctor_branch_id = $doctor['branch_id'] ?? 1;
    
    // Update session with latest data
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    
} catch (Exception $e) {
    error_log("update_doctor_status verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error verifying doctor account: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// GET STATUS FROM POST
// ================================================================
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

// Validate status (0 = offline, 1 = online)
if (!in_array($status, [0, 1])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value. Must be 0 (offline) or 1 (online).'
    ]);
    exit;
}

error_log("Setting status to: " . ($status ? 'ONLINE' : 'OFFLINE') . " for doctor_id: " . $doctor_id);

// ================================================================
// UPDATE DATABASE
// ================================================================
try {
    // Update user status
    $stmt = $db->prepare("UPDATE users SET is_online = ?, last_online = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $doctor_id]);
    
    if (!$result) {
        error_log("Failed to update status for doctor_id: " . $doctor_id);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update status in database'
        ]);
        exit;
    }
    
    error_log("Successfully updated status to: " . ($status ? 'ONLINE' : 'OFFLINE'));
    
    // Update session
    $_SESSION['is_online'] = $status;
    $_SESSION['user_id'] = $doctor_id;
    $_SESSION['doctor_id'] = $doctor_id;
    
    // ================================================================
    // LOG ACTIVITY - USING NEW DATABASE STRUCTURE
    // ================================================================
    try {
        $status_text = $status ? 'online' : 'offline';
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
            VALUES (?, ?, 'doctor_status_changed', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $doctor_branch_id,
            "Dr. $doctor_name changed status to: $status_text"
        ]);
        error_log("Activity logged successfully");
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
    
    // ================================================================
    // SEND NOTIFICATION TO RECEPTION - USING NEW DATABASE STRUCTURE
    // ================================================================
    try {
        $status_text_display = $status ? '🟢 Online' : '🔴 Offline';
        $status_message = $status 
            ? "Dr. $doctor_name is now ONLINE and available for patient assignments." 
            : "Dr. $doctor_name is now OFFLINE.";
        
        // Get all reception users in the same branch
        $stmt = $db->prepare("
            SELECT id FROM users 
            WHERE role = 'reception' 
            AND branch_id = ? 
            AND status = 'active'
        ");
        $stmt->execute([$doctor_branch_id]);
        $receptionists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($receptionists as $receptionist) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $receptionist['id'],
                $doctor_branch_id,
                "Doctor Status: $status_text_display",
                $status_message,
                $status ? 'success' : 'warning',
                '/dispensary_system/frontend/pages/reception/assign_doctor.php'
            ]);
        }
        
        // Also send to admin
        $stmt = $db->prepare("
            SELECT id FROM users 
            WHERE role = 'admin' 
            AND status = 'active'
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $admin['id'],
                $doctor_branch_id,
                "Doctor Status: $status_text_display",
                $status_message,
                $status ? 'success' : 'warning',
                '/dispensary_system/frontend/pages/admin/doctors.php'
            ]);
        }
        
        error_log("Notifications sent successfully");
        
    } catch (Exception $e) {
        error_log("Notification failed: " . $e->getMessage());
    }
    
    // ================================================================
    // RETURN SUCCESS RESPONSE
    // ================================================================
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'is_online' => $status,
        'doctor' => [
            'id' => $doctor_id,
            'name' => $doctor_name,
            'branch_id' => $doctor_branch_id,
            'status_text' => $status ? 'Online' : 'Offline',
            'status_icon' => $status ? '🟢' : '🔴'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
exit;
?>