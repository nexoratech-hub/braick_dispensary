<?php
// ================================================================
// FILE: frontend/components/doctor_sidebar.php
// DOCTOR - SHARED SIDEBAR (FULLY FIXED)
// FULLY RESPONSIVE - ALL DEVICES
// BACKGROUND: BLUE | HOVER: BLUE LIGHT
// WITH AUTO-UPDATE EVERY 3 SECONDS
// SERVICES - SINGLE LINK (NO DROPDOWN)
// SIDEBAR TOGGLE - WORKS PERFECTLY ON MOBILE
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
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

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $doctor_id = $_SESSION['user_id'];
    $doctor_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // 1. Total Patients (distinct patients)
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 2. Pending Lab Tests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([$doctor_id]);
        $lab_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 3. Pending Referrals
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM referrals WHERE from_doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctor_id]);
        $referral_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 4. Today's Appointments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')");
        $stmt->execute([$doctor_id]);
        $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 5. Pending Consultations
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
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
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctor_id]);
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // ================================================================
        // 10. SERVICES COUNTS
        // ================================================================
        // Procedures count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures WHERE branch_id = ? AND is_active = 1");
        $stmt->execute([$doctor_branch_id]);
        $procedures_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Tools count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedure_tools WHERE branch_id = ? AND is_active = 1");
        $stmt->execute([$doctor_branch_id]);
        $tools_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Lab Tests count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE branch_id = ? AND is_active = 1");
        $stmt->execute([$doctor_branch_id]);
        $lab_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // 11. Expiring Medicines
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medications_inventory 
                WHERE branch_id = ? 
                AND status = 'active' 
                AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND expiry_date >= CURDATE()
            ");
            $stmt->execute([$doctor_branch_id]);
            $expiring_medicines = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $expiring_medicines = 0;
        }
        
    } catch (Exception $e) {
        // If error, keep counts as 0
    }
}

// ================================================================
// DOCTOR ONLINE STATUS
// ================================================================
$doctor_is_online = $_SESSION['is_online'] ?? 0;
$doctor_full_name = $_SESSION['full_name'] ?? 'Doctor';

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
// HANDLE AJAX REQUEST FOR SIDEBAR DATA (SELF-CONTAINED)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_sidebar_data') {
    header('Content-Type: application/json');
    
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    
    $response = [
        'success' => false,
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
        'toolsCount' => 0,
        'labTestsCount' => 0,
        'expiringMedicines' => 0,
        'doctorName' => '',
        'doctorStatus' => 'offline'
    ];
    
    if ($doctor_id > 0 && isset($db) && $db !== null) {
        try {
            // Doctor info
            $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND role = 'doctor'");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doctor) {
                $response['doctorName'] = $doctor['full_name'] ?? '';
                $response['doctorStatus'] = ($doctor['is_online'] ?? 0) ? 'online' : 'offline';
            }
            
            // 1. Total Patients
            $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
            $stmt->execute([$doctor_id]);
            $response['patientCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 2. Pending Lab Tests
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status IN ('pending', 'in_progress')");
            $stmt->execute([$doctor_id]);
            $response['labCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 3. Pending Referrals
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM referrals WHERE from_doctor_id = ? AND status = 'pending'");
            $stmt->execute([$doctor_id]);
            $response['referralCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 4. Today's Appointments
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')");
            $stmt->execute([$doctor_id]);
            $response['appointmentCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 5. Pending Consultations
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE doctor_id = ? 
                AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
                AND is_completed = 0
            ");
            $stmt->execute([$doctor_id]);
            $response['pendingConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 6. Completed Consultations
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE doctor_id = ? 
                AND status = 'completed'
                AND is_completed = 1
            ");
            $stmt->execute([$doctor_id]);
            $response['completedConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 7. Cancelled Consultations
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE doctor_id = ? 
                AND status = 'cancelled'
            ");
            $stmt->execute([$doctor_id]);
            $response['cancelledConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 8. Total Consultations
            $response['totalConsultations'] = $response['pendingConsultations'] + $response['completedConsultations'] + $response['cancelledConsultations'];
            
            // 9. Pending Prescriptions
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
            $stmt->execute([$doctor_id]);
            $response['pendingPrescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // ================================================================
            // 10. SERVICES COUNTS
            // ================================================================
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures WHERE branch_id = ? AND is_active = 1");
            $stmt->execute([$branch_id]);
            $response['proceduresCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedure_tools WHERE branch_id = ? AND is_active = 1");
            $stmt->execute([$branch_id]);
            $response['toolsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE branch_id = ? AND is_active = 1");
            $stmt->execute([$branch_id]);
            $response['labTestsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // 11. Expiring Medicines
            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM medications_inventory 
                    WHERE branch_id = ? 
                    AND status = 'active' 
                    AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND expiry_date >= CURDATE()
                ");
                $stmt->execute([$branch_id]);
                $response['expiringMedicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {
                $response['expiringMedicines'] = 0;
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
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - FULLY FIXED FOR MOBILE
       ================================================================ */
    
    /* Sidebar Container - CRITICAL: Must be visible on all devices */
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
    
    /* Sidebar Open State - CRITICAL: !important ensures it works */
    .sidebar.open {
        transform: translateX(0) !important;
    }
    
    /* Scrollbar */
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0A3D7A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #9EC5FE; }
    
    /* ================================================================
       OVERLAY - For mobile
       ================================================================ */
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
    
    /* ================================================================
       SIDEBAR BRAND / HEADER
       ================================================================ */
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
    
    /* ================================================================
       SIDEBAR CLOSE BUTTON (Mobile)
       ================================================================ */
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
    
    /* ================================================================
       DOCTOR INFO
       ================================================================ */
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
    
    /* ================================================================
       NAVIGATION
       ================================================================ */
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
    
    /* ================================================================
       SIDEBAR LINKS
       ================================================================ */
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
    
    /* ================================================================
       BADGES ON SIDEBAR
       ================================================================ */
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
    
    /* ================================================================
       LOGOUT LINK
       ================================================================ */
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
    
    /* ================================================================
       BADGE UPDATE ANIMATION
       ================================================================ */
    .badge-update {
        animation: badgePop 0.3s ease;
    }
    
    @keyframes badgePop {
        0% { transform: scale(0.5); opacity: 0; }
        70% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ================================================================
       LIVE UPDATE INDICATOR
       ================================================================ */
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
    
    /* ================================================================
       SIDEBAR STATUS (Footer)
       ================================================================ */
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
    
    /* ================================================================
       RESPONSIVE BREAKPOINTS
       ================================================================ */
    
    /* Desktop: Sidebar always visible */
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
    
    /* Tablet and below: Sidebar hidden by default */
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
        .sidebar-doctor-info .doctor-name {
            font-size: 0.75rem;
        }
        .sidebar-doctor-info .doctor-avatar {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
        .sidebar-status {
            padding: 8px 14px;
        }
    }
    
    /* Mobile phones */
    @media (max-width: 768px) {
        .sidebar {
            width: 300px;
            transform: translateX(-100%);
            border-radius: 0 16px 16px 0;
        }
        .sidebar.open {
            transform: translateX(0) !important;
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
            gap: 8px;
        }
        .sidebar-link i {
            width: 16px;
            font-size: 0.75rem;
        }
        .sidebar-link .badge {
            font-size: 0.5rem;
            padding: 1px 6px;
        }
        .sidebar-nav .nav-label {
            font-size: 0.45rem;
        }
        .sidebar-doctor-info {
            padding: 10px 12px;
        }
        .sidebar-doctor-info .doctor-name {
            font-size: 0.7rem;
        }
        .sidebar-doctor-info .doctor-avatar {
            width: 28px;
            height: 28px;
            font-size: 0.7rem;
        }
        .sidebar-status {
            padding: 6px 12px;
        }
        .sidebar-status .status-text {
            font-size: 0.6rem;
        }
        .sidebar-status .update-time {
            font-size: 0.5rem;
        }
    }
    
    /* Small phones */
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
            transform: translateX(-100%);
            border-radius: 0 20px 20px 0;
        }
        .sidebar.open {
            transform: translateX(0) !important;
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
            padding: 0 8px;
        }
        .sidebar-doctor-info {
            padding: 8px 10px;
        }
        .sidebar-doctor-info .doctor-name {
            font-size: 0.65rem;
        }
        .sidebar-doctor-info .doctor-avatar {
            width: 24px;
            height: 24px;
            font-size: 0.6rem;
        }
        .sidebar-status {
            padding: 4px 10px;
        }
        .sidebar-status .status-text {
            font-size: 0.55rem;
        }
        .sidebar-status .update-time {
            font-size: 0.45rem;
        }
        .sidebar-status .status-dot {
            width: 6px;
            height: 6px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY (Mobile) -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR - DOCTOR PANEL -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- ================================================================ -->
    <!-- BRAND / HEADER -->
    <!-- ================================================================ -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Doctor Panel</p>
            </div>
            <!-- Close button for mobile -->
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- DOCTOR INFO -->
    <!-- ================================================================ -->
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
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav">
        
        <!-- ============================================================ -->
        <!-- MAIN MENU -->
        <!-- ============================================================ -->
        <div class="nav-label">Main Menu</div>
        
        <a href="../doctor/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ============================================================ -->
        <!-- PATIENTS -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Patients</div>
        
        <a href="../doctor/my_patients.php" class="sidebar-link <?= isActive('my_patients.php') ?>">
            <i class="fas fa-users"></i> My Patients
            <span class="badge" id="patientCount"><?= $patient_count ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- CLINICAL -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Clinical</div>
        
        <a href="../doctor/view_prescriptions.php" class="sidebar-link <?= isActive('view_prescriptions.php') ?>">
            <i class="fas fa-file-prescription"></i> Prescriptions
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge warning" id="prescriptionBadge"><?= $pending_prescriptions ?></span>
            <?php else: ?>
                <span class="badge" id="prescriptionBadge">0</span>
            <?php endif; ?>
        </a>
        
        <a href="../doctor/lab_results.php" class="sidebar-link <?= isActive('lab_results.php') ?>">
            <i class="fas fa-flask"></i> Lab Results
            <?php if ($lab_count > 0): ?>
                <span class="badge warning" id="labCount"><?= $lab_count ?></span>
            <?php else: ?>
                <span class="badge" id="labCount">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- CONSULTATIONS -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Consultations</div>
        
        <a href="../doctor/consultations.php" class="sidebar-link <?= isActive('consultations.php') ?>">
            <i class="fas fa-stethoscope"></i> Consultations
            <?php if ($pending_consultations > 0): ?>
                <span class="badge danger" id="pendingConsultBadge"><?= $pending_consultations ?></span>
            <?php else: ?>
                <span class="badge" id="pendingConsultBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- SERVICES - SINGLE LINK (NO DROPDOWN) -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Resources</div>
        
        <a href="../doctor/services.php" class="sidebar-link <?= isActive('services.php') ?>">
            <i class="fas fa-cog"></i> Services
            <?php 
                $total_services = $procedures_count + $tools_count + $lab_tests_count;
                if ($total_services > 0): 
            ?>
                <span class="badge purple" id="servicesTotalBadge"><?= $total_services ?></span>
            <?php else: ?>
                <span class="badge" id="servicesTotalBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- REFERRALS -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Referrals</div>
        
        <a href="../doctor/referrals.php" class="sidebar-link <?= isActive('referrals.php') ?>">
            <i class="fas fa-share-alt"></i> Referrals
            <?php if ($referral_count > 0): ?>
                <span class="badge warning" id="referralCount"><?= $referral_count ?></span>
            <?php else: ?>
                <span class="badge" id="referralCount">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- SCHEDULE -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Schedule</div>
        
        <a href="../doctor/appointments.php" class="sidebar-link <?= isActive('appointments.php') ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <?php if ($appointment_count > 0): ?>
                <span class="badge blue" id="appointmentCount"><?= $appointment_count ?></span>
            <?php else: ?>
                <span class="badge" id="appointmentCount">0</span>
            <?php endif; ?>
        </a>
        
        <a href="../doctor/documents.php" class="sidebar-link <?= isActive('documents.php') ?>">
            <i class="fas fa-folder"></i> Documents
        </a>
        
        <!-- ============================================================ -->
        <!-- ACCOUNT -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Account</div>
        
        <a href="../doctor/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="../../../logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- ================================================================ -->
    <!-- SIDEBAR STATUS (Footer) -->
    <!-- ================================================================ -->
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
<!-- JAVASCRIPT - FULL SIDEBAR FUNCTIONALITY -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE - FULLY FIXED FOR ALL DEVICES
    // ================================================================
    (function() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            initSidebar();
        }
        
        function initSidebar() {
            console.log('🔧 Initializing sidebar...');
            
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            // Create overlay if not exists
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
                console.log('✅ Sidebar overlay created');
            }
            
            if (!sidebar) {
                console.error('❌ Sidebar element not found!');
                return;
            }
            
            // Toggle function
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                console.log('🔓 Sidebar opened');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                console.log('🔒 Sidebar closed');
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            // ================================================================
            // EVENT: Toggle button (hamburger icon)
            // ================================================================
            if (toggleBtn) {
                // Remove all existing listeners
                var newToggle = toggleBtn.cloneNode(true);
                toggleBtn.parentNode.replaceChild(newToggle, toggleBtn);
                var freshToggle = document.getElementById('sidebarToggle');
                
                freshToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🔘 Hamburger clicked!');
                    toggleSidebar();
                });
                console.log('✅ Toggle button attached');
            } else {
                console.warn('⚠️ Toggle button not found - trying fallback');
                // Try to find by class
                var fallbackBtn = document.querySelector('.sidebar-toggle-btn');
                if (fallbackBtn) {
                    fallbackBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleSidebar();
                    });
                    console.log('✅ Fallback toggle button attached');
                }
            }
            
            // ================================================================
            // EVENT: Close button (X icon in sidebar)
            // ================================================================
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
                console.log('✅ Close button attached');
            }
            
            // ================================================================
            // EVENT: Close sidebar when clicking overlay
            // ================================================================
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
                console.log('✅ Overlay click handler attached');
            }
            
            // ================================================================
            // EVENT: Close sidebar with ESC key
            // ================================================================
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // ================================================================
            // EVENT: Auto-close on window resize (desktop)
            // ================================================================
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // ================================================================
            // LOG
            // ================================================================
            console.log('✅ Sidebar fully initialized!');
            console.log('📱 Sidebar element:', sidebar);
            console.log('🔘 Toggle button:', document.getElementById('sidebarToggle'));
            console.log('❌ Close button:', document.getElementById('sidebarCloseBtn'));
            console.log('📐 Window width:', window.innerWidth);
            console.log('📱 Is mobile:', window.innerWidth <= 1024);
            
            // ================================================================
            // TEST: Open sidebar after 1 second to verify it works
            // ================================================================
            // Uncomment to test automatically
            // setTimeout(function() {
            //     console.log('🧪 Testing sidebar open...');
            //     openSidebar();
            //     setTimeout(function() {
            //         console.log('🧪 Testing sidebar close...');
            //         closeSidebar();
            //     }, 2000);
            // }, 1000);
        }
    })();

    // ================================================================
    // UPDATE SIDEBAR BADGES
    // ================================================================
    function updateSidebarBadges(data) {
        // Patient Count
        if (data.patientCount !== undefined) {
            var el = document.getElementById('patientCount');
            if (el) {
                el.textContent = data.patientCount;
                el.style.opacity = data.patientCount === 0 ? '0.6' : '1';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // Lab Count (pending)
        if (data.labCount !== undefined) {
            var el = document.getElementById('labCount');
            if (el) {
                el.textContent = data.labCount;
                el.className = data.labCount > 0 ? 'badge warning' : 'badge';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // Referral Count
        if (data.referralCount !== undefined) {
            var el = document.getElementById('referralCount');
            if (el) {
                el.textContent = data.referralCount;
                el.className = data.referralCount > 0 ? 'badge warning' : 'badge';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // Appointment Count
        if (data.appointmentCount !== undefined) {
            var el = document.getElementById('appointmentCount');
            if (el) {
                el.textContent = data.appointmentCount;
                el.className = data.appointmentCount > 0 ? 'badge blue' : 'badge';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // Pending Consultations
        if (data.pendingConsultations !== undefined) {
            var el = document.getElementById('pendingConsultBadge');
            if (el) {
                el.textContent = data.pendingConsultations;
                el.className = data.pendingConsultations > 0 ? 'badge danger' : 'badge';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // Pending Prescriptions
        if (data.pendingPrescriptions !== undefined) {
            var el = document.getElementById('prescriptionBadge');
            if (el) {
                el.textContent = data.pendingPrescriptions;
                el.className = data.pendingPrescriptions > 0 ? 'badge warning' : 'badge';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // SERVICES TOTAL BADGE
        if (data.proceduresCount !== undefined && data.toolsCount !== undefined && data.labTestsCount !== undefined) {
            var total = data.proceduresCount + data.toolsCount + data.labTestsCount;
            var el = document.getElementById('servicesTotalBadge');
            if (el) {
                el.textContent = total;
                el.className = total > 0 ? 'badge purple' : 'badge';
                el.classList.remove('badge-update');
                void el.offsetWidth;
                el.classList.add('badge-update');
            }
        }
        
        // Update timestamp
        var timeEl = document.getElementById('sidebarUpdateTime');
        if (timeEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            timeEl.innerHTML = '<span class="sidebar-live-indicator"><span class="dot"></span> Live ' + timeStr;
        }
    }

    // ================================================================
    // UPDATE DOCTOR STATUS
    // ================================================================
    function updateDoctorStatus(data) {
        var statusDot = document.getElementById('sidebarStatusDot');
        var statusText = document.getElementById('sidebarStatusText');
        var footerDot = document.getElementById('sidebarFooterDot');
        var footerText = document.getElementById('sidebarFooterText');
        
        if (data.doctorStatus === 'online') {
            if (statusDot) { statusDot.className = 'status-dot online'; }
            if (statusText) { statusText.textContent = 'Online'; statusText.style.color = '#34D399'; }
            if (footerDot) { footerDot.className = 'status-dot online'; }
            if (footerText) { footerText.textContent = 'Online'; footerText.style.color = '#34D399'; }
        } else {
            if (statusDot) { statusDot.className = 'status-dot offline'; }
            if (statusText) { statusText.textContent = 'Offline'; statusText.style.color = '#94A3B8'; }
            if (footerDot) { footerDot.className = 'status-dot offline'; }
            if (footerText) { footerText.textContent = 'Offline'; footerText.style.color = '#94A3B8'; }
        }
    }

    // ================================================================
    // FETCH LIVE DATA - SELF-CONTAINED
    // ================================================================
    function fetchSidebarData() {
        var doctorId = <?= json_encode($_SESSION['user_id'] ?? 0) ?>;
        var branchId = <?= json_encode($_SESSION['branch_id'] ?? 1) ?>;
        
        if (!doctorId) return;
        
        var formData = new FormData();
        formData.append('action', 'get_sidebar_data');
        formData.append('doctor_id', doctorId);
        formData.append('branch_id', branchId);
        
        // Use the same file (self-contained)
        var url = window.location.href;
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                updateSidebarBadges(data);
                updateDoctorStatus(data);
            }
        })
        .catch(function(error) {
            // Silent fail - will retry in 3 seconds
            console.log('Doctor Sidebar: Waiting for next update cycle');
        });
    }

    // ================================================================
    // START AUTO-UPDATE (every 3 seconds)
    // ================================================================
    var sidebarUpdateInterval = null;
    var isSidebarUpdating = false;

    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // Initial update after 1 second
        setTimeout(function() {
            fetchSidebarData();
        }, 1000);
        // Then every 3 seconds
        sidebarUpdateInterval = setInterval(function() {
            if (!isSidebarUpdating) {
                isSidebarUpdating = true;
                fetchSidebarData();
                setTimeout(function() {
                    isSidebarUpdating = false;
                }, 100);
            }
        }, 3000);
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
    }

    // Start/stop updates based on page visibility
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    // Start updates after page load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    // ================================================================
    // EXPOSE FUNCTIONS FOR DEBUGGING
    // ================================================================
    window.updateSidebarBadges = updateSidebarBadges;
    window.updateDoctorStatus = updateDoctorStatus;
    window.fetchSidebarData = fetchSidebarData;
    window.startSidebarAutoUpdate = startSidebarAutoUpdate;
    window.stopSidebarAutoUpdate = stopSidebarAutoUpdate;
    window.toggleSidebar = function() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
            var overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            }
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
    };

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c👨‍⚕️ Braick Dispensary - Doctor Sidebar', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Full Doctor Sidebar with all menu items', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#6EA8FE;');
    console.log('%c📱 Fully responsive - all devices', 'font-size:13px; color:#F59E0B;');
    console.log('%c👤 Doctor: <?= htmlspecialchars($doctor_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Patients: <?= $patient_count ?>', 'font-size:13px; color:#9EC5FE;');
    console.log('%c📋 Pending Consultations: <?= $pending_consultations ?>', 'font-size:13px; color:#EF4444;');
    console.log('%c💊 Pending Prescriptions: <?= $pending_prescriptions ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔬 Pending Lab Tests: <?= $lab_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Sidebar toggle: Click hamburger icon ☰ to open/close', 'font-size:13px; color:#34D399;');
</script>