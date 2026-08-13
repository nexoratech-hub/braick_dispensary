<?php
// ================================================================
// FILE: frontend/pages/logout.php
// BRAICK DISPENSARY - LOGOUT (FULLY FIXED)
// WORKS WITH ALL SIDEBARS AND ROLES
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// GET USER INFO BEFORE DESTROYING SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? null;
$full_name = $_SESSION['full_name'] ?? 'Unknown';
$role = $_SESSION['role'] ?? 'unknown';
$branch_id = $_SESSION['branch_id'] ?? null;

// ================================================================
// IF USER IS DOCTOR, SET OFFLINE
// ================================================================
if ($user_id) {
    try {
        // ================================================================
        // INCLUDE DATABASE - CORRECT PATH FROM CURRENT LOCATION
        // Current file: /frontend/pages/logout.php
        // Database: /backend/config/database.php
        // ================================================================
        require_once __DIR__ . '/../../backend/config/database.php';
        
        $db = Database::getInstance()->getConnection();
        
        // Check if user is a doctor
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] === 'doctor') {
            // Set doctor offline
            $stmt = $db->prepare("UPDATE users SET is_online = 0, last_online = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        
        // Log logout activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'user_logout', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $branch_id,
                "User logged out: " . $full_name . " (Role: " . $role . ")"
            ]);
        } catch (Exception $e) {
            // Silent fail
        }
        
    } catch (Exception $e) {
        // Silent fail if database not available
    }
}

// ================================================================
// DESTROY SESSION
// ================================================================
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// ================================================================
// REDIRECT TO LOGIN PAGE
// ================================================================
header('Location: login.php');
exit;
?>