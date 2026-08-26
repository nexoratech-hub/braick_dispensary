<?php
// ================================================================
// FILE: frontend/components/doctor_sidebar.php
// DOCTOR - SHARED SIDEBAR (WITH API INTEGRATION - FIXED)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Doctor only)
// ================================================================
if ($_SESSION['role'] !== 'doctor') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'] ?? 0;
$doctor_full_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_role = $_SESSION['role'] ?? 'doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$doctor_username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $db = null;
}

// ================================================================
// GET SITE NAME FROM SETTINGS
// ================================================================
$site_name = 'Braick Dispensary';
$currency = 'TSh';
$slogan = 'Tunajali Afya Yako';

try {
    if ($db !== null) {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $site_name = $result['setting_value'];
        }
        
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $currency = $result['setting_value'];
        }
    }
} catch (Exception $e) {
    // Keep default
}

// ================================================================
// GET REAL DATA FOR BADGES - FIXED QUERIES
// ================================================================
$patient_count = 0;
$lab_count = 0;
$referral_count = 0;
$appointment_count = 0;
$pending_consultations = 0;
$completed_consultations = 0;
$cancelled_consultations = 0;
$pending_prescriptions = 0;
$total_consultations = 0;

// Services counts
$procedures_count = 0;
$tools_count = 0;
$lab_tests_count = 0;
$expiring_medicines = 0;

if ($db !== null && isset($_SESSION['user_id'])) {
    try {
        // 1. Total Patients (distinct patients)
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Pending Lab Tests
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status IN ('pending', 'in_progress')");
            $stmt->execute([$doctor_id]);
            $lab_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $lab_count = 0;
        }
        
        // 3. Pending Referrals
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM referrals WHERE from_doctor_id = ? AND status = 'pending'");
            $stmt->execute([$doctor_id]);
            $referral_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $referral_count = 0;
        }
        
        // 4. Today's Appointments
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')");
            $stmt->execute([$doctor_id]);
            $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $appointment_count = 0;
        }
        
        // 5. Pending Consultations - FIXED: includes 'prescribed' status
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
            AND is_completed = 0
        ");
        $stmt->execute([$doctor_id]);
        $pending_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 6. Completed Consultations
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status = 'completed'
            AND is_completed = 1
        ");
        $stmt->execute([$doctor_id]);
        $completed_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 7. Cancelled Consultations
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status = 'cancelled'
        ");
        $stmt->execute([$doctor_id]);
        $cancelled_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 8. Total Consultations
        $total_consultations = $pending_consultations + $completed_consultations + $cancelled_consultations;
        
        // 9. Pending Prescriptions
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
            $stmt->execute([$doctor_id]);
            $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $pending_prescriptions = 0;
        }
        
        // ================================================================
        // 10. SERVICES COUNTS
        // ================================================================
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
            $stmt->execute([$doctor_branch_id]);
            $procedures_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $procedures_count = 0;
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
            $stmt->execute([$doctor_branch_id]);
            $lab_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $lab_tests_count = 0;
        }
        
        // 11. Expiring Medicines
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medications_inventory 
                WHERE branch_id = ? 
                AND status = 'active' 
                AND expiry_date IS NOT NULL
                AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND expiry_date >= CURDATE()
            ");
            $stmt->execute([$doctor_branch_id]);
            $expiring_medicines = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $expiring_medicines = 0;
        }
        
    } catch (Exception $e) {
        error_log("Sidebar data error: " . $e->getMessage());
    }
}

// ================================================================
// DOCTOR ONLINE STATUS
// ================================================================
$doctor_is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// Detect current page
$current_page = basename($_SERVER['PHP_SELF']);

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE
// ================================================================
function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GENERATE INITIAL HASH FOR CHANGE DETECTION
// ================================================================
$initial_data_hash = md5(json_encode([
    'patient_count' => $patient_count,
    'pending_consultations' => $pending_consultations,
    'lab_count' => $lab_count,
    'appointment_count' => $appointment_count,
    'referral_count' => $referral_count,
    'pending_prescriptions' => $pending_prescriptions,
    'procedures_count' => $procedures_count,
    'lab_tests_count' => $lab_tests_count,
    'expiring_medicines' => $expiring_medicines,
    'doctor_status' => $doctor_is_online ? 'online' : 'offline'
]));

// ================================================================
// HANDLE AJAX REQUEST FOR SIDEBAR DATA - WITH HASH
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_sidebar_data') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $client_hash = $_POST['hash'] ?? '';
    
    if ($doctor_id !== (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Invalid doctor ID']);
        exit;
    }
    
    $response = [
        'success' => false,
        'has_changed' => false,
        'hash' => '',
        'data' => null
    ];
    
    $data = [
        'patientCount' => 0,
        'labCount' => 0,
        'referralCount' => 0,
        'appointmentCount' => 0,
        'pendingConsultations' => 0,
        'completedConsultations' => 0,
        'cancelledConsultations' => 0,
        'pendingPrescriptions' => 0,
        'totalConsultations' => 0,
        'proceduresCount' => 0,
        'labTestsCount' => 0,
        'expiringMedicines' => 0,
        'doctorName' => '',
        'doctorStatus' => 'offline'
    ];
    
    if ($doctor_id > 0 && $db !== null) {
        try {
            // Doctor info
            $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doctor) {
                $data['doctorName'] = $doctor['full_name'] ?? '';
                $data['doctorStatus'] = ($doctor['is_online'] ?? 0) ? 'online' : 'offline';
            }
            
            // 1. Total Patients
            $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
            $stmt->execute([$doctor_id]);
            $data['patientCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 2. Pending Lab Tests
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status IN ('pending', 'in_progress')");
                $stmt->execute([$doctor_id]);
                $data['labCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['labCount'] = 0;
            }
            
            // 3. Pending Referrals
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM referrals WHERE from_doctor_id = ? AND status = 'pending'");
                $stmt->execute([$doctor_id]);
                $data['referralCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['referralCount'] = 0;
            }
            
            // 4. Today's Appointments
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')");
                $stmt->execute([$doctor_id]);
                $data['appointmentCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['appointmentCount'] = 0;
            }
            
            // 5. Pending Consultations - FIXED: includes 'prescribed' status
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE doctor_id = ? 
                AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
                AND is_completed = 0
            ");
            $stmt->execute([$doctor_id]);
            $data['pendingConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 6. Completed Consultations
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE doctor_id = ? 
                AND status = 'completed'
                AND is_completed = 1
            ");
            $stmt->execute([$doctor_id]);
            $data['completedConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 7. Cancelled Consultations
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE doctor_id = ? 
                AND status = 'cancelled'
            ");
            $stmt->execute([$doctor_id]);
            $data['cancelledConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 8. Total Consultations
            $data['totalConsultations'] = $data['pendingConsultations'] + $data['completedConsultations'] + $data['cancelledConsultations'];
            
            // 9. Pending Prescriptions
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
                $stmt->execute([$doctor_id]);
                $data['pendingPrescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['pendingPrescriptions'] = 0;
            }
            
            // 10. Services Counts
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
                $stmt->execute([$branch_id]);
                $data['proceduresCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['proceduresCount'] = 0;
            }
            
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
                $stmt->execute([$branch_id]);
                $data['labTestsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['labTestsCount'] = 0;
            }
            
            // 11. Expiring Medicines
            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM medications_inventory 
                    WHERE branch_id = ? 
                    AND status = 'active' 
                    AND expiry_date IS NOT NULL
                    AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND expiry_date >= CURDATE()
                ");
                $stmt->execute([$branch_id]);
                $data['expiringMedicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $data['expiringMedicines'] = 0;
            }
            
            // Generate hash
            $hash = md5(json_encode([
                'patient_count' => $data['patientCount'],
                'pending_consultations' => $data['pendingConsultations'],
                'lab_count' => $data['labCount'],
                'appointment_count' => $data['appointmentCount'],
                'referral_count' => $data['referralCount'],
                'pending_prescriptions' => $data['pendingPrescriptions'],
                'procedures_count' => $data['proceduresCount'],
                'lab_tests_count' => $data['labTestsCount'],
                'expiring_medicines' => $data['expiringMedicines'],
                'doctor_status' => $data['doctorStatus']
            ]));
            
            $response['hash'] = $hash;
            $response['has_changed'] = ($client_hash !== $hash);
            
            if ($response['has_changed'] || empty($client_hash)) {
                $response['data'] = $data;
            }
            
            $response['success'] = true;
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
        }
    }
    
    echo json_encode($response);
    exit;
}

// ================================================================
// SITE SLOGAN
// ================================================================
$site_slogan = 'Tunajali Afya Yako';

// ================================================================
// PASS INITIAL DATA TO JAVASCRIPT
// ================================================================
$initial_data = [
    'patientCount' => $patient_count,
    'labCount' => $lab_count,
    'referralCount' => $referral_count,
    'appointmentCount' => $appointment_count,
    'pendingConsultations' => $pending_consultations,
    'completedConsultations' => $completed_consultations,
    'cancelledConsultations' => $cancelled_consultations,
    'pendingPrescriptions' => $pending_prescriptions,
    'totalConsultations' => $total_consultations,
    'proceduresCount' => $procedures_count,
    'labTestsCount' => $lab_tests_count,
    'expiringMedicines' => $expiring_medicines,
    'doctorName' => $doctor_full_name,
    'doctorStatus' => $doctor_is_online ? 'online' : 'offline'
];
?>

<!-- SIDEBAR HTML -->
<style>
    /* ================================================================
       SIDEBAR STYLES
       ================================================================ */
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        background: #0B4EA8;
        color: white;
        z-index: 9999;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateX(-100%);
        box-shadow: 4px 0 30px rgba(0,0,0,0.3);
        padding-bottom: 20px;
    }
    
    [data-theme="dark"] .sidebar {
        background: #0A3D7A;
        box-shadow: 4px 0 30px rgba(0,0,0,0.5);
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0A3D7A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #9EC5FE; }
    
    /* Overlay */
    #sidebarOverlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 9998;
        display: none;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        transition: opacity 0.3s ease;
    }
    #sidebarOverlay.active {
        display: block !important;
    }
    
    /* Sidebar Brand */
    .sidebar-brand {
        padding: 18px 16px 14px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: #0B4EA8;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    [data-theme="dark"] .sidebar-brand {
        background: #0A3D7A;
    }
    .sidebar-brand .logo {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        object-fit: cover;
        background: white;
        padding: 4px;
        border: 2px solid rgba(255,255,255,0.1);
    }
    .sidebar-brand .brand-text {
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        line-height: 1.2;
    }
    .sidebar-brand .brand-sub {
        color: #9EC5FE;
        font-size: 0.65rem;
        font-weight: 500;
    }
    
    .sidebar-close-btn {
        display: none;
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 4px 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
        margin-left: auto;
    }
    .sidebar-close-btn:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.05);
    }
    @media (max-width: 1024px) {
        .sidebar-close-btn {
            display: block;
        }
    }
    
    /* Doctor Info */
    .sidebar-doctor-info {
        padding: 12px 16px;
        border-bottom: 2px solid rgba(255,255,255,0.08);
        background: #0B4EA8;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    [data-theme="dark"] .sidebar-doctor-info {
        background: #0A3D7A;
    }
    .sidebar-doctor-info .doctor-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #0B5ED7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
        flex-shrink: 0;
        border: 2px solid rgba(255,255,255,0.15);
    }
    .sidebar-doctor-info .doctor-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: white;
    }
    .sidebar-doctor-info .doctor-role {
        font-size: 0.6rem;
        color: #9EC5FE;
    }
    .sidebar-doctor-info .doctor-status {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .sidebar-doctor-info .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .sidebar-doctor-info .status-dot.online {
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    .sidebar-doctor-info .status-dot.offline {
        background: #94A3B8;
    }
    .sidebar-doctor-info .status-text {
        font-size: 0.6rem;
        color: #D2E3FC;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }
    
    /* Navigation */
    .sidebar-nav {
        padding: 10px 8px 20px;
    }
    .sidebar-nav .nav-label {
        font-size: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6EA8FE;
        padding: 0 10px;
        margin: 12px 0 4px;
        font-weight: 700;
    }
    .sidebar-nav .nav-label:first-of-type {
        margin-top: 0;
    }
    
    /* Sidebar Links */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        color: #D2E3FC;
        text-decoration: none;
        transition: all 0.25s ease;
        font-size: 0.8rem;
        font-weight: 500;
        margin: 1px 0;
        background: transparent;
        cursor: pointer;
        border: none;
        width: 100%;
        text-align: left;
        position: relative;
    }
    .sidebar-link:hover {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
        transform: translateX(4px);
    }
    .sidebar-link.active {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 20%;
        bottom: 20%;
        width: 4px;
        background: white;
        border-radius: 0 4px 4px 0;
    }
    .sidebar-link i {
        width: 20px;
        text-align: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    
    /* Badges */
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
    }
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    .sidebar-link .badge.warning {
        background: #D97706;
    }
    .sidebar-link .badge.success {
        background: #059669;
    }
    .sidebar-link .badge.blue {
        background: #0B5ED7;
    }
    .sidebar-link .badge.purple {
        background: #7C3AED;
    }
    .sidebar-link .badge.orange {
        background: #EA580C;
    }
    .sidebar-link .badge.teal {
        background: #0D9488;
    }
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .badge-update {
        animation: badgePop 0.3s ease;
    }
    @keyframes badgePop {
        0% { transform: scale(0.5); opacity: 0; }
        70% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* Logout Link */
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.08);
        padding-top: 10px;
        margin-top: 6px;
        color: #FCA5A5;
    }
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    /* Live Indicator */
    .sidebar-live-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.55rem;
        color: #34D399;
        margin-left: 8px;
    }
    .sidebar-live-indicator .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
        display: inline-block;
    }
    
    /* Sidebar Status */
    .sidebar-status {
        padding: 10px 16px;
        border-top: 2px solid rgba(255,255,255,0.08);
        display: flex;
        align-items: center;
        gap: 10px;
        background: #0B4EA8;
        position: sticky;
        bottom: 0;
    }
    [data-theme="dark"] .sidebar-status {
        background: #0A3D7A;
    }
    .sidebar-status .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .sidebar-status .status-dot.online {
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
    }
    .sidebar-status .status-text {
        font-size: 0.7rem;
        color: #D2E3FC;
    }
    .sidebar-status .update-time {
        font-size: 0.55rem;
        color: #6EA8FE;
        margin-left: auto;
    }
    
    /* Responsive */
    @media (min-width: 1025px) {
        .sidebar {
            transform: translateX(0) !important;
            z-index: 50;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        #sidebarOverlay {
            display: none !important;
        }
        .sidebar-close-btn {
            display: none !important;
        }
    }
    
    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            z-index: 9999;
            border-radius: 0 12px 12px 0;
        }
        .sidebar.open {
            transform: translateX(0) !important;
        }
        #sidebarOverlay {
            display: none;
            z-index: 9998;
        }
        #sidebarOverlay.active {
            display: block !important;
        }
        .sidebar-brand {
            padding: 14px 14px 10px;
        }
        .sidebar-brand .logo {
            width: 36px;
            height: 36px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.85rem;
        }
        .sidebar-link {
            padding: 7px 10px;
            font-size: 0.75rem;
            gap: 8px;
        }
        .sidebar-link i {
            width: 18px;
            font-size: 0.8rem;
        }
        .sidebar-link .badge {
            font-size: 0.55rem;
            padding: 1px 7px;
        }
    }
    
    @media (max-width: 768px) {
        .sidebar {
            width: 300px;
        }
        .sidebar-brand {
            padding: 12px 12px 10px;
        }
        .sidebar-brand .logo {
            width: 34px;
            height: 34px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.8rem;
        }
        .sidebar-link {
            padding: 6px 10px;
            font-size: 0.7rem;
        }
        .sidebar-link i {
            width: 16px;
            font-size: 0.75rem;
        }
        .sidebar-doctor-info .doctor-name {
            font-size: 0.7rem;
        }
        .sidebar-doctor-info .doctor-avatar {
            width: 28px;
            height: 28px;
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
        }
        .sidebar-brand {
            padding: 10px 10px 8px;
        }
        .sidebar-brand .logo {
            width: 30px;
            height: 30px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.75rem;
        }
        .sidebar-link {
            padding: 5px 8px;
            font-size: 0.65rem;
            gap: 6px;
        }
        .sidebar-link i {
            width: 14px;
            font-size: 0.7rem;
        }
        .sidebar-link .badge {
            font-size: 0.45rem;
            padding: 1px 5px;
            min-width: 16px;
        }
        .sidebar-nav .nav-label {
            font-size: 0.4rem;
        }
        .sidebar-doctor-info .doctor-name {
            font-size: 0.65rem;
        }
        .sidebar-doctor-info .doctor-avatar {
            width: 24px;
            height: 24px;
            font-size: 0.6rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text"><?= htmlspecialchars($site_name) ?></p>
                <p class="brand-sub">❤️ Doctor Panel</p>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- Doctor Info -->
    <div class="sidebar-doctor-info">
        <div class="doctor-avatar">
            <?php
            $initials = '';
            $name_parts = explode(' ', $doctor_full_name);
            foreach ($name_parts as $part) {
                if (!empty($part)) {
                    $initials .= strtoupper($part[0]);
                }
            }
            echo substr($initials, 0, 2);
            ?>
        </div>
        <div>
            <div class="doctor-name"><?= htmlspecialchars($doctor_full_name) ?></div>
            <div class="doctor-role">👨‍⚕️ Doctor</div>
        </div>
        <div class="doctor-status">
            <span class="status-dot <?= $doctor_is_online ? 'online' : 'offline' ?>" id="sidebarStatusDot"></span>
            <span class="status-text" id="sidebarStatusText"><?= $doctor_is_online ? 'Online' : 'Offline' ?></span>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        
        <div class="nav-label">Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <div class="nav-label mt-2">Patients</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/my_patients.php" class="sidebar-link <?= isActive('my_patients.php') ?>">
            <i class="fas fa-users"></i> My Patients
            <span class="badge" id="patientCount"><?= $patient_count ?></span>
        </a>
        
        <div class="nav-label mt-2">Clinical</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/consultations.php" class="sidebar-link <?= isActive('consultations.php') ?>">
            <i class="fas fa-stethoscope"></i> Consultations
            <?php if ($pending_consultations > 0): ?>
                <span class="badge danger" id="pendingConsultBadge"><?= $pending_consultations ?></span>
            <?php else: ?>
                <span class="badge" id="pendingConsultBadge">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/doctor/view_prescriptions.php" class="sidebar-link <?= isActive('view_prescriptions.php') ?>">
            <i class="fas fa-file-prescription"></i> Prescriptions
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge warning" id="prescriptionBadge"><?= $pending_prescriptions ?></span>
            <?php else: ?>
                <span class="badge" id="prescriptionBadge">0</span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/doctor/lab_results.php" class="sidebar-link <?= isActive('lab_results.php') ?>">
            <i class="fas fa-flask"></i> Lab Results
            <?php if ($lab_count > 0): ?>
                <span class="badge warning" id="labCount"><?= $lab_count ?></span>
            <?php else: ?>
                <span class="badge" id="labCount">0</span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label mt-2">Schedule</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/appointments.php" class="sidebar-link <?= isActive('appointments.php') ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <?php if ($appointment_count > 0): ?>
                <span class="badge blue" id="appointmentCount"><?= $appointment_count ?></span>
            <?php else: ?>
                <span class="badge" id="appointmentCount">0</span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label mt-2">Referrals</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/referrals.php" class="sidebar-link <?= isActive('referrals.php') ?>">
            <i class="fas fa-share-alt"></i> Referrals
            <?php if ($referral_count > 0): ?>
                <span class="badge warning" id="referralCount"><?= $referral_count ?></span>
            <?php else: ?>
                <span class="badge" id="referralCount">0</span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label mt-2">Documents</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/documents.php" class="sidebar-link <?= isActive('documents.php') ?>">
            <i class="fas fa-folder"></i> Documents
        </a>
        
        <div class="nav-label mt-2">Resources</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/services.php" class="sidebar-link <?= isActive('services.php') ?>">
            <i class="fas fa-cog"></i> Services
            <?php 
                $total_services = $procedures_count + $lab_tests_count;
                if ($total_services > 0): 
            ?>
                <span class="badge purple" id="servicesTotalBadge"><?= $total_services ?></span>
            <?php else: ?>
                <span class="badge" id="servicesTotalBadge">0</span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label mt-2">Account</div>
        
        <a href="/dispensary_system/frontend/pages/doctor/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- Status Footer -->
    <div class="sidebar-status">
        <span class="status-dot <?= $doctor_is_online ? 'online' : 'offline' ?>" id="sidebarFooterDot"></span>
        <span class="status-text" id="sidebarFooterText"><?= $doctor_is_online ? 'Online' : 'Offline' ?></span>
        <span class="update-time" id="sidebarUpdateTime">
            <span class="sidebar-live-indicator">
                <span class="dot"></span> Live
            </span>
        </span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT - WITH API INTEGRATION - FIXED AUTO-UPDATE -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CONFIGURATION - FIXED API URL
    // ================================================================
    var SIDEBAR_CONFIG = {
        API_URL: '/dispensary_system/backend/api/get_doctor_sidebar_stats.php',
        CHECK_INTERVAL: 3000, // Check every 3 seconds
        DOCTOR_ID: <?= json_encode($doctor_id) ?>,
        BRANCH_ID: <?= json_encode($doctor_branch_id) ?>,
        INITIAL_HASH: '<?= $initial_data_hash ?>'
    };
    
    // ================================================================
    // STATE
    // ================================================================
    var sidebarState = {
        dataHash: SIDEBAR_CONFIG.INITIAL_HASH,
        isUpdating: false,
        hasInitialData: false,
        updateInterval: null,
        lastUpdate: null,
        changeCount: 0,
        apiRequestCount: 0
    };
    
    // ================================================================
    // SIDEBAR TOGGLE - FULLY FIXED
    // ================================================================
    (function() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            initSidebar();
        }
        
        function initSidebar() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
            }
            
            if (!sidebar) {
                console.error('Sidebar element not found!');
                return;
            }
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            if (toggleBtn) {
                var newToggle = toggleBtn.cloneNode(true);
                toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
                var freshToggle = document.getElementById('sidebarToggle');
                
                if (freshToggle) {
                    freshToggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleSidebar();
                    });
                }
            }
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        }
    })();

    // ================================================================
    // UPDATE SIDEBAR BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        if (!data) return false;
        
        var badgeMap = {
            'patientCount': 'patientCount',
            'labCount': 'labCount',
            'referralCount': 'referralCount',
            'appointmentCount': 'appointmentCount',
            'pendingConsultations': 'pendingConsultBadge',
            'pendingPrescriptions': 'prescriptionBadge'
        };
        
        var hasChanges = false;
        
        for (var key in badgeMap) {
            if (data[key] !== undefined) {
                var el = document.getElementById(badgeMap[key]);
                if (el) {
                    var oldValue = el.textContent;
                    var newValue = data[key];
                    if (oldValue !== String(newValue)) {
                        hasChanges = true;
                        el.textContent = newValue;
                        el.classList.remove('badge-update');
                        void el.offsetWidth;
                        el.classList.add('badge-update');
                        
                        // Update badge class based on value
                        var numValue = parseInt(newValue);
                        if (key === 'pendingConsultations' || key === 'labCount' || key === 'referralCount') {
                            el.className = numValue > 0 ? 'badge danger badge-update' : 'badge badge-update';
                        }
                        if (key === 'pendingPrescriptions') {
                            el.className = numValue > 0 ? 'badge warning badge-update' : 'badge badge-update';
                        }
                        if (key === 'appointmentCount') {
                            el.className = numValue > 0 ? 'badge blue badge-update' : 'badge badge-update';
                        }
                    }
                }
            }
        }
        
        // Update Services badge
        if (data.proceduresCount !== undefined && data.labTestsCount !== undefined) {
            var total = data.proceduresCount + data.labTestsCount;
            var el = document.getElementById('servicesTotalBadge');
            if (el) {
                var oldVal = el.textContent;
                if (oldVal !== String(total)) {
                    hasChanges = true;
                    el.textContent = total;
                    el.className = total > 0 ? 'badge purple' : 'badge';
                    el.classList.remove('badge-update');
                    void el.offsetWidth;
                    el.classList.add('badge-update');
                }
            }
        }
        
        // Update timestamp
        var timeEl = document.getElementById('sidebarUpdateTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            timeEl.innerHTML = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr;
        }
        
        if (hasChanges) {
            sidebarState.changeCount++;
            console.log('📊 Sidebar updated: ' + sidebarState.changeCount + ' changes detected');
        }
        
        return hasChanges;
    }

    // ================================================================
    // UPDATE DOCTOR STATUS
    // ================================================================
    function updateDoctorStatus(data) {
        if (!data) return;
        
        var statusDot = document.getElementById('sidebarStatusDot');
        var statusText = document.getElementById('sidebarStatusText');
        var footerDot = document.getElementById('sidebarFooterDot');
        var footerText = document.getElementById('sidebarFooterText');
        
        var isOnline = data.doctorStatus === 'online';
        
        if (statusDot) {
            statusDot.className = isOnline ? 'status-dot online' : 'status-dot offline';
        }
        if (statusText) {
            statusText.textContent = isOnline ? 'Online' : 'Offline';
            statusText.style.color = isOnline ? '#34D399' : '#94A3B8';
        }
        if (footerDot) {
            footerDot.className = isOnline ? 'status-dot online' : 'status-dot offline';
        }
        if (footerText) {
            footerText.textContent = isOnline ? 'Online' : 'Offline';
            footerText.style.color = isOnline ? '#34D399' : '#94A3B8';
        }
    }

    // ================================================================
    // FETCH LIVE DATA FROM API (WITH HASH) - FIXED
    // ================================================================
    function fetchSidebarData(forceUpdate) {
        var doctorId = <?= json_encode($doctor_id) ?>;
        var branchId = <?= json_encode($doctor_branch_id) ?>;
        
        if (!doctorId) return;
        if (sidebarState.isUpdating && !forceUpdate) return;
        
        sidebarState.isUpdating = true;
        sidebarState.apiRequestCount++;
        
        var formData = new FormData();
        formData.append('doctor_id', doctorId);
        formData.append('branch_id', branchId);
        formData.append('hash', sidebarState.dataHash);
        formData.append('force_update', forceUpdate ? '1' : '0');
        
        // FIXED: Use the correct API endpoint
        var url = SIDEBAR_CONFIG.API_URL;
        
        fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            sidebarState.isUpdating = false;
            
            if (data.success) {
                // Check if data has changed
                if (data.has_changed && data.data) {
                    // Update UI with new data
                    updateSidebarBadges(data.data);
                    updateDoctorStatus(data.data);
                    
                    // Store new hash from server
                    if (data.hash) {
                        sidebarState.dataHash = data.hash;
                    }
                    sidebarState.hasInitialData = true;
                    sidebarState.lastUpdate = new Date();
                    
                    // Dispatch custom event for other components
                    var event = new CustomEvent('sidebarDataUpdated', {
                        detail: {
                            data: data.data,
                            timestamp: new Date().toISOString()
                        }
                    });
                    document.dispatchEvent(event);
                    
                    console.log('📊 Sidebar updated at:', sidebarState.lastUpdate.toLocaleTimeString());
                    
                } else if (data.has_changed === false) {
                    // No change - just update timestamp
                    var timeEl = document.getElementById('sidebarUpdateTime');
                    if (timeEl) {
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', {
                            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                        });
                        timeEl.innerHTML = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr;
                    }
                    sidebarState.hasInitialData = true;
                }
            } else {
                console.warn('Sidebar API error:', data.message || 'Unknown error');
                // If unauthorized, redirect to login
                if (data.message && (data.message.includes('Unauthorized') || data.message.includes('login'))) {
                    window.location.href = '/dispensary_system/frontend/pages/login.php';
                }
            }
        })
        .catch(function(error) {
            sidebarState.isUpdating = false;
            if (forceUpdate) {
                console.warn('Sidebar API error:', error.message);
            }
        });
    }

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    var sidebarUpdateInterval = null;

    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        
        // Initial fetch after 1 second
        setTimeout(function() {
            fetchSidebarData(true);
        }, 1000);
        
        // Check for changes every 3 seconds
        sidebarUpdateInterval = setInterval(function() {
            if (!sidebarState.isUpdating) {
                fetchSidebarData(false);
            }
        }, SIDEBAR_CONFIG.CHECK_INTERVAL);
        
        console.log('🔄 Sidebar auto-update started (check every ' + 
            SIDEBAR_CONFIG.CHECK_INTERVAL/1000 + 's)');
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
        console.log('🔄 Sidebar auto-update stopped');
    }

    // ================================================================
    // MANUAL REFRESH (Exposed for other pages)
    // ================================================================
    function refreshSidebarData() {
        fetchSidebarData(true);
        return true;
    }

    // ================================================================
    // EXPOSE FUNCTIONS
    // ================================================================
    window.refreshSidebarData = refreshSidebarData;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;
    window.getSidebarState = function() { return sidebarState; };
    window.getSidebarHash = function() { return sidebarState.dataHash; };

    // ================================================================
    // VISIBILITY CHANGE - Pause when tab is hidden
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
            // Force immediate refresh when tab becomes visible
            setTimeout(function() {
                fetchSidebarData(true);
            }, 500);
        }
    });

    // ================================================================
    // DOM READY
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to ensure DOM is fully rendered
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 1500);
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c👨‍⚕️ Braick Dispensary - Doctor Sidebar (API Integrated)', 
        'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($doctor_full_name) ?> (<?= htmlspecialchars($doctor_role) ?>)', 
        'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 
        'font-size:13px; color:#6EA8FE;');
    console.log('%c📊 Patients: <?= $patient_count ?>', 
        'font-size:13px; color:#9EC5FE;');
    console.log('%c📋 Pending Consultations: <?= $pending_consultations ?>', 
        'font-size:13px; color:#EF4444;');
    console.log('%c💊 Pending Prescriptions: <?= $pending_prescriptions ?>', 
        'font-size:13px; color:#D97706;');
    console.log('%c🔬 Pending Lab Tests: <?= $lab_count ?>', 
        'font-size:13px; color:#D97706;');
    console.log('%c❤️ Slogan: <?= $site_slogan ?>', 
        'font-size:13px; color:#34D399;');
    console.log('%c📌 FIXED: Consultations query includes "prescribed" status', 
        'font-size:13px; color:#34D399;');
    console.log('%c⚡ Smart Updates: Every 3s (only if data changed)', 
        'font-size:13px; color:#34D399;');
    console.log('%c🔄 API Endpoint: ' + SIDEBAR_CONFIG.API_URL, 
        'font-size:12px; color:#6EA8FE;');
    console.log('%c💡 Call window.refreshSidebarData() to manually update', 
        'font-size:12px; color:#6EA8FE;');
</script>