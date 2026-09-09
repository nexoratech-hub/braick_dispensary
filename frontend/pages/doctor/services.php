<?php
// ================================================================
// FILE: frontend/pages/doctor/services.php
// SERVICES MANAGEMENT - FIXED VERSION
// ONLY: Procedures & Lab Tests (Equipment removed)
// Lab Tests: Equipment selection (FREE) - shows ALL equipment
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
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Main Branch';
try {
    require_once __DIR__ . '/../../../backend/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$doctor_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'];
    }
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// GET CATEGORIES FROM service_categories
// ================================================================
$service_categories = [];
try {
    $stmt = $db->prepare("
        SELECT id, category_name, description, icon, color 
        FROM service_categories 
        WHERE is_active = 1 
        ORDER BY display_order, category_name
    ");
    $stmt->execute();
    $service_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $service_categories = [];
}

// ================================================================
// GET ACTIVE TAB
// ================================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'procedures';

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$message = '';
$message_type = '';

// ================================================================
// MONEY FORMAT FUNCTION
// ================================================================
function formatMoneyInput($value) {
    if (empty($value)) return 0;
    return floatval(str_replace(',', '', $value));
}

// ================================================================
// FUNCTION TO GENERATE PROCEDURE CODE
// ================================================================
function generateProcedureCode() {
    return 'PROC-' . date('Ymd') . '-' . rand(1000, 9999);
}

// ================================================================
// ADD PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_procedure'])) {
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name = trim($_POST['category_name'] ?? '');
    $price = formatMoneyInput($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    $final_category = '';
    if ($category_id > 0) {
        foreach ($service_categories as $cat) {
            if ($cat['id'] == $category_id) {
                $final_category = $cat['category_name'];
                break;
            }
        }
    } elseif (!empty($category_name)) {
        $final_category = $category_name;
    }
    
    if (empty($procedure_name) || $price < 0) {
        $message = "❌ Procedure name and valid price are required!";
        $message_type = 'error';
    } else {
        try {
            $procedure_code = generateProcedureCode();
            
            $stmt = $db->prepare("
                INSERT INTO procedures_catalog (
                    procedure_name, procedure_code, category, price, 
                    description, is_active, branch_id, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW())
            ");
            $stmt->execute([
                $procedure_name,
                $procedure_code,
                $final_category,
                $price,
                $description,
                $doctor_branch_id,
                $doctor_id
            ]);
            
            $message = "✅ Procedure added successfully! Code: " . $procedure_code;
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// ADD LAB TEST - With Equipment Selection (FREE)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab_test'])) {
    $test_name = trim($_POST['test_name'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name = trim($_POST['category_name'] ?? '');
    $price = formatMoneyInput($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $equipment_ids = isset($_POST['equipment_ids']) ? $_POST['equipment_ids'] : [];
    
    if (!is_array($equipment_ids)) {
        $equipment_ids = [];
    }
    
    $final_category = '';
    if ($category_id > 0) {
        foreach ($service_categories as $cat) {
            if ($cat['id'] == $category_id) {
                $final_category = $cat['category_name'];
                break;
            }
        }
    } elseif (!empty($category_name)) {
        $final_category = $category_name;
    }
    
    if (empty($test_name) || $price < 0) {
        $message = "❌ Test name and valid price are required!";
        $message_type = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Insert lab test
            $stmt = $db->prepare("
                INSERT INTO lab_tests_catalog (
                    test_name, category, branch_id, price, description,
                    is_active, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            $stmt->execute([
                $test_name, 
                $final_category, 
                $doctor_branch_id, 
                $price, 
                $description,
                $doctor_id
            ]);
            $test_id = $db->lastInsertId();
            
            // ✅ Link equipment to lab test (FREE) - with branch check
            if (!empty($equipment_ids)) {
                $equipment_ids = array_map('intval', $equipment_ids);
                foreach ($equipment_ids as $equip_id) {
                    // Verify equipment exists and belongs to branch
                    $stmt = $db->prepare("
                        SELECT id FROM medical_equipment 
                        WHERE id = ? AND branch_id = ? AND status = 'active'
                    ");
                    $stmt->execute([$equip_id, $doctor_branch_id]);
                    if ($stmt->fetch()) {
                        // Check if link already exists
                        $stmt_check = $db->prepare("
                            SELECT id FROM lab_test_equipment 
                            WHERE lab_test_id = ? AND equipment_id = ? AND branch_id = ?
                        ");
                        $stmt_check->execute([$test_id, $equip_id, $doctor_branch_id]);
                        if (!$stmt_check->fetch()) {
                            $stmt = $db->prepare("
                                INSERT INTO lab_test_equipment (lab_test_id, equipment_id, branch_id, created_at)
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmt->execute([$test_id, $equip_id, $doctor_branch_id]);
                        }
                    }
                }
            }
            
            $db->commit();
            
            $equipment_text = '';
            if (!empty($equipment_ids)) {
                $equipment_text = ' with ' . count($equipment_ids) . ' equipment(s) linked (FREE)';
            }
            
            $message = "✅ Lab test added successfully!$equipment_text";
            $message_type = 'success';
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// FETCH DATA
// ================================================================

// Procedures
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as created_by_name 
        FROM procedures_catalog p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE (p.branch_id = ? OR p.branch_id IS NULL) 
        ORDER BY p.procedure_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $procedures = []; 
}

// ✅ MEDICAL EQUIPMENT - ALL ACTIVE EQUIPMENT (for lab test linking)
$all_equipment = [];
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            equipment_name,
            category,
            unit,
            quantity,
            selling_price,
            batch_number,
            expiry_date,
            status
        FROM medical_equipment 
        WHERE branch_id = ? AND status = 'active'
        ORDER BY equipment_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $all_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $all_equipment = []; 
}

// ✅ LAB TESTS with linked equipment - FIXED QUERY
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.test_name,
            l.test_code,
            l.category,
            l.price,
            l.description,
            l.is_active,
            l.branch_id,
            l.created_at,
            u.full_name as created_by_name,
            GROUP_CONCAT(DISTINCT e.id SEPARATOR ',') as equipment_ids,
            GROUP_CONCAT(DISTINCT e.equipment_name SEPARATOR '|') as equipment_names
        FROM lab_tests_catalog l
        LEFT JOIN users u ON l.created_by = u.id
        LEFT JOIN lab_test_equipment le ON l.id = le.lab_test_id AND l.branch_id = le.branch_id
        LEFT JOIN medical_equipment e ON le.equipment_id = e.id AND e.branch_id = l.branch_id
        WHERE (l.branch_id = ? OR l.branch_id IS NULL)
        GROUP BY l.id
        ORDER BY l.test_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    // Fallback query if JOIN fails
    try {
        $stmt = $db->prepare("
            SELECT 
                l.*,
                u.full_name as created_by_name,
                '' as equipment_ids,
                '' as equipment_names
            FROM lab_tests_catalog l
            LEFT JOIN users u ON l.created_by = u.id
            WHERE (l.branch_id = ? OR l.branch_id IS NULL)
            ORDER BY l.test_name
        ");
        $stmt->execute([$doctor_branch_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { 
        $lab_tests = []; 
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Braick Dispensary</title>
    <link rel="icon" href="<?php echo $logo_path; ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
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
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Inter', 'Arial', 'Segoe UI', sans-serif;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            transition: all 0.3s ease;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
        }
        [data-theme="dark"] .page-header {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .page-title i { color: var(--primary); }
        [data-theme="dark"] .page-title { color: var(--gray-100); }
        
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .page-subtitle strong { color: var(--gray-700); }
        [data-theme="dark"] .page-subtitle strong { color: var(--gray-200); }
        
        .branch-badge {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--primary-light);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] .branch-badge {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
        .admin-badge {
            display: inline-block;
            background: #FEE2E2;
            color: #DC2626;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid #DC2626;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] .admin-badge {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        [data-theme="dark"] .stat-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .stat-icon.purple { background: var(--purple-bg); color: var(--purple); }
        .stat-icon.teal { background: var(--teal-bg); color: var(--teal); }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] .stat-number { color: var(--gray-100); }
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        
        .tabs {
            display: flex;
            gap: 4px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .tabs {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .tab-btn {
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--gray-500);
            flex: 1;
            text-align: center;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .tab-btn:hover {
            background: rgba(255,255,255,0.5);
            color: var(--gray-700);
        }
        .tab-btn.active {
            background: #ffffff;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .tab-btn.active {
            background: var(--gray-800);
            color: var(--primary-light);
        }
        .tab-btn i { margin-right: 8px; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .table-wrapper {
            position: relative;
            overflow: hidden;
        }
        
        .table-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll::-webkit-scrollbar {
            height: 8px;
            width: 6px;
        }
        
        .table-scroll::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }
        
        .table-scroll::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .table-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        .slide-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(11, 94, 215, 0.3);
            opacity: 0;
            pointer-events: none;
        }
        
        .slide-arrow:hover {
            background: var(--primary-dark);
            transform: translateY(-50%) scale(1.1);
        }
        
        .slide-arrow.left { left: 8px; }
        .slide-arrow.right { right: 8px; }
        .slide-arrow.visible { opacity: 1; pointer-events: auto; }
        
        .table-container {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        [data-theme="dark"] .table-container {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .table-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        [data-theme="dark"] .table-header {
            border-color: var(--gray-700);
        }
        
        .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .table-header h3 i { color: var(--primary); }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 800px;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        
        thead th {
            text-align: left;
            padding: 12px 18px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-bottom: 3px solid #0A4CA8;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        thead th:first-child { border-radius: 8px 0 0 0; }
        thead th:last-child { border-radius: 0 8px 0 0; }
        [data-theme="dark"] thead th {
            background: linear-gradient(135deg, #1E3A5F, #0A3D7A);
            border-bottom-color: #0A3D7A;
        }
        
        td {
            padding: 10px 18px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-family: 'Inter', 'Arial', sans-serif;
            font-size: 0.85rem;
        }
        [data-theme="dark"] td {
            color: var(--gray-300);
            border-color: var(--gray-700);
        }
        tr:hover td { background: var(--gray-50); }
        [data-theme="dark"] tr:hover td { background: var(--gray-700); }
        tr:nth-child(even) td { background: var(--gray-50); }
        [data-theme="dark"] tr:nth-child(even) td { background: #1A2A3A; }
        
        .doctor-name-tag {
            display: inline-block;
            font-size: 0.65rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 1px 10px;
            border-radius: 12px;
            border: 1px solid var(--primary-light);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] .doctor-name-tag {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary-light);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .btn-view:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11,94,215,0.3);
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
        
        .badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-teal { background: var(--teal-bg); color: var(--teal); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #991B1B; }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        
        .form-group { margin-bottom: 14px; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 4px;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .form-control {
            width: 100%;
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.85rem;
            background: #ffffff;
            color: var(--gray-800);
            outline: none;
            transition: all 0.3s ease;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
        }
        [data-theme="dark"] .form-control {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 2px solid var(--gray-200);
            border-top: none;
            border-radius: 0 0 8px 8px;
            z-index: 100;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .autocomplete-list {
            background: var(--gray-800);
            border-color: var(--gray-600);
        }
        .autocomplete-list.show { display: block; }
        
        .autocomplete-item {
            padding: 8px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.85rem;
            transition: all 0.2s ease;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] .autocomplete-item {
            border-color: var(--gray-600);
        }
        .autocomplete-item:hover {
            background: var(--primary-bg);
            color: var(--primary);
        }
        .autocomplete-item.active {
            background: var(--primary);
            color: white;
        }
        .autocomplete-item .item-detail {
            font-size: 0.65rem;
            color: var(--gray-400);
            display: block;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }
        
        .money-input {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
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
        .modal-overlay.show { display: flex; }
        .modal {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 32px;
            max-width: 700px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        [data-theme="dark"] .modal { background: var(--gray-800); }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            justify-content: flex-end;
        }
        
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] .footer { border-color: var(--gray-700); }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        .empty-state .sub-text {
            font-size: 0.8rem;
            color: var(--gray-400);
            margin-top: 4px;
        }
        
        .code-badge {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-family: monospace;
            border: 1px solid var(--gray-300);
        }
        [data-theme="dark"] .code-badge {
            background: var(--gray-700);
            color: var(--gray-400);
            border-color: var(--gray-600);
        }
        
        .equipment-checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            max-height: 180px;
            overflow-y: auto;
            padding: 10px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            background: var(--gray-50);
        }
        [data-theme="dark"] .equipment-checkbox-group {
            border-color: var(--gray-600);
            background: var(--gray-700);
        }
        .equipment-checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            cursor: pointer;
            font-family: 'Inter', 'Arial', sans-serif;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .equipment-checkbox-item:hover {
            background: var(--primary-bg);
            border-color: var(--primary-light);
        }
        .equipment-checkbox-item input[type="checkbox"] {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .equipment-checkbox-item .equip-qty {
            font-size: 0.6rem;
            color: var(--gray-400);
            margin-left: auto;
        }
        .equipment-checkbox-item .equip-free {
            font-size: 0.55rem;
            color: var(--success);
            font-weight: 600;
            background: var(--success-bg);
            padding: 0 6px;
            border-radius: 8px;
        }
        .equipment-checkbox-item.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
        }
        
        .equipment-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 4px;
        }
        .equipment-tag {
            font-size: 0.6rem;
            background: var(--teal-bg);
            color: var(--teal);
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid var(--teal);
            font-family: 'Inter', 'Arial', sans-serif;
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .form-row-3 { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { flex: none; }
            .table-container { overflow-x: auto; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .equipment-checkbox-group { grid-template-columns: 1fr; }
            .modal { padding: 16px; }
            .slide-arrow { display: none !important; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cog"></i> Services Management
                <?php if ($is_admin): ?>
                    <span class="admin-badge"><i class="fas fa-user-shield"></i> Admin</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Manage <strong>Procedures</strong> and <strong>Lab Tests</strong>
                <span class="branch-badge">
                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($branch_name); ?>
                </span>
                <span style="font-size:0.75rem;color:var(--gray-400);">
                    <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($doctor_name); ?>
                </span>
                <span style="font-size:0.75rem;color:var(--teal);">
                    <i class="fas fa-info-circle"></i> Equipment linked to Lab Tests is FREE
                </span>
            </p>
        </div>
        <div>
            <a href="../doctor/dashboard.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-syringe"></i></div>
            <div>
                <div class="stat-number"><?php echo count($procedures); ?></div>
                <div class="stat-label">Procedures</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-microscope"></i></div>
            <div>
                <div class="stat-number"><?php echo count($lab_tests); ?></div>
                <div class="stat-label">Lab Tests</div>
            </div>
        </div>
    </div>
    
    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab === 'procedures' ? 'active' : ''; ?>" data-tab="procedures">
            <i class="fas fa-syringe"></i> Procedures (<?php echo count($procedures); ?>)
        </button>
        <button class="tab-btn <?php echo $active_tab === 'lab_tests' ? 'active' : ''; ?>" data-tab="lab_tests">
            <i class="fas fa-microscope"></i> Lab Tests (<?php echo count($lab_tests); ?>)
        </button>
    </div>
    
    <!-- ================================================================ -->
    <!-- TAB 1: PROCEDURES -->
    <!-- ================================================================ -->
    <div class="tab-content <?php echo $active_tab === 'procedures' ? 'active' : ''; ?>" id="tab-procedures">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-syringe"></i> Procedures - <?php echo htmlspecialchars($branch_name); ?></h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('procedureModal')">
                    <i class="fas fa-plus"></i> Add Procedure
                </button>
            </div>
            <div class="table-wrapper">
                <button class="slide-arrow left" onclick="slideTable('proceduresTable', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="slide-arrow right" onclick="slideTable('proceduresTable', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="table-scroll" id="proceduresTable">
                    <?php if (count($procedures) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:22%;">Procedure Name</th>
                                    <th style="width:14%;">Code</th>
                                    <th style="width:18%;">Category</th>
                                    <th style="width:14%;text-align:right;">Price (TSh)</th>
                                    <th style="width:10%;text-align:center;">Status</th>
                                    <th style="width:12%;">Added By</th>
                                    <th style="width:5%;text-align:center;">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($procedures as $proc): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($proc['procedure_name']); ?></strong></td>
                                        <td><span class="code-badge"><?php echo htmlspecialchars($proc['procedure_code'] ?? 'N/A'); ?></span></td>
                                        <td><?php echo htmlspecialchars($proc['category'] ?? '-'); ?></td>
                                        <td style="text-align:right;font-weight:600;color:var(--success);">
                                            <?php echo number_format($proc['price'] ?? 0, 0); ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="badge <?php echo $proc['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $proc['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="doctor-name-tag">
                                                <i class="fas fa-user-md"></i> 
                                                <?php echo htmlspecialchars($proc['created_by_name'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <button class="btn-view" onclick="viewProcedure(<?php echo htmlspecialchars(json_encode($proc)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-syringe"></i>
                            <p>No procedures added yet.</p>
                            <p class="sub-text">Click "Add Procedure" to add your first procedure.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- TAB 2: LAB TESTS -->
    <!-- ================================================================ -->
    <div class="tab-content <?php echo $active_tab === 'lab_tests' ? 'active' : ''; ?>" id="tab-lab_tests">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-microscope"></i> Lab Tests - <?php echo htmlspecialchars($branch_name); ?></h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('labTestModal')">
                    <i class="fas fa-plus"></i> Add Lab Test
                </button>
            </div>
            <div class="table-wrapper">
                <button class="slide-arrow left" onclick="slideTable('labTestsTable', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="slide-arrow right" onclick="slideTable('labTestsTable', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="table-scroll" id="labTestsTable">
                    <?php if (count($lab_tests) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:20%;">Test Name</th>
                                    <th style="width:14%;">Category</th>
                                    <th style="width:12%;text-align:right;">Price (TSh)</th>
                                    <th style="width:25%;">Equipment (FREE)</th>
                                    <th style="width:10%;text-align:center;">Status</th>
                                    <th style="width:9%;">Added By</th>
                                    <th style="width:5%;text-align:center;">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($lab_tests as $test): 
                                    $equipment_names = $test['equipment_names'] ?? '';
                                    $equipment_names_arr = !empty($equipment_names) ? explode('|', $equipment_names) : [];
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($test['test_name']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($test['category'])): ?>
                                                <span class="badge badge-purple"><?php echo htmlspecialchars($test['category']); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Uncategorized</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;font-weight:600;color:var(--success);">
                                            <?php echo number_format($test['price'], 0); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($equipment_names_arr)): ?>
                                                <div class="equipment-tags">
                                                    <?php foreach ($equipment_names_arr as $eq_name): ?>
                                                        <?php if (!empty(trim($eq_name))): ?>
                                                            <span class="equipment-tag">
                                                                <i class="fas fa-tools"></i> <?php echo htmlspecialchars(trim($eq_name)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.7rem;">No equipment linked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="badge <?php echo $test['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $test['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="doctor-name-tag">
                                                <i class="fas fa-user-md"></i> 
                                                <?php echo htmlspecialchars($test['created_by_name'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <button class="btn-view" onclick="viewLabTest(<?php echo htmlspecialchars(json_encode($test)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-microscope"></i>
                            <p>No lab tests added yet.</p>
                            <p class="sub-text">Click "Add Lab Test" to add your first test.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <p>
            <span style="color:var(--primary);font-weight:600;">Braick Dispensary</span> 
            <span style="color:var(--teal);font-weight:600;">❤️ Tunajari Afya Yako</span> |
            Services Management &copy; <?php echo date('Y'); ?> | 
            Branch: <?php echo htmlspecialchars($branch_name); ?> |
            <?php if ($is_admin): ?>
                <span style="color:#DC2626;">👑 Admin Mode</span> |
            <?php endif; ?>
            Logged in as: <strong><?php echo htmlspecialchars($doctor_name); ?></strong>
        </p>
    </footer>
</main>

<!-- ================================================================ -->
<!-- ADD PROCEDURE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="procedureModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-syringe"></i> Add Procedure
            <span style="font-size:0.7rem;font-weight:400;color:var(--gray-500);margin-left:8px;">
                by <?php echo htmlspecialchars($doctor_name); ?>
            </span>
        </h3>
        <form method="POST" id="procedureForm">
            <div class="form-group">
                <label class="form-label">Procedure Name <span style="color:red;">*</span></label>
                <div class="autocomplete-container">
                    <input type="text" name="procedure_name" id="procedureNameInput" class="form-control" required placeholder="e.g. Wound Dressing" autocomplete="off">
                    <div class="autocomplete-list" id="procedureAutocomplete"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($service_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="0">-- Other (Type manually) --</option>
                </select>
                <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
            </div>
            <div class="form-group">
                <label class="form-label">Price (TSh) <span style="color:red;">*</span></label>
                <input type="text" name="price" class="form-control money-input" required placeholder="e.g. 1,500,000" value="0">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
            </div>
            <div style="font-size:0.7rem;color:var(--gray-400);margin-bottom:12px;">
                <i class="fas fa-user-md"></i> Will be added by: <strong><?php echo htmlspecialchars($doctor_name); ?></strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('procedureModal')">Cancel</button>
                <button type="submit" name="add_procedure" class="btn btn-primary">Add Procedure</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- ADD LAB TEST MODAL - With Equipment Selection (FREE) -->
<!-- ================================================================ -->
<div class="modal-overlay" id="labTestModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-microscope"></i> Add Lab Test
            <span style="font-size:0.7rem;font-weight:400;color:var(--gray-500);margin-left:8px;">
                by <?php echo htmlspecialchars($doctor_name); ?>
            </span>
        </h3>
        <form method="POST" id="labTestForm">
            <div class="form-group">
                <label class="form-label">Test Name <span style="color:red;">*</span></label>
                <div class="autocomplete-container">
                    <input type="text" name="test_name" id="labTestNameInput" class="form-control" required placeholder="e.g. Complete Blood Count" autocomplete="off">
                    <div class="autocomplete-list" id="labTestAutocomplete"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Category <span style="color:red;">*</span></label>
                <select name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($service_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="0">-- Other (Type manually) --</option>
                </select>
                <input type="text" name="category_name" class="form-control" style="margin-top:4px;display:none;" placeholder="Enter custom category...">
            </div>
            <div class="form-group">
                <label class="form-label">Price (TSh) <span style="color:red;">*</span></label>
                <input type="text" name="price" class="form-control money-input" required placeholder="e.g. 5,000" value="0">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
            </div>
            
            <!-- ✅ Equipment Selection - Shows ALL active equipment -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-tools"></i> Select Equipment (FREE)
                    <span style="font-size:0.6rem;font-weight:400;color:var(--gray-400);">Equipment price is NOT added to test price</span>
                </label>
                <?php if (count($all_equipment) > 0): ?>
                    <div class="equipment-checkbox-group" id="equipmentCheckboxGroup">
                        <?php foreach ($all_equipment as $eq): ?>
                            <?php if ($eq['id'] > 0): ?>
                                <label class="equipment-checkbox-item" data-equip-id="<?php echo $eq['id']; ?>">
                                    <input type="checkbox" name="equipment_ids[]" value="<?php echo $eq['id']; ?>">
                                    <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                    <span class="equip-qty">(<?php echo $eq['quantity'] ?? 0; ?> in stock)</span>
                                    <span class="equip-free">FREE</span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:0.6rem;color:var(--gray-400);margin-top:4px;">
                        <i class="fas fa-info-circle"></i> Selected equipment will be linked to this test. Equipment price is FREE.
                    </div>
                <?php else: ?>
                    <div style="padding:10px;background:var(--warning-bg);border-radius:8px;color:var(--warning);font-size:0.8rem;">
                        <i class="fas fa-exclamation-triangle"></i> No equipment available. Please add equipment first in the main system.
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="font-size:0.7rem;color:var(--gray-400);margin-bottom:12px;">
                <i class="fas fa-user-md"></i> Will be added by: <strong><?php echo htmlspecialchars($doctor_name); ?></strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('labTestModal')">Cancel</button>
                <button type="submit" name="add_lab_test" class="btn btn-primary">Add Lab Test</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- VIEW MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width:600px;">
        <h3 class="modal-title" id="viewModalTitle">
            <i class="fas fa-eye"></i> Details
        </h3>
        <div id="viewModalContent" style="padding:10px 0;">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-danger" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO-SEARCH - Procedure Name
    // ================================================================
    (function() {
        var procedureData = <?php echo json_encode($procedures); ?>;
        var input = document.getElementById('procedureNameInput');
        var autocomplete = document.getElementById('procedureAutocomplete');
        
        if (!input || !autocomplete) return;
        
        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            
            if (query.length < 1) {
                autocomplete.classList.remove('show');
                return;
            }
            
            var matches = procedureData.filter(function(item) {
                return item.procedure_name.toLowerCase().includes(query);
            });
            
            if (matches.length === 0) {
                autocomplete.classList.remove('show');
                return;
            }
            
            var html = '';
            matches.forEach(function(item) {
                html += `
                    <div class="autocomplete-item" data-name="${escapeHtml(item.procedure_name)}">
                        <strong>${escapeHtml(item.procedure_name)}</strong>
                        <span class="item-detail">
                            ${escapeHtml(item.category || 'N/A')} | 
                            TSh ${Number(item.price || 0).toLocaleString()} | 
                            ${item.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                `;
            });
            
            autocomplete.innerHTML = html;
            autocomplete.classList.add('show');
            
            autocomplete.querySelectorAll('.autocomplete-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    autocomplete.classList.remove('show');
                });
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container')) {
                autocomplete.classList.remove('show');
            }
        });
    })();

    // ================================================================
    // AUTO-SEARCH - Lab Test Name
    // ================================================================
    (function() {
        var labTestData = <?php echo json_encode($lab_tests); ?>;
        var input = document.getElementById('labTestNameInput');
        var autocomplete = document.getElementById('labTestAutocomplete');
        
        if (!input || !autocomplete) return;
        
        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            
            if (query.length < 1) {
                autocomplete.classList.remove('show');
                return;
            }
            
            var matches = labTestData.filter(function(item) {
                return item.test_name.toLowerCase().includes(query);
            });
            
            if (matches.length === 0) {
                autocomplete.classList.remove('show');
                return;
            }
            
            var html = '';
            matches.forEach(function(item) {
                var equipmentNames = item.equipment_names || '';
                var equipDisplay = equipmentNames ? '🔧 ' + equipmentNames.replace(/\|/g, ', ').substring(0, 30) + (equipmentNames.length > 30 ? '...' : '') : 'No equipment';
                html += `
                    <div class="autocomplete-item" data-name="${escapeHtml(item.test_name)}">
                        <strong>${escapeHtml(item.test_name)}</strong>
                        <span class="item-detail">
                            ${escapeHtml(item.category || 'N/A')} | 
                            TSh ${Number(item.price || 0).toLocaleString()} | 
                            ${equipDisplay}
                        </span>
                    </div>
                `;
            });
            
            autocomplete.innerHTML = html;
            autocomplete.classList.add('show');
            
            autocomplete.querySelectorAll('.autocomplete-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    autocomplete.classList.remove('show');
                });
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container')) {
                autocomplete.classList.remove('show');
            }
        });
    })();

    // ================================================================
    // SLIDE TABLE FUNCTION
    // ================================================================
    function slideTable(tableId, direction) {
        var container = document.getElementById(tableId);
        if (!container) return;
        
        var scrollAmount = 400;
        var currentScroll = container.scrollLeft;
        
        if (direction === 'left') {
            container.scrollTo({ left: currentScroll - scrollAmount, behavior: 'smooth' });
        } else {
            container.scrollTo({ left: currentScroll + scrollAmount, behavior: 'smooth' });
        }
    }
    
    // ================================================================
    // SHOW/HIDE SLIDE ARROWS
    // ================================================================
    document.querySelectorAll('.table-scroll').forEach(function(container) {
        var wrapper = container.closest('.table-wrapper');
        if (!wrapper) return;
        
        var leftArrow = wrapper.querySelector('.slide-arrow.left');
        var rightArrow = wrapper.querySelector('.slide-arrow.right');
        
        function checkArrows() {
            if (!container) return;
            var scrollLeft = container.scrollLeft;
            var maxScroll = container.scrollWidth - container.clientWidth;
            if (leftArrow) {
                leftArrow.classList.toggle('visible', scrollLeft > 10);
            }
            if (rightArrow) {
                rightArrow.classList.toggle('visible', scrollLeft < maxScroll - 10);
            }
        }
        
        container.addEventListener('scroll', checkArrows);
        setTimeout(checkArrows, 300);
        window.addEventListener('resize', checkArrows);
    });

    // ================================================================
    // MONEY FORMAT
    // ================================================================
    (function() {
        function formatWithCommas(value) {
            if (!value) return '';
            var clean = value.toString().replace(/[^0-9.]/g, '');
            var parts = clean.split('.');
            var integerPart = parts[0] || '0';
            var decimalPart = parts.length > 1 ? '.' + parts[1] : '';
            
            if (integerPart.length > 3) {
                var formatted = '';
                var counter = 0;
                for (var i = integerPart.length - 1; i >= 0; i--) {
                    counter++;
                    formatted = integerPart[i] + formatted;
                    if (counter % 3 === 0 && i !== 0) {
                        formatted = ',' + formatted;
                    }
                }
                integerPart = formatted;
            }
            return integerPart + decimalPart;
        }
        
        function autoFormatMoney(input) {
            if (!input) return;
            var cursorPos = input.selectionStart;
            var lengthBefore = input.value.length;
            var formatted = formatWithCommas(input.value);
            if (formatted !== input.value) {
                input.value = formatted;
                var lengthAfter = formatted.length;
                var diff = lengthAfter - lengthBefore;
                input.setSelectionRange(cursorPos + diff, cursorPos + diff);
            }
        }
        
        function initMoneyInputs() {
            document.querySelectorAll('.money-input').forEach(function(input) {
                if (input.dataset.moneyInitialized) return;
                input.dataset.moneyInitialized = 'true';
                input.addEventListener('input', function() { autoFormatMoney(this); });
                input.addEventListener('focus', function() {
                    var raw = this.value.replace(/,/g, '');
                    this.value = raw;
                    this.select();
                });
                input.addEventListener('blur', function() {
                    this.value = this.value ? formatWithCommas(this.value) : '0';
                });
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initMoneyInputs, 100);
        });
        document.addEventListener('modalOpened', function() {
            setTimeout(initMoneyInputs, 200);
        });
    })();

    // ================================================================
    // CATEGORY - Toggle manual input
    // ================================================================
    document.querySelectorAll('select[name="category_id"]').forEach(function(select) {
        select.addEventListener('change', function() {
            var manualInput = this.parentElement.querySelector('input[name="category_name"]');
            if (manualInput) {
                if (this.value === '0') {
                    manualInput.style.display = 'block';
                    manualInput.required = true;
                } else {
                    manualInput.style.display = 'none';
                    manualInput.required = false;
                    manualInput.value = '';
                }
            }
        });
    });

    // ================================================================
    // TABS
    // ================================================================
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var tab = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.getElementById('tab-' + tab).classList.add('active');
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        });
    });
    
    // ================================================================
    // MODAL FUNCTIONS
    // ================================================================
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
        var event = new CustomEvent('modalOpened');
        document.dispatchEvent(event);
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
    // VIEW FUNCTIONS
    // ================================================================
    function viewProcedure(data) {
        document.getElementById('viewModalTitle').innerHTML = '<i class="fas fa-syringe"></i> Procedure Details';
        document.getElementById('viewModalContent').innerHTML = `
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Procedure Name</div>
                <div style="font-size:1rem;font-weight:600;">${escapeHtml(data.procedure_name)}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Code</div>
                <div><span class="code-badge">${escapeHtml(data.procedure_code || 'N/A')}</span></div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Category</div>
                <div>${escapeHtml(data.category || '-')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Price</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--success);">TSh ${Number(data.price || 0).toLocaleString()}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Description</div>
                <div>${escapeHtml(data.description || 'No description')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Status</div>
                <div><span class="badge ${data.is_active ? 'badge-success' : 'badge-danger'}">${data.is_active ? 'Active' : 'Inactive'}</span></div>
            </div>
            <div style="padding:8px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">Added By</div>
                <div><span class="doctor-name-tag"><i class="fas fa-user-md"></i> ${escapeHtml(data.created_by_name || 'Unknown')}</span></div>
            </div>
        `;
        openModal('viewModal');
    }
    
    function viewLabTest(data) {
        var equipmentNames = data.equipment_names || '';
        var equipmentHtml = '';
        if (equipmentNames) {
            var names = equipmentNames.split('|');
            equipmentHtml = '<div class="equipment-tags">';
            names.forEach(function(name) {
                if (name.trim()) {
                    equipmentHtml += '<span class="equipment-tag"><i class="fas fa-tools"></i> ' + escapeHtml(name.trim()) + ' <span style="color:var(--success);font-weight:600;">FREE</span></span>';
                }
            });
            equipmentHtml += '</div>';
        } else {
            equipmentHtml = '<span class="text-muted">No equipment linked</span>';
        }
        
        document.getElementById('viewModalTitle').innerHTML = '<i class="fas fa-microscope"></i> Lab Test Details';
        document.getElementById('viewModalContent').innerHTML = `
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Test Name</div>
                <div style="font-size:1rem;font-weight:600;">${escapeHtml(data.test_name)}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Category</div>
                <div>${escapeHtml(data.category || 'Uncategorized')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Price</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--success);">TSh ${Number(data.price).toLocaleString()}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Description</div>
                <div>${escapeHtml(data.description || 'No description')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Equipment (FREE)</div>
                <div>${equipmentHtml}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Added By</div>
                <div><span class="doctor-name-tag"><i class="fas fa-user-md"></i> ${escapeHtml(data.created_by_name || 'Unknown')}</span></div>
            </div>
            <div style="padding:8px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">Status</div>
                <div><span class="badge ${data.is_active ? 'badge-success' : 'badge-danger'}">${data.is_active ? 'Active' : 'Inactive'}</span></div>
            </div>
        `;
        openModal('viewModal');
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
    
    console.log('%c⚙️ Services Management - FIXED VERSION', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?php echo htmlspecialchars($doctor_name); ?>', 'font-size:12px; color:#64748B;');
    console.log('%c✅ Tab ya Equipment imeondolewa', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Lab Tests inaonyesha ALL equipment zilizopo', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Linking inakubalika na inaonekana kwenye database', 'font-size:12px; color:#34D399;');
    console.log('%c❤️ Braick Dispensary - Tunajari Afya Yako', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>