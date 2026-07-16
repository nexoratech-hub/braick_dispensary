<?php
// ============================================================
// FILE: backend/config/config.php
// ============================================================

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'dispensary_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ============================================================
// APPLICATION CONFIGURATION
// ============================================================
define('APP_NAME', 'Dispensary System');
define('APP_URL', 'http://localhost/dispensary_system/');
define('APP_ENV', 'development'); // development, production

// ============================================================
// TIMEZONE
// ============================================================
date_default_timezone_set('Africa/Dar_es_Salaam');

// ============================================================
// UPLOAD CONFIGURATION
// ============================================================
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB

// ============================================================
// DATABASE CONNECTION
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ============================================================
// FUNCTIONS
// ============================================================

// Get database connection
function getDB() {
    global $pdo;
    return $pdo;
}

// Get user by ID
function getUser($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get branch by ID
function getBranch($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get all branches
function getBranches() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM branches WHERE status = 'active'");
    return $stmt->fetchAll();
}

// Get patient by ID
function getPatient($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get total patients
function getTotalPatients() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
    return $stmt->fetch()['total'];
}

// Get today's visits
function getTodayVisits() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visits WHERE DATE(created_at) = CURDATE()");
    return $stmt->fetch()['total'];
}

// Get online doctors
function getOnlineDoctors() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'doctor' AND is_online = 1 AND status = 'active'");
    return $stmt->fetchAll();
}

// Get today's revenue
function getTodayRevenue() {
    global $pdo;
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM pharmacy_sales WHERE DATE(sale_date) = CURDATE()");
    return $stmt->fetch()['total'];
}

// Send notification
function sendNotification($user_id, $title, $message, $type = 'info', $link = '') {
    global $pdo;
    $sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id, $title, $message, $type, $link]);
}

// Get unread notifications
function getUnreadNotifications($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// ============================================================
// TIME AGO FUNCTION - Check if already defined
// ============================================================
if (!function_exists('time_ago')) {
    /**
     * Convert timestamp to human readable time ago
     * 
     * @param string $timestamp
     * @return string
     */
    function time_ago($timestamp) {
        if (empty($timestamp)) {
            return 'N/A';
        }
        
        try {
            $time = strtotime($timestamp);
            if ($time === false) {
                return 'N/A';
            }
            
            $diff = time() - $time;
            
            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                return $minutes . 'm ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . 'h ago';
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return $days . 'd ago';
            } elseif ($diff < 2592000) {
                $weeks = floor($diff / 604800);
                return $weeks . 'w ago';
            } else {
                return date('M d, Y', $time);
            }
        } catch (Exception $e) {
            return 'N/A';
        }
    }
}
?>