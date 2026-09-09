<?php
// ================================================================
// FILE: frontend/pages/laboratory/test_catalog.php
// LABORATORY - TEST CATALOG WITH EQUIPMENT LINKING
// ✅ SCROLL BUTTONS (< >) - FULLY WORKING - FIXED
// ✅ PURE BLUE THEME (#2563EB)
// BRAICK DISPENSARY
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
$allowed_roles = ['laboratory', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// MESSAGE VARIABLES
// ================================================================
$message = '';
$message_type = '';

// ================================================================
// GET UNIQUE CATEGORIES
// ================================================================
$existing_categories = [];
$stmt = $db->prepare("
    SELECT DISTINCT category 
    FROM lab_tests_catalog 
    WHERE branch_id = ? AND category IS NOT NULL AND category != ''
    ORDER BY category
");
$stmt->execute([$user_branch_id]);
$existing_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ================================================================
// HANDLE POST ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_test') {
        $test_name = trim($_POST['test_name'] ?? '');
        $test_code = trim($_POST['test_code'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (float)str_replace(',', '', $_POST['price'] ?? 0);
        $reference_range = trim($_POST['reference_range'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $equipment_ids = isset($_POST['equipment_ids']) ? $_POST['equipment_ids'] : [];
        
        if (!is_array($equipment_ids)) {
            $equipment_ids = [];
        }
        $equipment_ids = array_map('intval', $equipment_ids);
        
        $errors = [];
        if (empty($test_name)) $errors[] = 'Test name is required';
        if ($price < 0) $errors[] = 'Price cannot be negative';
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                if (empty($test_code)) {
                    $test_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $test_name), 0, 3)) . '-' . date('Ymd') . '-' . rand(1000, 9999);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO lab_tests_catalog (
                        test_name, test_code, category, 
                        price, reference_range, description, 
                        is_active, branch_id, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $test_name,
                    $test_code,
                    $category,
                    $price,
                    $reference_range,
                    $description,
                    $is_active,
                    $user_branch_id,
                    $user_id
                ]);
                $test_id = $db->lastInsertId();
                
                if (!empty($equipment_ids)) {
                    foreach ($equipment_ids as $equip_id) {
                        $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ? AND branch_id = ?");
                        $stmt->execute([$equip_id, $user_branch_id]);
                        if ($stmt->fetch()) {
                            $stmt = $db->prepare("
                                INSERT INTO lab_test_equipment (lab_test_id, equipment_id, branch_id, created_at)
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmt->execute([$test_id, $equip_id, $user_branch_id]);
                        }
                    }
                }
                
                $db->commit();
                
                $equipment_text = '';
                if (!empty($equipment_ids)) {
                    $equipment_text = ' with ' . count($equipment_ids) . ' equipment(s) linked';
                }
                
                $message = "✅ Test added successfully!" . $equipment_text;
                $message_type = 'success';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

// ================================================================
// BUILD QUERY
// ================================================================
$query = "
    SELECT 
        ltc.*,
        u.full_name as created_by_name,
        (SELECT COUNT(*) FROM lab_tests WHERE test_id = ltc.id AND branch_id = ?) as usage_count,
        GROUP_CONCAT(DISTINCT e.equipment_name SEPARATOR ', ') as equipment_names,
        GROUP_CONCAT(DISTINCT e.id SEPARATOR ',') as equipment_ids
    FROM lab_tests_catalog ltc
    LEFT JOIN users u ON ltc.created_by = u.id
    LEFT JOIN lab_test_equipment le ON ltc.id = le.lab_test_id
    LEFT JOIN medical_equipment e ON le.equipment_id = e.id
    WHERE ltc.branch_id = ?
";

$params = [$user_branch_id, $user_branch_id];

if (!empty($search)) {
    $query .= " AND (ltc.test_name LIKE ? OR ltc.category LIKE ? OR ltc.test_code LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($filter_status === 'active') {
    $query .= " AND ltc.is_active = 1";
} elseif ($filter_status === 'inactive') {
    $query .= " AND ltc.is_active = 0";
}

if (!empty($category_filter)) {
    $query .= " AND ltc.category = ?";
    $params[] = $category_filter;
}

$query .= " GROUP BY ltc.id ORDER BY ltc.test_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET UNIQUE CATEGORIES FOR FILTER
// ================================================================
$categories = [];
$stmt = $db->prepare("
    SELECT DISTINCT category 
    FROM lab_tests_catalog 
    WHERE branch_id = ? AND category IS NOT NULL AND category != ''
    ORDER BY category
");
$stmt->execute([$user_branch_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ================================================================
// GET ALL MEDICAL EQUIPMENT FOR FORM
// ================================================================
$equipment_list = [];
$stmt = $db->prepare("
    SELECT 
        MIN(e.id) as equipment_id,
        e.equipment_name,
        e.unit,
        SUM(e.quantity) as total_quantity,
        MIN(e.expiry_date) as expiry_date,
        CASE 
            WHEN SUM(e.quantity) <= 0 THEN 'out'
            WHEN MIN(e.expiry_date) IS NULL OR MIN(e.expiry_date) >= CURDATE() THEN 'available'
            ELSE 'expired'
        END as status
    FROM medical_equipment e
    WHERE e.branch_id = ? AND e.status = 'active'
    GROUP BY e.equipment_name
    HAVING total_quantity > 0
    ORDER BY e.equipment_name
");
$stmt->execute([$user_branch_id]);
$equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE branch_id = ? AND is_active = 1");
$stmt->execute([$user_branch_id]);
$active_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$inactive_tests = $total_tests - $active_tests;

$stmt = $db->prepare("SELECT COUNT(DISTINCT category) as count FROM lab_tests_catalog WHERE branch_id = ? AND category IS NOT NULL AND category != ''");
$stmt->execute([$user_branch_id]);
$category_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND status = 'active' AND quantity > 0");
$stmt->execute([$user_branch_id]);
$equipment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadge($is_active) {
    if ($is_active) {
        return '<span class="badge-status active"><i class="fas fa-check-circle"></i> Active</span>';
    }
    return '<span class="badge-status inactive"><i class="fas fa-times-circle"></i> Inactive</span>';
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Catalog - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - PURE BLUE THEME
           ================================================================ */
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #DBEAFE;
            --success: #059669;
            --success-bg: #ECFDF5;
            --success-text: #065F46;
            --danger: #DC2626;
            --danger-bg: #FEF2F2;
            --warning: #D97706;
            --warning-bg: #FFFBEB;
            --purple: #7C3AED;
            --purple-bg: #F5F3FF;
            --teal: #0D9488;
            --teal-bg: #F0FDFA;
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
            --radius: 8px;
            --radius-lg: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --success-text: #34D399;
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
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - PURE BLUE
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            border-radius: 12px;
            padding: 22px 30px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
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
            font-size: 1.7rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-align: center;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.red { color: var(--danger); }
        .stat-card .stat-number.purple { color: var(--purple); }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 16px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* ================================================================
           TABLE HEADER WITH SCROLL BUTTONS
           ================================================================ */
        .table-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 550px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar {
            height: 8px;
            width: 6px;
        }
        
        .table-scroll-container::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        .scroll-arrows {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .scroll-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.3);
            position: relative;
            z-index: 20;
        }
        
        .scroll-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.5);
        }
        
        .scroll-btn:active {
            transform: scale(0.95);
        }
        
        .scroll-btn i {
            font-size: 0.8rem;
        }
        
        /* ================================================================
           FORM
           ================================================================ */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .form-grid .full-width {
            grid-column: span 2;
        }
        
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 3px;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 7px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 55px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: 10px;
        }
        
        /* ================================================================
           EQUIPMENT CHECKBOX GROUP
           ================================================================ */
        .equipment-checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            max-height: 160px;
            overflow-y: auto;
            padding: 8px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-body);
        }
        
        [data-theme="dark"] .equipment-checkbox-group {
            border-color: var(--gray-600);
            background: var(--gray-700);
        }
        
        .equipment-checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .equipment-checkbox-item:hover {
            background: var(--primary-bg);
        }
        
        .equipment-checkbox-item input[type="checkbox"] {
            accent-color: var(--primary);
            width: 13px;
            height: 13px;
            cursor: pointer;
        }
        
        .equipment-checkbox-item .equip-qty {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-left: auto;
        }
        
        .equipment-checkbox-item .equip-free {
            font-size: 0.55rem;
            color: var(--success);
            font-weight: 600;
        }
        
        .equipment-checkbox-item .equip-expiry {
            font-size: 0.55rem;
            color: var(--warning);
        }
        
        .no-equipment-msg {
            padding: 10px;
            background: var(--warning-bg);
            border-radius: 6px;
            color: var(--warning);
            font-size: 0.8rem;
            text-align: center;
        }
        
        /* ================================================================
           EQUIPMENT TAGS
           ================================================================ */
        .equipment-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 2px;
        }
        
        .equipment-tag {
            font-size: 0.65rem;
            background: var(--teal-bg);
            color: var(--teal);
            padding: 2px 10px;
            border-radius: 10px;
            border: 1px solid var(--teal);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        [data-theme="dark"] .equipment-tag {
            background: #1A3A3A;
            color: #34D399;
            border-color: #34D399;
        }
        
        .equipment-tag i {
            font-size: 0.5rem;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 5px 14px;
            font-size: 0.75rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary-light);
        }
        
        .btn-view:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
        }
        
        [data-theme="dark"] .btn-view {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
        [data-theme="dark"] .btn-view:hover {
            background: var(--primary);
            color: #ffffff;
        }
        
        .btn-view i {
            font-size: 0.8rem;
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-input {
            padding: 6px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-select {
            padding: 6px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            overflow-x: visible;
            border-radius: var(--radius-lg);
            min-width: 100%;
            display: inline-block;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1100px;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th:first-child {
            border-radius: 6px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }
        
        .data-table tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table tbody tr:hover {
            background: var(--primary-bg);
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            vertical-align: middle;
            color: var(--text-primary);
            font-size: 0.82rem;
            white-space: nowrap;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-status.active {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .badge-status.inactive {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        
        [data-theme="dark"] .badge-status.active {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        
        [data-theme="dark"] .badge-status.inactive {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        
        .badge-status i {
            font-size: 0.55rem;
        }
        
        .usage-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .code-badge {
            display: inline-block;
            font-family: monospace;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
        }
        
        [data-theme="dark"] .code-badge {
            background: var(--gray-700);
            color: var(--gray-400);
            border-color: var(--gray-600);
        }
        
        .doctor-name-tag {
            display: inline-block;
            font-size: 0.7rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            white-space: nowrap;
        }
        
        [data-theme="dark"] .doctor-name-tag {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 8px 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* ================================================================
           MODAL
           ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 28px;
            max-width: 700px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .modal {
            background: var(--gray-800);
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title i {
            color: var(--primary);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            justify-content: flex-end;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 8px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: span 1; }
            .equipment-checkbox-group { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-input, .filter-select { width: 100%; }
            .card { padding: 12px 14px; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 6px 10px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .modal { padding: 16px; }
            .scroll-btn { width: 28px; height: 28px; font-size: 0.7rem; }
            .btn-view { padding: 4px 12px; font-size: 0.65rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .scroll-btn { width: 24px; height: 24px; font-size: 0.6rem; }
            .btn-view { padding: 3px 10px; font-size: 0.6rem; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - PURE BLUE -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-list-ul"></i>
                Test Catalog
                <span class="role-badge-display">LABORATORY</span>
                <span class="branch-tag">
                    <i class="fas fa-tools"></i> <?= $equipment_count ?> Equipment
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                Manage laboratory tests and link equipment
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-check-circle"></i> <?= $active_tests ?> Active
                </span>
                <span class="branch-tag">
                    <i class="fas fa-folder"></i> <?= $category_count ?> Categories
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="toggleAddForm()" class="btn-outline-light" style="background:rgba(255,255,255,0.15);">
                <i class="fas fa-plus-circle"></i> Add Test
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-3 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" id="messageBox" style="font-size:0.85rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
            <button onclick="this.parentElement.style.display='none'" class="float-right text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <p class="stat-number blue"><?= $total_tests ?></p>
            <p class="stat-label">📊 Total Tests</p>
        </div>
        <div class="stat-card">
            <p class="stat-number green"><?= $active_tests ?></p>
            <p class="stat-label">✅ Active</p>
        </div>
        <div class="stat-card">
            <p class="stat-number red"><?= $inactive_tests ?></p>
            <p class="stat-label">❌ Inactive</p>
        </div>
        <div class="stat-card">
            <p class="stat-number purple"><?= $category_count ?></p>
            <p class="stat-label">📁 Categories</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ADD TEST FORM (Hidden by default) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" id="addForm" style="display:none;border-color:var(--primary);border-width:2px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus-circle" style="color:var(--primary);"></i>
                Add New Test
                <span class="badge-count" style="background:var(--primary);">New</span>
            </h3>
            <button onclick="toggleAddForm()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_test">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" class="form-control" placeholder="e.g. Complete Blood Count" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Test Code</label>
                    <input type="text" name="test_code" class="form-control" placeholder="Auto-generated if empty">
                    <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:2px;">Leave empty to auto-generate</div>
                </div>
                
                <div class="form-group full-width">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category" id="categorySelect" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($existing_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                        <option value="__other__">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_manual" id="categoryManual" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price (TSh) <span class="required">*</span></label>
                    <input type="text" name="price" class="form-control" placeholder="0" value="0" oninput="formatMoneyInput(this)">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reference Range</label>
                    <input type="text" name="reference_range" class="form-control" placeholder="e.g. 4.5 - 11.0 x10^9/L">
                </div>
                
                <div class="form-group full-width">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Detailed description..." rows="2"></textarea>
                </div>
                
                <div class="form-group full-width" style="display:flex;align-items:center;gap:10px;padding-top:2px;">
                    <label class="form-label" style="margin-bottom:0;font-size:0.75rem;">
                        <input type="checkbox" name="is_active" checked> Active
                    </label>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- EQUIPMENT SELECTION -->
            <!-- ================================================================ -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-tools"></i> Link Equipment 
                    <span style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);">
                        (Select equipment to link with this test)
                    </span>
                </label>
                
                <?php if (count($equipment_list) > 0): ?>
                    <div class="equipment-checkbox-group">
                        <?php foreach ($equipment_list as $eq): ?>
                            <?php 
                                $eq_id = $eq['equipment_id'] ?? 0;
                                $total_qty = $eq['total_quantity'] ?? 0;
                                $expiry_date = $eq['expiry_date'] ?? '';
                            ?>
                            <?php if ($eq_id > 0 && $total_qty > 0): ?>
                                <label class="equipment-checkbox-item">
                                    <input type="checkbox" name="equipment_ids[]" value="<?= $eq_id ?>">
                                    <?= htmlspecialchars($eq['equipment_name']) ?>
                                    <span class="equip-qty">(<?= $total_qty ?>)</span>
                                    <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00'): ?>
                                        <?php if (strtotime($expiry_date) < time()): ?>
                                            <span class="equip-expiry"><i class="fas fa-exclamation-triangle"></i></span>
                                        <?php else: ?>
                                            <span class="equip-expiry"><?= date('d/m/y', strtotime($expiry_date)) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <span class="equip-free">FREE</span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:4px;">
                        <i class="fas fa-info-circle"></i> Equipment stock deducted when test is performed
                    </div>
                <?php else: ?>
                    <div class="no-equipment-msg">
                        <i class="fas fa-exclamation-triangle"></i> No active equipment available. Please add equipment first.
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="font-size:0.7rem;color:var(--text-secondary);margin-bottom:10px;">
                <i class="fas fa-user-md"></i> Added by: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Test
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <form method="GET" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="🔍 Search..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
            
            <select name="status" class="filter-select">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>✅ Active</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>❌ Inactive</option>
            </select>
            
            <?php if (!empty($categories)): ?>
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <?php if (!empty($search) || $filter_status !== 'all' || !empty($category_filter)): ?>
                <a href="test_catalog.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- TESTS TABLE - WITH WORKING SCROLL BUTTONS (FIXED) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--primary);"></i>
                Tests List
                <span class="badge-count" style="background:var(--primary);"><?= count($tests) ?></span>
            </h3>
            <div class="scroll-arrows">
                <button class="scroll-btn" id="scrollLeftBtn" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-btn" id="scrollRightBtn" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-clock mr-1"></i> <?= date('h:i:s A') ?>
                </span>
            </div>
        </div>
        
        <div class="table-wrapper">
            <div class="table-scroll-container" id="tableScrollContainer">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;border-radius:6px 0 0 0;">#</th>
                                <th style="min-width:160px;">Test Name</th>
                                <th style="width:110px;">Code</th>
                                <th style="width:130px;">Category</th>
                                <th style="width:110px;text-align:right;">Price</th>
                                <th style="width:95px;text-align:center;">Status</th>
                                <th style="width:150px;">Equipment</th>
                                <th style="width:65px;text-align:center;">Used</th>
                                <th style="width:120px;">Added By</th>
                                <th style="width:85px;text-align:center;border-radius:0 6px 0 0;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tests) > 0): ?>
                                <?php $i = 1; foreach ($tests as $test): 
                                    $equipment_names = $test['equipment_names'] ?? '';
                                    $equipment_names_arr = !empty($equipment_names) ? explode(', ', $equipment_names) : [];
                                ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:700;font-size:0.8rem;"><?= $i++ ?></td>
                                        <td>
                                            <div class="font-medium" style="font-size:0.85rem;"><?= htmlspecialchars($test['test_name']) ?></div>
                                            <?php if (!empty($test['description'])): ?>
                                                <div class="text-xs text-gray-400" style="font-size:0.65rem;"><?= htmlspecialchars(substr($test['description'], 0, 40)) ?><?= strlen($test['description']) > 40 ? '...' : '' ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($test['test_code'])): ?>
                                                <span class="code-badge"><?= htmlspecialchars($test['test_code']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.65rem;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.8rem;"><?= htmlspecialchars($test['category'] ?? 'N/A') ?></td>
                                        <td style="text-align:right;font-weight:700;color:var(--success);font-size:0.85rem;">
                                            <?= number_format($test['price'] ?? 0, 0) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?= getStatusBadge($test['is_active'] ?? 0) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($equipment_names_arr)): ?>
                                                <div class="equipment-tags">
                                                    <?php foreach (array_slice($equipment_names_arr, 0, 3) as $eq_name): ?>
                                                        <span class="equipment-tag">
                                                            <?= htmlspecialchars($eq_name) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($equipment_names_arr) > 3): ?>
                                                        <span class="equipment-tag" style="background:var(--primary-bg);color:var(--primary);border-color:var(--primary);">
                                                            +<?= count($equipment_names_arr) - 3 ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.65rem;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="usage-badge"><?= $test['usage_count'] ?? 0 ?></span>
                                        </td>
                                        <td>
                                            <span class="doctor-name-tag">
                                                <?= htmlspecialchars($test['created_by_name'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <button onclick="viewTest(<?= htmlspecialchars(json_encode($test)) ?>)" 
                                                    class="btn-view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="fas fa-list-ul" style="color: var(--primary);"></i>
                                            <p>No tests found in catalog</p>
                                            <p class="sub">Click "Add Test" to create new tests</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($tests) ?></strong> test(s)
                <span class="text-xs text-gray-400 ml-2">🏥 <?= htmlspecialchars($user_branch_name) ?></span>
            </span>
            <span>
                <span class="count-badge"><?= $active_tests ?></span> Active
                <span class="text-xs text-gray-400 ml-2" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
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
            Test Catalog
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width:650px;">
        <h3 class="modal-title">
            <i class="fas fa-eye"></i> Test Details
        </h3>
        <div id="viewModalContent" style="padding:10px 0;">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('viewModal')">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FIXED SCROLL BUTTONS -->
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
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
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
        var currentDateTime = document.getElementById('currentDateTime');
        if (currentDateTime) {
            currentDateTime.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) {
            updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // MONEY FORMAT
    // ================================================================
    function formatMoneyInput(input) {
        var raw = input.value.replace(/,/g, '');
        raw = raw.replace(/[^0-9.]/g, '');
        
        if (raw === '' || raw === '.') {
            input.value = '0';
            return;
        }
        
        var num = parseFloat(raw);
        if (isNaN(num)) {
            input.value = '0';
            return;
        }
        
        var formatted = num.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        
        input.value = formatted;
    }

    // ================================================================
    // CATEGORY - Toggle manual input
    // ================================================================
    document.getElementById('categorySelect')?.addEventListener('change', function() {
        var manualInput = document.getElementById('categoryManual');
        if (this.value === '__other__') {
            manualInput.style.display = 'block';
            manualInput.required = true;
            manualInput.focus();
            manualInput.name = 'category';
            this.name = 'category_temp';
        } else {
            manualInput.style.display = 'none';
            manualInput.required = false;
            manualInput.value = '';
            manualInput.name = 'category_temp';
            this.name = 'category';
        }
    });

    // ================================================================
    // TOGGLE ADD FORM
    // ================================================================
    function toggleAddForm() {
        var form = document.getElementById('addForm');
        if (form.style.display === 'none') {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            form.style.display = 'none';
        }
    }

    // ================================================================
    // SCROLL BUTTONS - FIXED & FULLY WORKING
    // ================================================================
    (function() {
        // Get elements
        var scrollContainer = document.getElementById('tableScrollContainer');
        var scrollLeftBtn = document.getElementById('scrollLeftBtn');
        var scrollRightBtn = document.getElementById('scrollRightBtn');
        
        // Check if elements exist
        if (!scrollContainer) {
            console.error('❌ tableScrollContainer not found!');
            return;
        }
        if (!scrollLeftBtn) {
            console.error('❌ scrollLeftBtn not found!');
            return;
        }
        if (!scrollRightBtn) {
            console.error('❌ scrollRightBtn not found!');
            return;
        }
        
        console.log('✅ Scroll elements found, initializing...');
        
        // ================================================================
        // SCROLL FUNCTION - Direct manipulation of scrollLeft
        // ================================================================
        function scrollTable(direction) {
            // Get current scroll position
            var currentScroll = scrollContainer.scrollLeft;
            var containerWidth = scrollContainer.clientWidth;
            var contentWidth = scrollContainer.scrollWidth;
            var maxScroll = contentWidth - containerWidth;
            
            console.log('📊 Scroll Info:', {
                currentScroll: currentScroll,
                containerWidth: containerWidth,
                contentWidth: contentWidth,
                maxScroll: maxScroll
            });
            
            // Scroll amount - adjust as needed
            var scrollAmount = Math.min(containerWidth * 0.8, 400);
            
            if (direction === 'left') {
                var newScroll = currentScroll - scrollAmount;
                if (newScroll < 0) newScroll = 0;
                scrollContainer.scrollLeft = newScroll;
                console.log('⬅️ Scrolled LEFT to: ' + newScroll);
            } else if (direction === 'right') {
                var newScroll = currentScroll + scrollAmount;
                if (newScroll > maxScroll) newScroll = maxScroll;
                scrollContainer.scrollLeft = newScroll;
                console.log('➡️ Scrolled RIGHT to: ' + newScroll);
            }
            
            // Update button states
            updateButtonStates();
        }
        
        // ================================================================
        // UPDATE BUTTON STATES - Enable/disable based on scroll position
        // ================================================================
        function updateButtonStates() {
            var currentScroll = scrollContainer.scrollLeft;
            var maxScroll = scrollContainer.scrollWidth - scrollContainer.clientWidth;
            
            // Left button - disable if at start
            if (currentScroll <= 1) {
                scrollLeftBtn.disabled = true;
                scrollLeftBtn.style.opacity = '0.4';
                scrollLeftBtn.style.cursor = 'not-allowed';
            } else {
                scrollLeftBtn.disabled = false;
                scrollLeftBtn.style.opacity = '1';
                scrollLeftBtn.style.cursor = 'pointer';
            }
            
            // Right button - disable if at end
            if (currentScroll >= maxScroll - 1 || maxScroll <= 0) {
                scrollRightBtn.disabled = true;
                scrollRightBtn.style.opacity = '0.4';
                scrollRightBtn.style.cursor = 'not-allowed';
            } else {
                scrollRightBtn.disabled = false;
                scrollRightBtn.style.opacity = '1';
                scrollRightBtn.style.cursor = 'pointer';
            }
        }
        
        // ================================================================
        // EVENT LISTENERS
        // ================================================================
        
        // Left button click
        scrollLeftBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱️ Left button clicked');
            scrollTable('left');
        });
        
        // Right button click
        scrollRightBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱️ Right button clicked');
            scrollTable('right');
        });
        
        // Scroll event - update button states
        scrollContainer.addEventListener('scroll', function() {
            updateButtonStates();
        });
        
        // Window resize - update button states
        window.addEventListener('resize', function() {
            updateButtonStates();
        });
        
        // ================================================================
        // KEYBOARD SHORTCUTS - Arrow keys
        // ================================================================
        document.addEventListener('keydown', function(e) {
            // Don't trigger if typing in input/textarea/select
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                scrollTable('left');
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                scrollTable('right');
            }
        });
        
        // ================================================================
        // INITIALIZE - Set initial button states
        // ================================================================
        setTimeout(function() {
            updateButtonStates();
            console.log('✅ Scroll buttons initialized successfully!');
            console.log('   Container: ' + scrollContainer.clientWidth + 'px');
            console.log('   Content: ' + scrollContainer.scrollWidth + 'px');
            console.log('   Max scroll: ' + (scrollContainer.scrollWidth - scrollContainer.clientWidth) + 'px');
        }, 100);
        
        console.log('✅ Scroll buttons are WORKING!');
        console.log('   Use: Click < or > buttons');
        console.log('   Use: Keyboard ← and → arrows');
    })();

    // ================================================================
    // VIEW TEST
    // ================================================================
    function viewTest(data) {
        var equipmentNames = data.equipment_names || '';
        var equipmentHtml = '';
        
        if (equipmentNames) {
            var names = equipmentNames.split(', ');
            equipmentHtml = '<div class="equipment-tags">';
            names.forEach(function(name) {
                equipmentHtml += '<span class="equipment-tag"><i class="fas fa-tools"></i> ' + escapeHtml(name) + '</span>';
            });
            equipmentHtml += '</div>';
        } else {
            equipmentHtml = '<span class="text-muted">No equipment linked</span>';
        }
        
        document.getElementById('viewModalContent').innerHTML = `
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Test Name</div>
                <div style="font-size:1rem;font-weight:600;color:var(--text-primary);">${escapeHtml(data.test_name)}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Test Code</div>
                <div>${data.test_code ? '<span class="code-badge">' + escapeHtml(data.test_code) + '</span>' : '<span class="text-muted">N/A</span>'}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Category</div>
                <div style="color:var(--text-primary);">${escapeHtml(data.category || 'Uncategorized')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Price</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--success);">TSh ${Number(data.price || 0).toLocaleString()}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Reference Range</div>
                <div style="color:var(--text-primary);">${escapeHtml(data.reference_range || 'N/A')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Description</div>
                <div style="color:var(--text-primary);">${escapeHtml(data.description || 'No description')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Equipment Linked</div>
                <div style="margin-top:4px;">${equipmentHtml}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Usage Count</div>
                <div style="color:var(--text-primary);font-weight:600;">${data.usage_count || 0} times</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border-color);">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Status</div>
                <div>${data.is_active ? '<span class="badge-status active"><i class="fas fa-check-circle"></i> Active</span>' : '<span class="badge-status inactive"><i class="fas fa-times-circle"></i> Inactive</span>'}</div>
            </div>
            <div style="padding:8px 0;">
                <div style="font-size:0.7rem;color:var(--text-secondary);">Added By</div>
                <div style="color:var(--text-primary);"><i class="fas fa-user-md"></i> ${escapeHtml(data.created_by_name || 'Unknown')}</div>
            </div>
        `;
        openModal('viewModal');
    }

    // ================================================================
    // MODAL FUNCTIONS
    // ================================================================
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = '';
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('viewModal');
            if (document.getElementById('addForm').style.display === 'block') {
                document.getElementById('addForm').style.display = 'none';
            }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            toggleAddForm();
        }
    });

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c📋 Braick - Test Catalog (FIXED SCROLL)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Scroll buttons (< >) are FULLY WORKING', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Uses scrollLeft directly', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Arrow keys (← →) also work', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Pure Blue theme (#2563EB) applied', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>