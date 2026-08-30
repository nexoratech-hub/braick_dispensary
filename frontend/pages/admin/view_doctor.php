<?php
// ================================================================
// FILE: frontend/pages/admin/view_doctor.php
// SUPER ADMIN - VIEW DOCTOR COMPLETE DASHBOARD
// WITH ALL PATIENT DATA, LAB TESTS, RESULTS, DIAGNOSIS, 
// PRESCRIPTIONS, PROCEDURES, TOOLS & BILLS
// WITH VIEW, EDIT & DELETE BUTTONS FOR EACH SECTION
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
// FIXED: Blue background on table headers only
// FIXED: Uses bills table instead of patient_bills
// FIXED: Uses prescriptions/prescription_items for revenue
// FIXED: Functions defined before use
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// HELPER FUNCTIONS - DEFINED BEFORE USE
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#0891B2', '#2563EB'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger',
        'completed' => 'success',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'in_progress' => 'info',
        'scheduled' => 'info',
        'assigned' => 'primary',
        'active' => 'success',
        'inactive' => 'danger',
        'online' => 'success',
        'offline' => 'danger',
        'with_doctor' => 'primary',
        'lab_test' => 'info',
        'lab_completed' => 'success',
        'prescribed' => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

// ================================================================
// GET DOCTOR ID
// ================================================================
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($doctor_id <= 0) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET DOCTOR DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role = 'doctor'
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id . '&error=not_found');
    exit;
}

// ================================================================
// SET DEFAULTS FOR MISSING FIELDS
// ================================================================
$doctor['full_name'] = $doctor['full_name'] ?? 'Unknown Doctor';
$doctor['email'] = $doctor['email'] ?? 'No email provided';
$doctor['phone'] = $doctor['phone'] ?? 'No phone provided';
$doctor['specialty'] = $doctor['specialty'] ?? 'General Practitioner';
$doctor['branch_name'] = $doctor['branch_name'] ?? 'Not Assigned';
$doctor['status'] = $doctor['status'] ?? 'active';
$doctor['is_online'] = $doctor['is_online'] ?? 0;
$doctor['created_at'] = $doctor['created_at'] ?? date('Y-m-d H:i:s');
$doctor['username'] = $doctor['username'] ?? 'N/A';

// ================================================================
// GET ALL DOCTOR STATISTICS
// ================================================================

// 1. Total Patients
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Pending Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Pending Lab Tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Revenue Generated - FIXED: Uses bills and bill_items
$stmt = $db->prepare("
    SELECT COALESCE(SUM(bi.total_price), 0) as revenue 
    FROM bill_items bi
    INNER JOIN bills b ON bi.bill_id = b.id
    INNER JOIN visits v ON b.visit_id = v.id
    WHERE v.doctor_id = ? AND b.status = 'paid'
");
$stmt->execute([$doctor_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 7. Today's Appointments
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, p.patient_id 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_date
");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL PATIENTS WITH COMPLETE DATA
// ================================================================
$all_patients = [];
$stmt = $db->prepare("
    SELECT DISTINCT 
        p.id,
        p.patient_id,
        p.full_name,
        p.gender,
        p.date_of_birth,
        p.phone,
        p.email,
        p.address,
        p.blood_group,
        p.allergies,
        p.created_at,
        p.assigned_doctor_id,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as total_visits,
        (SELECT COUNT(*) FROM lab_tests lt 
         JOIN visits v ON lt.visit_id = v.id 
         WHERE v.patient_id = p.id AND lt.doctor_id = ?) as total_lab_tests,
        (SELECT COUNT(*) FROM prescriptions pr 
         JOIN visits v ON pr.visit_id = v.id 
         WHERE v.patient_id = p.id AND pr.doctor_id = ?) as total_prescriptions,
        (SELECT COUNT(*) FROM bills b 
         JOIN visits v ON b.visit_id = v.id 
         WHERE v.patient_id = p.id) as total_bills
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name ASC
");
$stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id]);
$all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL VISITS WITH DETAILS
// ================================================================
$all_visits = [];
$stmt = $db->prepare("
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as doctor_name
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.doctor_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL LAB TESTS
// ================================================================
$all_lab_tests = [];
$stmt = $db->prepare("
    SELECT 
        lt.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        v.visit_number,
        u.full_name as technician_name
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.lab_technician_id = u.id
    WHERE lt.doctor_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL PRESCRIPTIONS
// ================================================================
$all_prescriptions = [];
$stmt = $db->prepare("
    SELECT 
        pr.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        v.visit_number,
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) as item_count
    FROM prescriptions pr
    LEFT JOIN visits v ON pr.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE pr.doctor_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL PROCEDURES & TOOLS - FIXED: Uses bills table
// ================================================================
$all_procedures = [];
$stmt = $db->prepare("
    SELECT 
        bi.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        b.bill_number,
        v.visit_number
    FROM bill_items bi
    LEFT JOIN bills b ON bi.bill_id = b.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE bi.item_type IN ('procedure', 'tool')
    AND v.doctor_id = ?
    ORDER BY bi.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL BILLS - FIXED: Uses bills table
// ================================================================
$all_bills = [];
$stmt = $db->prepare("
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as created_by_name,
        v.visit_number
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE v.doctor_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET WEEKLY VISITS CHART DATA
// ================================================================
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$doctor_id]);
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    $found = false;
    foreach ($weekly_data as $data) {
        if ($data['date'] == $date) {
            $chart_values[] = (int)$data['count'];
            $found = true;
            break;
        }
    }
    if (!$found) $chart_values[] = 0;
}

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// SIDEBAR STATISTICS
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests_total = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests_total = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests_total = 0;
}

$pending_prescriptions_total = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions_total = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions_total = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Doctor - <?= htmlspecialchars($doctor['full_name']) ?></title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           VIEW DOCTOR - BLUE THEME (Headers only)
           ================================================================ */
        
        :root {
            --primary-blue: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --danger: #DC2626;
            --warning: #D97706;
            --purple: #7C3AED;
            --teal: #0D9488;
            --orange: #F59E0B;
            
            --bg-card: #FFFFFF;
            --bg-body: #F0F4F8;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
        }
        
        [data-theme="dark"] {
            --bg-card: #1E293B;
            --bg-body: #0F172A;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
        }
        
        /* ================================================================
           TOP NAV
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary-blue);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary-blue);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary-blue);
            background: var(--bg-card);
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary-blue);
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           SECTION TITLE
           ================================================================ */
        .section-header-clean {
            padding: 12px 0 12px 0;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-header-clean .section-title {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header-clean .section-title i {
            color: var(--primary-blue);
        }
        
        .section-header-clean .section-badge {
            background: var(--bg-body);
            color: var(--text-secondary);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
        }
        
        /* ================================================================
           DOCTOR HEADER
           ================================================================ */
        .doctor-header {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .doctor-header:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.1);
        }
        
        .doctor-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .doctor-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #FFFFFF;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        }
        
        .doctor-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .doctor-specialty-badge {
            display: inline-block;
            background: #E8F0FE;
            color: var(--primary-blue);
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        [data-theme="dark"] .doctor-specialty-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .doctor-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }
        
        .doctor-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 4px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .doctor-meta span i {
            font-size: 0.8rem;
            color: var(--primary-blue);
        }
        
        .online-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse-dot 1.5s infinite;
        }
        
        .offline-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #94A3B8;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .badge-status {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #FFFFFF;
            border: none;
        }
        
        .badge-status.success { background: var(--success); }
        .badge-status.danger { background: var(--danger); }
        .badge-status.warning { background: var(--warning); }
        
        .admin-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            color: #FFFFFF !important;
        }
        
        .btn-blue {
            background: var(--primary-blue);
            color: #FFFFFF !important;
        }
        .btn-blue:hover {
            background: #0A4CA8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-green {
            background: var(--success);
            color: #FFFFFF !important;
        }
        .btn-green:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-red {
            background: var(--danger);
            color: #FFFFFF !important;
        }
        .btn-red:hover {
            background: #B91C1C;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary) !important;
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary-blue);
            color: var(--primary-blue) !important;
        }
        
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ================================================================
           STATISTICS CARDS - BLUE BACKGROUND
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card-blue {
            background: var(--primary-gradient-strong);
            border-radius: 14px;
            padding: 18px 20px;
            border: none;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            cursor: default;
            box-shadow: 0 4px 15px rgba(10, 76, 168, 0.25);
        }
        
        .stat-card-blue:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(10, 76, 168, 0.35);
        }
        
        .stat-card-blue .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.15);
            color: #FFFFFF;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat-card-blue .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #FFFFFF !important;
        }
        
        .stat-card-blue .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8) !important;
            font-weight: 500;
            margin-top: 2px;
        }
        
        /* ================================================================
           DASHBOARD GRID
           ================================================================ */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card-clean {
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card-clean:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .card-clean .card-body {
            padding: 16px 18px;
        }
        
        .card-clean .card-body .empty-state {
            text-align: center;
            padding: 15px;
            color: var(--text-secondary);
        }
        
        .card-clean .card-body .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 6px;
        }
        
        .card-clean .card-body .empty-state p {
            font-size: 0.85rem;
        }
        
        /* Chart Container */
        .chart-container {
            height: 110px !important;
            max-height: 110px !important;
        }
        .chart-container canvas {
            height: 100% !important;
            max-height: 110px !important;
        }
        
        /* Appointments */
        .appointments-container {
            max-height: 160px;
            overflow-y: auto;
        }
        .appointments-container::-webkit-scrollbar { width: 4px; }
        .appointments-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .appointments-container::-webkit-scrollbar-thumb { background: var(--primary-blue); border-radius: 4px; }
        
        .appointment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .appointment-item:hover { background: var(--bg-body); }
        .appointment-item:last-child { border-bottom: none; }
        .appointment-time { font-weight: 600; font-size: 0.8rem; min-width: 55px; }
        .appointment-patient .name { font-weight: 500; font-size: 0.85rem; }
        .appointment-patient .phone { font-size: 0.65rem; color: var(--text-secondary); }
        .appointment-status { font-size: 0.65rem; font-weight: 600; padding: 2px 10px; border-radius: 12px; }
        .appointment-status.scheduled { background: #E8F0FE; color: var(--primary-blue); }
        .appointment-status.confirmed { background: #D1FAE5; color: var(--success); }
        .appointment-status.completed { background: #D1FAE5; color: var(--success); }
        .appointment-status.cancelled { background: #FEE2E2; color: var(--danger); }
        .appointment-status.pending { background: #FEF3C7; color: var(--warning); }
        
        /* ================================================================
           DATA TABLE - BLUE HEADERS ONLY
           ================================================================ */
        .table-container {
            overflow-x: auto;
            padding: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient-strong);
            color: white;
            font-weight: 700;
            padding: 12px 14px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 3px solid var(--primary-dark);
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-size: 0.8rem;
            min-height: 60px;
        }
        
        .data-table tbody tr {
            transition: background 0.2s ease;
            min-height: 60px;
        }
        
        .data-table tbody tr:nth-child(even) { background: var(--bg-body); }
        .data-table tbody tr:hover { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        [data-theme="dark"] .data-table tbody tr:hover { background: #1E3A5F; }
        
        /* ================================================================
           ACTION BUTTONS - VERTICAL IN ONE ROW
           ================================================================ */
        .action-btns {
            display: flex;
            flex-direction: row;
            gap: 4px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            min-height: 30px;
        }
        
        .btn-action i { font-size: 0.65rem; }
        
        .btn-action.view {
            background: #E8F0FE;
            color: #0B5ED7;
            border: 1px solid rgba(11, 94, 215, 0.15);
        }
        .btn-action.view:hover {
            background: #0B5ED7;
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-action.edit {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.15);
        }
        .btn-action.edit:hover {
            background: #D97706;
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-action.delete {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid rgba(220, 38, 38, 0.15);
        }
        .btn-action.delete:hover {
            background: #DC2626;
            color: white;
            transform: translateY(-1px);
        }
        
        [data-theme="dark"] .btn-action.view {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: rgba(59, 130, 246, 0.2);
        }
        [data-theme="dark"] .btn-action.view:hover {
            background: #0B5ED7;
            color: white;
        }
        
        [data-theme="dark"] .btn-action.edit {
            background: #3D2E0A;
            color: #FBBF24;
            border-color: rgba(251, 191, 36, 0.2);
        }
        [data-theme="dark"] .btn-action.edit:hover {
            background: #D97706;
            color: white;
        }
        
        [data-theme="dark"] .btn-action.delete {
            background: #3A1A1A;
            color: #F87171;
            border-color: rgba(248, 113, 113, 0.2);
        }
        [data-theme="dark"] .btn-action.delete:hover {
            background: #DC2626;
            color: white;
        }
        
        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            color: white;
        }
        .badge-info { background: #0B5ED7; }
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        .badge-secondary { background: #64748B; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary-blue); font-weight: 600; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.warning { background: #D97706; color: #1E293B; }
        .toast-custom.info { background: #0B5ED7; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar { transform: translateX(-100%) !important; }
            .sidebar.open { transform: translateX(0) !important; }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .doctor-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .doctor-info { flex-wrap: wrap; }
            .doctor-avatar-large { width: 60px; height: 60px; font-size: 1.5rem; }
            .doctor-name { font-size: 1.2rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .chart-container { height: 90px !important; }
            .appointments-container { max-height: 140px; }
            .data-table td { font-size: 0.7rem; padding: 8px 8px; }
            .data-table thead th { font-size: 0.5rem; padding: 6px 8px; }
            .action-btns { flex-direction: row; flex-wrap: wrap; gap: 3px; }
            .btn-action { font-size: 0.55rem; padding: 3px 6px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .doctor-meta span { font-size: 0.7rem; padding: 2px 8px; }
            .stat-card-blue .stat-number { font-size: 1.2rem; }
            .chart-container { height: 80px !important; }
            .data-table td { font-size: 0.6rem; padding: 6px 6px; }
            .btn-action { font-size: 0.5rem; padding: 2px 5px; }
            .btn-action i { font-size: 0.5rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        /* Sidebar */
        .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 270px !important;
            background: #0B4EA8 !important;
            color: white !important;
            z-index: 50 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            transition: transform 0.3s ease-in-out !important;
            transform: translateX(0) !important;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15) !important;
        }
        
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 45;
            display: none;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        
        #sidebarOverlay.active {
            display: block !important;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div style="padding:18px 16px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;position:sticky;top:0;z-index:5;">
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= $logo_url ?>" alt="Braick Logo" style="width:42px;height:42px;border-radius:10px;object-fit:cover;background:white;padding:4px;border:2px solid rgba(255,255,255,0.1);"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p style="color:white;font-weight:700;font-size:0.95rem;line-height:1.2;margin:0;">Braick Dispensary</p>
                <p style="color:#9EC5FE;font-size:0.65rem;font-weight:500;margin:0;">Super Admin</p>
            </div>
        </div>
    </div>
    
    <div style="padding:10px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)" style="width:100%;padding:7px 10px;border-radius:8px;border:none;background:rgba(255,255,255,0.12);color:white;font-size:0.75rem;cursor:pointer;outline:none;transition:all 0.3s ease;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 12 12%22%3E%3Cpath fill=%22white%22 d=%22M6 8L1 3h10z%22/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?> style="background:#0B4EA8;color:white;padding:8px;">
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav style="padding:10px 8px 20px;">
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/employees.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-users"></i> Employees
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/patients.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-injured"></i> Patients
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Modules</div>
        
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-md"></i> Doctors
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-prescription"></i> Pharmacy
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-headset"></i> Reception
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-flask"></i> Laboratory
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-cash-register"></i> Cashier
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Management</div>
        
        <a href="/dispensary_system/frontend/pages/admin/branches.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-store-alt"></i> Branches
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-chart-bar"></i> Reports
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Account</div>
        
        <a href="/dispensary_system/frontend/pages/admin/profile.php" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;border-top:2px solid rgba(255,255,255,0.08);padding-top:10px;margin-top:6px;color:#FCA5A5;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent;width:auto;">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3 no-print">
        <span class="branch-badge-display" style="background:var(--primary-bg);color:var(--primary-blue);padding:4px 14px;border-radius:20px;font-size:0.78rem;font-weight:500;display:inline-flex;align-items:center;gap:6px;border:1px solid var(--primary-light);">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2236%22 height=%2236%22%3E%3Crect width=%2236%22 height=%2236%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2218%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- DOCTOR HEADER -->
    <!-- ================================================================ -->
    <div class="doctor-header animate-fade-in-up">
        <div class="doctor-info">
            <div class="doctor-avatar-large" style="background: <?= getUserColor($doctor['full_name']) ?>;">
                <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
            </div>
            <div>
                <div class="doctor-name">
                    <?= htmlspecialchars($doctor['full_name']) ?>
                    <span class="doctor-specialty-badge"><?= htmlspecialchars($doctor['specialty']) ?></span>
                </div>
                <div class="doctor-meta">
                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name']) ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email']) ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone']) ?></span>
                    <span>
                        <?php if ($doctor['is_online']): ?>
                            <span class="online-dot"></span> Online
                        <?php else: ?>
                            <span class="offline-dot"></span> Offline
                        <?php endif; ?>
                    </span>
                    <span><i class="fas fa-calendar-alt"></i> Joined: <?= date('M d, Y', strtotime($doctor['created_at'])) ?></span>
                    <span><span class="badge-status <?= $doctor['status'] === 'active' ? 'success' : 'danger' ?>">
                        <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($doctor['status']) ?>
                    </span></span>
                </div>
            </div>
        </div>
        <div class="admin-actions">
            <a href="edit_employee.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button onclick="deactivateDoctor(<?= $doctor['id'] ?>)" class="btn btn-red btn-sm">
                <i class="fas fa-user-slash"></i> Deactivate
            </button>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> All Doctors
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - BLUE BACKGROUND -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card-blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-number"><?= number_format($total_patients) ?></p>
                <p class="stat-label">Total Patients</p>
            </div>
        </div>
        <div class="stat-card-blue">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div>
                <p class="stat-number"><?= number_format($today_visits) ?></p>
                <p class="stat-label">Today's Visits</p>
            </div>
        </div>
        <div class="stat-card-blue">
            <div class="stat-icon"><i class="fas fa-notes-medical"></i></div>
            <div>
                <p class="stat-number"><?= number_format($total_visits) ?></p>
                <p class="stat-label">Total Visits</p>
            </div>
        </div>
        <div class="stat-card-blue">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-number"><?= number_format($pending_prescriptions) ?></p>
                <p class="stat-label">Pending Prescriptions</p>
            </div>
        </div>
        <div class="stat-card-blue">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-number"><?= number_format($pending_lab_tests) ?></p>
                <p class="stat-label">Pending Lab Tests</p>
            </div>
        </div>
        <div class="stat-card-blue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-number">TSh <?= number_format($revenue) ?></p>
                <p class="stat-label">Revenue Generated</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHART & APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="dashboard-grid mb-4">
        <div class="card-clean animate-fade-in-up">
            <div class="section-header-clean">
                <h4 class="section-title"><i class="fas fa-chart-line"></i> Weekly Visits</h4>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="visitsChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card-clean animate-fade-in-up">
            <div class="section-header-clean">
                <h4 class="section-title"><i class="fas fa-calendar-check"></i> Today's Appointments</h4>
                <span class="section-badge"><?= count($today_appointments) ?></span>
            </div>
            <div class="card-body">
                <div class="appointments-container">
                    <?php if (count($today_appointments) > 0): ?>
                        <?php foreach ($today_appointments as $appt): ?>
                            <div class="appointment-item">
                                <span class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                                <div class="appointment-patient">
                                    <span class="name"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                    <span class="phone"><?= htmlspecialchars($appt['patient_id'] ?? '') ?></span>
                                </div>
                                <span class="appointment-status <?= $appt['status'] ?? 'scheduled' ?>">
                                    <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 1. ALL PATIENTS - BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="card-clean animate-fade-in-up mb-4">
        <div class="section-header-clean">
            <h4 class="section-title"><i class="fas fa-users"></i> All Patients</h4>
            <span class="section-badge"><?= count($all_patients) ?></span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>ID</th>
                        <th>Gender</th>
                        <th>Blood Group</th>
                        <th>Visits</th>
                        <th>Lab Tests</th>
                        <th>Prescriptions</th>
                        <th>Bills</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_patients) > 0): ?>
                        <?php $i = 1; foreach ($all_patients as $patient): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($patient['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($patient['patient_id']) ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $patient['total_visits'] ?? 0 ?></span></td>
                                <td><span class="badge badge-purple"><?= $patient['total_lab_tests'] ?? 0 ?></span></td>
                                <td><span class="badge badge-teal"><?= $patient['total_prescriptions'] ?? 0 ?></span></td>
                                <td><span class="badge badge-success"><?= $patient['total_bills'] ?? 0 ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItem('patient', <?= $patient['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center py-4 text-gray-400">No patients found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 2. ALL VISITS - BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="card-clean animate-fade-in-up mb-4">
        <div class="section-header-clean">
            <h4 class="section-title"><i class="fas fa-notes-medical"></i> All Visits</h4>
            <span class="section-badge"><?= count($all_visits) ?></span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visit #</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Diagnosis</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_visits) > 0): ?>
                        <?php $i = 1; foreach ($all_visits as $visit): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>"><?= ucfirst($visit['status'] ?? 'Pending') ?></span></td>
                                <td><?= htmlspecialchars(substr($visit['diagnosis'] ?? '', 0, 30)) ?><?= strlen($visit['diagnosis'] ?? '') > 30 ? '...' : '' ?></td>
                                <td><?= date('M d, Y', strtotime($visit['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItem('visit', <?= $visit['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-4 text-gray-400">No visits found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3. LAB TESTS - BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="card-clean animate-fade-in-up mb-4">
        <div class="section-header-clean">
            <h4 class="section-title"><i class="fas fa-flask"></i> Lab Tests</h4>
            <span class="section-badge"><?= count($all_lab_tests) ?></span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th>Technician</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_lab_tests) > 0): ?>
                        <?php $i = 1; foreach ($all_lab_tests as $test): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></td>
                                <td><strong><?= htmlspecialchars($test['test_name']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($test['results'] ?? '', 0, 20)) ?><?= strlen($test['results'] ?? '') > 20 ? '...' : '' ?></td>
                                <td><span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItem('lab_test', <?= $test['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-4 text-gray-400">No lab tests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 4. PRESCRIPTIONS - BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="card-clean animate-fade-in-up mb-4">
        <div class="section-header-clean">
            <h4 class="section-title"><i class="fas fa-prescription"></i> Prescriptions</h4>
            <span class="section-badge"><?= count($all_prescriptions) ?></span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Prescription #</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($all_prescriptions as $presc): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($presc['patient_name'] ?? 'N/A') ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($presc['prescription_number'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $presc['item_count'] ?? 0 ?></span></td>
                                <td><span class="badge badge-<?= getStatusBadge($presc['status'] ?? 'pending') ?>"><?= ucfirst($presc['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($presc['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_prescription.php?id=<?= $presc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_prescription.php?id=<?= $presc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItem('prescription', <?= $presc['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-gray-400">No prescriptions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 5. PROCEDURES & TOOLS - BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="card-clean animate-fade-in-up mb-4">
        <div class="section-header-clean">
            <h4 class="section-title"><i class="fas fa-syringe"></i> Procedures & Tools</h4>
            <span class="section-badge"><?= count($all_procedures) ?></span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_procedures) > 0): ?>
                        <?php $i = 1; foreach ($all_procedures as $proc): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($proc['patient_name'] ?? 'N/A') ?></td>
                                <td><strong><?= htmlspecialchars($proc['item_name']) ?></strong></td>
                                <td><?= ucfirst($proc['item_type'] ?? 'N/A') ?></td>
                                <td><?= $proc['quantity'] ?? 1 ?></td>
                                <td class="font-bold">TSh <?= number_format($proc['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_bill_item.php?id=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_bill_item.php?id=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItem('bill_item', <?= $proc['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-gray-400">No procedures/tools found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 6. ALL BILLS - BLUE HEADER -->
    <!-- ================================================================ -->
    <div class="card-clean animate-fade-in-up mb-4">
        <div class="section-header-clean">
            <h4 class="section-title"><i class="fas fa-file-invoice"></i> All Bills</h4>
            <span class="section-badge"><?= count($all_bills) ?></span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_bills) > 0): ?>
                        <?php $i = 1; foreach ($all_bills as $bill): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                                <td class="font-bold">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td class="text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td class="<?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                <td><span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItem('bill', <?= $bill['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-4 text-gray-400">No bills found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Doctor Dashboard - <?= htmlspecialchars($doctor['full_name']) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        if (url.searchParams.has('id')) {
            url.searchParams.delete('id');
        }
        window.location.href = url.toString();
    }

    // ================================================================
    // DEACTIVATE DOCTOR
    // ================================================================
    function deactivateDoctor(doctorId) {
        if (confirm('⚠️ Are you sure you want to DEACTIVATE this doctor?\n\nThis action can be reversed later.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_user.php';
            form.innerHTML = `
                <input type="hidden" name="user_id" value="${doctorId}">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ================================================================
    // DELETE ITEM
    // ================================================================
    function deleteItem(type, id) {
        var typeLabels = {
            'patient': 'patient',
            'visit': 'visit',
            'lab_test': 'lab test',
            'prescription': 'prescription',
            'bill_item': 'procedure/tool',
            'bill': 'bill'
        };
        var label = typeLabels[type] || 'item';
        
        if (confirm('⚠️ Are you sure you want to DELETE this ' + label + '?\n\nThis action CANNOT be undone!')) {
            window.location.href = 'delete_item.php?type=' + type + '&id=' + id + '&branch=<?= $selected_branch_id ?>&doctor_id=<?= $doctor_id ?>';
        }
    }

    // ================================================================
    // CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('visitsChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Visits',
                        data: values,
                        backgroundColor: '#0B5ED7',
                        borderColor: '#0A4CA8',
                        borderWidth: 1,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) el.textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    if (searchBtn) searchBtn.addEventListener('click', performSearch);
    if (searchInput) searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c👨‍⚕️ Braick - View Doctor Complete Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Doctor: <?= htmlspecialchars($doctor['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Visits: <?= number_format($total_visits) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🧪 Lab Tests: <?= count($all_lab_tests) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💊 Prescriptions: <?= count($all_prescriptions) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c💰 Bills: <?= count($all_bills) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Using bills table (not patient_bills)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ FIXED: Revenue from bill_items (not prescription_sales)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ FIXED: Functions defined before use', 'font-size:13px; color:#34D399;');
    console.log('%c🔵 Stat Cards have BLUE background', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔵 Table Headers have BLUE background', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>