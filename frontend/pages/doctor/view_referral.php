<?php
// ================================================================
// FILE: frontend/pages/doctor/view_referral.php
// DOCTOR - VIEW REFERRAL DETAILS
// Session-based login - USING dispensary_db
// FIXED: Print button changed to View PDF
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// GET REFERRAL ID
// ================================================================
$referral_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($referral_id <= 0) {
    header('Location: referrals.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE DATABASE - USING dispensary_db
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, profile_pic, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("view_referral verification error: " . $e->getMessage());
}

// ================================================================
// GET REFERRAL DETAILS - FIXED: Using correct columns
// ================================================================
try {
    $sql = "
        SELECT 
            r.id,
            r.referral_number,
            r.visit_id,
            r.patient_id,
            r.from_doctor_id,
            r.referral_type,
            r.to_doctor_id,
            r.to_hospital_name,
            r.to_hospital_address,
            r.to_hospital_phone,
            r.reason,
            r.clinical_notes,
            r.diagnosis,
            r.treatment_given,
            r.urgency,
            r.status,
            r.referral_date,
            r.created_by,
            r.branch_id,
            r.created_at,
            r.updated_at,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            p.date_of_birth,
            p.gender,
            p.address,
            p.blood_group,
            p.allergies,
            p.emergency_contact,
            u_from.full_name as from_doctor_name,
            u_from.specialty as from_doctor_specialty,
            u_from.phone as from_doctor_phone,
            u_to.full_name as to_doctor_name,
            u_to.specialty as to_doctor_specialty,
            u_to.phone as to_doctor_phone,
            v.visit_number,
            v.diagnosis as visit_diagnosis,
            v.symptoms as visit_symptoms,
            v.treatment as visit_treatment,
            v.created_at as visit_created_at,
            v.status as visit_status,
            b.name as branch_name
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN visits v ON r.visit_id = v.id
        LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
        LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$referral_id]);
    $referral = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$referral) {
        header('Location: referrals.php?error=not_found');
        exit;
    }
    
    // Check if doctor has access (either sent or received)
    if ($referral['from_doctor_id'] != $doctor_id && $referral['to_doctor_id'] != $doctor_id) {
        header('Location: referrals.php?error=access_denied');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Referral fetch error: " . $e->getMessage());
    header('Location: referrals.php?error=database_error');
    exit;
}

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$doctor_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'accepted': return 'badge-success';
        case 'completed': return 'badge-info';
        case 'rejected': return 'badge-danger';
        case 'pending': return 'badge-warning';
        case 'cancelled': return 'badge-danger';
        default: return 'badge-warning';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'accepted': return 'fa-check-circle';
        case 'completed': return 'fa-check-double';
        case 'rejected': return 'fa-times-circle';
        case 'pending': return 'fa-clock';
        case 'cancelled': return 'fa-ban';
        default: return 'fa-circle';
    }
}

function getReferralTypeLabel($type) {
    if ($type === 'internal') {
        return '🏥 Internal Referral';
    } else {
        return '🌍 External Referral';
    }
}

function getUrgencyBadgeClass($urgency) {
    switch ($urgency) {
        case 'emergency': return 'badge-danger';
        case 'urgent': return 'badge-warning';
        case 'routine': return 'badge-success';
        default: return 'badge-info';
    }
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y', strtotime($datetime));
}

// ================================================================
// GET LOGO
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Details - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES */
        /* ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --orange: #EA580C;
            --orange-bg: #FEF3C7;
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================ */
        /* MAIN CONTENT */
        /* ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================ */
        /* PAGE HEADER */
        /* ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* PDF Button - RED */
        .btn-pdf-light {
            background: rgba(220,38,38,0.25);
            color: white;
            border: 1px solid rgba(220,38,38,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-pdf-light:hover {
            background: rgba(220,38,38,0.4);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220,38,38,0.3);
            color: white;
        }
        
        /* ================================================================ */
        /* SUMMARY HEADER */
        /* ================================================================ */
        .summary-header {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            transition: all 0.3s ease;
        }
        
        .summary-header:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .summary-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .summary-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .meta-item {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .meta-item i {
            font-size: 0.8rem;
            color: var(--primary);
        }
        
        .summary-header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .status-badge {
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            border: none;
        }
        
        .status-badge.badge-success { background: #059669; }
        .status-badge.badge-danger { background: #EF4444; }
        .status-badge.badge-warning { background: #D97706; }
        .status-badge.badge-info { background: var(--primary); }
        
        .urgency-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            border: none;
        }
        
        .urgency-badge.badge-success { background: #059669; }
        .urgency-badge.badge-danger { background: #EF4444; }
        .urgency-badge.badge-warning { background: #D97706; }
        .urgency-badge.badge-info { background: var(--primary); }
        
        .meta-date {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        [data-theme="dark"] .summary-header {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .summary-title {
            color: #F1F5F9;
        }
        [data-theme="dark"] .meta-item {
            color: #94A3B8;
        }
        
        /* ================================================================ */
        /* INFO GRID */
        /* ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .info-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .info-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card-body {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        .info-value.font-semibold { font-weight: 600; }
        .info-value.font-mono { font-family: monospace; }
        .info-value.text-sm { font-size: 0.8rem; }
        .text-muted { color: var(--text-secondary); }
        .text-xs { font-size: 0.75rem; }
        
        [data-theme="dark"] .info-card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .info-card-title {
            color: #F1F5F9;
            border-color: #334155;
        }
        [data-theme="dark"] .info-row {
            border-color: #334155;
        }
        [data-theme="dark"] .info-value {
            color: #F1F5F9;
        }
        
        /* ================================================================ */
        /* DETAIL CARD */
        /* ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .title-blue { color: var(--primary); }
        .title-green { color: #059669; }
        .title-purple { color: #7C3AED; }
        .title-orange { color: var(--orange); }
        .title-teal { color: var(--teal); }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .gap-2 { gap: 8px; }
        
        [data-theme="dark"] .detail-card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .card-title {
            color: #F1F5F9;
            border-color: #334155;
        }
        [data-theme="dark"] .detail-row {
            border-color: #334155;
        }
        [data-theme="dark"] .detail-value {
            color: #F1F5F9;
        }
        
        /* ================================================================ */
        /* TIMELINE */
        /* ================================================================ */
        .timeline {
            position: relative;
            padding-left: 28px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 16px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-dot {
            position: absolute;
            left: -24px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid var(--bg-card);
        }
        
        .timeline-dot.created { background: var(--primary); }
        .timeline-dot.pending { background: var(--warning); }
        .timeline-dot.accepted { background: var(--success); }
        .timeline-dot.completed { background: var(--teal); }
        .timeline-dot.rejected { background: var(--danger); }
        .timeline-dot.cancelled { background: var(--gray-400); }
        
        .timeline-content {
            padding-left: 12px;
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .timeline-status {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .timeline-status.accepted { color: var(--success); }
        .timeline-status.completed { color: var(--teal); }
        .timeline-status.rejected { color: var(--danger); }
        .timeline-status.cancelled { color: var(--gray-400); }
        .timeline-status.pending { color: var(--warning); }
        
        .timeline-time {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .timeline-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        [data-theme="dark"] .timeline::before {
            background: #334155;
        }
        [data-theme="dark"] .timeline-dot {
            border-color: #1E293B;
        }
        [data-theme="dark"] .timeline-status {
            color: #F1F5F9;
        }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 36px;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #059669;
            color: #fff;
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-success:hover {
            background: #047857;
            transform: scale(1.05);
        }
        
        .btn-danger {
            background: #EF4444;
            color: #fff;
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-danger:hover {
            background: #DC2626;
            transform: scale(1.05);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 6px;
            min-height: 30px;
        }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .text-gray-300 { color: #D1D5DB; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .ml-2 { margin-left: 8px; }
        .mr-1 { margin-right: 4px; }
        .mr-2 { margin-right: 8px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-3 { margin-bottom: 12px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex { display: flex; }
        
        /* ================================================================ */
        /* TOAST */
        /* ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: #D97706; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 12px; margin-left: 0; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-header { flex-direction: column; align-items: flex-start; padding: 16px 18px; }
            .summary-header-right { align-items: flex-start; width: 100%; }
            .summary-title { font-size: 1rem; }
            .summary-meta { flex-direction: column; gap: 4px; }
            .info-card { padding: 14px 16px; }
            .detail-card { padding: 14px 16px; }
            .info-row { flex-direction: column; align-items: flex-start; }
            .info-value { text-align: left; max-width: 100%; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .md\:grid-cols-2 { grid-template-columns: 1fr; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .btn { font-size: 0.7rem; padding: 4px 10px; }
            .timeline-header { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .page-header .page-title { font-size: 1rem; }
            .info-card { padding: 10px 12px; }
            .detail-card { padding: 10px 12px; }
            .summary-header { padding: 12px 14px; }
            .status-badge { font-size: 0.7rem; padding: 4px 12px; }
            .urgency-badge { font-size: 0.6rem; padding: 2px 10px; }
            .tag { font-size: 0.6rem; padding: 2px 10px; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .btn-outline-light, .btn-pdf-light, .footer { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .summary-header, .info-card, .detail-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .page-header { background: #0B5ED7 !important; }
            .status-badge { color: white !important; }
            .status-badge.badge-success { background: #059669 !important; }
            .status-badge.badge-warning { background: #D97706 !important; }
            .status-badge.badge-info { background: #0B5ED7 !important; }
            .status-badge.badge-danger { background: #DC2626 !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-ambulance"></i>
                Referral Details
                <span class="role-badge">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                View complete referral information
                <span class="tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
                <span class="tag"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?></span>
                <span class="tag"><i class="fas fa-user"></i> <?= htmlspecialchars($referral['patient_name'] ?? 'N/A') ?></span>
                <span class="tag"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <a href="referrals.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if (($referral['status'] ?? '') === 'pending' && $referral['to_doctor_id'] == $doctor_id): ?>
                <a href="accept_referral.php?id=<?= $referral['id'] ?>" class="btn-outline-light" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.3);color:#34D399;" onclick="return confirm('Accept this referral?')">
                    <i class="fas fa-check"></i> Accept
                </a>
                <a href="reject_referral.php?id=<?= $referral['id'] ?>" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#F87171;" onclick="return confirm('Reject this referral?')">
                    <i class="fas fa-times"></i> Reject
                </a>
            <?php endif; ?>
            <!-- VIEW PDF BUTTON - Replaced Print -->
            <a href="view_referral_pdf.php?id=<?= $referral_id ?>" target="_blank" class="btn-pdf-light">
                <i class="fas fa-file-pdf"></i> View PDF
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REFERRAL SUMMARY -->
    <!-- ================================================================ -->
    <div class="summary-header">
        <div class="summary-header-left">
            <h2 class="summary-title">
                <?= getReferralTypeLabel($referral['referral_type']) ?>
                <span class="status-badge <?= getStatusBadgeClass($referral['status']) ?>">
                    <i class="fas <?= getStatusIcon($referral['status']) ?>"></i>
                    <?= ucfirst($referral['status'] ?? 'Pending') ?>
                </span>
                <span class="urgency-badge <?= getUrgencyBadgeClass($referral['urgency']) ?>">
                    <?= ucfirst($referral['urgency'] ?? 'Routine') ?>
                </span>
            </h2>
            <div class="summary-meta">
                <span class="meta-item">
                    <i class="far fa-calendar-alt"></i>
                    <?= formatDate($referral['referral_date'] ?? $referral['created_at']) ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-user-md"></i>
                    From: <strong>Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? 'Unknown') ?></strong>
                    <?= !empty($referral['from_doctor_specialty']) ? '(' . htmlspecialchars($referral['from_doctor_specialty']) . ')' : '' ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-user-md"></i>
                    To: <strong>Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') ?></strong>
                    <?= !empty($referral['to_doctor_specialty']) ? '(' . htmlspecialchars($referral['to_doctor_specialty']) . ')' : '' ?>
                </span>
                <?php if ($referral['visit_number']): ?>
                    <span class="meta-item">
                        <i class="fas fa-clinic-medical"></i>
                        Visit: <?= htmlspecialchars($referral['visit_number']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($referral['referral_type'] === 'external' && !empty($referral['to_hospital_name'])): ?>
                    <span class="meta-item">
                        <i class="fas fa-hospital"></i>
                        Facility: <?= htmlspecialchars($referral['to_hospital_name']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($referral['branch_name'])): ?>
                    <span class="meta-item">
                        <i class="fas fa-store-alt"></i>
                        Branch: <?= htmlspecialchars($referral['branch_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="summary-header-right">
            <span class="meta-date">
                <i class="fas fa-clock text-gray-400"></i>
                Created: <?= formatDate($referral['created_at']) ?>
            </span>
            <?php if (!empty($referral['updated_at']) && $referral['updated_at'] != $referral['created_at']): ?>
                <span class="meta-date">
                    <i class="fas fa-edit text-gray-400"></i>
                    Updated: <?= formatDate($referral['updated_at']) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user" style="color:#0B5ED7;"></i> Patient Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($referral['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($referral['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= htmlspecialchars($referral['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?= !empty($referral['date_of_birth']) ? date('M d, Y', strtotime($referral['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?= calculateAge($referral['date_of_birth'] ?? '') ?> years</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($referral['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($referral['patient_email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Emergency Contact</span>
                    <span class="info-value font-semibold" style="color:#DC2626;"><?= htmlspecialchars($referral['emergency_contact'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($referral['blood_group'])): ?>
                    <div class="info-row">
                        <span class="info-label">Blood Group</span>
                        <span class="info-value"><?= htmlspecialchars($referral['blood_group']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['allergies'])): ?>
                    <div class="info-row">
                        <span class="info-label">Allergies</span>
                        <span class="info-value" style="color:#DC2626;"><?= htmlspecialchars($referral['allergies']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['address'])): ?>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value text-sm"><?= htmlspecialchars($referral['address']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-stethoscope" style="color:#059669;"></i> Visit & Doctor Information
            </h4>
            <div class="info-card-body">
                <?php if ($referral['visit_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Number</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($referral['visit_number']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['visit_status'])): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Status</span>
                        <span class="info-value">
                            <span class="badge <?= getStatusBadgeClass($referral['visit_status']) ?>" style="font-size:0.6rem;padding:2px 10px;">
                                <?= ucfirst(str_replace('_', ' ', $referral['visit_status'])) ?>
                            </span>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['visit_diagnosis'])): ?>
                    <div class="info-row">
                        <span class="info-label">Diagnosis</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($referral['visit_diagnosis'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['visit_symptoms'])): ?>
                    <div class="info-row">
                        <span class="info-label">Symptoms</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($referral['visit_symptoms'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['visit_treatment'])): ?>
                    <div class="info-row">
                        <span class="info-label">Treatment</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($referral['visit_treatment'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($referral['visit_created_at'])): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Date</span>
                        <span class="info-value"><?= formatDate($referral['visit_created_at']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">From Doctor</span>
                    <span class="info-value">
                        <strong>Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? 'Unknown') ?></strong>
                        <?= !empty($referral['from_doctor_specialty']) ? '<br><span class="text-xs text-muted">' . htmlspecialchars($referral['from_doctor_specialty']) . '</span>' : '' ?>
                        <?= !empty($referral['from_doctor_phone']) ? '<br><span class="text-xs text-muted">📞 ' . htmlspecialchars($referral['from_doctor_phone']) . '</span>' : '' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">To Doctor</span>
                    <span class="info-value">
                        <strong>Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') ?></strong>
                        <?= !empty($referral['to_doctor_specialty']) ? '<br><span class="text-xs text-muted">' . htmlspecialchars($referral['to_doctor_specialty']) . '</span>' : '' ?>
                        <?= !empty($referral['to_doctor_phone']) ? '<br><span class="text-xs text-muted">📞 ' . htmlspecialchars($referral['to_doctor_phone']) . '</span>' : '' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EXTERNAL REFERRAL DETAILS -->
    <!-- ================================================================ -->
    <?php if ($referral['referral_type'] === 'external'): ?>
    <div class="detail-card">
        <h3 class="card-title">
            <i class="fas fa-hospital title-purple mr-2"></i>
            External Referral Details
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="detail-row"><span class="detail-label">Facility Name</span><span class="detail-value"><strong><?= htmlspecialchars($referral['to_hospital_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($referral['to_hospital_phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($referral['to_hospital_address'] ?? 'N/A') ?></span></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- REASON & CLINICAL NOTES -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title">
            <i class="fas fa-file-alt title-blue mr-2"></i>
            Reason & Clinical Notes
        </h3>
        <div class="detail-row">
            <span class="detail-label">Referral Number</span>
            <span class="detail-value font-mono"><strong><?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?></strong></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Reason for Referral</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($referral['reason'] ?? 'No reason provided')) ?></span>
        </div>
        <?php if (!empty($referral['clinical_notes'])): ?>
            <div class="detail-row">
                <span class="detail-label">Clinical Notes</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($referral['clinical_notes'])) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($referral['diagnosis'])): ?>
            <div class="detail-row">
                <span class="detail-label">Diagnosis</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($referral['diagnosis'])) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($referral['treatment_given'])): ?>
            <div class="detail-row">
                <span class="detail-label">Treatment Given</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($referral['treatment_given'])) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- STATUS HISTORY - FIXED: Using available columns -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title">
            <i class="fas fa-history title-orange mr-2"></i>
            Status History
        </h3>
        <div class="timeline">
            <!-- Created -->
            <div class="timeline-item">
                <div class="timeline-dot created"></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-status">📋 Created</span>
                        <span class="timeline-time"><?= formatDate($referral['created_at'] ?? '') ?></span>
                    </div>
                    <div class="timeline-desc">
                        Referral created by <strong>Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? 'Unknown') ?></strong>
                        <?php if (!empty($referral['from_doctor_specialty'])): ?>
                            (<?= htmlspecialchars($referral['from_doctor_specialty']) ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Current Status -->
            <div class="timeline-item">
                <div class="timeline-dot <?= $referral['status'] ?? 'pending' ?>"></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-status <?= $referral['status'] ?? 'pending' ?>">
                            <?php if (($referral['status'] ?? 'pending') === 'accepted'): ?>
                                ✅ Accepted
                            <?php elseif (($referral['status'] ?? 'pending') === 'completed'): ?>
                                ✅ Completed
                            <?php elseif (($referral['status'] ?? 'pending') === 'rejected'): ?>
                                ❌ Rejected
                            <?php elseif (($referral['status'] ?? 'pending') === 'cancelled'): ?>
                                🚫 Cancelled
                            <?php else: ?>
                                ⏳ Pending
                            <?php endif; ?>
                        </span>
                        <span class="timeline-time"><?= formatDate($referral['updated_at'] ?? '') ?></span>
                    </div>
                    <div class="timeline-desc">
                        <?php if (($referral['status'] ?? 'pending') === 'pending'): ?>
                            Waiting for response from <strong>Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') ?></strong>
                        <?php elseif (($referral['status'] ?? 'pending') === 'accepted'): ?>
                            Accepted by <strong>Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') ?></strong>
                        <?php elseif (($referral['status'] ?? 'pending') === 'completed'): ?>
                            Referral completed successfully
                        <?php elseif (($referral['status'] ?? 'pending') === 'rejected'): ?>
                            Rejected by <strong>Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') ?></strong>
                        <?php elseif (($referral['status'] ?? 'pending') === 'cancelled'): ?>
                            Referral cancelled
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Referral Details
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
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
    // DARK MODE - SYNC WITH HEADER
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
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

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

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
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c📋 Referral Details - <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c👤 Patient: <?= htmlspecialchars($referral['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($referral['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🔄 Type: <?= ucfirst($referral['referral_type'] ?? 'N/A') ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ From: Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? 'Unknown') ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ To: Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ View PDF button added (replaced Print)', 'font-size:12px; color:#DC2626;');
    console.log('%c📄 PDF opens in new window: view_referral_pdf.php?id=<?= $referral_id ?>', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>