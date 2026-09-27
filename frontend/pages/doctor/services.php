<?php
// ================================================================
// FILE: frontend/pages/doctor/services.php
// SERVICES MANAGEMENT - V2 (WITH DIAGNOSIS MANAGEMENT)
// ================================================================
// ✅ Procedures + Lab Tests + Diseases (Diagnosis)
// ✅ Doctors can ADD + VIEW + EDIT Diagnosis
// ✅ Only DIAGNOSIS has View/Edit buttons
// ✅ Lab Tests: Equipment selection (FREE) - shows ALL equipment
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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

$doctor_id        = $_SESSION['user_id'];
$doctor_name      = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$profile_pic      = $_SESSION['profile_pic'] ?? '';
$is_admin         = ($_SESSION['role'] === 'admin');

$branch_name = 'Main Branch';
try {
    require_once __DIR__ . '/../../../backend/config/database.php';
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$doctor_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) $branch_name = $branch['name'];
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// GET SERVICE CATEGORIES
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

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'procedures';

// ================================================================
// MESSAGE SYSTEM
// ================================================================
$message = '';
$message_type = '';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

function setFlash($msg, $type = 'success') {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
}

function formatMoneyInput($value) {
    if (empty($value)) return 0;
    return floatval(str_replace(',', '', $value));
}

function generateProcedureCode() {
    return 'PROC-' . date('Ymd') . '-' . rand(1000, 9999);
}

function generateDiseaseCode() {
    return 'D-' . strtoupper(substr(md5(uniqid()), 0, 4)) . '-' . rand(100, 999);
}

// ================================================================
// ✅ ADD PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_procedure'])) {
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category_id    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name  = trim($_POST['category_name'] ?? '');
    $price          = formatMoneyInput($_POST['price'] ?? 0);
    $description    = trim($_POST['description'] ?? '');

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
        setFlash("❌ Procedure name and valid price are required!", 'error');
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
                $procedure_name, $procedure_code, $final_category,
                $price, $description, $doctor_branch_id, $doctor_id
            ]);
            setFlash("✅ Procedure added successfully! Code: " . $procedure_code, 'success');
        } catch (Exception $e) {
            setFlash("❌ Error: " . $e->getMessage(), 'error');
        }
    }
    header("Location: services.php?tab=procedures");
    exit;
}

// ================================================================
// ✅ ADD LAB TEST - With Equipment (FREE)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab_test'])) {
    $test_name     = trim($_POST['test_name'] ?? '');
    $category_id   = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name = trim($_POST['category_name'] ?? '');
    $price         = formatMoneyInput($_POST['price'] ?? 0);
    $description   = trim($_POST['description'] ?? '');
    $equipment_ids = isset($_POST['equipment_ids']) ? $_POST['equipment_ids'] : [];

    if (!is_array($equipment_ids)) $equipment_ids = [];

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
        setFlash("❌ Test name and valid price are required!", 'error');
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("
                INSERT INTO lab_tests_catalog (
                    test_name, category, branch_id, price, description,
                    is_active, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            $stmt->execute([
                $test_name, $final_category, $doctor_branch_id,
                $price, $description, $doctor_id
            ]);
            $test_id = $db->lastInsertId();

            if (!empty($equipment_ids)) {
                $equipment_ids = array_map('intval', $equipment_ids);
                foreach ($equipment_ids as $equip_id) {
                    $stmt = $db->prepare("
                        SELECT id FROM medical_equipment
                        WHERE id = ? AND branch_id = ? AND status = 'active'
                    ");
                    $stmt->execute([$equip_id, $doctor_branch_id]);
                    if ($stmt->fetch()) {
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
            $equipment_text = !empty($equipment_ids) ? ' with ' . count($equipment_ids) . ' equipment(s) linked (FREE)' : '';
            setFlash("✅ Lab test added successfully!$equipment_text", 'success');
        } catch (Exception $e) {
            $db->rollBack();
            setFlash("❌ Error: " . $e->getMessage(), 'error');
        }
    }
    header("Location: services.php?tab=lab_tests");
    exit;
}

// ================================================================
// ✅ ADD DISEASE (DIAGNOSIS)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_disease'])) {
    $disease_name = trim($_POST['disease_name'] ?? '');
    $disease_code = trim($_POST['disease_code'] ?? '');
    $icd_code     = trim($_POST['icd_code'] ?? '');
    $category     = trim($_POST['disease_category'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $treatment    = trim($_POST['treatment'] ?? '');

    if (empty($disease_code)) {
        $disease_code = generateDiseaseCode();
    }

    if (empty($disease_name)) {
        setFlash("❌ Disease name is required!", 'error');
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO diseases (
                    disease_code, disease_name, icd_code, category,
                    description, treatment, is_active, created_by, branch_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())
            ");
            $stmt->execute([
                $disease_code, $disease_name, $icd_code ?: null, $category ?: null,
                $description ?: null, $treatment ?: null,
                $doctor_id, $doctor_branch_id
            ]);
            setFlash("✅ Diagnosis added successfully! Code: " . $disease_code, 'success');
        } catch (Exception $e) {
            setFlash("❌ Error: " . $e->getMessage(), 'error');
        }
    }
    header("Location: services.php?tab=diagnosis");
    exit;
}

// ================================================================
// ✅ UPDATE DISEASE (EDIT)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_disease'])) {
    $disease_id   = (int)($_POST['disease_id'] ?? 0);
    $disease_name = trim($_POST['edit_disease_name'] ?? '');
    $disease_code = trim($_POST['edit_disease_code'] ?? '');
    $icd_code     = trim($_POST['edit_icd_code'] ?? '');
    $category     = trim($_POST['edit_disease_category'] ?? '');
    $description  = trim($_POST['edit_description'] ?? '');
    $treatment    = trim($_POST['edit_treatment'] ?? '');
    $is_active    = isset($_POST['edit_is_active']) ? (int)$_POST['edit_is_active'] : 1;

    if ($disease_id <= 0 || empty($disease_name)) {
        setFlash("❌ Invalid data. Disease name is required!", 'error');
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE diseases SET
                    disease_name = ?,
                    disease_code = ?,
                    icd_code = ?,
                    category = ?,
                    description = ?,
                    treatment = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ? AND (branch_id = ? OR branch_id IS NULL)
            ");
            $stmt->execute([
                $disease_name, $disease_code, $icd_code ?: null, $category ?: null,
                $description ?: null, $treatment ?: null,
                $is_active, $disease_id, $doctor_branch_id
            ]);
            setFlash("✅ Diagnosis updated successfully!", 'success');
        } catch (Exception $e) {
            setFlash("❌ Error: " . $e->getMessage(), 'error');
        }
    }
    header("Location: services.php?tab=diagnosis");
    exit;
}

// ================================================================
// FETCH PROCEDURES
// ================================================================
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
} catch (Exception $e) { $procedures = []; }

// ================================================================
// FETCH EQUIPMENT
// ================================================================
$all_equipment = [];
try {
    $stmt = $db->prepare("
        SELECT id, equipment_name, category, unit, quantity, selling_price,
               batch_number, expiry_date, status
        FROM medical_equipment
        WHERE branch_id = ? AND status = 'active'
        ORDER BY equipment_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $all_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $all_equipment = []; }

// ================================================================
// FETCH LAB TESTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT
            l.id, l.test_name, l.test_code, l.category, l.price, l.description,
            l.is_active, l.branch_id, l.created_at,
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
    try {
        $stmt = $db->prepare("
            SELECT l.*, u.full_name as created_by_name,
                   '' as equipment_ids, '' as equipment_names
            FROM lab_tests_catalog l
            LEFT JOIN users u ON l.created_by = u.id
            WHERE (l.branch_id = ? OR l.branch_id IS NULL)
            ORDER BY l.test_name
        ");
        $stmt->execute([$doctor_branch_id]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { $lab_tests = []; }
}

// ================================================================
// ✅ FETCH DISEASES (DIAGNOSIS)
// ================================================================
$diseases = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name as created_by_name
        FROM diseases d
        LEFT JOIN users u ON d.created_by = u.id
        WHERE (d.branch_id = ? OR d.branch_id IS NULL)
        ORDER BY d.disease_name ASC
    ");
    $stmt->execute([$doctor_branch_id]);
    $diseases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback bila join ya users
    try {
        $stmt = $db->prepare("
            SELECT d.*, NULL as created_by_name
            FROM diseases d
            WHERE (d.branch_id = ? OR d.branch_id IS NULL)
            ORDER BY d.disease_name ASC
        ");
        $stmt->execute([$doctor_branch_id]);
        $diseases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { $diseases = []; }
}

// ================================================================
// PROFILE PIC + LOGO
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            --pink: #DB2777;
            --pink-bg: #FCE7F3;
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
            font-family: 'Inter', 'Arial', sans-serif;
        }
        [data-theme="dark"] body { background: var(--gray-900); color: var(--gray-100); }

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .main-content { background: var(--gray-900); color: var(--gray-100); }

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
        [data-theme="dark"] .page-header { background: var(--gray-800); border-color: var(--gray-700); }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title i { color: var(--primary); }
        [data-theme="dark"] .page-title { color: var(--gray-100); }

        .page-subtitle { font-size: 0.9rem; color: var(--gray-500); }
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
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        [data-theme="dark"] .stat-card { background: var(--gray-800); border-color: var(--gray-700); }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .stat-icon.purple { background: var(--purple-bg); color: var(--purple); }
        .stat-icon.teal { background: var(--teal-bg); color: var(--teal); }
        .stat-icon.pink { background: var(--pink-bg); color: var(--pink); }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            font-family: 'JetBrains Mono', monospace;
        }
        [data-theme="dark"] .stat-number { color: var(--gray-100); }
        .stat-label { font-size: 0.8rem; color: var(--gray-500); }

        .tabs {
            display: flex;
            gap: 4px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .tabs { background: var(--gray-700); border-color: var(--gray-600); }

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
        .tab-btn:hover { background: rgba(255,255,255,0.5); color: var(--gray-700); }
        .tab-btn.active {
            background: #ffffff;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .tab-btn.active { background: var(--gray-800); color: var(--primary-light); }
        .tab-btn i { margin-right: 8px; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .table-wrapper { position: relative; overflow: hidden; }

        .table-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .table-scroll::-webkit-scrollbar { height: 8px; width: 6px; }
        .table-scroll::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 4px; }
        .table-scroll::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }

        .table-container {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        [data-theme="dark"] .table-container { background: var(--gray-800); border-color: var(--gray-700); }

        .table-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        [data-theme="dark"] .table-header { border-color: var(--gray-700); }

        .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-header h3 i { color: var(--primary); }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 900px;
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
        }
        [data-theme="dark"] thead th {
            background: linear-gradient(135deg, #1E3A5F, #0A3D7A);
            border-bottom-color: #0A3D7A;
        }

        td {
            padding: 10px 18px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-size: 0.85rem;
        }
        [data-theme="dark"] td { color: var(--gray-300); border-color: var(--gray-700); }
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
        }
        .btn-view:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11,94,215,0.3);
        }

        .btn-edit {
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
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        .btn-edit:hover {
            background: var(--warning);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(217,119,6,0.3);
        }

        .badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-teal { background: var(--teal-bg); color: var(--teal); }
        .badge-pink { background: var(--pink-bg); color: var(--pink); }

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
        }
        [data-theme="dark"] .form-label { color: var(--gray-300); }
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
        textarea.form-control { resize: vertical; min-height: 60px; font-family: 'Inter', 'Arial', sans-serif; }

        .autocomplete-container { position: relative; width: 100%; }
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
        [data-theme="dark"] .autocomplete-list { background: var(--gray-800); border-color: var(--gray-600); }
        .autocomplete-list.show { display: block; }
        .autocomplete-item {
            padding: 8px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        [data-theme="dark"] .autocomplete-item { border-color: var(--gray-600); }
        .autocomplete-item:hover { background: var(--primary-bg); color: var(--primary); }
        .autocomplete-item .item-detail {
            font-size: 0.65rem;
            color: var(--gray-400);
            display: block;
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .money-input { font-family: 'JetBrains Mono', monospace; font-weight: 600; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
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
        }
        [data-theme="dark"] .footer { border-color: var(--gray-700); }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
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
            font-family: 'JetBrains Mono', monospace;
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
        }

        /* ============================================================
           DIAGNOSIS / DISEASE SPECIFIC STYLES
           ============================================================ */
        .disease-name {
            font-weight: 600;
            color: var(--gray-800);
        }
        [data-theme="dark"] .disease-name { color: var(--gray-100); }
        .disease-icd {
            font-size: 0.65rem;
            color: var(--gray-400);
            font-family: 'JetBrains Mono', monospace;
            margin-left: 6px;
        }
        .diagnosis-cell {
            max-width: 260px;
            white-space: normal;
            line-height: 1.4;
            font-size: 0.78rem;
        }
        .treatment-cell {
            max-width: 240px;
            white-space: normal;
            line-height: 1.4;
            font-size: 0.78rem;
            color: var(--success);
            font-weight: 500;
        }
        .action-buttons-group {
            display: flex;
            gap: 4px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { flex: none; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .equipment-checkbox-group { grid-template-columns: 1fr; }
            .modal { padding: 16px; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cog"></i> Services Management
                <?php if ($is_admin): ?>
                    <span class="admin-badge"><i class="fas fa-user-shield"></i> Admin</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Manage <strong>Procedures</strong>, <strong>Lab Tests</strong> and <strong>Diagnosis</strong>
                <span class="branch-badge">
                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($branch_name); ?>
                </span>
                <span style="font-size:0.75rem;color:var(--gray-400);">
                    <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($doctor_name); ?>
                </span>
            </p>
        </div>
        <div>
            <a href="../doctor/dashboard.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- STATS -->
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
        <div class="stat-card">
            <div class="stat-icon pink"><i class="fas fa-virus"></i></div>
            <div>
                <div class="stat-number"><?php echo count($diseases); ?></div>
                <div class="stat-label">Diagnosis</div>
            </div>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" id="flashAlert">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab === 'procedures' ? 'active' : ''; ?>" data-tab="procedures">
            <i class="fas fa-syringe"></i> Procedures (<?php echo count($procedures); ?>)
        </button>
        <button class="tab-btn <?php echo $active_tab === 'lab_tests' ? 'active' : ''; ?>" data-tab="lab_tests">
            <i class="fas fa-microscope"></i> Lab Tests (<?php echo count($lab_tests); ?>)
        </button>
        <button class="tab-btn <?php echo $active_tab === 'diagnosis' ? 'active' : ''; ?>" data-tab="diagnosis">
            <i class="fas fa-virus"></i> Diagnosis (<?php echo count($diseases); ?>)
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 1: PROCEDURES (no View/Edit on rows) -->
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
                <div class="table-scroll" id="proceduresTable">
                    <?php if (count($procedures) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:25%;">Procedure Name</th>
                                    <th style="width:15%;">Code</th>
                                    <th style="width:20%;">Category</th>
                                    <th style="width:15%;text-align:right;">Price (TSh)</th>
                                    <th style="width:10%;text-align:center;">Status</th>
                                    <th style="width:15%;">Added By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($procedures as $proc): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($proc['procedure_name']); ?></strong></td>
                                        <td><span class="code-badge"><?php echo htmlspecialchars($proc['procedure_code'] ?? 'N/A'); ?></span></td>
                                        <td><?php echo htmlspecialchars($proc['category'] ?? '-'); ?></td>
                                        <td style="text-align:right;font-weight:600;color:var(--success);font-family:'JetBrains Mono',monospace;">
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
    <!-- TAB 2: LAB TESTS (no View/Edit on rows) -->
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
                <div class="table-scroll" id="labTestsTable">
                    <?php if (count($lab_tests) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:22%;">Test Name</th>
                                    <th style="width:14%;">Category</th>
                                    <th style="width:12%;text-align:right;">Price (TSh)</th>
                                    <th style="width:25%;">Equipment (FREE)</th>
                                    <th style="width:10%;text-align:center;">Status</th>
                                    <th style="width:12%;">Added By</th>
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
                                        <td style="text-align:right;font-weight:600;color:var(--success);font-family:'JetBrains Mono',monospace;">
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
                                                <span style="font-size:0.7rem;color:var(--gray-400);">No equipment linked</span>
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

    <!-- ================================================================ -->
    <!-- TAB 3: DIAGNOSIS (with View + Edit buttons) -->
    <!-- ================================================================ -->
    <div class="tab-content <?php echo $active_tab === 'diagnosis' ? 'active' : ''; ?>" id="tab-diagnosis">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-virus"></i> Diagnosis - <?php echo htmlspecialchars($branch_name); ?></h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('diseaseModal')">
                    <i class="fas fa-plus"></i> Add Diagnosis
                </button>
            </div>
            <div class="table-wrapper">
                <div class="table-scroll" id="diseasesTable">
                    <?php if (count($diseases) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:4%;">#</th>
                                    <th style="width:22%;">Disease Name</th>
                                    <th style="width:12%;">Code</th>
                                    <th style="width:10%;">ICD Code</th>
                                    <th style="width:14%;">Category</th>
                                    <th style="width:20%;">Description</th>
                                    <th style="width:10%;text-align:center;">Status</th>
                                    <th style="width:8%;text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($diseases as $disease): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <span class="disease-name"><?php echo htmlspecialchars($disease['disease_name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="code-badge"><?php echo htmlspecialchars($disease['disease_code'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($disease['icd_code'])): ?>
                                                <span class="code-badge"><?php echo htmlspecialchars($disease['icd_code']); ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--gray-400);font-size:0.7rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($disease['category'])): ?>
                                                <span class="badge badge-purple"><?php echo htmlspecialchars($disease['category']); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Uncategorized</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="diagnosis-cell">
                                            <?php echo htmlspecialchars(mb_substr($disease['description'] ?? '—', 0, 60)) . (mb_strlen($disease['description'] ?? '') > 60 ? '...' : ''); ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="badge <?php echo ($disease['is_active'] ?? 1) ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo ($disease['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons-group">
                                                <button class="btn-view" onclick='viewDisease(<?php echo json_encode($disease, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="View">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn-edit" onclick='editDisease(<?php echo json_encode($disease, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-virus"></i>
                            <p>No diagnosis added yet.</p>
                            <p class="sub-text">Click "Add Diagnosis" to add your first diagnosis entry.</p>
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
        <form method="POST">
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
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
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
<!-- ADD LAB TEST MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="labTestModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-microscope"></i> Add Lab Test
            <span style="font-size:0.7rem;font-weight:400;color:var(--gray-500);margin-left:8px;">
                by <?php echo htmlspecialchars($doctor_name); ?>
            </span>
        </h3>
        <form method="POST">
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
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
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
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-tools"></i> Select Equipment (FREE)
                    <span style="font-size:0.6rem;font-weight:400;color:var(--gray-400);">Equipment price is NOT added to test price</span>
                </label>
                <?php if (count($all_equipment) > 0): ?>
                    <div class="equipment-checkbox-group">
                        <?php foreach ($all_equipment as $eq): ?>
                            <?php if ($eq['id'] > 0): ?>
                                <label class="equipment-checkbox-item">
                                    <input type="checkbox" name="equipment_ids[]" value="<?php echo $eq['id']; ?>">
                                    <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                    <span class="equip-qty">(<?php echo $eq['quantity'] ?? 0; ?> in stock)</span>
                                    <span class="equip-free">FREE</span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="padding:10px;background:var(--warning-bg);border-radius:8px;color:var(--warning);font-size:0.8rem;">
                        <i class="fas fa-exclamation-triangle"></i> No equipment available. Please add equipment first.
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
<!-- ✅ ADD DIAGNOSIS MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="diseaseModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-virus"></i> Add Diagnosis
            <span style="font-size:0.7rem;font-weight:400;color:var(--gray-500);margin-left:8px;">
                by <?php echo htmlspecialchars($doctor_name); ?>
            </span>
        </h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Disease Name <span style="color:red;">*</span></label>
                    <input type="text" name="disease_name" class="form-control" required placeholder="e.g. Malaria" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Disease Code (auto if empty)</label>
                    <input type="text" name="disease_code" class="form-control" placeholder="e.g. D-MALARIA-001" autocomplete="off">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ICD Code</label>
                    <input type="text" name="icd_code" class="form-control" placeholder="e.g. B54" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="disease_category" class="form-control" placeholder="e.g. Infectious Disease" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the disease..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment / Recommendations</label>
                <textarea name="treatment" class="form-control" rows="3" placeholder="e.g. Take Coartem 20/120mg for 3 days, plenty of fluids..."></textarea>
            </div>
            <div style="font-size:0.7rem;color:var(--gray-400);margin-bottom:12px;">
                <i class="fas fa-user-md"></i> Will be added by: <strong><?php echo htmlspecialchars($doctor_name); ?></strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('diseaseModal')">Cancel</button>
                <button type="submit" name="add_disease" class="btn btn-primary">Add Diagnosis</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- ✅ VIEW DIAGNOSIS MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="viewDiseaseModal">
    <div class="modal" style="max-width:640px;">
        <h3 class="modal-title">
            <i class="fas fa-virus"></i> Diagnosis Details
        </h3>
        <div id="viewDiseaseContent" style="padding:10px 0;"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-danger" onclick="closeModal('viewDiseaseModal')">Close</button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ✅ EDIT DIAGNOSIS MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="editDiseaseModal">
    <div class="modal" style="max-width:640px;">
        <h3 class="modal-title">
            <i class="fas fa-edit"></i> Edit Diagnosis
        </h3>
        <form method="POST" id="editDiseaseForm">
            <input type="hidden" name="update_disease" value="1">
            <input type="hidden" name="disease_id" id="edit_disease_id">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Disease Name <span style="color:red;">*</span></label>
                    <input type="text" name="edit_disease_name" id="edit_disease_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Disease Code</label>
                    <input type="text" name="edit_disease_code" id="edit_disease_code" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ICD Code</label>
                    <input type="text" name="edit_icd_code" id="edit_icd_code" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="edit_disease_category" id="edit_disease_category" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="edit_description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment / Recommendations</label>
                <textarea name="edit_treatment" id="edit_treatment" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="edit_is_active" id="edit_is_active" class="form-control">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('editDiseaseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    /* ================================================================
       ESCAPE HTML HELPER
       ================================================================ */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* ================================================================
       AUTOCOMPLETE - Procedure
       ================================================================ */
    (function() {
        var procedureData = <?php echo json_encode($procedures); ?>;
        var input = document.getElementById('procedureNameInput');
        var autocomplete = document.getElementById('procedureAutocomplete');
        if (!input || !autocomplete) return;

        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            if (query.length < 1) { autocomplete.classList.remove('show'); return; }

            var matches = procedureData.filter(function(item) {
                return item.procedure_name.toLowerCase().includes(query);
            });
            if (matches.length === 0) { autocomplete.classList.remove('show'); return; }

            var html = '';
            matches.forEach(function(item) {
                html += '<div class="autocomplete-item" data-name="' + escapeHtml(item.procedure_name) + '">' +
                        '<strong>' + escapeHtml(item.procedure_name) + '</strong>' +
                        '<span class="item-detail">' + escapeHtml(item.category || 'N/A') + ' | TSh ' + Number(item.price || 0).toLocaleString() + '</span>' +
                        '</div>';
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
            if (!e.target.closest('.autocomplete-container')) autocomplete.classList.remove('show');
        });
    })();

    /* ================================================================
       AUTOCOMPLETE - Lab Test
       ================================================================ */
    (function() {
        var labTestData = <?php echo json_encode($lab_tests); ?>;
        var input = document.getElementById('labTestNameInput');
        var autocomplete = document.getElementById('labTestAutocomplete');
        if (!input || !autocomplete) return;

        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            if (query.length < 1) { autocomplete.classList.remove('show'); return; }

            var matches = labTestData.filter(function(item) {
                return item.test_name.toLowerCase().includes(query);
            });
            if (matches.length === 0) { autocomplete.classList.remove('show'); return; }

            var html = '';
            matches.forEach(function(item) {
                var equipmentNames = item.equipment_names || '';
                var equipDisplay = equipmentNames ? '🔧 ' + equipmentNames.replace(/\|/g, ', ').substring(0, 30) : 'No equipment';
                html += '<div class="autocomplete-item" data-name="' + escapeHtml(item.test_name) + '">' +
                        '<strong>' + escapeHtml(item.test_name) + '</strong>' +
                        '<span class="item-detail">' + escapeHtml(item.category || 'N/A') + ' | TSh ' + Number(item.price || 0).toLocaleString() + ' | ' + equipDisplay + '</span>' +
                        '</div>';
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
            if (!e.target.closest('.autocomplete-container')) autocomplete.classList.remove('show');
        });
    })();

    /* ================================================================
       TABS
       ================================================================ */
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

    /* ================================================================
       MODAL FUNCTIONS
       ================================================================ */
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
        document.dispatchEvent(new CustomEvent('modalOpened'));
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

    /* ================================================================
       MONEY FORMAT
       ================================================================ */
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
                    if (counter % 3 === 0 && i !== 0) formatted = ',' + formatted;
                }
                integerPart = formatted;
            }
            return integerPart + decimalPart;
        }
        function initMoneyInputs() {
            document.querySelectorAll('.money-input').forEach(function(input) {
                if (input.dataset.moneyInit) return;
                input.dataset.moneyInit = 'true';
                input.addEventListener('focus', function() { this.value = this.value.replace(/,/g, ''); this.select(); });
                input.addEventListener('blur', function() { this.value = this.value ? formatWithCommas(this.value) : '0'; });
                input.addEventListener('input', function() {
                    var cursorPos = this.selectionStart;
                    var lengthBefore = this.value.length;
                    var formatted = formatWithCommas(this.value);
                    if (formatted !== this.value) {
                        this.value = formatted;
                        var diff = formatted.length - lengthBefore;
                        this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() { setTimeout(initMoneyInputs, 100); });
        document.addEventListener('modalOpened', function() { setTimeout(initMoneyInputs, 200); });
    })();

    /* ================================================================
       CATEGORY TOGGLE (manual input)
       ================================================================ */
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

    /* ================================================================
       ✅ VIEW DISEASE (Diagnosis)
       ================================================================ */
    function viewDisease(data) {
        var desc = data.description || 'No description';
        var treatment = data.treatment || 'No treatment specified';
        var icd = data.icd_code || '—';
        var category = data.category || 'Uncategorized';
        var code = data.disease_code || 'N/A';
        var createdBy = data.created_by_name || 'Unknown';
        var status = data.is_active ? 'Active' : 'Inactive';
        var statusClass = data.is_active ? 'badge-success' : 'badge-danger';

        document.getElementById('viewDiseaseContent').innerHTML = `
            <div style="padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Disease Name</div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--pink);">${escapeHtml(data.disease_name)}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div>
                    <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Disease Code</div>
                    <div><span class="code-badge">${escapeHtml(code)}</span></div>
                </div>
                <div>
                    <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">ICD Code</div>
                    <div><span class="code-badge">${escapeHtml(icd)}</span></div>
                </div>
            </div>
            <div style="padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Category</div>
                <div><span class="badge badge-purple">${escapeHtml(category)}</span></div>
            </div>
            <div style="padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Description</div>
                <div style="margin-top:4px;font-size:0.85rem;line-height:1.6;">${escapeHtml(desc)}</div>
            </div>
            <div style="padding:10px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Treatment / Recommendations</div>
                <div style="margin-top:4px;font-size:0.85rem;line-height:1.6;color:var(--success);font-weight:500;background:var(--success-bg);padding:10px;border-radius:8px;">${escapeHtml(treatment)}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:10px 0;">
                <div>
                    <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Status</div>
                    <div><span class="badge ${statusClass}">${status}</span></div>
                </div>
                <div>
                    <div style="font-size:0.7rem;color:var(--gray-500);text-transform:uppercase;font-weight:700;">Added By</div>
                    <div><span class="doctor-name-tag"><i class="fas fa-user-md"></i> ${escapeHtml(createdBy)}</span></div>
                </div>
            </div>
        `;
        openModal('viewDiseaseModal');
    }

    /* ================================================================
       ✅ EDIT DISEASE (Diagnosis)
       ================================================================ */
    function editDisease(data) {
        document.getElementById('edit_disease_id').value         = data.id || '';
        document.getElementById('edit_disease_name').value       = data.disease_name || '';
        document.getElementById('edit_disease_code').value       = data.disease_code || '';
        document.getElementById('edit_icd_code').value           = data.icd_code || '';
        document.getElementById('edit_disease_category').value   = data.category || '';
        document.getElementById('edit_description').value        = data.description || '';
        document.getElementById('edit_treatment').value          = data.treatment || '';
        document.getElementById('edit_is_active').value          = data.is_active ? '1' : '0';

        openModal('editDiseaseModal');
    }

    /* ================================================================
       AUTO-DISMISS FLASH MESSAGE
       ================================================================ */
    var flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(function() {
            flashAlert.style.transition = 'all 0.5s ease';
            flashAlert.style.opacity = '0';
            flashAlert.style.transform = 'translateY(-10px)';
            setTimeout(function() { flashAlert.remove(); }, 500);
        }, 5000);
    }

    /* ================================================================
       DARK MODE
       ================================================================ */
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    console.log('%c⚙️ Services Management - WITH DIAGNOSIS', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ Procedures + Lab Tests + Diagnosis tabs', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Diagnosis has View + Edit buttons', 'font-size:12px; color:#DB2777;');
    console.log('%c❤️ Braick Dispensary - Tunajari Afya Yako', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>