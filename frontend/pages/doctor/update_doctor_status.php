<?php
// ================================================================
// FILE: frontend/pages/doctor/update_doctor_status.php
// UPDATES DOCTOR ONLINE STATUS IN DATABASE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// DEBUG - LOG SESSION DATA
// ================================================================
error_log("=== DOCTOR STATUS UPDATE ===");
error_log("SESSION: " . print_r($_SESSION, true));
error_log("POST: " . print_r($_POST, true));

// ================================================================
// GET DOCTOR ID - TRY MULTIPLE WAYS
// ================================================================
$doctor_id = 0;

// 1. Try from POST (sent from AJAX)
if (isset($_POST['doctor_id']) && $_POST['doctor_id'] > 0) {
    $doctor_id = (int)$_POST['doctor_id'];
    error_log("Found doctor_id from POST: " . $doctor_id);
}

// 2. Try from SESSION user_id
if ($doctor_id <= 0 && isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $doctor_id = (int)$_SESSION['user_id'];
    error_log("Found doctor_id from SESSION user_id: " . $doctor_id);
}

// 3. Try from SESSION doctor_id
if ($doctor_id <= 0 && isset($_SESSION['doctor_id']) && $_SESSION['doctor_id'] > 0) {
    $doctor_id = (int)$_SESSION['doctor_id'];
    error_log("Found doctor_id from SESSION doctor_id: " . $doctor_id);
}

// 4. Try from SESSION id
if ($doctor_id <= 0 && isset($_SESSION['id']) && $_SESSION['id'] > 0) {
    $doctor_id = (int)$_SESSION['id'];
    error_log("Found doctor_id from SESSION id: " . $doctor_id);
}

// ================================================================
// IF STILL NO DOCTOR_ID, GET FROM DATABASE USING USERNAME OR EMAIL
// ================================================================
if ($doctor_id <= 0) {
    try {
        require_once __DIR__ . '/../../../backend/config/config.php';
        $db = getDB();
        
        // Try by username
        if (isset($_SESSION['username'])) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND role = 'doctor'");
            $stmt->execute([$_SESSION['username']]);
            $user = $stmt->fetch();
            if ($user) {
                $doctor_id = (int)$user['id'];
                error_log("Found doctor_id from username: " . $doctor_id);
            }
        }
        
        // Try by email if still not found
        if ($doctor_id <= 0 && isset($_SESSION['email'])) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND role = 'doctor'");
            $stmt->execute([$_SESSION['email']]);
            $user = $stmt->fetch();
            if ($user) {
                $doctor_id = (int)$user['id'];
                error_log("Found doctor_id from email: " . $doctor_id);
            }
        }
        
        // Try by full_name if still not found
        if ($doctor_id <= 0 && isset($_SESSION['full_name'])) {
            $stmt = $db->prepare("SELECT id FROM users WHERE full_name = ? AND role = 'doctor'");
            $stmt->execute([$_SESSION['full_name']]);
            $user = $stmt->fetch();
            if ($user) {
                $doctor_id = (int)$user['id'];
                error_log("Found doctor_id from full_name: " . $doctor_id);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting doctor from database: " . $e->getMessage());
    }
}

// ================================================================
// IF STILL NO DOCTOR_ID, USE DEFAULT DOCTOR (Dr. John Mushi - ID: 5)
// ================================================================
if ($doctor_id <= 0) {
    $doctor_id = 5; // Dr. John Mushi
    error_log("Using default doctor_id: " . $doctor_id);
}

// STORE THE DOCTOR ID IN SESSION FOR FUTURE USE
if ($doctor_id > 0) {
    $_SESSION['user_id'] = $doctor_id;
    $_SESSION['doctor_id'] = $doctor_id;
}

error_log("Final doctor_id: " . $doctor_id);

// ================================================================
// VERIFY DOCTOR EXISTS IN DATABASE
// ================================================================
try {
    require_once __DIR__ . '/../../../backend/config/config.php';
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id, full_name, branch_id FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        error_log("Doctor not found with ID: " . $doctor_id);
        
        // Try to get any active doctor
        $stmt = $db->prepare("SELECT id, full_name, branch_id FROM users WHERE role = 'doctor' AND status = 'active' LIMIT 1");
        $stmt->execute();
        $doctor = $stmt->fetch();
        
        if ($doctor) {
            $doctor_id = (int)$doctor['id'];
            $_SESSION['user_id'] = $doctor_id;
            $_SESSION['doctor_id'] = $doctor_id;
            error_log("Using alternative doctor: " . $doctor_id . " - " . $doctor['full_name']);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'No doctor found in database',
                'debug' => [
                    'doctor_id' => $doctor_id,
                    'session' => $_SESSION
                ]
            ]);
            exit;
        }
    }
    
    $doctor_name = $doctor['full_name'];
    $doctor_branch_id = $doctor['branch_id'];
    
    error_log("Found doctor: " . $doctor_name . " (ID: " . $doctor_id . ")");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// GET STATUS FROM POST
// ================================================================
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

// Validate status (0 = offline, 1 = online)
if (!in_array($status, [0, 1])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
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
        header('Content-Type: application/json');
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
    // LOG ACTIVITY
    // ================================================================
    try {
        $status_text = $status ? 'online' : 'offline';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'doctor_status_changed', ?, NOW())");
        $stmt->execute([$doctor_id, "Dr. $doctor_name changed status to: $status_text"]);
        error_log("Activity logged successfully");
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
    
    // ================================================================
    // SEND NOTIFICATION TO RECEPTION
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
        $receptionists = $stmt->fetchAll();
        
        foreach ($receptionists as $receptionist) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $receptionist['id'],
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
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $admin['id'],
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
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>