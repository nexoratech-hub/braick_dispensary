<?php
// ================================================================
// FILE: frontend/pages/admin/view_doctor.php
// SUPER ADMIN - VIEW DOCTOR DASHBOARD
// FIXED: Reduced height for charts & appointments
// BRAICK DISPENSARY
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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
// GET DOCTOR STATISTICS
// ================================================================

// 1. Total Patients
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Pending Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Pending Lab Tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Revenue Generated
$stmt = $db->prepare("
    SELECT COALESCE(SUM(ps.total), 0) as revenue 
    FROM pharmacy_sales ps
    JOIN prescriptions p ON ps.prescription_id = p.id
    WHERE p.doctor_id = ? AND ps.payment_status = 'paid'
");
$stmt->execute([$doctor_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 6. Today's Appointments
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, p.patient_id 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_date
");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Recent Patients
$stmt = $db->prepare("
    SELECT DISTINCT p.*, v.created_at as last_visit 
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY v.created_at DESC LIMIT 10
");
$stmt->execute([$doctor_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Recent Prescriptions
$stmt = $db->prepare("
    SELECT pr.*, p.full_name as patient_name 
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    WHERE pr.doctor_id = ?
    ORDER BY pr.created_at DESC LIMIT 5
");
$stmt->execute([$doctor_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Weekly Visits Chart Data
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$doctor_id]);
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart labels and values
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
    if (!$found) {
        $chart_values[] = 0;
    }
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
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       VIEW DOCTOR - REDUCED HEIGHT
       ================================================================ */
    
    :root {
        --primary-blue: #0B5ED7;
        --primary-green: #059669;
        --primary-yellow: #D97706;
        --primary-purple: #7C3AED;
        --primary-red: #DC2626;
        --primary-teal: #0D9488;
        
        --bg-card: #FFFFFF;
        --bg-body: #F1F5F9;
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
    
    .doctor-meta span .fa-store-alt { color: var(--primary-green); }
    .doctor-meta span .fa-envelope { color: var(--text-muted); }
    .doctor-meta span .fa-phone { color: var(--text-muted); }
    .doctor-meta span .fa-calendar-alt { color: var(--text-muted); }
    
    .admin-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .online-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--primary-green);
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
    
    .badge-status.success { background: var(--primary-green); }
    .badge-status.danger { background: var(--primary-red); }
    .badge-status.warning { background: var(--primary-yellow); }
    
    /* ================================================================
       STATISTICS CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        cursor: default;
    }
    
    .stat-card:hover {
        border-color: var(--primary-blue);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        color: #FFFFFF;
    }
    
    .stat-icon.blue { background: var(--primary-blue); }
    .stat-icon.green { background: var(--primary-green); }
    .stat-icon.yellow { background: var(--primary-yellow); }
    .stat-icon.purple { background: var(--primary-purple); }
    .stat-icon.red { background: var(--primary-red); }
    .stat-icon.teal { background: var(--primary-teal); }
    
    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary) !important;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary) !important;
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* ================================================================
       DASHBOARD GRID - REDUCED HEIGHT
       ================================================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary-blue);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary) !important;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .card-title i {
        color: var(--primary-blue);
    }
    
    .card-title .fa-calendar-check { color: var(--primary-green); }
    .card-title .fa-user-injured { color: var(--primary-blue); }
    .card-title .fa-prescription { color: var(--primary-green); }
    .card-title .fa-chart-line { color: var(--primary-blue); }
    
    /* ================================================================
       CHART CONTAINER - REDUCED HEIGHT
       ================================================================ */
    .chart-container {
        height: 110px !important;
        max-height: 110px !important;
    }
    
    .chart-container canvas {
        height: 100% !important;
        max-height: 110px !important;
    }
    
    /* ================================================================
       APPOINTMENTS CONTAINER - REDUCED HEIGHT
       ================================================================ */
    .appointments-container {
        max-height: 160px;
        overflow-y: auto;
    }
    
    .appointments-container::-webkit-scrollbar {
        width: 4px;
    }
    
    .appointments-container::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    
    .appointments-container::-webkit-scrollbar-thumb {
        background: var(--primary-blue);
        border-radius: 4px;
    }
    
    .appointment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
    }
    
    .appointment-item:hover {
        background: var(--bg-body);
    }
    
    .appointment-item:last-child {
        border-bottom: none;
    }
    
    .appointment-time {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--text-primary) !important;
        min-width: 55px;
    }
    
    .appointment-patient .name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary) !important;
    }
    
    .appointment-patient .phone {
        font-size: 0.65rem;
        color: var(--text-secondary) !important;
    }
    
    .appointment-status {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
    }
    
    .appointment-status.scheduled { background: #E8F0FE; color: var(--primary-blue); }
    .appointment-status.confirmed { background: #D1FAE5; color: var(--primary-green); }
    .appointment-status.completed { background: #D1FAE5; color: var(--primary-green); }
    .appointment-status.cancelled { background: #FEE2E2; color: var(--primary-red); }
    .appointment-status.pending { background: #FEF3C7; color: var(--primary-yellow); }
    
    [data-theme="dark"] .appointment-status.scheduled { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .appointment-status.confirmed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .appointment-status.pending { background: #3D2E0A; color: #FBBF24; }
    
    /* ================================================================
       PATIENT ITEMS
       ================================================================ */
    .patients-container {
        max-height: 200px;
        overflow-y: auto;
    }
    
    .patients-container::-webkit-scrollbar {
        width: 4px;
    }
    
    .patients-container::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    
    .patients-container::-webkit-scrollbar-thumb {
        background: var(--primary-blue);
        border-radius: 4px;
    }
    
    .patient-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 12px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .patient-item:last-child {
        border-bottom: none;
    }
    
    .patient-avatar-small {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #FFFFFF;
        font-weight: 600;
        font-size: 0.65rem;
        flex-shrink: 0;
    }
    
    .patient-name {
        flex: 1;
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary) !important;
    }
    
    .patient-id {
        font-size: 0.6rem;
        color: var(--text-secondary) !important;
        font-family: monospace;
    }
    
    .patient-last-visit {
        font-size: 0.6rem;
        color: var(--text-secondary) !important;
    }
    
    /* ================================================================
       PRESCRIPTIONS CONTAINER
       ================================================================ */
    .prescriptions-container {
        max-height: 200px;
        overflow-y: auto;
    }
    
    .prescriptions-container::-webkit-scrollbar {
        width: 4px;
    }
    
    .prescriptions-container::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    
    .prescriptions-container::-webkit-scrollbar-thumb {
        background: var(--primary-blue);
        border-radius: 4px;
    }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 20px;
        color: var(--text-secondary) !important;
    }
    
    .empty-state i {
        font-size: 2rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        font-size: 0.85rem;
        color: var(--text-secondary) !important;
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
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
        background: var(--primary-green);
        color: #FFFFFF !important;
    }
    .btn-green:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-red {
        background: var(--primary-red);
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
       ADMIN ACTIONS CARD
       ================================================================ */
    .admin-actions-card {
        border: 2px solid var(--primary-red);
        background: var(--bg-card);
        border-radius: 14px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .admin-actions-card .card-title {
        color: var(--primary-red) !important;
    }
    
    .admin-actions-card .card-title i {
        color: var(--primary-red);
    }
    
    .admin-actions-card .text-gray-400 {
        color: var(--text-secondary) !important;
    }
    
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
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .doctor-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        .doctor-info {
            flex-wrap: wrap;
        }
        .doctor-avatar-large {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        .doctor-name {
            font-size: 1.2rem;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .chart-container {
            height: 90px !important;
        }
        .appointments-container {
            max-height: 140px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .doctor-meta span {
            font-size: 0.7rem;
            padding: 2px 8px;
        }
        .stat-card {
            padding: 12px 14px;
        }
        .stat-number {
            font-size: 1.2rem;
        }
        .chart-container {
            height: 80px !important;
        }
    }
    
    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.10s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    .animate-fade-in-up:nth-child(4) { animation-delay: 0.20s; }
    .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
    .animate-fade-in-up:nth-child(6) { animation-delay: 0.30s; }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent; width:auto;">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-white ml-3 opacity-60"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
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
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2236%22 height=%2236%22%3E%3Crect width=%2236%22 height=%2236%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2218%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                    <span>
                        <i class="fas fa-store-alt"></i>
                        <?= htmlspecialchars($doctor['branch_name']) ?>
                    </span>
                    <span>
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($doctor['email']) ?>
                    </span>
                    <span>
                        <i class="fas fa-phone"></i>
                        <?= htmlspecialchars($doctor['phone']) ?>
                    </span>
                    <span>
                        <?php if ($doctor['is_online']): ?>
                            <span class="online-dot"></span> Online
                        <?php else: ?>
                            <span class="offline-dot"></span> Offline
                        <?php endif; ?>
                    </span>
                    <span>
                        <i class="fas fa-calendar-alt"></i>
                        Joined: <?= date('M d, Y', strtotime($doctor['created_at'])) ?>
                    </span>
                    <span>
                        <span class="badge-status <?= $doctor['status'] === 'active' ? 'success' : 'danger' ?>">
                            <i class="fas fa-circle text-[6px]"></i>
                            <?= ucfirst($doctor['status']) ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="admin-actions">
            <a href="edit_employee.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn btn-green btn-sm" title="Edit Doctor">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button onclick="deactivateDoctor(<?= $doctor['id'] ?>)" 
                    class="btn btn-red btn-sm" title="Deactivate Doctor">
                <i class="fas fa-user-slash"></i> Deactivate
            </button>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" 
               class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> All Doctors
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-number"><?= number_format($total_patients) ?></p>
                <p class="stat-label">Total Patients</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
            <div>
                <p class="stat-number"><?= number_format($today_visits) ?></p>
                <p class="stat-label">Today's Visits</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-number"><?= number_format($pending_prescriptions) ?></p>
                <p class="stat-label">Pending Prescriptions</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-number"><?= number_format($pending_lab_tests) ?></p>
                <p class="stat-label">Pending Lab Tests</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-number">TSh <?= number_format($revenue) ?></p>
                <p class="stat-label">Revenue Generated</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-star"></i></div>
            <div>
                <p class="stat-number"><?= number_format($total_patients > 0 ? round($revenue / ($total_patients ?: 1), 0) : 0) ?></p>
                <p class="stat-label">Avg per Patient (TSh)</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHART & APPOINTMENTS - REDUCED HEIGHT -->
    <!-- ================================================================ -->
    <div class="dashboard-grid mb-4">
        
        <!-- Weekly Visits Chart - Reduced Height -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-chart-line"></i> Weekly Visits
            </h3>
            <div class="chart-container">
                <canvas id="visitsChart"></canvas>
            </div>
        </div>
        
        <!-- Today's Appointments - Reduced Height with Scroll -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-calendar-check"></i> Today's Appointments
                <span class="text-sm font-normal text-gray-400">(<?= count($today_appointments) ?>)</span>
            </h3>
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

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS & PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="dashboard-grid">
        
        <!-- Recent Patients -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-user-injured"></i> Recent Patients
                <span class="text-sm font-normal text-gray-400">(<?= count($recent_patients) ?>)</span>
            </h3>
            <div class="patients-container">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-avatar-small" style="background: <?= getUserColor($patient['full_name']) ?>;">
                                <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                            </div>
                            <div class="patient-name">
                                <?= htmlspecialchars($patient['full_name']) ?>
                                <span class="patient-id"><?= htmlspecialchars($patient['patient_id'] ?? '') ?></span>
                            </div>
                            <span class="patient-last-visit">
                                <?= isset($patient['last_visit']) ? time_ago($patient['last_visit']) : 'N/A' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <p>No patients yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Prescriptions -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i> Recent Prescriptions
                <span class="text-sm font-normal text-gray-400">(<?= count($recent_prescriptions) ?>)</span>
            </h3>
            <div class="prescriptions-container">
                <?php if (count($recent_prescriptions) > 0): ?>
                    <?php foreach ($recent_prescriptions as $prescription): ?>
                        <div class="appointment-item">
                            <div class="appointment-patient">
                                <span class="name"><?= htmlspecialchars($prescription['patient_name']) ?></span>
                                <span class="phone"><?= htmlspecialchars($prescription['prescription_number'] ?? '') ?></span>
                            </div>
                            <span class="appointment-status <?= $prescription['status'] ?? 'pending' ?>">
                                <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                            </span>
                            <span class="patient-last-visit">
                                <?= isset($prescription['created_at']) ? time_ago($prescription['created_at']) : 'N/A' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription"></i>
                        <p>No prescriptions yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ADMIN ACTIONS - FULL ACCESS -->
    <!-- ================================================================ -->
    <div class="admin-actions-card animate-fade-in-up">
        <h3 class="card-title">
            <i class="fas fa-shield-alt"></i> Admin Actions - Full Access
            <span class="text-sm font-normal text-gray-400">(Super Admin Only)</span>
        </h3>
        <div class="flex flex-wrap gap-3">
            <a href="edit_employee.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn btn-green">
                <i class="fas fa-edit"></i> Edit Doctor
            </a>
            <button onclick="deactivateDoctor(<?= $doctor['id'] ?>)" 
                    class="btn btn-red">
                <i class="fas fa-user-slash"></i> Deactivate
            </button>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" 
               class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> All Doctors
            </a>
            <a href="#" class="btn btn-blue">
                <i class="fas fa-file-alt"></i> Full Report
            </a>
        </div>
        <p class="text-xs mt-3" style="color: var(--text-secondary) !important;">
            <i class="fas fa-info-circle"></i> As Super Admin, you have full access to view, edit, and deactivate this doctor.
        </p>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Doctor Dashboard (Admin View)
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
<!-- JAVASCRIPT - NO AUTO RELOAD/REFRESH -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // ================================================================
    // DARK MODE - MANUAL ONLY, NO AUTO
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
    // BRANCH SWITCHER - USER CLICK ONLY, NO AUTO
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
    // TOAST - NO AUTO
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
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
    // CHART - REDUCED HEIGHT, RENDER ONCE
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
                        borderColor: '#0B3D8A',
                        borderWidth: 1,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });

    // ================================================================
    // DATE & TIME - LIVE CLOCK ONLY, NO AUTO REFRESH
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
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH - USER CLICK ONLY, NO AUTO
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
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    console.log('%c👨‍⚕️ Braick - View Doctor Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Doctor: <?= htmlspecialchars($doctor['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📈 Chart Height: 110px (Reduced)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Appointments Height: 160px (Reduced)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🚫 Auto Refresh: DISABLED', 'font-size:13px; color:#EF4444;');
</script>

<?php
// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}
?>

</body>
</html>