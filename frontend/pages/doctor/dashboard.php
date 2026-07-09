<?php
// ================================================================
// FILE: frontend/pages/doctor/dashboard.php
// BRAICK DISPENSARY - DOCTOR DASHBOARD
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// GET BRANCH FROM URL PARAMETER
// ================================================================
// If branch is passed via URL, use it
if (isset($_GET['branch']) && is_numeric($_GET['branch'])) {
    $_SESSION['branch_id'] = (int)$_GET['branch'];
}

// Get branch ID from session or default to 1
if (!isset($_SESSION['user_id'])) {
    // Demo doctor - get from database
    $root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
    require_once $root_path . 'backend/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    // Get first active doctor
    $stmt = $db->query("SELECT id, full_name, specialty, branch_id FROM users WHERE role = 'doctor' AND status = 'active' LIMIT 1");
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $_SESSION['user_id'] = $doctor['id'];
        $_SESSION['full_name'] = $doctor['full_name'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['branch_id'] = $doctor['branch_id'] ?? 1;
        $_SESSION['specialty'] = $doctor['specialty'] ?? 'General';
    } else {
        // Fallback default doctor
        $_SESSION['user_id'] = 103;
        $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
        $_SESSION['role'] = 'doctor';
        $_SESSION['branch_id'] = 1;
        $_SESSION['specialty'] = 'Cardiology';
    }
}

// ================================================================
// BRANCH CHECK - Use branch from URL or session
// ================================================================
$user_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : ($_SESSION['branch_id'] ?? 1);

// Include database and helpers
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
require_once $root_path . 'backend/config/database.php';
require_once $root_path . 'backend/helpers/functions.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48'%3E%3Crect width='48' height='48' fill='%230B5ED7' rx='12'/%3E%3Ctext x='24' y='32' text-anchor='middle' fill='white' font-size='20' font-weight='bold'%3EB%3C/text%3E%3C/svg%3E";
$avatar_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='38' height='38'%3E%3Crect width='38' height='38' fill='%230B5ED7' rx='50%25'/%3E%3Ctext x='19' y='25' text-anchor='middle' fill='white' font-size='18' font-weight='bold'%3ED%3C/text%3E%3C/svg%3E";

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Default Branch';
$stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($branch_data) {
    $branch_name = $branch_data['name'];
    $branch_location = $branch_data['location'] ?? '';
} else {
    // If branch not found, get first active branch
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' LIMIT 1");
    $default = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($default) {
        $branch_name = $default['name'];
        $_SESSION['branch_id'] = $default['id'];
        $user_branch_id = $default['id'];
    }
}

// ================================================================
// GET DOCTOR ID
// ================================================================
$doctor_id = $_SESSION['user_id'] ?? 0;

// Get doctor details from database to ensure correct ID
$stmt = $db->prepare("SELECT id, full_name, specialty FROM users WHERE id = ? AND role = 'doctor'");
$stmt->execute([$doctor_id]);
$doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);
if ($doctor_info) {
    $_SESSION['full_name'] = $doctor_info['full_name'];
    $_SESSION['specialty'] = $doctor_info['specialty'] ?? 'General';
    $doctor_id = $doctor_info['id'];
}

// ================================================================
// FETCH STATISTICS - ONLY 4 CARDS (Blue & Green)
// ================================================================

$today = date('Y-m-d');

// CARD 1: Total Patients Today - BLUE
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits v 
    WHERE DATE(v.created_at) = ? 
    AND v.doctor_id = ? 
    AND v.branch_id = ?
");
$stmt->execute([$today, $doctor_id, $user_branch_id]);
$total_patients_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// CARD 2: New Assigned Patients (Waiting) - GREEN
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits v 
    WHERE v.status IN ('pending', 'assigned') 
    AND v.doctor_id = ? 
    AND v.branch_id = ?
");
$stmt->execute([$doctor_id, $user_branch_id]);
$waiting_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// CARD 3: Prescriptions Today - BLUE
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN p.status = 'dispensed' THEN 1 ELSE 0 END) as dispensed
    FROM prescriptions p 
    WHERE DATE(p.created_at) = ? 
    AND p.doctor_id = ? 
    AND p.branch_id = ?
");
$stmt->execute([$today, $doctor_id, $user_branch_id]);
$prescription_data = $stmt->fetch(PDO::FETCH_ASSOC);
$prescriptions_today = $prescription_data['total'] ?? 0;
$prescriptions_pending = $prescription_data['pending'] ?? 0;
$prescriptions_dispensed = $prescription_data['dispensed'] ?? 0;

// CARD 4: Laboratory Tests - GREEN
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN lt.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN lt.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM lab_tests lt 
    WHERE lt.doctor_id = ? 
    AND lt.branch_id = ?
");
$stmt->execute([$doctor_id, $user_branch_id]);
$lab_data = $stmt->fetch(PDO::FETCH_ASSOC);
$lab_total = $lab_data['total'] ?? 0;
$lab_pending = $lab_data['pending'] ?? 0;
$lab_completed = $lab_data['completed'] ?? 0;

// ================================================================
// PATIENT QUEUE LIST
// ================================================================
$patient_queue = [];
$stmt = $db->prepare("
    SELECT v.*, p.patient_id, p.full_name, p.gender,
           DATEDIFF(CURDATE(), p.date_of_birth) as age,
           p.phone, v.status,
           TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_minutes
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ? 
    AND v.branch_id = ? 
    AND v.status IN ('pending', 'assigned', 'with_doctor')
    ORDER BY v.created_at ASC
    LIMIT 10
");
$stmt->execute([$doctor_id, $user_branch_id]);
$patient_queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT PRESCRIPTIONS
// ================================================================
$recent_prescriptions = [];
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name,
           COUNT(pi.id) as medicine_count
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
    WHERE p.doctor_id = ? 
    AND p.branch_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$doctor_id, $user_branch_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT LAB RESULTS
// ================================================================
$recent_lab_results = [];
$stmt = $db->prepare("
    SELECT lt.*, p.full_name as patient_name
    FROM lab_tests lt
    LEFT JOIN patients p ON lt.patient_id = p.id
    WHERE lt.doctor_id = ? 
    AND lt.branch_id = ?
    AND lt.status IN ('completed', 'pending')
    ORDER BY lt.created_at DESC
    LIMIT 5
");
$stmt->execute([$doctor_id, $user_branch_id]);
$recent_lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT ACTIVITIES
// ================================================================
$recent_activities = [
    ['action' => 'Consultation Completed', 'details' => 'Patient John Doe - Diagnosis: Hypertension', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['action' => 'Lab Request Sent', 'details' => 'Patient Mary Jane - Blood Sugar Test', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ['action' => 'Prescription Written', 'details' => 'Patient Peter Smith - Amoxicillin 500mg', 'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))],
    ['action' => 'Follow-up Scheduled', 'details' => 'Patient Alice Mwangi - 2026-07-15', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
];
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --secondary: #0AA84F;
            --secondary-dark: #08944A;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --radius: 18px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ===== SIDEBAR - BLUE WITH GREEN HOVER ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; background: var(--primary); 
            color: white;
            z-index: 50; overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-brand .branch-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 4px;
        }
        .sidebar-brand .specialty-badge {
            display: inline-block;
            background: rgba(10, 168, 79, 0.3);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            margin-top: 2px;
        }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { 
            font-size: 0.55rem; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            color: rgba(255,255,255,0.4); 
            padding: 0 12px; 
            margin: 12px 0 6px; 
            font-weight: 700; 
        }
        .sidebar-link {
            display: flex; 
            align-items: center; 
            gap: 12px;
            padding: 9px 14px; 
            border-radius: 10px;
            color: rgba(255,255,255,0.75); 
            text-decoration: none;
            transition: var(--transition); 
            font-size: 0.85rem; 
            font-weight: 500;
            margin: 2px 0;
        }
        /* Hover effect - GREEN */
        .sidebar-link:hover { 
            background: rgba(10, 168, 79, 0.25); 
            color: #FFFFFF; 
        }
        /* Active link - GREEN */
        .sidebar-link.active { 
            background: var(--secondary); 
            color: white; 
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4); 
        }
        .sidebar-link.active i { color: white; }
        .sidebar-link i { 
            width: 20px; 
            text-align: center; 
            font-size: 1rem; 
        }
        .sidebar-link .badge { 
            margin-left: auto; 
            background: rgba(255,255,255,0.15); 
            padding: 1px 9px; 
            border-radius: 20px; 
            font-size: 0.65rem; 
            font-weight: 600; 
        }
        .sidebar-link.active .badge { 
            background: rgba(255,255,255,0.25); 
            color: white; 
        }
        
        /* ===== TOP NAV ===== */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-card); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--bg-body); border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }
        .top-nav .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 280px; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        .top-nav .search-wrapper input::placeholder { color: var(--text-secondary); }
        .top-nav .search-wrapper .search-btn {
            background: var(--primary); color: white;
            border: none; padding: 8px 16px; border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: var(--transition);
        }
        .top-nav .search-wrapper .search-btn:hover { background: var(--primary-dark); }
        .top-nav .branch-badge {
            background: rgba(11, 94, 215, 0.08);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(11, 94, 215, 0.12);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: var(--transition);
        }
        .top-nav .avatar:hover { border-color: var(--primary); }
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: var(--transition);
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: #EF4444;
            border-radius: 50%; border: 2px solid var(--bg-card);
        }
        .dark-toggle {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 6px 12px;
            cursor: pointer; font-size: 0.85rem;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex; align-items: center; gap: 6px;
        }
        .dark-toggle:hover { border-color: var(--primary); }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ===== SUMMARY CARDS - ONLY BLUE & GREEN ===== */
        .summary-card {
            border-radius: var(--radius);
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .summary-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        /* Hover effect - GREEN */
        .summary-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--secondary);
        }
        /* Card 1 & 3 - BLUE */
        .summary-card.blue { 
            background: rgba(11, 94, 215, 0.08); 
            border-left: 4px solid var(--primary);
        }
        .summary-card.blue::after { background: linear-gradient(90deg, var(--primary), #1E88E5); }
        .summary-card.blue .sc-icon { background: rgba(11, 94, 215, 0.15); color: var(--primary); }
        .summary-card.blue .sc-number { color: var(--primary); }
        
        /* Card 2 & 4 - GREEN */
        .summary-card.green { 
            background: rgba(10, 168, 79, 0.08); 
            border-left: 4px solid var(--secondary);
        }
        .summary-card.green::after { background: linear-gradient(90deg, var(--secondary), #34D399); }
        .summary-card.green .sc-icon { background: rgba(10, 168, 79, 0.15); color: var(--secondary); }
        .summary-card.green .sc-number { color: var(--secondary); }
        
        .summary-card .sc-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .summary-card .sc-number {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .summary-card .sc-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .summary-card .sc-trend {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 20px;
        }
        .summary-card .sc-trend.up { background: rgba(10, 168, 79, 0.12); color: var(--secondary); }
        .summary-card .sc-trend.neutral { background: rgba(100, 116, 139, 0.12); color: #64748B; }
        .summary-card .sc-trend.waiting { background: rgba(245, 158, 11, 0.12); color: #F59E0B; }
        .summary-card .sc-stats {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        .summary-card .sc-stats span { font-weight: 600; }
        .summary-card .sc-stats .pending { color: #F59E0B; }
        .summary-card .sc-stats .completed { color: var(--secondary); }
        
        .summary-card .sc-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .summary-card .sc-actions .btn {
            padding: 5px 14px;
            font-size: 0.7rem;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .summary-card .sc-actions .btn-primary {
            background: var(--primary);
            color: white;
        }
        .summary-card .sc-actions .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .summary-card .sc-actions .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .summary-card .sc-actions .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        .summary-card .sc-actions .btn-success {
            background: var(--secondary);
            color: white;
        }
        .summary-card .sc-actions .btn-success:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .card-title i { color: var(--primary); }
        
        /* ===== QUEUE ITEM ===== */
        .queue-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            transition: var(--transition);
        }
        .queue-item:hover { background: var(--bg-body); }
        .queue-item .q-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(11, 94, 215, 0.1);
            color: var(--primary);
            flex-shrink: 0;
        }
        .queue-item .q-status {
            font-size: 0.6rem;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .queue-item .q-status.pending { background: rgba(245, 158, 11, 0.12); color: #F59E0B; }
        .queue-item .q-status.assigned { background: rgba(11, 94, 215, 0.12); color: var(--primary); }
        .queue-item .q-status.with_doctor { background: rgba(139, 92, 246, 0.12); color: #8B5CF6; }
        
        /* ===== TABLES ===== */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .data-table th {
            text-align: left; padding: 8px 12px;
            font-weight: 600; color: var(--text-secondary);
            font-size: 0.6rem; text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        .data-table td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .data-table tr:hover td { background: var(--bg-body); }
        .status-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.pending { background: rgba(245, 158, 11, 0.12); color: #F59E0B; }
        .status-badge.completed { background: rgba(10, 168, 79, 0.12); color: var(--secondary); }
        .status-badge.dispensed { background: rgba(10, 168, 79, 0.12); color: var(--secondary); }
        .status-badge.in_progress { background: rgba(11, 94, 215, 0.12); color: var(--primary); }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper input { width: 160px; }
        }
        @media (max-width: 768px) {
            .summary-card .sc-number { font-size: 1.6rem; }
            .summary-card .sc-actions { flex-direction: column; }
            .summary-card .sc-actions .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 640px) {
            .top-nav .search-wrapper input { width: 100px; }
            .top-nav .branch-badge { font-size: 0.6rem; padding: 2px 8px; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 10px; }
            .summary-card .sc-number { font-size: 1.4rem; }
            .top-nav .dark-toggle span { display: none; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
        
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            display: flex; align-items: center; gap: 10px;
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR - BLUE WITH GREEN HOVER -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='<?= $logo_fallback ?>'">
            <div>
                <p class="font-bold text-base leading-tight">Braick Dispensary</p>
                <p class="text-xs opacity-80">Doctor</p>
                <span class="specialty-badge"><i class="fas fa-stethoscope mr-1"></i> <?= htmlspecialchars($_SESSION['specialty'] ?? 'General') ?></span>
                <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?></span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php?branch=<?= $user_branch_id ?>" class="sidebar-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="today_patients.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-user-clock"></i> Today's Patients <span class="badge"><?= $total_patients_today ?></span></a>
        <a href="patient_queue.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-hourglass-half"></i> Patient Queue <span class="badge"><?= $waiting_patients ?></span></a>
        <a href="medical_records.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-notes-medical"></i> Medical Records</a>
        <a href="consultations.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-stethoscope"></i> Consultations</a>
        <a href="prescriptions.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="lab_requests.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-flask"></i> Laboratory <span class="badge"><?= $lab_pending ?></span></a>
        <a href="completed_patients.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-check-circle"></i> Completed</a>
        <a href="reports.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="<?= $root_path ?>logout.php" class="sidebar-link" style="margin-top:8px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Search patient, prescription..." class="search-input">
            <button id="searchBtn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle">
            <i class="fas fa-moon" id="darkIcon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn" id="notifToggle">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-envelope text-lg"></i>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $avatar_fallback ?>'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-primary">Doctor Dashboard</h1>
            <p class="text-sm text-secondary">Welcome, Dr. <?= htmlspecialchars($_SESSION['full_name'] ?? 'Doctor') ?>! 
                <span class="inline-flex ml-2" style="background: rgba(11, 94, 215, 0.08); color: var(--primary); padding: 3px 14px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(11, 94, 215, 0.15);">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="patient_queue.php?branch=<?= $user_branch_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-user-md"></i> Start Consultation</a>
            <button onclick="refreshData()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 4 SUMMARY CARDS - BLUE & GREEN ONLY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        
        <!-- CARD 1: Today's Patients - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Today's Patients</p>
                    <p class="sc-number"><?= $total_patients_today ?></p>
                    <span class="sc-trend up"><i class="fas fa-arrow-up"></i> Assigned today</span>
                </div>
                <div class="sc-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="sc-actions">
                <a href="today_patients.php?branch=<?= $user_branch_id ?>" class="btn btn-primary">View Patients</a>
            </div>
        </div>
        
        <!-- CARD 2: New Assigned Patients - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">New Assigned Patients</p>
                    <p class="sc-number"><?= $waiting_patients ?></p>
                    <span class="sc-trend waiting"><i class="fas fa-clock"></i> Waiting</span>
                </div>
                <div class="sc-icon"><i class="fas fa-user-clock"></i></div>
            </div>
            <div class="sc-actions">
                <a href="patient_queue.php?branch=<?= $user_branch_id ?>" class="btn btn-success">Start Consultation</a>
            </div>
        </div>
        
        <!-- CARD 3: Prescriptions - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Prescriptions</p>
                    <p class="sc-number"><?= $prescriptions_today ?></p>
                    <div class="sc-stats">
                        <span class="pending"><?= $prescriptions_pending ?></span> pending · 
                        <span class="completed"><?= $prescriptions_dispensed ?></span> dispensed
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-prescription"></i></div>
            </div>
            <div class="sc-actions">
                <a href="prescriptions.php?branch=<?= $user_branch_id ?>" class="btn btn-primary">View Prescriptions</a>
                <a href="prescribe.php?branch=<?= $user_branch_id ?>" class="btn btn-outline">Send to Pharmacy</a>
            </div>
        </div>
        
        <!-- CARD 4: Laboratory Tests - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Laboratory Tests</p>
                    <p class="sc-number"><?= $lab_total ?></p>
                    <div class="sc-stats">
                        <span class="pending"><?= $lab_pending ?></span> pending · 
                        <span class="completed"><?= $lab_completed ?></span> completed
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-flask"></i></div>
            </div>
            <div class="sc-actions">
                <a href="lab_requests.php?branch=<?= $user_branch_id ?>" class="btn btn-primary">View Lab Requests</a>
                <a href="lab_results.php?branch=<?= $user_branch_id ?>" class="btn btn-outline">View Results</a>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT QUEUE -->
    <!-- ================================================================ -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-hourglass-half mr-2"></i> Patient Queue</h3>
            <a href="patient_queue.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="space-y-1 max-h-60 overflow-y-auto">
            <?php if (count($patient_queue) > 0): ?>
                <?php foreach ($patient_queue as $patient): ?>
                    <?php 
                        $age = $patient['age'] ?? 0;
                        $age_display = $age > 0 ? floor($age / 365) . 'y' : 'N/A';
                        $status_label = str_replace('_', ' ', $patient['status'] ?? 'pending');
                    ?>
                    <div class="queue-item">
                        <span class="q-number"><?= rand(1, 20) ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm truncate"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></p>
                            <p class="text-xs text-secondary">
                                <?= htmlspecialchars($patient['patient_id'] ?? '') ?> • <?= $age_display ?> • <?= htmlspecialchars($patient['gender'] ?? '') ?>
                            </p>
                        </div>
                        <span class="q-status <?= $patient['status'] ?>"><?= $status_label ?></span>
                        <span class="text-xs text-secondary"><?= $patient['waiting_minutes'] ?? 0 ?>m</span>
                        <a href="consultation.php?id=<?= $patient['id'] ?>&branch=<?= $user_branch_id ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-stethoscope"></i> Consult
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-secondary text-sm py-4">
                    <i class="fas fa-check-circle text-green-500 text-2xl block mb-2"></i>
                    No patients in queue
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS & LAB RESULTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-prescription mr-2"></i> Recent Prescriptions</h3>
                <a href="prescriptions.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
            </div>
            <div class="overflow-x-auto max-h-48 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Patient</th><th>Medicines</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_prescriptions) > 0): ?>
                            <?php foreach ($recent_prescriptions as $prescription): ?>
                                <tr>
                                    <td class="text-sm"><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></td>
                                    <td class="text-xs"><?= $prescription['medicine_count'] ?? 0 ?> items</td>
                                    <td><span class="status-badge <?= $prescription['status'] ?>"><?= $prescription['status'] ?></span></td>
                                    <td class="text-xs"><?= date('M d', strtotime($prescription['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-secondary text-sm py-3">No prescriptions today</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-flask mr-2"></i> Recent Lab Results</h3>
                <a href="lab_results.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
            </div>
            <div class="overflow-x-auto max-h-48 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Patient</th><th>Test</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_lab_results) > 0): ?>
                            <?php foreach ($recent_lab_results as $lab): ?>
                                <tr>
                                    <td class="text-sm"><?= htmlspecialchars($lab['patient_name'] ?? 'Unknown') ?></td>
                                    <td class="text-xs"><?= htmlspecialchars($lab['test_name'] ?? 'N/A') ?></td>
                                    <td><span class="status-badge <?= $lab['status'] ?>"><?= $lab['status'] ?></span></td>
                                    <td class="text-xs"><?= date('M d', strtotime($lab['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-secondary text-sm py-3">No lab results</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock mr-2"></i> Recent Activity</h3>
            <a href="activity_logs.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
            <?php foreach ($recent_activities as $activity): ?>
                <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-body transition">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(11, 94, 215, 0.08); color: var(--primary);">
                        <i class="fas fa-circle text-[8px]"></i>
                    </div>
                    <div>
                        <p class="font-medium text-sm text-primary"><?= htmlspecialchars($activity['action']) ?></p>
                        <p class="text-xs text-secondary"><?= htmlspecialchars($activity['details']) ?></p>
                        <p class="text-[10px] text-secondary mt-0.5"><?= time_ago($activity['created_at']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i> Quick Reports</h3>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="reports.php?type=today&branch=<?= $user_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-calendar-day"></i> Today's Patients</a>
            <a href="reports.php?type=weekly&branch=<?= $user_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-calendar-week"></i> Weekly Consultations</a>
            <a href="reports.php?type=monthly&branch=<?= $user_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-calendar-alt"></i> Monthly Consultations</a>
            <a href="reports.php?type=lab&branch=<?= $user_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-flask"></i> Lab Requests</a>
            <a href="reports.php?type=prescriptions&branch=<?= $user_branch_id ?>" class="btn btn-outline btn-sm"><i class="fas fa-prescription"></i> Prescriptions</a>
            <div class="flex-1"></div>
            <button onclick="downloadPDF()" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
            <button onclick="exportExcel()" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</button>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p class="font-medium text-sm">Braick Dispensary Management System</p>
        <p class="text-xs">Doctor Dashboard v2.0 &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const darkToggle = document.getElementById('darkModeToggle');
    const darkIcon = document.getElementById('darkIcon');
    const darkText = document.getElementById('darkText');
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');
    const refreshBtn = document.getElementById('refreshBtn');

    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    let isDark = false;
    darkToggle?.addEventListener('click', () => {
        isDark = !isDark;
        const html = document.getElementById('htmlRoot');
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
        } else {
            html.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
        }
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        isDark = true;
        document.getElementById('htmlRoot').setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }

    function performSearch() {
        const query = searchInput.value.trim();
        if (query.length > 0) {
            showToast('Search', 'Searching for patient: "' + query + '"', 'info');
            window.location.href = 'search_patient.php?q=' + encodeURIComponent(query) + '&branch=<?= $user_branch_id ?>';
        }
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    function refreshData() {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<span class="spinner"></span> Refreshing...';
            refreshBtn.disabled = true;
        }
        setTimeout(() => { location.reload(); }, 800);
    }

    function showToast(title, message, type = 'info') {
        const existing = document.querySelector('.toast-custom');
        if (existing) existing.remove();
        const colors = {
            info: { bg: '#0B5ED7', icon: 'fa-info-circle' },
            success: { bg: '#0AA84F', icon: 'fa-check-circle' },
            error: { bg: '#EF4444', icon: 'fa-exclamation-circle' },
            warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' }
        };
        const style = colors[type] || colors.info;
        const toast = document.createElement('div');
        toast.className = 'toast-custom';
        toast.style.cssText = `
            background: ${style.bg}; color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        `;
        toast.innerHTML = `
            <i class="fas ${style.icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    function downloadPDF() {
        showToast('Downloading PDF', 'Generating PDF report...', 'info');
        window.location.href = 'reports.php?export=pdf&branch=<?= $user_branch_id ?>';
    }
    function exportExcel() {
        showToast('Exporting Excel', 'Preparing Excel export...', 'info');
        window.location.href = 'reports.php?export=excel&branch=<?= $user_branch_id ?>';
    }

    function updateDateTime() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            showToast('Welcome', 'Doctor Dashboard loaded successfully', 'success');
        }, 300);
    });

    console.log('%c🏥 Braick Dispensary - Doctor Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Dr. <?= htmlspecialchars($_SESSION['full_name'] ?? 'Doctor') ?>', 'font-size:13px; color:#0AA84F;');
    console.log('%c🏛️ Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>