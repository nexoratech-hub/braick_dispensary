<?php
// backend/core/Auth.php

class Auth {
    private $db;
    private $user = null;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->init();
    }
    
    private function init() {
        session_start();
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }
    }
    
    private function loadUser($user_id) {
        $query = "SELECT u.*, b.name as branch_name FROM users u 
                  LEFT JOIN branches b ON u.branch_id = b.id 
                  WHERE u.id = ? AND u.status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id]);
        $this->user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function login($username, $password) {
        $query = "SELECT * FROM users WHERE username = ? AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['branch_id'] = $user['branch_id'];
            
            // Update online status
            $query = "UPDATE users SET is_online = 1, last_online = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user['id']]);
            
            $this->loadUser($user['id']);
            return $this->user;
        }
        
        return false;
    }
    
    public function logout() {
        if ($this->user) {
            $query = "UPDATE users SET is_online = 0 WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->user['id']]);
        }
        
        session_destroy();
        return true;
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function isAuthenticated() {
        return $this->user !== null;
    }
    
    public function hasRole($role) {
        return $this->user && $this->user['role'] === $role;
    }
    
    public function hasAnyRole($roles) {
        if (!$this->user) return false;
        return in_array($this->user['role'], $roles);
    }
    
    public function hasPermission($permission) {
        // Simple permission check - can be expanded
        $permissions = [
            'admin' => ['*'],
            'reception' => ['view_patients', 'register_patient', 'view_appointments'],
            'doctor' => ['view_patients', 'view_visits', 'create_prescription', 'view_lab_tests'],
            'laboratory' => ['view_lab_tests', 'create_lab_test', 'update_lab_test'],
            'pharmacy' => ['view_prescriptions', 'dispense_medication', 'view_inventory'],
            'cashier' => ['view_payments', 'create_payment', 'view_revenue']
        ];
        
        if ($this->user && isset($permissions[$this->user['role']])) {
            $user_perms = $permissions[$this->user['role']];
            return in_array('*', $user_perms) || in_array($permission, $user_perms);
        }
        return false;
    }
}
?>