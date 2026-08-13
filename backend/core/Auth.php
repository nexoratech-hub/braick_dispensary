<?php
// ================================================================
// FILE: backend/core/Auth.php
// BRAICK DISPENSARY - AUTHENTICATION CLASS
// ================================================================

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    private $user_id;
    private $user_data;
    
    public function __construct() {
        // Get database connection
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            $this->db = null;
        }
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set user_id from session
        $this->user_id = $_SESSION['user_id'] ?? null;
        
        // Load user data if logged in
        if ($this->user_id && $this->db) {
            $this->loadUserData();
        }
    }
    
    /**
     * Load user data from database
     */
    private function loadUserData() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$this->user_id]);
            $this->user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->user_data = null;
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->user_id !== null && $this->user_data !== false;
    }
    
    /**
     * Get current user data
     */
    public function getUser() {
        return $this->user_data;
    }
    
    /**
     * Get user ID
     */
    public function getUserId() {
        return $this->user_id;
    }
    
    /**
     * Get user role
     */
    public function getRole() {
        return $this->user_data['role'] ?? null;
    }
    
    /**
     * Get user branch ID
     */
    public function getBranchId() {
        return $this->user_data['branch_id'] ?? null;
    }
    
    /**
     * Logout user - destroy session and update status
     */
    public function logout($redirect = true) {
        // Get user ID before destroying session
        $user_id = $this->user_id;
        
        // If user is a doctor, set offline status
        if ($user_id && $this->db) {
            try {
                // Check if user is a doctor
                $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && $user['role'] === 'doctor') {
                    // Set doctor offline
                    $stmt = $this->db->prepare("UPDATE users SET is_online = 0, last_online = NOW() WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                // Log logout activity
                $stmt = $this->db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at) 
                    VALUES (?, 'user_logout', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    "User logged out: " . ($this->user_data['full_name'] ?? 'Unknown') . " (Role: " . ($this->user_data['role'] ?? 'unknown') . ")"
                ]);
            } catch (Exception $e) {
                // Silent fail on logout errors
            }
        }
        
        // Destroy session
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Redirect to login page
        if ($redirect) {
            header('Location: ../frontend/pages/login.php');
            exit;
        }
    }
}