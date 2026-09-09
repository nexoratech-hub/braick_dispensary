<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescriptions.php
// DOCTOR - VIEW PRESCRIPTIONS
// SHOWS ALL PRESCRIPTIONS GROUPED BY PATIENT
// WITH TOTAL ITEMS COUNT AND VIEW DETAILS
// DESIGN KAMA ADMIN - NO DELETE BUTTON (VIEW ONLY)
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
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';

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
    error_log("view_prescriptions verification error: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// GET STATUS COUNTS - USING ACTUAL TABLES
// ================================================================
$status_counts = ['pending' => 0, 'dispensed' => 0, 'cancelled' => 0];
$statuses = ['pending', 'dispensed', 'cancelled'];

foreach ($statuses as $status) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions 
            WHERE doctor_id = ? AND status = ?
        ");
        $stmt->execute([$doctor_id, $status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $status_counts[$status] = 0;
    }
}

// Total prescriptions
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $total_count = 0;
}

// ================================================================
// GET PRESCRIPTIONS GROUPED BY PATIENT WITH ITEMS COUNT
// ================================================================
$conditions = ["p.doctor_id = ?"];
$params = [$doctor_id];

if ($filter_status !== 'all') {
    $conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $conditions);

$sql = "
    SELECT 
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone,
        pat.gender,
        pat.date_of_birth,
        pat.blood_group,
        pat.allergies,
        COUNT(DISTINCT p.id) as total_prescriptions,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN p.status = 'dispensed' THEN 1 ELSE 0 END) as dispensed_count,
        SUM(CASE WHEN p.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        MAX(p.created_at) as last_prescription_date,
        COALESCE(
            (SELECT COUNT(*) 
             FROM prescription_items pi 
             WHERE pi.prescription_id IN (
                 SELECT p2.id 
                 FROM prescriptions p2 
                 WHERE p2.patient_id = pat.id AND p2.doctor_id = p.doctor_id
             )),
            0
        ) as total_items,
        GROUP_CONCAT(
            DISTINCT CONCAT(
                p.id, '|',
                p.prescription_number, '|',
                p.status, '|',
                DATE_FORMAT(p.created_at, '%Y-%m-%d %H:%i:%s'), '|',
                COALESCE(p.dispensed_at, ''), '|',
                COALESCE(p.notes, '')
            ) SEPARATOR '||'
        ) as prescription_data
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    WHERE $where_clause
    GROUP BY pat.id
    ORDER BY MAX(p.created_at) DESC
";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $grouped_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Grouped prescriptions fetch error: " . $e->getMessage());
    $grouped_prescriptions = [];
}

// ================================================================
// GET PRESCRIPTION ITEMS FOR VIEW DETAILS (AJAX)
// ================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_patient_prescriptions') {
    $patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    
    if ($patient_id > 0) {
        header('Content-Type: application/json');
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.prescription_number,
                    p.diagnosis,
                    p.instructions as prescription_instructions,
                    p.notes,
                    p.status,
                    p.created_at,
                    p.dispensed_at,
                    p.updated_at,
                    pat.full_name as patient_name,
                    pat.patient_id as patient_code,
                    pat.phone,
                    pat.gender,
                    pat.date_of_birth,
                    v.visit_number,
                    v.visit_type,
                    u.full_name as doctor_name,
                    ph.full_name as pharmacy_name
                FROM prescriptions p
                LEFT JOIN patients pat ON p.patient_id = pat.id
                LEFT JOIN visits v ON p.visit_id = v.id
                LEFT JOIN users u ON p.doctor_id = u.id
                LEFT JOIN users ph ON p.pharmacy_id = ph.id
                WHERE p.patient_id = ? AND p.doctor_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$patient_id, $doctor_id]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($prescriptions) > 0) {
                $result = [];
                foreach ($prescriptions as $pres) {
                    $stmt_items = $db->prepare("
                        SELECT 
                            id,
                            medication_name,
                            dosage,
                            frequency,
                            quantity,
                            duration,
                            route,
                            instructions,
                            unit_price,
                            total_price
                        FROM prescription_items
                        WHERE prescription_id = ?
                        ORDER BY created_at ASC
                    ");
                    $stmt_items->execute([$pres['id']]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    $pres['items'] = $items;
                    $result[] = $pres;
                }
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No prescriptions found for this patient']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    }
    exit;
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'confirmed' => 'badge-info'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'dispensed' => '✅ Dispensed',
        'cancelled' => '❌ Cancelled',
        'confirmed' => '🔄 Confirmed'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y', strtotime($datetime));
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#2563EB', '#0891B2'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
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
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescriptions - Braick Dispensary</title>
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
            --primary-gradient: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            --success: #059669;
            --success-dark: #047857;
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
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #E2E8F0;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1E293B;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
            --gray-400: #64748B;
            --gray-500: #94A3B8;
            --gray-600: #A3B8CC;
            --gray-700: #CBD5E1;
            --gray-800: #E2E8F0;
            --gray-900: #F1F5F9;
            --shadow-md: 0 4px 16px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #064E3B;
            --danger-bg: #7F1D1D;
            --warning-bg: #78350F;
            --purple-bg: #4C1D95;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
        /* PAGE HEADER - BLUE GRADIENT */
        /* ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            color: #ffffff !important;
        }
        .page-header * { color: #ffffff !important; }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
            color: #ffffff !important;
        }
        .page-title i { color: rgba(255,255,255,0.8) !important; }
        
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: #ffffff !important;
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9) !important;
        }
        .page-subtitle strong { color: #ffffff !important; font-weight: 700; }
        
        .btn-primary-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.15);
            color: #ffffff;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary-header:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ================================================================ */
        /* STATS CARDS */
        /* ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
        }
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .stat-card.total::before { background: var(--primary); }
        .stat-card.total .stat-icon { color: var(--primary); }
        .stat-card.total .stat-number { color: var(--primary); }
        
        .stat-card.pending::before { background: var(--warning); }
        .stat-card.pending .stat-icon { color: var(--warning); }
        .stat-card.pending .stat-number { color: var(--warning); }
        
        .stat-card.dispensed::before { background: var(--success); }
        .stat-card.dispensed .stat-icon { color: var(--success); }
        .stat-card.dispensed .stat-number { color: var(--success); }
        
        .stat-card.cancelled::before { background: var(--danger); }
        .stat-card.cancelled .stat-icon { color: var(--danger); }
        .stat-card.cancelled .stat-number { color: var(--danger); }
        
        /* ================================================================ */
        /* FILTER SECTION */
        /* ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-input {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
        }
        [data-theme="dark"] .filter-input {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        
        .btn-search {
            padding: 8px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-search:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        /* ================================================================ */
        /* TABLE */
        /* ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            position: relative;
        }
        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary-gradient);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        [data-theme="dark"] .data-table thead th {
            background: linear-gradient(135deg, #1E3A5F, #0A3D7A);
            border-bottom-color: #0A3D7A;
        }
        
        .data-table tbody td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        .data-table tbody tr.patient-row td {
            border-left: 4px solid var(--primary);
        }
        
        /* ================================================================ */
        /* BADGES */
        /* ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn-view {
            padding: 5px 16px;
            border-radius: 6px;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11,94,215,0.3);
        }
        
        .btn-view-only {
            padding: 5px 16px;
            border-radius: 6px;
            background: var(--success);
            color: white;
            border: none;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .btn-view-only:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        
        /* ================================================================ */
        /* MODAL */
        /* ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            max-width: 950px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 0;
        }
        
        .modal-header {
            padding: 18px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--bg-card);
            z-index: 10;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .modal-header h3 i { color: var(--primary); }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            padding: 0 8px;
        }
        .modal-close:hover { color: var(--danger); transform: rotate(90deg); }
        
        .modal-body {
            padding: 24px;
        }
        
        /* ================================================================ */
        /* DETAIL GRID */
        /* ================================================================ */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            margin-bottom: 20px;
            padding: 16px 20px;
            background: var(--gray-50);
            border-radius: var(--radius);
        }
        [data-theme="dark"] .detail-grid { background: var(--gray-700); }
        
        .detail-item .label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        .detail-item .value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        /* ================================================================ */
        /* PRESCRIPTION CARD */
        /* ================================================================ */
        .prescription-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 16px;
            background: var(--bg-card);
            transition: all 0.3s ease;
        }
        .prescription-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .prescription-card {
            background: var(--gray-700);
        }
        
        /* ================================================================ */
        /* ITEMS TABLE */
        /* ================================================================ */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.85rem;
        }
        .items-table thead th {
            background: var(--gray-100);
            padding: 10px 14px;
            text-align: left;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }
        [data-theme="dark"] .items-table thead th {
            background: var(--gray-700);
            color: var(--gray-400);
            border-color: var(--gray-600);
        }
        .items-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .items-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        .items-table .med-name { font-weight: 600; }
        .items-table .med-instruction {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-style: italic;
        }
        
        /* ================================================================ */
        /* EMPTY STATE */
        /* ================================================================ */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 16px;
        }
        
        /* ================================================================ */
        /* TABLE FOOTER */
        /* ================================================================ */
        .table-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* ================================================================ */
        /* SPINNER */
        /* ================================================================ */
        .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid var(--border-color);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 0.8s ease infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================ */
        /* TOAST */
        /* ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 992px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .page-header { flex-direction: column; }
            .detail-grid { grid-template-columns: 1fr; }
            .modal-content { max-width: 100%; margin: 10px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-title { font-size: 1.1rem; }
            .data-table { font-size: 0.75rem; }
            .data-table thead th, .data-table tbody td { padding: 8px 10px; }
        }
        
        /* UTILITY */
        .text-xs { font-size: 0.75rem; }
        .text-sm { font-size: 0.875rem; }
        .text-lg { font-size: 1.125rem; }
        .font-mono { font-family: monospace; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .ml-2 { margin-left: 8px; }
        .mr-1 { margin-right: 4px; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .text-gray-300 { color: var(--gray-300); }
        .text-gray-400 { color: var(--gray-400); }
        .text-gray-500 { color: var(--gray-500); }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i> My Prescriptions
                <span class="page-badge"><?= $total_count ?> Total</span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);padding:4px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                View all prescriptions grouped by patient
                <span style="font-size:0.75rem;opacity:0.7;"><?= date('F d, Y') ?></span>
                <span class="ml-2 inline-flex bg-white/20 px-3 py-1 rounded-full text-xs border border-white/10">
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="prescribe.php" class="btn-primary-header">
                <i class="fas fa-plus"></i> New Prescription
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number"><?= $total_count ?></div>
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-sub"><i class="fas fa-clock"></i> All time</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">Pending</div>
            <div class="stat-sub"><i class="fas fa-hourglass-half"></i> Awaiting pharmacy</div>
        </div>
        <div class="stat-card dispensed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">Dispensed</div>
            <div class="stat-sub"><i class="fas fa-check"></i> Completed</div>
        </div>
        <div class="stat-card cancelled">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $status_counts['cancelled'] ?? 0 ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-sub"><i class="fas fa-ban"></i> Not dispensed</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-row">
            <a href="?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=dispensed" class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">✅ Dispensed</a>
            <a href="?status=cancelled" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" placeholder="Search patient..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="view_prescriptions.php" class="filter-btn" style="border-color:#DC2626;color:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE - GROUPED BY PATIENT (NO DELETE BUTTON) -->
    <!-- ================================================================ -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:45px;"><i class="fas fa-hashtag"></i> #</th>
                        <th style="min-width:180px;"><i class="fas fa-user"></i> Patient</th>
                        <th style="min-width:80px;"><i class="fas fa-prescription"></i> Total Rx</th>
                        <th style="min-width:80px;"><i class="fas fa-pills"></i> Items</th>
                        <th style="min-width:120px;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="min-width:120px;"><i class="fas fa-calendar"></i> Last Prescription</th>
                        <th style="min-width:90px;text-align:center;"><i class="fas fa-eye"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($grouped_prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($grouped_prescriptions as $group): 
                            $prescriptions_list = [];
                            if (!empty($group['prescription_data'])) {
                                $parts = explode('||', $group['prescription_data']);
                                foreach ($parts as $part) {
                                    $data = explode('|', $part);
                                    if (count($data) >= 5) {
                                        $prescriptions_list[] = [
                                            'id' => $data[0],
                                            'number' => $data[1],
                                            'status' => $data[2],
                                            'created_at' => $data[3],
                                            'dispensed_at' => $data[4],
                                            'notes' => $data[5] ?? ''
                                        ];
                                    }
                                }
                            }
                        ?>
                            <tr class="patient-row">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div style="width:40px;height:40px;border-radius:50%;background:<?= getUserColor($group['patient_name'] ?? 'Unknown') ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                                            <?= strtoupper(substr($group['patient_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-sm"><?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($group['patient_code'] ?? 'N/A') ?></div>
                                            <?php if (!empty($group['phone'])): ?>
                                                <div class="text-xs text-gray-400"><i class="fas fa-phone"></i> <?= htmlspecialchars($group['phone']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($group['gender'])): ?>
                                                <div class="text-xs text-gray-400"><i class="fas fa-venus-mars"></i> <?= $group['gender'] ?> <?= !empty($group['date_of_birth']) && $group['date_of_birth'] !== '0000-00-00' ? '• ' . calculateAge($group['date_of_birth']) . ' yrs' : '' ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-bold text-lg" style="color:var(--primary);"><?= $group['total_prescriptions'] ?></span>
                                    <span class="text-xs text-gray-400 block">prescriptions</span>
                                </td>
                                <td>
                                    <span class="font-semibold text-lg" style="color:var(--success);"><?= $group['total_items'] ?? 0 ?></span>
                                    <span class="text-xs text-gray-400 block">total items</span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <?php if (($group['pending_count'] ?? 0) > 0): ?>
                                            <span class="badge-status badge-warning">⏳ <?= $group['pending_count'] ?></span>
                                        <?php endif; ?>
                                        <?php if (($group['dispensed_count'] ?? 0) > 0): ?>
                                            <span class="badge-status badge-success">✅ <?= $group['dispensed_count'] ?></span>
                                        <?php endif; ?>
                                        <?php if (($group['cancelled_count'] ?? 0) > 0): ?>
                                            <span class="badge-status badge-danger">❌ <?= $group['cancelled_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-xs"><?= !empty($group['last_prescription_date']) ? formatDateShort($group['last_prescription_date']) : 'N/A' ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <button onclick="viewPrescriptionDetails(<?= $group['patient_id'] ?>)" class="btn-view-only" title="View All Prescriptions for this patient">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-prescription"></i>
                                    <p>No prescriptions found</p>
                                    <p class="text-sm text-gray-400 mt-1">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            You haven't written any prescriptions yet.
                                            <br><a href="prescribe.php" style="color:var(--primary);text-decoration:underline;">Write your first prescription</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($grouped_prescriptions) ?></strong> patients with prescriptions
            </span>
            <span>
                <span class="count-badge"><?= $total_count ?></span> Total prescriptions
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Prescriptions (Grouped by Patient)
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- MODAL - PRESCRIPTION DETAILS -->
<!-- ================================================================ -->
<div class="modal-overlay" id="prescriptionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-prescription" style="color:var(--primary);"></i> Patient Prescriptions</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="text-center" style="padding:40px 0;">
                <div class="spinner"></div>
                <p style="margin-top:12px;color:var(--text-secondary);">Loading prescriptions...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;font-size:0.85rem;margin:0;">Notification</p>
        <p id="toastMessage" style="font-size:0.75rem;opacity:0.9;margin:0;"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        setTimeout(function() { toast.classList.add('show'); }, 50);
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    // ================================================================
    // VIEW PRESCRIPTION DETAILS
    // ================================================================
    function viewPrescriptionDetails(patientId) {
        var modal = document.getElementById('prescriptionModal');
        var body = document.getElementById('modalBody');
        modal.classList.add('active');
        body.innerHTML = '<div class="text-center" style="padding:40px 0;"><div class="spinner"></div><p style="margin-top:12px;color:var(--text-secondary);">Loading prescriptions...</p></div>';
        
        fetch('view_prescriptions.php?ajax=get_patient_prescriptions&patient_id=' + patientId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderPatientPrescriptions(data.data);
                } else {
                    body.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>' + (data.message || 'Failed to load prescriptions') + '</p></div>';
                }
            })
            .catch(function(error) {
                body.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Network error. Please try again.</p></div>';
                console.error('Fetch error:', error);
            });
    }

    function renderPatientPrescriptions(data) {
        var body = document.getElementById('modalBody');
        if (!data || data.length === 0) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions found for this patient</p></div>';
            return;
        }
        
        var html = '';
        var patient = data[0];
        
        // Patient Info
        html += `
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label"><i class="fas fa-user"></i> Patient</span>
                    <span class="value">${escapeHtml(patient.patient_name || 'Unknown')}</span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-id-card"></i> Patient ID</span>
                    <span class="value">${escapeHtml(patient.patient_code || 'N/A')}</span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value">${escapeHtml(patient.phone || 'N/A')}</span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-venus-mars"></i> Gender</span>
                    <span class="value">${escapeHtml(patient.gender || 'N/A')} ${patient.date_of_birth ? '• ' + calculateAge(patient.date_of_birth) + ' yrs' : ''}</span>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <span class="label"><i class="fas fa-prescription"></i> Total Prescriptions</span>
                    <span class="value">${data.length} prescriptions</span>
                </div>
            </div>
        `;
        
        // Each Prescription
        data.forEach(function(pres, index) {
            var statusClass = pres.status === 'dispensed' ? 'badge-success' : (pres.status === 'cancelled' ? 'badge-danger' : 'badge-warning');
            var statusLabel = pres.status === 'dispensed' ? '✅ Dispensed' : (pres.status === 'cancelled' ? '❌ Cancelled' : '⏳ Pending');
            
            html += `
                <div class="prescription-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <div>
                            <span class="font-bold" style="color:var(--primary);">
                                <i class="fas fa-receipt"></i> ${escapeHtml(pres.prescription_number || 'N/A')}
                            </span>
                            <span class="badge-status ${statusClass}" style="margin-left:10px;">${statusLabel}</span>
                            ${pres.dispensed_at ? '<span class="text-xs text-gray-400 ml-2"><i class="fas fa-check-circle" style="color:#059669;"></i> Dispensed: ' + formatDate(pres.dispensed_at) + '</span>' : ''}
                        </div>
                        <div class="text-xs text-gray-400">
                            <i class="fas fa-calendar"></i> ${formatDate(pres.created_at)}
                            ${pres.visit_number ? '• Visit: ' + escapeHtml(pres.visit_number) : ''}
                        </div>
                    </div>
            `;
            
            // Diagnosis
            if (pres.diagnosis) {
                html += `
                    <div style="margin-bottom:8px;">
                        <span class="text-xs text-gray-500 font-semibold">Diagnosis:</span>
                        <span class="text-sm">${escapeHtml(pres.diagnosis)}</span>
                    </div>
                `;
            }
            
            // Instructions
            if (pres.prescription_instructions) {
                html += `
                    <div style="margin-bottom:8px;">
                        <span class="text-xs text-gray-500 font-semibold">Instructions:</span>
                        <span class="text-sm">${escapeHtml(pres.prescription_instructions)}</span>
                    </div>
                `;
            }
            
            // Notes
            if (pres.notes) {
                html += `
                    <div style="margin-bottom:8px;">
                        <span class="text-xs text-gray-500 font-semibold">Notes:</span>
                        <span class="text-sm">${escapeHtml(pres.notes)}</span>
                    </div>
                `;
            }
            
            // Pharmacy
            if (pres.pharmacy_name) {
                html += `
                    <div style="margin-bottom:8px;">
                        <span class="text-xs text-gray-500 font-semibold">Pharmacy:</span>
                        <span class="text-sm">${escapeHtml(pres.pharmacy_name)}</span>
                    </div>
                `;
            }
            
            // Items Table
            if (pres.items && pres.items.length > 0) {
                html += `
                    <div style="margin-top:10px;">
                        <div class="text-xs text-gray-500 font-semibold mb-2"><i class="fas fa-pills"></i> Medications (${pres.items.length} items)</div>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:25%;">Medication</th>
                                    <th style="width:12%;">Dosage</th>
                                    <th style="width:15%;">Frequency</th>
                                    <th style="width:8%;">Qty</th>
                                    <th style="width:10%;">Route</th>
                                    <th style="width:15%;">Price</th>
                                    <th style="width:15%;">Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                pres.items.forEach(function(item) {
                    html += `
                        <tr>
                            <td class="med-name">${escapeHtml(item.medication_name || 'N/A')}</td>
                            <td>${escapeHtml(item.dosage || '-')}</td>
                            <td>${escapeHtml(item.frequency || '-')}</td>
                            <td>${item.quantity || 0}</td>
                            <td>${escapeHtml(item.route || '-')}</td>
                            <td>${item.total_price ? 'TSh ' + Number(item.total_price).toLocaleString() : '-'}</td>
                            <td class="med-instruction">${escapeHtml(item.instructions || '-')}</td>
                        </tr>
                    `;
                });
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                html += `
                    <div class="text-sm text-gray-400" style="padding:8px 0;">
                        <i class="fas fa-info-circle"></i> No medication items
                    </div>
                `;
            }
            
            html += `
                </div>
            `;
        });
        
        body.innerHTML = html;
    }

    // ================================================================
    // CLOSE MODAL
    // ================================================================
    function closeModal() {
        var modal = document.getElementById('prescriptionModal');
        modal.classList.remove('active');
    }

    document.getElementById('prescriptionModal').addEventListener('click', function(e) {
        if (e.target === this) { closeModal(); }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeModal(); }
    });

    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        var date = new Date(datetime);
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function calculateAge(dob) {
        if (!dob || dob === '0000-00-00') return 'N/A';
        var birthDate = new Date(dob);
        var today = new Date();
        var age = today.getFullYear() - birthDate.getFullYear();
        var m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) { age--; }
        return age;
    }

    console.log('%c💊 View Prescriptions - VIEW ONLY (No Delete)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Prescriptions: <?= $total_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:12px; color:#059669;');
    console.log('%c❌ Cancelled: <?= $status_counts['cancelled'] ?? 0 ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c🚫 DELETE BUTTON REMOVED - VIEW ONLY', 'font-size:12px; color:#7C3AED;');
</script>

</body>
</html>