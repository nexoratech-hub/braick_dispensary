<?php
// ================================================================
// FILE: frontend/pages/reception/visit_details.php
// RECEPTION - VIEW VISIT DETAILS (BRANCH FILTERED)
// WITH PDF EXPORT WITH OFFICIAL STAMP
// USING NEW DATABASE: dispensary_db
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
// CHECK USER ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if ($visit_id <= 0) {
    header('Location: visits.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS COUNT
    // ================================================================
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
    
    // ================================================================
    // GET VISIT DETAILS - USING NEW DB (bills table)
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name, 
               p.patient_id as patient_number, 
               p.phone, 
               p.email, 
               p.address, 
               p.gender, 
               p.date_of_birth,
               p.blood_group,
               p.allergies,
               p.marital_status,
               u.id as doctor_id,
               u.full_name as doctor_name, 
               u.specialty, 
               u.phone as doctor_phone,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$visit_id, $branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $error = "Visit not found or you don't have permission to view it.";
    }
    
    // ================================================================
    // GET PRESCRIPTIONS FOR THIS VISIT
    // ================================================================
    $prescriptions = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM prescriptions 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET LAB TESTS FOR THIS VISIT
    // ================================================================
    $lab_tests = [];
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM lab_tests 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET BILLS FOR THIS VISIT - USING bills TABLE (NEW DB)
    // ================================================================
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    if ($visit) {
        $stmt = $db->prepare("
            SELECT * FROM bills 
            WHERE visit_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bills as $bill) {
            $total_amount += $bill['total_amount'] ?? 0;
            $total_paid += $bill['paid_amount'] ?? 0;
            $total_balance += $bill['balance'] ?? 0;
        }
    }
    
    // ================================================================
    // GET BILL ITEMS FOR THIS VISIT
    // ================================================================
    $bill_items = [];
    if ($visit) {
        foreach ($bills as $bill) {
            $stmt = $db->prepare("
                SELECT * FROM bill_items 
                WHERE bill_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$bill['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $bill_items[$bill['id']] = $items;
        }
    }
    
    // ================================================================
    // GET TIMELINE (activities related to this visit)
    // ================================================================
    $activities = [];
    if ($visit) {
        try {
            $stmt = $db->prepare("
                SELECT action, details, created_at 
                FROM activity_logs 
                WHERE details LIKE ? OR action = 'visit_created'
                ORDER BY created_at DESC
                LIMIT 15
            ");
            $search = '%' . $visit['visit_number'] . '%';
            $stmt->execute([$search]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $activities = [];
        }
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $visit = null;
    $prescriptions = [];
    $lab_tests = [];
    $activities = [];
    $bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $unread_notifications = 0;
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'Just now';
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->getTimestamp() - $past->getTimestamp();
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return $past->format('M d, Y');
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'pending',
        'assigned' => 'assigned',
        'with_doctor' => 'with_doctor',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        'scheduled' => 'scheduled'
    ];
    return $map[$status] ?? 'pending';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'assigned' => '👨‍⚕️ Assigned',
        'with_doctor' => '🔄 With Doctor',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled',
        'scheduled' => '📅 Scheduled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            background: var(--primary);
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
            background: var(--primary-dark);
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
            border-color: var(--primary);
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
            color: var(--primary);
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
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        /* ================================================================
           PDF MODAL
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        
        .pdf-modal-overlay.active {
            display: flex;
        }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-md);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal:hover {
            background: rgba(220,38,38,0.5);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 0.85rem;
            background: var(--bg-card);
            padding: 32px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        /* ================================================================
           PDF CONTENT STYLES
           ================================================================ */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 24px;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 6px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .doc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 6px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            margin: 18px 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
        }
        
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .pdf-content .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin: 8px 0;
        }
        
        .pdf-content .pdf-table th {
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        
        .pdf-content .pdf-table td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        /* PDF Footer with Official Stamp */
        .pdf-content .pdf-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid var(--text-secondary);
            margin-left: 4px;
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 8px 20px;
            border: 3px solid var(--primary);
            border-radius: 10px;
            background: var(--primary-bg);
            min-width: 180px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 0.55rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 0.55rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 12px;
            font-size: 0.6rem;
            color: var(--text-muted);
        }
        
        .pdf-content .pdf-footer .footer-bottom .footer-brand {
            color: var(--primary);
            font-weight: 700;
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
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .btn-outline-light {
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .status-badge-visit {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
        }
        .status-badge-visit.pending { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.assigned { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.with_doctor { background: #FEF3C7; color: #D97706; }
        .status-badge-visit.completed { background: #D1FAE5; color: #059669; }
        .status-badge-visit.cancelled { background: #FEE2E2; color: #DC2626; }
        .status-badge-visit.scheduled { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-visit.paid { background: #D1FAE5; color: #059669; }
        .status-badge-visit.partial { background: #FEF3C7; color: #D97706; }
        
        [data-theme="dark"] .status-badge-visit.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.assigned { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-visit.with_doctor { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-visit.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-visit.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge-visit.scheduled { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-visit.paid { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-visit.partial { background: #3D2E0A; color: #FBBF24; }
        
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: #0B5ED7; }
        .card-title .title-green { color: #059669; }
        .card-title .title-purple { color: #7C3AED; }
        .card-title .title-orange { color: #D97706; }
        
        .timeline-item {
            display: flex;
            gap: 14px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            align-items: flex-start;
        }
        .timeline-item:last-child {
            border-bottom: none;
        }
        .timeline-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            flex-shrink: 0;
            background: var(--primary-bg);
            color: var(--primary);
        }
        .timeline-content {
            flex: 1;
        }
        .timeline-content .action {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        .timeline-content .details {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        .timeline-content .time {
            font-size: 0.65rem;
            color: var(--text-secondary);
            opacity: 0.7;
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
        }
        .btn-blue {
            background: #0B5ED7;
            color: white;
        }
        .btn-blue:hover {
            background: #0A4CA8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .btn-green {
            background: #059669;
            color: white;
        }
        .btn-green:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: #0B5ED7;
            color: #0B5ED7;
        }
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        .patient-avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        .scroll-container {
            max-height: 200px;
            overflow-y: auto;
        }
        .scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .bill-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 8px;
        }
        .bill-item {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 10px 14px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .bill-item .bill-amount {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .bill-item .bill-amount.total { color: var(--primary); }
        .bill-item .bill-amount.paid { color: var(--success); }
        .bill-item .bill-amount.balance { color: var(--danger); }
        .bill-item .bill-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 500;
        }
        .bill-item .bill-amount.balance.zero { color: var(--success); }
        
        [data-theme="dark"] .bill-item {
            background: #1E293B;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .error-box {
            background: var(--danger-bg);
            border: 2px solid var(--danger);
            border-radius: 12px;
            padding: 20px 24px;
            text-align: center;
            max-width: 600px;
            margin: 40px auto;
        }
        .error-box i {
            font-size: 3rem;
            color: var(--danger);
            display: block;
            margin-bottom: 12px;
        }
        .error-box h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--danger-dark);
        }
        .error-box p {
            color: var(--text-secondary);
            margin: 8px 0 16px;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .bill-summary { grid-template-columns: 1fr; }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-row { flex-direction: column; }
            .pdf-content .pdf-row .pdf-label { width: 100%; }
            .pdf-content .pdf-footer .footer-stamp { flex-direction: column; align-items: center; }
            .pdf-modal-header { flex-direction: column; gap: 10px; align-items: stretch; }
            .pdf-modal-header .modal-actions { justify-content: center; }
            .pdf-modal-body .pdf-content { padding: 12px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav no-print">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
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
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <!-- CLOCK IN HEADER -->
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('D, M d, Y h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-circle"></i>
            <h3>❌ Error</h3>
            <p><?= htmlspecialchars($error) ?></p>
            <a href="visits.php" class="btn btn-blue">
                <i class="fas fa-arrow-left"></i> Back to Visits
            </a>
        </div>
    <?php elseif ($visit): ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Visit Details
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View complete visit information
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
                <?php if ($visit['is_completed'] == 1): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-check-circle"></i> Completed
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right no-print">
            <a href="visits.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                <a href="visit_status.php?id=<?= $visit['id'] ?>&status=completed&redirect=visit_details.php?id=<?= $visit['id'] ?>" class="btn-outline-light" style="background:rgba(5,150,105,0.2);border-color:rgba(5,150,105,0.3);color:#34D399;">
                    <i class="fas fa-check"></i> Complete
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Visit Info -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Visit Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-visit <?= $visit['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Date & Time</p>
                    <p class="detail-value"><?= isset($visit['visit_date']) ? date('F d, Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?></p>
                </div>
                <div class="col-span-2">
                    <p class="detail-label">Branch</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($visit['symptoms'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Symptoms</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['symptoms'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['complaint'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Complaint</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['complaint'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['notes'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Notes</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['notes'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['diagnosis'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Diagnosis</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['diagnosis'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($visit['treatment'])): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Treatment</p>
                        <p class="detail-value"><?= nl2br(htmlspecialchars($visit['treatment'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($visit['follow_up_date']): ?>
                    <div class="col-span-2">
                        <p class="detail-label">Follow-up Date</p>
                        <p class="detail-value"><?= date('F d, Y', strtotime($visit['follow_up_date'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Info -->
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient
            </h3>
            <div class="flex items-center gap-3 mb-3">
                <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($visit['patient_name']), 0, 6) ?>;">
                    <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($visit['patient_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value capitalize"><?= ucfirst(htmlspecialchars($visit['gender'] ?? 'N/A')) ?></p>
                </div>
                <div>
                    <p class="detail-label">Date of Birth</p>
                    <p class="detail-value"><?= !empty($visit['date_of_birth']) ? date('F d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="detail-label">Blood Group</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($visit['allergies'])): ?>
                    <div>
                        <p class="detail-label">Allergies</p>
                        <p class="detail-value text-red-600"><?= htmlspecialchars($visit['allergies']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR INFO -->
    <!-- ================================================================ -->
    <div class="detail-card mb-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-user-md text-primary mr-2"></i> Doctor
        </h3>
        <?php if ($visit['doctor_id']): ?>
            <div class="flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold">
                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($visit['specialty'] ?? 'General Practitioner') ?></p>
                    </div>
                </div>
                <?php if (!empty($visit['doctor_phone'])): ?>
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($visit['doctor_phone']) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No doctor assigned</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY - USING NEW DB (bills table) -->
    <!-- ================================================================ -->
    <?php if (!empty($bills)): ?>
    <div class="detail-card mb-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-money-bill-wave text-primary mr-2"></i> Bill Summary
            <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bill(s))</span>
        </h3>
        <div class="bill-summary">
            <div class="bill-item">
                <p class="bill-amount total">TSh <?= number_format($total_amount, 2) ?></p>
                <p class="bill-label">Total Amount</p>
            </div>
            <div class="bill-item">
                <p class="bill-amount paid">TSh <?= number_format($total_paid, 2) ?></p>
                <p class="bill-label">Paid Amount</p>
            </div>
            <div class="bill-item">
                <p class="bill-amount balance <?= $total_balance <= 0 ? 'zero' : '' ?>">TSh <?= number_format($total_balance, 2) ?></p>
                <p class="bill-label">Balance</p>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-sm font-medium">Overall Status: </span>
            <span class="status-badge-visit <?= $total_balance <= 0 ? 'paid' : 'partial' ?>">
                <?= $total_balance <= 0 ? '✅ Paid' : '⏳ Partial / Pending' ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS & LAB TESTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Prescriptions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-prescription title-blue mr-2"></i> Prescriptions
                    <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container">
                <?php if (count($prescriptions) > 0): ?>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                            <div>
                                <p class="font-medium text-sm text-gray-800">#<?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></p>
                                <p class="text-xs text-gray-500"><?= isset($prescription['created_at']) ? date('M d, Y h:i A', strtotime($prescription['created_at'])) : 'N/A' ?></p>
                            </div>
                            <span class="status-badge-visit <?= $prescription['status'] ?? 'pending' ?>">
                                <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-prescription text-xl block mb-2"></i>
                        <p class="text-sm">No prescriptions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lab Tests -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-flask title-purple mr-2"></i> Lab Tests
                    <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container">
                <?php if (count($lab_tests) > 0): ?>
                    <?php foreach ($lab_tests as $test): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                            <div>
                                <p class="font-medium text-sm text-gray-800"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></p>
                                <p class="text-xs text-gray-500"><?= isset($test['created_at']) ? date('M d, Y h:i A', strtotime($test['created_at'])) : 'N/A' ?></p>
                            </div>
                            <span class="status-badge-visit <?= $test['status'] ?? 'pending' ?>">
                                <?= ucfirst($test['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-flask text-xl block mb-2"></i>
                        <p class="text-sm">No lab tests</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TIMELINE -->
    <!-- ================================================================ -->
    <?php if (count($activities) > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock title-blue mr-2"></i> Activity Timeline
            </h3>
        </div>
        <div class="timeline">
            <?php foreach ($activities as $activity): ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-circle text-[6px]"></i>
                    </div>
                    <div class="timeline-content">
                        <p class="action"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                        <p class="details"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                        <p class="time"><?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-clinic-medical text-4xl block mb-3"></i>
            <p class="text-lg">Visit not found</p>
            <a href="visits.php" class="text-primary hover:underline">Back to visits</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                Visit PDF Preview - <?= htmlspecialchars($visit['visit_number'] ?? 'Visit') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CLOCK UPDATE
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

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
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // GENERATE PDF - WITH OFFICIAL STAMP
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        // Visit data from PHP
        var visitData = {
            visit_number: '<?= addslashes($visit['visit_number'] ?? 'N/A') ?>',
            visit_date: '<?= isset($visit['visit_date']) ? date('d/m/Y h:i A', strtotime($visit['visit_date'])) : 'N/A' ?>',
            status: '<?= ucfirst($visit['status'] ?? 'N/A') ?>',
            visit_type: '<?= ucfirst($visit['visit_type'] ?? 'N/A') ?>',
            symptoms: '<?= addslashes($visit['symptoms'] ?? 'N/A') ?>',
            complaint: '<?= addslashes($visit['complaint'] ?? 'N/A') ?>',
            diagnosis: '<?= addslashes($visit['diagnosis'] ?? 'N/A') ?>',
            treatment: '<?= addslashes($visit['treatment'] ?? 'N/A') ?>',
            notes: '<?= addslashes($visit['notes'] ?? 'N/A') ?>',
            follow_up_date: '<?= !empty($visit['follow_up_date']) ? date('d/m/Y', strtotime($visit['follow_up_date'])) : 'N/A' ?>',
            branch_name: '<?= addslashes($visit['branch_name'] ?? $branch_name) ?>',
            
            patient_name: '<?= addslashes($visit['patient_name'] ?? 'N/A') ?>',
            patient_number: '<?= addslashes($visit['patient_number'] ?? 'N/A') ?>',
            phone: '<?= addslashes($visit['phone'] ?? 'N/A') ?>',
            email: '<?= addslashes($visit['email'] ?? 'N/A') ?>',
            gender: '<?= addslashes($visit['gender'] ?? 'N/A') ?>',
            date_of_birth: '<?= !empty($visit['date_of_birth']) ? date('d/m/Y', strtotime($visit['date_of_birth'])) : 'N/A' ?>',
            blood_group: '<?= addslashes($visit['blood_group'] ?? 'N/A') ?>',
            allergies: '<?= addslashes($visit['allergies'] ?? 'None') ?>',
            address: '<?= addslashes($visit['address'] ?? 'N/A') ?>',
            
            doctor_name: '<?= addslashes($visit['doctor_name'] ?? 'Not Assigned') ?>',
            doctor_specialty: '<?= addslashes($visit['specialty'] ?? 'N/A') ?>',
            
            total_amount: '<?= number_format($total_amount, 0) ?>',
            total_paid: '<?= number_format($total_paid, 0) ?>',
            total_balance: '<?= number_format($total_balance, 0) ?>'
        };
        
        // Prescriptions
        var prescriptions = <?= json_encode($prescriptions) ?>;
        
        // Lab tests
        var labTests = <?= json_encode($lab_tests) ?>;
        
        // Bills with items
        var bills = <?= json_encode($bills) ?>;
        var billItems = <?= json_encode($bill_items) ?>;
        
        var now = new Date();
        var reportDate = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var reportTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        // Build prescriptions HTML
        var prescriptionsHtml = '';
        if (prescriptions && prescriptions.length > 0) {
            prescriptionsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${prescriptions.map(function(p) {
                            return `
                                <tr>
                                    <td>${p.prescription_number || 'N/A'}</td>
                                    <td>${new Date(p.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${p.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            prescriptionsHtml = `<p style="color:var(--text-secondary);">No prescriptions found</p>`;
        }
        
        // Build lab tests HTML
        var labTestsHtml = '';
        if (labTests && labTests.length > 0) {
            labTestsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${labTests.map(function(lt) {
                            return `
                                <tr>
                                    <td>${lt.test_name || 'N/A'}</td>
                                    <td>${new Date(lt.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>${lt.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            labTestsHtml = `<p style="color:var(--text-secondary);">No lab tests found</p>`;
        }
        
        // Build bills HTML
        var billsHtml = '';
        if (bills && bills.length > 0) {
            billsHtml = `
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bills.map(function(b) {
                            return `
                                <tr>
                                    <td>${b.bill_number || 'N/A'}</td>
                                    <td>${new Date(b.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td>TSh ${Number(b.total_amount || 0).toLocaleString()}</td>
                                    <td>TSh ${Number(b.paid_amount || 0).toLocaleString()}</td>
                                    <td>TSh ${Number(b.balance || 0).toLocaleString()}</td>
                                    <td>${b.status || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            billsHtml = `<p style="color:var(--text-secondary);">No bills found</p>`;
        }
        
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name">BRAICK DISPENSARY</span>
                </div>
                <div class="clinic-sub">Quality Healthcare Services • ${visitData.branch_name}</div>
                <div class="doc-title">📋 Visit Details Report</div>
                <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                    Report Generated: ${reportDate} • ${reportTime}
                </div>
            </div>
            
            <!-- Visit Information -->
            <div class="section-title">🏥 Visit Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Visit Number</span><span class="pdf-value"><strong>${visitData.visit_number}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Status</span><span class="pdf-value">${visitData.status}</span></div>
                <div class="pdf-row"><span class="pdf-label">Visit Type</span><span class="pdf-value">${visitData.visit_type}</span></div>
                <div class="pdf-row"><span class="pdf-label">Date & Time</span><span class="pdf-value">${visitData.visit_date}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Symptoms</span><span class="pdf-value">${visitData.symptoms}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Complaint</span><span class="pdf-value">${visitData.complaint}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Diagnosis</span><span class="pdf-value">${visitData.diagnosis}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Treatment</span><span class="pdf-value">${visitData.treatment}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Notes</span><span class="pdf-value">${visitData.notes}</span></div>
                <div class="pdf-row"><span class="pdf-label">Follow-up Date</span><span class="pdf-value">${visitData.follow_up_date}</span></div>
                <div class="pdf-row"><span class="pdf-label">Branch</span><span class="pdf-value">${visitData.branch_name}</span></div>
            </div>
            
            <!-- Patient Information -->
            <div class="section-title">👤 Patient Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value"><strong>${visitData.patient_name}</strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value">${visitData.patient_number}</span></div>
                <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value">${visitData.gender}</span></div>
                <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value">${visitData.date_of_birth}</span></div>
                <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value">${visitData.phone}</span></div>
                <div class="pdf-row"><span class="pdf-label">Email</span><span class="pdf-value">${visitData.email}</span></div>
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value">${visitData.blood_group}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Allergies</span><span class="pdf-value">${visitData.allergies}</span></div>
                <div class="pdf-row" style="grid-column: 1 / -1;"><span class="pdf-label">Address</span><span class="pdf-value">${visitData.address}</span></div>
            </div>
            
            <!-- Doctor Information -->
            <div class="section-title">👨‍⚕️ Doctor Information</div>
            <div class="pdf-grid-2">
                <div class="pdf-row"><span class="pdf-label">Doctor Name</span><span class="pdf-value">${visitData.doctor_name}</span></div>
                <div class="pdf-row"><span class="pdf-label">Specialty</span><span class="pdf-value">${visitData.doctor_specialty}</span></div>
            </div>
            
            <!-- Bills -->
            <div class="section-title">💰 Bill Summary</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:8px;">
                <div style="background:var(--primary-bg);padding:8px 12px;border-radius:6px;text-align:center;">
                    <div style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;">Total Amount</div>
                    <div style="font-size:1rem;font-weight:700;color:var(--primary);">TSh ${visitData.total_amount}</div>
                </div>
                <div style="background:var(--success-bg);padding:8px 12px;border-radius:6px;text-align:center;">
                    <div style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;">Paid Amount</div>
                    <div style="font-size:1rem;font-weight:700;color:var(--success);">TSh ${visitData.total_paid}</div>
                </div>
                <div style="background:${parseInt(visitData.total_balance) > 0 ? 'var(--danger-bg)' : 'var(--success-bg)'};padding:8px 12px;border-radius:6px;text-align:center;">
                    <div style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;">Balance</div>
                    <div style="font-size:1rem;font-weight:700;color:${parseInt(visitData.total_balance) > 0 ? 'var(--danger)' : 'var(--success)'};">TSh ${visitData.total_balance}</div>
                </div>
            </div>
            ${billsHtml}
            
            <!-- Prescriptions -->
            <div class="section-title">💊 Prescriptions</div>
            ${prescriptionsHtml}
            
            <!-- Lab Tests -->
            <div class="section-title">🧪 Lab Tests</div>
            ${labTestsHtml}
            
            <!-- Footer with Official Stamp -->
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Technician: _________________</span>
                        <span style="margin-left:20px;">Date: ${reportDate}</span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: ${reportDate}</div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <span class="footer-brand">Braick Dispensary</span> • 
                    Generated on ${reportDate} at ${reportTime} • 
                    All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Visit_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>_<?= $visit['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { mode: 'avoid-all' }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE PDF MODAL
    // ================================================================
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c🏥 Braick - Visit Details (PDF with Official Stamp)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= ucfirst($visit['status'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: TSh <?= number_format($total_amount, 0) ?> | Paid: TSh <?= number_format($total_paid, 0) ?> | Balance: TSh <?= number_format($total_balance, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 PDF with Official Stamp and all visit information', 'font-size:13px; color:#DC2626;');
    console.log('%c🗄️ Using NEW DATABASE: dispensary_db (bills, bill_items)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ PDF Download button works with html2pdf.js', 'font-size:13px; color:#34D399;');
    console.log('%c🕐 Clock updates every second', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>