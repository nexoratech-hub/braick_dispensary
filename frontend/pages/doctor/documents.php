<?php
// ================================================================
// FILE: frontend/pages/doctor/documents.php
// DOCTOR - PATIENT DOCUMENTS (SEPARATE TABS)
// - Sick Sheets: View PDF (Single Button)
// - Uploaded Documents: View | Download
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'sick_sheets';

// ================================================================
// GET SICK SHEETS FROM BOTH TABLES
// ================================================================
$sick_sheets = [];

// From patient_documents (registered patients)
if ($is_admin) {
    $sql = "
        SELECT 
            pd.id as doc_id,
            pd.document_number,
            pd.patient_id,
            pd.doctor_id,
            pd.branch_id,
            pd.document_type,
            pd.document_name,
            pd.file_name,
            pd.file_path,
            pd.file_size,
            pd.file_type,
            pd.sick_sheet_days,
            pd.sick_sheet_from_date,
            pd.sick_sheet_to_date,
            pd.sick_sheet_diagnosis,
            pd.sick_sheet_recommendations,
            pd.sick_sheet_restrictions,
            pd.is_verified,
            pd.upload_date,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            'registered' as source
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        WHERE pd.document_type = 'sick_sheet'
    ";
} else {
    $sql = "
        SELECT 
            pd.id as doc_id,
            pd.document_number,
            pd.patient_id,
            pd.doctor_id,
            pd.branch_id,
            pd.document_type,
            pd.document_name,
            pd.file_name,
            pd.file_path,
            pd.file_size,
            pd.file_type,
            pd.sick_sheet_days,
            pd.sick_sheet_from_date,
            pd.sick_sheet_to_date,
            pd.sick_sheet_diagnosis,
            pd.sick_sheet_recommendations,
            pd.sick_sheet_restrictions,
            pd.is_verified,
            pd.upload_date,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            'registered' as source
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        WHERE pd.document_type = 'sick_sheet' AND pd.doctor_id = ?
    ";
    $params = [$doctor_id];
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR pd.document_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY pd.upload_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params ?? []);
$registered_sick_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// From external_sick_sheets (external patients)
if ($is_admin) {
    $sql2 = "
        SELECT 
            ess.id as doc_id,
            ess.document_number,
            NULL as patient_id,
            ess.doctor_id,
            ess.branch_id,
            'sick_sheet' as document_type,
            CONCAT('Sick Sheet - ', ess.full_name) as document_name,
            ess.file_name,
            ess.file_path,
            NULL as file_size,
            ess.file_type,
            ess.sick_days as sick_sheet_days,
            ess.sick_from as sick_sheet_from_date,
            ess.sick_to as sick_sheet_to_date,
            ess.diagnosis as sick_sheet_diagnosis,
            ess.instructions as sick_sheet_recommendations,
            ess.sick_restrictions as sick_sheet_restrictions,
            1 as is_verified,
            ess.created_at as upload_date,
            ess.full_name as patient_name,
            ess.patient_id as patient_code,
            u.full_name as doctor_name,
            'external' as source
        FROM external_sick_sheets ess
        LEFT JOIN users u ON ess.doctor_id = u.id
        WHERE 1=1
    ";
} else {
    $sql2 = "
        SELECT 
            ess.id as doc_id,
            ess.document_number,
            NULL as patient_id,
            ess.doctor_id,
            ess.branch_id,
            'sick_sheet' as document_type,
            CONCAT('Sick Sheet - ', ess.full_name) as document_name,
            ess.file_name,
            ess.file_path,
            NULL as file_size,
            ess.file_type,
            ess.sick_days as sick_sheet_days,
            ess.sick_from as sick_sheet_from_date,
            ess.sick_to as sick_sheet_to_date,
            ess.diagnosis as sick_sheet_diagnosis,
            ess.instructions as sick_sheet_recommendations,
            ess.sick_restrictions as sick_sheet_restrictions,
            1 as is_verified,
            ess.created_at as upload_date,
            ess.full_name as patient_name,
            ess.patient_id as patient_code,
            u.full_name as doctor_name,
            'external' as source
        FROM external_sick_sheets ess
        LEFT JOIN users u ON ess.doctor_id = u.id
        WHERE ess.doctor_id = ?
    ";
    $params2 = [$doctor_id];
}

if (!empty($search)) {
    $sql2 .= " AND (ess.full_name LIKE ? OR ess.document_number LIKE ?)";
    $params2[] = "%$search%";
    $params2[] = "%$search%";
}

$sql2 .= " ORDER BY ess.created_at DESC";

$stmt = $db->prepare($sql2);
$stmt->execute($params2 ?? []);
$external_sick_sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge sick sheets
$sick_sheets = array_merge($registered_sick_sheets, $external_sick_sheets);
usort($sick_sheets, function($a, $b) {
    return strtotime($b['upload_date'] ?? '') - strtotime($a['upload_date'] ?? '');
});

// ================================================================
// GET UPLOADED DOCUMENTS (NOT SICK SHEETS)
// ================================================================
if ($is_admin) {
    $sql = "
        SELECT 
            pd.id as doc_id,
            pd.document_number,
            pd.patient_id,
            pd.doctor_id,
            pd.branch_id,
            pd.document_type,
            pd.document_name,
            pd.description,
            pd.file_name,
            pd.file_path,
            pd.file_size,
            pd.file_type,
            pd.is_verified,
            pd.upload_date,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        WHERE pd.document_type != 'sick_sheet'
    ";
    $params = [];
} else {
    $sql = "
        SELECT 
            pd.id as doc_id,
            pd.document_number,
            pd.patient_id,
            pd.doctor_id,
            pd.branch_id,
            pd.document_type,
            pd.document_name,
            pd.description,
            pd.file_name,
            pd.file_path,
            pd.file_size,
            pd.file_type,
            pd.is_verified,
            pd.upload_date,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        WHERE pd.document_type != 'sick_sheet' AND pd.doctor_id = ?
    ";
    $params = [$doctor_id];
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR pd.document_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY pd.upload_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$uploaded_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$sick_sheet_count = count($sick_sheets);
$uploaded_count = count($uploaded_documents);
$total_documents = $sick_sheet_count + $uploaded_count;

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// FUNCTIONS
// ================================================================
function getDocumentTypeBadge($type) {
    $types = [
        'medical_record' => 'badge-blue',
        'referral_letter' => 'badge-purple',
        'lab_result' => 'badge-green',
        'prescription' => 'badge-orange',
        'x_ray' => 'badge-teal',
        'scan' => 'badge-indigo',
        'insurance' => 'badge-pink',
        'id_document' => 'badge-gray',
        'sick_sheet' => 'badge-red',
        'other' => 'badge-gray'
    ];
    return $types[$type] ?? 'badge-gray';
}

function getDocumentTypeLabel($type) {
    $labels = [
        'medical_record' => 'Medical Record',
        'referral_letter' => 'Referral Letter',
        'lab_result' => 'Lab Result',
        'prescription' => 'Prescription',
        'x_ray' => 'X-Ray',
        'scan' => 'Scan',
        'insurance' => 'Insurance',
        'id_document' => 'ID Document',
        'sick_sheet' => 'Sick Sheet',
        'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function getFileIcon($file_type) {
    if (strpos($file_type, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($file_type, 'image') !== false) return 'fa-file-image';
    if (strpos($file_type, 'word') !== false || strpos($file_type, 'doc') !== false) return 'fa-file-word';
    if (strpos($file_type, 'excel') !== false || strpos($file_type, 'xls') !== false) return 'fa-file-excel';
    if (strpos($file_type, 'html') !== false) return 'fa-file-code';
    return 'fa-file';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}

function getSickSheetColor($days) {
    if ($days <= 3) return '#059669';
    if ($days <= 7) return '#D97706';
    return '#DC2626';
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$doctor_branch_name = 'Dodoma';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $doctor_branch_name = $branch['name'];
    }
} catch (Exception $e) {}

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
    <title>Documents - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - LIGHT & DARK MODE
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
        }

        /* ================================================================
           BASE STYLES
           ================================================================ */
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
           TOP NAVIGATION
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
            box-shadow: var(--shadow-sm);
        }

        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }

        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
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
            border-radius: var(--radius);
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

        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
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

        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }

        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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

        .btn-sick-sheet {
            background: #DC2626;
            color: white;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-sick-sheet:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success);
            border: 2px solid var(--success);
        }

        .alert-success i { color: var(--success); }

        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .mb-6 { margin-bottom: 24px; }

        /* ================================================================
           FILTER FORM
           ================================================================ */
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .filter-search {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 200px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            transition: all 0.3s;
            padding: 0 12px;
        }

        .filter-search:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }

        .filter-search .fa-search {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .filter-input {
            border: none;
            background: transparent;
            padding: 8px 12px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }

        .filter-input::placeholder {
            color: var(--text-muted);
        }

        /* ================================================================
           BUTTONS
           ================================================================ */
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
        }

        .btn-blue {
            background: var(--primary);
            color: #fff;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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

        .btn-sm {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 6px;
        }

        .btn-view {
            background: var(--primary);
            color: #fff;
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-view:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .btn-download {
            background: #7C3AED;
            color: #fff;
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-download:hover {
            background: #6D28D9;
            transform: scale(1.05);
        }

        /* SICK SHEET - SINGLE BUTTON: View PDF */
        .btn-pdf-view {
            background: #DC2626;
            color: white;
            padding: 4px 14px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }

        .btn-pdf-view:hover {
            background: #B91C1C;
            transform: scale(1.05);
        }

        /* ================================================================
           TAB CONTAINER
           ================================================================ */
        .tab-container {
            display: flex;
            gap: 4px;
            background: var(--bg-body);
            padding: 4px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .tab-btn {
            flex: 1;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-btn:hover {
            background: var(--gray-200);
            color: var(--text-primary);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }

        .tab-btn .tab-badge {
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .tab-btn:not(.active) .tab-badge {
            background: var(--gray-200);
            color: var(--gray-500);
        }

        .tab-btn .tab-badge.sick-badge { background: #DC2626; color: white; }
        .tab-btn .tab-badge.doc-badge { background: #2563EB; color: white; }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeInUp 0.4s ease;
        }

        /* ================================================================
           SICK SHEET CARD
           ================================================================ */
        .sick-sheet-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 14px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .sick-sheet-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .sick-sheet-card .ss-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .sick-sheet-card .ss-patient {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sick-sheet-card .ss-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .sick-sheet-card .ss-days-badge {
            display: inline-block;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
        }

        .sick-sheet-card .ss-days-badge.short { background: #059669; }
        .sick-sheet-card .ss-days-badge.medium { background: #D97706; }
        .sick-sheet-card .ss-days-badge.long { background: #DC2626; }

        .sick-sheet-card .ss-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .sick-sheet-card .ss-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }

        .badge-external {
            background: #D97706;
            color: white;
            font-size: 0.5rem;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        .badge-unregistered {
            background: #94A3B8;
            color: white;
            font-size: 0.5rem;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }

        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }

        .data-table tbody tr:nth-child(even) {
            background: var(--primary-bg);
        }

        .data-table tbody tr:hover {
            background: #D1FAE5;
        }

        [data-theme="dark"] .data-table tbody tr:hover {
            background: #1A3A2A;
        }

        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .data-table td .font-medium { font-weight: 500; }
        .data-table td .text-xs { font-size: 0.75rem; }
        .data-table td .text-sm { font-size: 0.8rem; }
        .data-table td .text-muted { color: var(--text-muted); }

        .document-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            flex-shrink: 0;
        }

        .document-icon.badge-blue { background: var(--primary); }
        .document-icon.badge-green { background: #059669; }
        .document-icon.badge-purple { background: #7C3AED; }
        .document-icon.badge-orange { background: #D97706; }
        .document-icon.badge-teal { background: #0D9488; }
        .document-icon.badge-indigo { background: #4F46E5; }
        .document-icon.badge-pink { background: #DB2777; }
        .document-icon.badge-gray { background: #64748B; }
        .document-icon.badge-red { background: #DC2626; }

        .badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #fff;
            border: none;
        }

        .badge-success { background: #059669; }
        .badge-danger { background: #EF4444; }
        .badge-warning { background: #D97706; }
        .badge-info { background: var(--primary); }
        .badge-blue { background: var(--primary); }
        .badge-green { background: #059669; }
        .badge-purple { background: #7C3AED; }
        .badge-orange { background: #D97706; }
        .badge-teal { background: #0D9488; }
        .badge-indigo { background: #4F46E5; }
        .badge-pink { background: #DB2777; }
        .badge-gray { background: #64748B; }
        .badge-red { background: #DC2626; }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
            justify-content: center;
        }

        /* ================================================================
           BRANCH TAG
           ================================================================ */
        .branch-tag {
            background: #059669;
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            max-width: 1200px;
            margin: 0 auto;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }

        .empty-state h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 0.85rem;
            color: var(--text-secondary);
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

        .footer .footer-brand { color: var(--primary); font-weight: 600; }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .text-muted { color: var(--text-muted); }
        .text-center { text-align: center; }
        .py-8 { padding: 2rem 0; }
        .text-3xl { font-size: 1.875rem; }
        .block { display: block; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-3 { margin-top: 0.75rem; }
        .mt-4 { margin-top: 1rem; }
        .ml-2 { margin-left: 0.5rem; }
        .ml-auto { margin-left: auto; }
        .mr-1 { margin-right: 0.25rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .no-print { }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
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
            .tab-container { flex-direction: column; }
            .tab-btn { padding: 8px 12px; font-size: 0.75rem; }
            .sick-sheet-card { padding: 14px 16px; }
            .sick-sheet-card .ss-header { flex-direction: column; }
            .sick-sheet-card .ss-actions { width: 100%; justify-content: flex-start; }
            .card { padding: 14px 16px; }
            .filter-group { flex-direction: column; align-items: stretch; }
            .filter-search { min-width: 100%; }
            .filter-form .btn { width: 100%; justify-content: center; }
            .data-table { font-size: 0.75rem; }
            .data-table th, .data-table td { padding: 6px 10px; }
            .btn-sm { padding: 3px 8px; font-size: 0.6rem; }
            .action-buttons { flex-wrap: wrap; justify-content: center; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .page-header .page-title { font-size: 1rem; }
            .page-header .btn-outline-light { font-size: 0.7rem; padding: 6px 12px; }
            .data-table th, .data-table td { padding: 4px 6px; font-size: 0.7rem; }
            .btn-sm { padding: 2px 6px; font-size: 0.55rem; }
            .document-icon { width: 24px; height: 24px; font-size: 0.6rem; }
        }

        /* ================================================================
           DARK MODE OVERRIDES
           ================================================================ */
        [data-theme="dark"] .stat-card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .stat-card .stat-number {
            color: #F1F5F9;
        }
        [data-theme="dark"] .stat-card .stat-label {
            color: #94A3B8;
        }
        [data-theme="dark"] .card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #1E293B;
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
            background: #1E293B;
        }
        [data-theme="dark"] .filter-search {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .filter-input {
            color: #F1F5F9;
        }
        [data-theme="dark"] .sick-sheet-card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .sick-sheet-card:hover {
            border-color: var(--primary);
        }
        [data-theme="dark"] .footer {
            border-color: #334155;
            color: #64748B;
        }
        [data-theme="dark"] .empty-state h4 {
            color: #F1F5F9;
        }
        [data-theme="dark"] .top-nav {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .branch-tag {
            background: #1A3A2A;
            color: #34D399;
        }
        [data-theme="dark"] .tab-container {
            background: #0F172A;
            border-color: #334155;
        }
        [data-theme="dark"] .tab-btn:hover {
            background: #1E293B;
            color: #F1F5F9;
        }
        [data-theme="dark"] .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        [data-theme="dark"] .btn-outline {
            color: #94A3B8;
            border-color: #334155;
        }
        [data-theme="dark"] .btn-outline:hover {
            background: #0F172A;
            border-color: var(--primary);
            color: var(--primary);
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
            <input type="text" id="searchInput" placeholder="Search documents..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3 no-print">
        <span class="branch-tag">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($doctor_branch_name ?? 'Dodoma') ?>
        </span>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i>
            <?= date('D, M d, Y h:i:s A') ?>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot has-notif"></span>
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical mr-2"></i> Documents
                <?php if ($is_admin): ?>
                    <span class="role-badge-display" style="background:rgba(220,38,38,0.3);border-color:rgba(220,38,38,0.3);color:white;">👑 Admin</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Manage patient documents and sick sheets
                <span class="header-badge">
                    <i class="fas fa-file mr-1"></i> <?= $total_documents ?> Total
                </span>
                <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#F87171;">
                    <i class="fas fa-file-medical mr-1"></i> <?= $sick_sheet_count ?> Sick Sheets
                </span>
                <span class="header-badge">
                    <i class="fas fa-upload mr-1"></i> <?= $uploaded_count ?> Uploaded
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
            </p>
        </div>
        <div class="header-right no-print" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="sick_sheet.php" class="btn-sick-sheet">
                <i class="fas fa-file-medical"></i> New Sick Sheet
            </a>
            <a href="upload_document.php" class="btn-outline-light">
                <i class="fas fa-upload"></i> Upload
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_GET['sick_sheet_created'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>Sick Sheet created successfully!</div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['uploaded'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>Document uploaded successfully!</div>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card mb-6">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient or document..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <input type="hidden" name="tab" value="<?= $tab ?>">
                <button type="submit" class="btn btn-blue btn-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="documents.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <div class="tab-container">
        <button class="tab-btn <?= $tab === 'sick_sheets' ? 'active' : '' ?>" onclick="switchTab('sick_sheets')">
            <i class="fas fa-file-medical"></i> Sick Sheets
            <span class="tab-badge sick-badge"><?= $sick_sheet_count ?></span>
        </button>
        <button class="tab-btn <?= $tab === 'uploaded' ? 'active' : '' ?>" onclick="switchTab('uploaded')">
            <i class="fas fa-upload"></i> Uploaded Documents
            <span class="tab-badge doc-badge"><?= $uploaded_count ?></span>
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 1: SICK SHEETS (View PDF - SINGLE BUTTON) -->
    <!-- ================================================================ -->
    <div id="tabSickSheets" class="tab-content <?= $tab === 'sick_sheets' ? 'active' : '' ?>">
        <?php if (count($sick_sheets) > 0): ?>
            <?php foreach ($sick_sheets as $ss): 
                $is_external = ($ss['source'] === 'external');
                $is_unregistered = ($ss['patient_id'] === null || $ss['patient_id'] == 0);
                $days = (int)($ss['sick_sheet_days'] ?? 0);
                $color = getSickSheetColor($days);
                $days_class = $days <= 3 ? 'short' : ($days <= 7 ? 'medium' : 'long');
                $initial = strtoupper(substr($ss['patient_name'] ?? 'U', 0, 1));
                
                $pdf_url = 'generate_sick_sheet_pdf.php?id=' . $ss['doc_id'] . '&source=' . $ss['source'];
            ?>
                <div class="sick-sheet-card">
                    <div class="ss-header">
                        <div class="ss-patient">
                            <div class="ss-avatar" style="background:<?= $color ?>;">
                                <?= $initial ?>
                            </div>
                            <div>
                                <div class="font-medium">
                                    <?= htmlspecialchars($ss['patient_name'] ?? 'Unknown Patient') ?>
                                    <?php if ($is_external): ?>
                                        <span class="badge-external">🌍 External</span>
                                    <?php endif; ?>
                                    <?php if ($is_unregistered && !$is_external): ?>
                                        <span class="badge-unregistered">⚠️ Unregistered</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-muted">
                                    <?= htmlspecialchars($ss['document_number'] ?? 'N/A') ?>
                                    <?php if (!empty($ss['patient_code'])): ?>
                                        • <?= htmlspecialchars($ss['patient_code']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <span class="ss-days-badge <?= $days_class ?>"><?= $days ?> days</span>
                            <span class="text-xs text-muted"><?= time_ago($ss['upload_date'] ?? '') ?></span>
                        </div>
                    </div>
                    
                    <div class="ss-meta">
                        <span><i class="fas fa-calendar-alt"></i> From: <?= !empty($ss['sick_sheet_from_date']) ? date('d/m/Y', strtotime($ss['sick_sheet_from_date'])) : 'N/A' ?></span>
                        <span><i class="fas fa-calendar-alt"></i> To: <?= !empty($ss['sick_sheet_to_date']) ? date('d/m/Y', strtotime($ss['sick_sheet_to_date'])) : 'N/A' ?></span>
                        <?php if (!empty($ss['doctor_name'])): ?>
                            <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($ss['doctor_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($ss['sick_sheet_diagnosis'])): ?>
                        <div class="text-sm mt-2" style="color:var(--text-secondary);">
                            <strong>Diagnosis:</strong> <?= htmlspecialchars(substr($ss['sick_sheet_diagnosis'], 0, 100)) ?><?= strlen($ss['sick_sheet_diagnosis'] ?? '') > 100 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- ========================================================== -->
                    <!-- SICK SHEET ACTIONS: View PDF (SINGLE BUTTON) -->
                    <!-- ========================================================== -->
                    <div class="ss-actions">
                        <a href="<?= $pdf_url ?>" target="_blank" class="btn-pdf-view">
                            <i class="fas fa-eye"></i> View PDF
                        </a>
                        <?php if (!$is_external && !empty($ss['patient_id'])): ?>
                            <a href="view_patient.php?id=<?= $ss['patient_id'] ?>" class="btn btn-view btn-sm" title="View Patient">
                                <i class="fas fa-user"></i>
                            </a>
                        <?php endif; ?>
                        <span class="text-xs text-muted ml-auto">
                            <i class="fas fa-check-circle" style="color:#059669;"></i> Verified
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-medical"></i>
                <h4>No Sick Sheets</h4>
                <p>No sick sheets found. Click "New Sick Sheet" to create one.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 2: UPLOADED DOCUMENTS (View | Download) -->
    <!-- ================================================================ -->
    <div id="tabUploaded" class="tab-content <?= $tab === 'uploaded' ? 'active' : '' ?>">
        <div class="card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Document</th>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th style="border-radius: 0 8px 0 0; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($uploaded_documents) > 0): ?>
                            <?php foreach ($uploaded_documents as $index => $doc): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="document-icon <?= getDocumentTypeBadge($doc['document_type']) ?>">
                                                <i class="fas <?= getFileIcon($doc['file_type'] ?? '') ?>"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium"><?= htmlspecialchars($doc['document_name']) ?></div>
                                                <div class="text-xs text-muted"><?= htmlspecialchars($doc['document_number'] ?? 'N/A') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-medium"><?= htmlspecialchars($doc['patient_name'] ?? 'N/A') ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($doc['patient_code'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= getDocumentTypeBadge($doc['document_type']) ?>">
                                            <?= getDocumentTypeLabel($doc['document_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatFileSize($doc['file_size'] ?? 0) ?></td>
                                    <td class="text-sm"><?= time_ago($doc['upload_date'] ?? '') ?></td>
                                    <td>
                                        <!-- ========================================================== -->
                                        <!-- UPLOADED DOCUMENTS ACTIONS: View | Download -->
                                        <!-- ========================================================== -->
                                        <div class="action-buttons">
                                            <a href="view_document.php?id=<?= $doc['doc_id'] ?>" class="btn btn-view btn-sm" title="View">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if (!empty($doc['file_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $doc['file_path'])): ?>
                                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-download btn-sm" title="Download" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($doc['is_verified'] == 0): ?>
                                                <a href="verify_document.php?id=<?= $doc['doc_id'] ?>" class="btn btn-sm" style="background:#7C3AED;color:white;" title="Verify" onclick="return confirm('Verify this document?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-muted">
                                    <i class="fas fa-upload text-3xl block mb-2"></i>
                                    <p>No uploaded documents found</p>
                                    <div class="mt-3">
                                        <a href="upload_document.php" class="btn btn-blue btn-sm">
                                            <i class="fas fa-upload"></i> Upload Document
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Documents
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
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
    // TAB SWITCH
    // ================================================================
    function switchTab(tab) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
        
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        
        document.querySelectorAll('.tab-content').forEach(function(content) {
            content.classList.remove('active');
        });
        
        if (tab === 'sick_sheets') {
            document.querySelector('.tab-btn:first-child').classList.add('active');
            document.getElementById('tabSickSheets').classList.add('active');
        } else {
            document.querySelector('.tab-btn:last-child').classList.add('active');
            document.getElementById('tabUploaded').classList.add('active');
        }
    }

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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.innerHTML = '<i class="fas fa-clock mr-1" style="color: var(--primary-light);"></i> ' + dateStr + ' • ' + timeStr;
        }
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'documents.php?search=' + encodeURIComponent(query) + '&tab=<?= $tab ?>';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    console.log('%c📄 Documents - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Sick Sheets: <?= $sick_sheet_count ?> | Uploaded: <?= $uploaded_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c📤 "New Sick Sheet" and "Upload" buttons at top', 'font-size:12px; color:#34D399;');
    console.log('%c📄 Sick Sheet Action: View PDF (Single Button)', 'font-size:12px; color:#DC2626;');
    console.log('%c📄 Uploaded Actions: View | Download', 'font-size:12px; color:#7C3AED;');
</script>

</body>
</html>