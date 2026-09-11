<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_test.php
// LABORATORY - VIEW TEST(S) - WITH EQUIPMENT & 2-COLUMN LAYOUT
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PARAMETERS - Support test_ids
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';
$test_ids_param = isset($_GET['test_ids']) ? trim($_GET['test_ids']) : '';

// Parse test_ids
$test_ids_array = [];
if (!empty($test_ids_param)) {
    $test_ids_array = array_filter(array_map('intval', explode(',', $test_ids_param)));
}

$tests = [];
$patient_info = [];
$is_group_view = false;
$total_price = 0;
$lab_bill = null;
$lab_bill_items = [];
$lab_bill_total = 0;

try {
    if (!empty($test_ids_array)) {
        // ================================================================
        // VIEW BY TEST IDs (from grouped completed_tests)
        // ================================================================
        $is_group_view = true;
        $placeholders = implode(',', array_fill(0, count($test_ids_array), '?'));
        
        $stmt = $db->prepare("
            SELECT 
                lt.*,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone, p.gender, p.date_of_birth, p.blood_group,
                p.allergies, p.address,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
                v.hpi, v.physical_exam, v.treatment
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            WHERE lt.id IN ($placeholders)
            AND lt.branch_id = ?
            ORDER BY lt.completed_at ASC
        ");
        $params = array_merge($test_ids_array, [$user_branch_id]);
        $stmt->execute($params);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tests)) {
            header('Location: completed_tests.php?error=no_tests');
            exit;
        }
        
        $first = $tests[0];
        $visit_id = $first['visit_id'];
        $patient_id = $first['patient_id'];
        $patient_info = [
            'patient_name' => $first['patient_name'],
            'patient_code' => $first['patient_code'],
            'phone' => $first['phone'],
            'gender' => $first['gender'],
            'date_of_birth' => $first['date_of_birth'],
            'blood_group' => $first['blood_group'],
            'allergies' => $first['allergies'],
            'address' => $first['address'],
            'doctor_name' => $first['doctor_name'],
            'doctor_specialty' => $first['doctor_specialty'],
            'visit_number' => $first['visit_number'],
            'visit_type' => $first['visit_type'],
            'diagnosis' => $first['diagnosis'],
            'symptoms' => $first['symptoms'],
            'hpi' => $first['hpi'],
            'physical_exam' => $first['physical_exam'],
            'treatment' => $first['treatment'],
        ];
        
        foreach ($tests as $t) {
            $total_price += (float)($t['test_price'] ?? 0);
        }
        
    } elseif ($view_all && $patient_id > 0 && $visit_id > 0) {
        // ================================================================
        // VIEW ALL TESTS (by patient + visit)
        // ================================================================
        $is_group_view = true;
        
        $stmt = $db->prepare("
            SELECT 
                lt.*,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone, p.gender, p.date_of_birth, p.blood_group,
                p.allergies, p.address,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
                v.hpi, v.physical_exam, v.treatment
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            WHERE lt.patient_id = ? 
            AND lt.visit_id = ?
            AND lt.branch_id = ?
            AND lt.status = 'completed'
            ORDER BY lt.completed_at ASC
        ");
        $stmt->execute([$patient_id, $visit_id, $user_branch_id]);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tests)) {
            header('Location: completed_tests.php?error=no_tests');
            exit;
        }
        
        $first = $tests[0];
        $patient_info = [
            'patient_name' => $first['patient_name'],
            'patient_code' => $first['patient_code'],
            'phone' => $first['phone'],
            'gender' => $first['gender'],
            'date_of_birth' => $first['date_of_birth'],
            'blood_group' => $first['blood_group'],
            'allergies' => $first['allergies'],
            'address' => $first['address'],
            'doctor_name' => $first['doctor_name'],
            'doctor_specialty' => $first['doctor_specialty'],
            'visit_number' => $first['visit_number'],
            'visit_type' => $first['visit_type'],
            'diagnosis' => $first['diagnosis'],
            'symptoms' => $first['symptoms'],
            'hpi' => $first['hpi'],
            'physical_exam' => $first['physical_exam'],
            'treatment' => $first['treatment'],
        ];
        
        foreach ($tests as $t) {
            $total_price += (float)($t['test_price'] ?? 0);
        }
        
    } elseif ($test_id > 0) {
        // ================================================================
        // VIEW SINGLE TEST
        // ================================================================
        $stmt = $db->prepare("
            SELECT 
                lt.*,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone, p.gender, p.date_of_birth, p.blood_group,
                p.allergies, p.address,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
                v.hpi, v.physical_exam, v.treatment
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            WHERE lt.id = ? AND lt.branch_id = ?
        ");
        $stmt->execute([$test_id, $user_branch_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            header('Location: completed_tests.php?error=not_found');
            exit;
        }
        
        $tests = [$test];
        $total_price = (float)($test['test_price'] ?? 0);
        $visit_id = $test['visit_id'];
        $patient_id = $test['patient_id'];
        
        $patient_info = [
            'patient_name' => $test['patient_name'],
            'patient_code' => $test['patient_code'],
            'phone' => $test['phone'],
            'gender' => $test['gender'],
            'date_of_birth' => $test['date_of_birth'],
            'blood_group' => $test['blood_group'],
            'allergies' => $test['allergies'],
            'address' => $test['address'],
            'doctor_name' => $test['doctor_name'],
            'doctor_specialty' => $test['doctor_specialty'],
            'visit_number' => $test['visit_number'],
            'visit_type' => $test['visit_type'],
            'diagnosis' => $test['diagnosis'],
            'symptoms' => $test['symptoms'],
            'hpi' => $test['hpi'],
            'physical_exam' => $test['physical_exam'],
            'treatment' => $test['treatment'],
        ];
        
    } else {
        header('Location: completed_tests.php');
        exit;
    }
    
    // ================================================================
    // ✅ GET LINKED EQUIPMENT FOR EACH TEST (NEW)
    // ================================================================
    foreach ($tests as $key => $test) {
        $linked_equipment = [];
        $linked_tests = [];
        
        try {
            // Check kama lab_tests ina column ya equipment_id au linked_test_id
            // Jaribu kutafuta equipment iliyotumika kwa test hii
            
            // Option 1: Kama kuna table ya lab_test_equipment (many-to-many)
            try {
                $stmt = $db->prepare("
                    SELECT 
                        lte.id,
                        lte.equipment_id,
                        lte.quantity_used,
                        lte.notes,
                        me.equipment_name,
                        me.category,
                        me.unit,
                        me.selling_price,
                        me.batch_number
                    FROM lab_test_equipment lte
                    LEFT JOIN medical_equipment me ON lte.equipment_id = me.id
                    WHERE lte.test_id = ?
                ");
                $stmt->execute([$test['id']]);
                $linked_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Table haipo - tumia fallback
            }
            
            // Option 2: Kama kuna equipment_id kwenye lab_tests
            if (empty($linked_equipment) && !empty($test['equipment_id'])) {
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            me.id as equipment_id,
                            me.equipment_name,
                            me.category,
                            me.unit,
                            me.selling_price,
                            me.batch_number,
                            1 as quantity_used
                        FROM medical_equipment me
                        WHERE me.id = ?
                    ");
                    $stmt->execute([$test['equipment_id']]);
                    $equip = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($equip) {
                        $linked_equipment = [$equip];
                    }
                } catch (Exception $e) {}
            }
            
            // Option 3: Tafuta kwenye bill_items kama test ina linked equipment
            if (empty($linked_equipment) && !empty($test['visit_id'])) {
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            bi.id,
                            bi.item_name as equipment_name,
                            bi.quantity as quantity_used,
                            bi.unit_price as selling_price,
                            bi.total_price,
                            bi.item_type,
                            bi.reference_id as equipment_id
                        FROM bill_items bi
                        INNER JOIN bills b ON bi.bill_id = b.id
                        WHERE b.visit_id = ?
                        AND bi.item_type IN ('equipment', 'lab_supply', 'medical_supply')
                        AND bi.status != 'cancelled'
                        LIMIT 5
                    ");
                    $stmt->execute([$test['visit_id']]);
                    $linked_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
            }
            
            // Get linked test (kama test ime-link na test nyingine)
            if (!empty($test['linked_test_id'])) {
                try {
                    $stmt = $db->prepare("
                        SELECT id, test_name, test_type, status
                        FROM lab_tests
                        WHERE id = ?
                    ");
                    $stmt->execute([$test['linked_test_id']]);
                    $linked = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($linked) {
                        $linked_tests[] = $linked;
                    }
                } catch (Exception $e) {}
            }
            
        } catch (Exception $e) {
            error_log("Equipment fetch error: " . $e->getMessage());
        }
        
        $tests[$key]['linked_equipment'] = $linked_equipment;
        $tests[$key]['linked_tests'] = $linked_tests;
    }
    
    // ================================================================
    // GET LAB TEST BILL
    // ================================================================
    if ($visit_id > 0) {
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT
                    b.id as bill_id, b.bill_number, b.status as bill_status,
                    b.created_at as bill_created, b.subtotal as bill_subtotal,
                    b.total_discount as bill_discount, b.pharmacy_discount,
                    b.cashier_discount, b.premium_amount as bill_premium,
                    b.total_amount as bill_total, b.paid_amount as bill_paid,
                    b.balance as bill_balance
                FROM bills b
                INNER JOIN bill_items bi ON bi.bill_id = b.id
                WHERE b.visit_id = ?
                AND b.branch_id = ?
                AND bi.item_type = 'lab_test'
                AND bi.status != 'cancelled'
                ORDER BY b.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$visit_id, $user_branch_id]);
            $lab_bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lab_bill) {
                $stmt = $db->prepare("
                    SELECT 
                        bi.id, bi.item_name, bi.item_type, bi.quantity,
                        bi.unit_price, bi.total_price, bi.status as item_status,
                        bi.reference_id, bi.reference_type, bi.created_at
                    FROM bill_items bi
                    WHERE bi.bill_id = ?
                    AND bi.item_type = 'lab_test'
                    AND bi.status != 'cancelled'
                    ORDER BY bi.created_at ASC
                ");
                $stmt->execute([$lab_bill['bill_id']]);
                $lab_bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $lab_bill_total = 0;
                foreach ($lab_bill_items as $item) {
                    $lab_bill_total += (float)($item['total_price'] ?? 0);
                }
                
                $lab_paid_total = 0;
                if ($lab_bill['bill_status'] === 'paid') {
                    $lab_paid_total = $lab_bill_total;
                } elseif ($lab_bill['bill_status'] === 'partial') {
                    $bill_paid_ratio = ($lab_bill['bill_total'] > 0) 
                        ? ($lab_bill['bill_paid'] / $lab_bill['bill_total']) 
                        : 0;
                    $lab_paid_total = round($lab_bill_total * $bill_paid_ratio, 2);
                }
                
                $lab_discount = (float)($lab_bill['bill_discount'] ?? 0);
                $lab_premium = (float)($lab_bill['bill_premium'] ?? 0);
                
                $lab_bill['lab_subtotal'] = $lab_bill_total;
                $lab_bill['lab_paid'] = $lab_paid_total;
                $lab_bill['lab_total'] = max(0, $lab_bill_total - $lab_discount + $lab_premium);
                $lab_bill['lab_balance'] = max(0, $lab_bill['lab_total'] - $lab_paid_total);
                
                foreach ($lab_bill_items as $key => $item) {
                    $effective_status = $item['item_status'];
                    if ($lab_bill['bill_status'] === 'paid') {
                        $effective_status = 'paid';
                    } elseif ($lab_bill['bill_status'] === 'partial') {
                        $effective_status = 'partial';
                    }
                    $lab_bill_items[$key]['item_status'] = $effective_status;
                }
            }
        } catch (Exception $e) {
            error_log("Lab bill error: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    error_log("View test error: " . $e->getMessage());
    header('Location: completed_tests.php?error=db_error');
    exit;
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}

function getUserColor($name) {
    $colors = ['#1E3A5F', '#2563EB', '#3B82F6', '#0D9488', '#7C3AED', '#DC2626', '#D97706'];
    $index = abs(crc32($name ?? 'Unknown')) % count($colors);
    return $colors[$index];
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_group_view ? 'View All Tests' : 'View Test' ?> - Laboratory</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #DBEAFE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --premium: #D97706;
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
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            line-height: 1.6;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #1E3A5F, #2563EB, #3B82F6);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
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
            gap: 14px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .header-badge.success-badge {
            background: rgba(5, 150, 105, 0.3);
            border-color: rgba(5, 150, 105, 0.4);
            color: #6EE7B7;
            font-weight: 600;
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-title i { color: var(--primary); font-size: 1.1rem; }
        
        /* Patient Info */
        .patient-info-block {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 18px 22px;
            background: linear-gradient(135deg, var(--primary-bg), rgba(37, 99, 235, 0.08));
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
            flex-wrap: wrap;
        }
        
        .patient-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.8rem;
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .patient-details h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }
        
        .patient-details .patient-code {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: monospace;
            margin-bottom: 4px;
        }
        
        .patient-details .patient-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .patient-details .patient-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }
        
        .info-item {
            padding: 12px 16px;
            background: var(--gray-50);
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        
        [data-theme="dark"] .info-item { background: var(--gray-800); }
        
        .info-item .info-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }
        
        .info-item .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-item.full-width { grid-column: 1 / -1; }
        
        /* ================================================================ */
        /* TEST RESULTS HEADER (NEW) */
        /* ================================================================ */
        .test-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 16px 24px;
            background: linear-gradient(135deg, #1E3A5F, #2563EB);
            border-radius: var(--radius-lg);
            color: white;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .test-results-header .trh-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .test-results-header .trh-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .test-results-header .trh-title {
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .test-results-header .trh-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 2px;
        }
        
        .test-results-header .trh-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .test-results-header .trh-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* ================================================================ */
        /* TEST CARDS - 2 COLUMNS (NEW) */
        /* ================================================================ */
        .test-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 20px;
        }
        
        .test-result-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .test-result-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
        }
        
        .test-result-header {
            background: linear-gradient(135deg, #1E3A5F, #2563EB);
            color: white;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .test-result-header .test-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            flex: 1;
            min-width: 0;
        }
        
        .test-result-header .test-title .test-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            border: 2px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
        }
        
        .test-result-header .test-title .test-name-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .test-result-header .test-status {
            background: rgba(5, 150, 105, 0.3);
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 700;
            border: 1px solid rgba(5, 150, 105, 0.4);
            color: #6EE7B7;
            flex-shrink: 0;
        }
        
        .test-result-body {
            padding: 18px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        
        /* Test Meta Row */
        .test-meta-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 0.7rem;
        }
        
        .test-meta-row .meta-chip {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .test-meta-row .meta-chip.purple {
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        .test-meta-row .meta-chip.warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        /* Result Box */
        .result-box {
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.08), rgba(5, 150, 105, 0.03));
            border: 2px solid var(--success);
            border-radius: var(--radius);
            padding: 14px 16px;
        }
        
        [data-theme="dark"] .result-box {
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.15), rgba(5, 150, 105, 0.05));
        }
        
        .result-box .result-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--success);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .result-box .result-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.6;
            word-break: break-word;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            padding: 10px 14px;
            background: var(--bg-card);
            border-radius: 8px;
            border-left: 3px solid var(--success);
            max-height: 200px;
            overflow-y: auto;
        }
        
        .reference-box {
            background: var(--warning-bg);
            border: 1px solid var(--warning);
            border-radius: var(--radius);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        [data-theme="dark"] .reference-box {
            background: rgba(217, 119, 6, 0.15);
        }
        
        .reference-box i { color: var(--warning); font-size: 1rem; }
        
        .reference-box .reference-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: var(--warning);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
        }
        
        .reference-box .reference-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            font-family: monospace;
        }
        
        /* ================================================================ */
        /* EQUIPMENT USED SECTION (NEW) */
        /* ================================================================ */
        .equipment-used-box {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(124, 58, 237, 0.03));
            border: 2px solid var(--purple);
            border-radius: var(--radius);
            padding: 12px 16px;
        }
        
        [data-theme="dark"] .equipment-used-box {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(124, 58, 237, 0.05));
        }
        
        .equipment-used-box .eq-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--purple);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .equipment-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .equipment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            background: var(--bg-card);
            border-radius: 6px;
            font-size: 0.8rem;
            border-left: 3px solid var(--purple);
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .equipment-item .eq-name {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .equipment-item .eq-name i {
            color: var(--purple);
            font-size: 0.75rem;
        }
        
        .equipment-item .eq-qty {
            font-size: 0.7rem;
            color: var(--purple);
            background: var(--purple-bg);
            padding: 2px 10px;
            border-radius: 10px;
            font-weight: 700;
        }
        
        /* Linked Test Box */
        .linked-test-box {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(37, 99, 235, 0.03));
            border: 2px solid var(--primary);
            border-radius: var(--radius);
            padding: 12px 16px;
        }
        
        .linked-test-box .lt-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .linked-test-item {
            padding: 6px 10px;
            background: var(--bg-card);
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            border-left: 3px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Test Footer */
        .test-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }
        
        .test-footer .footer-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .test-footer .footer-info .footer-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .test-footer .test-price {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--success);
            font-family: monospace;
            background: var(--success-bg);
            padding: 4px 14px;
            border-radius: 16px;
            border: 1px solid var(--success);
        }
        
        /* Lab Bill Table */
        .lab-bill-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .lab-bill-table thead th {
            background: linear-gradient(135deg, #1E3A5F, #2563EB);
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }
        
        .lab-bill-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .lab-bill-table tbody tr:hover { background: var(--primary-bg); }
        
        .lab-bill-table tfoot td {
            padding: 10px 16px;
            font-size: 0.85rem;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        
        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, #1E3A5F, #2563EB);
            color: white;
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
        }
        
        .summary-box .summary-item { display: flex; flex-direction: column; gap: 4px; }
        
        .summary-box .summary-item .summary-label {
            font-size: 0.7rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }
        
        .summary-box .summary-item .summary-value {
            font-size: 1.4rem;
            font-weight: 800;
            font-family: monospace;
        }
        
        .summary-box .summary-item .summary-value.success { color: #6EE7B7; }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .action-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
            padding: 20px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 16px;
        }
        
        .empty-state h3 { font-size: 1.3rem; color: var(--text-primary); margin-bottom: 8px; }
        
        .footer {
            padding: 16px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 28px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        /* Print Styles */
        @media print {
            .laboratory-sidebar, .laboratory-header,
            .page-header .btn-outline-light, .action-bar, .footer, .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                margin-top: 0 !important;
                padding: 20px !important;
                background: white !important;
            }
            
            .card, .test-result-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
                background: white !important;
            }
            
            .test-results-header, .test-result-header, .summary-box, .page-header,
            .lab-bill-table thead th {
                background: #1E3A5F !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Print: 2 columns still */
            .test-cards-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px;
            }
            
            body { background: white !important; color: black !important; }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .test-cards-grid { gap: 14px; }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .test-cards-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.2rem; }
            .patient-info-block { padding: 14px; }
            .patient-avatar { width: 55px; height: 55px; font-size: 1.4rem; }
            .card { padding: 16px 18px; }
            .test-result-body { padding: 14px 16px; }
            .test-result-header { padding: 12px 16px; }
            .summary-box { padding: 18px; }
            .action-bar { justify-content: center; }
            .action-bar .btn { flex: 1; justify-content: center; min-width: 140px; }
            .lab-bill-table { font-size: 0.75rem; }
            .lab-bill-table thead th, .lab-bill-table tbody td { padding: 8px 10px; }
            .test-cards-grid { grid-template-columns: 1fr; }
            .test-results-header { padding: 12px 16px; }
            .test-results-header .trh-title { font-size: 1rem; }
        }
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
                <i class="fas fa-<?= $is_group_view ? 'file-medical-alt' : 'flask' ?>"></i>
                <?= $is_group_view ? 'All Test Results' : 'Test Result' ?>
                <span class="header-badge success-badge">
                    <i class="fas fa-check-circle"></i> Completed
                </span>
                <?php if ($is_group_view): ?>
                    <span class="header-badge">
                        <i class="fas fa-flask"></i> <?= count($tests) ?> <?= count($tests) == 1 ? 'Test' : 'Tests' ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($patient_info['patient_name'] ?? 'N/A') ?>
                (<?= htmlspecialchars($patient_info['patient_code'] ?? 'N/A') ?>)
                <?php if (!empty($patient_info['visit_number'])): ?>
                    <span class="header-badge">
                        <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($patient_info['visit_number']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_info['doctor_name'])): ?>
                    <span class="header-badge">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient_info['doctor_name']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;" class="no-print">
            <a href="completed_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light" style="background:rgba(5,150,105,0.3);border-color:rgba(5,150,105,0.4);">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFO CARD -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="patient-info-block">
            <div class="patient-avatar" style="background: <?= getUserColor($patient_info['patient_name'] ?? 'Unknown') ?>;">
                <?= strtoupper(substr($patient_info['patient_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="patient-details" style="flex:1;">
                <h3><?= htmlspecialchars($patient_info['patient_name'] ?? 'N/A') ?></h3>
                <div class="patient-code">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient_info['patient_code'] ?? 'N/A') ?>
                </div>
                <div class="patient-meta">
                    <?php if (!empty($patient_info['gender'])): ?>
                        <span>
                            <i class="fas fa-<?= $patient_info['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i>
                            <?= htmlspecialchars($patient_info['gender']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($patient_info['date_of_birth'])): ?>
                        <span>
                            <i class="fas fa-birthday-cake"></i>
                            <?= calculateAge($patient_info['date_of_birth']) ?> yrs
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($patient_info['blood_group'])): ?>
                        <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient_info['blood_group']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($patient_info['phone'])): ?>
                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient_info['phone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($patient_info['allergies'])): ?>
                        <span style="color:var(--danger);">
                            <i class="fas fa-exclamation-triangle"></i>
                            Allergies: <?= htmlspecialchars($patient_info['allergies']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY BOX -->
    <!-- ================================================================ -->
    <?php if ($is_group_view): ?>
    <div class="summary-box">
        <div class="summary-item">
            <span class="summary-label"><i class="fas fa-flask"></i> Total Tests</span>
            <span class="summary-value"><?= count($tests) ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label"><i class="fas fa-money-bill-wave"></i> Total Price</span>
            <span class="summary-value success">TSh <?= number_format($total_price, 0) ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label"><i class="fas fa-calendar-check"></i> Latest Completion</span>
            <span class="summary-value" style="font-size:0.9rem;">
                <?= date('M d, Y h:i A', strtotime($tests[count($tests) - 1]['completed_at'] ?? 'now')) ?>
            </span>
        </div>
        <div class="summary-item">
            <span class="summary-label"><i class="fas fa-check-circle"></i> Status</span>
            <span class="summary-value success" style="font-size:0.95rem;">✅ ALL COMPLETED</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CLINICAL INFO -->
    <!-- ================================================================ -->
    <?php 
    $has_clinical = !empty($patient_info['symptoms']) || 
                    !empty($patient_info['hpi']) || 
                    !empty($patient_info['physical_exam']) || 
                    !empty($patient_info['diagnosis']) ||
                    !empty($patient_info['treatment']);
    ?>
    
    <?php if ($has_clinical): ?>
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-stethoscope"></i> Clinical Information
        </h3>
        <div class="info-grid">
            <?php if (!empty($patient_info['symptoms'])): ?>
                <div class="info-item full-width">
                    <span class="info-label"><i class="fas fa-notes-medical"></i> Chief Complaint / Symptoms</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($patient_info['symptoms'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($patient_info['hpi'])): ?>
                <div class="info-item full-width">
                    <span class="info-label"><i class="fas fa-history"></i> History of Presenting Illness</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($patient_info['hpi'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($patient_info['physical_exam'])): ?>
                <div class="info-item full-width">
                    <span class="info-label"><i class="fas fa-user-md"></i> Physical Examination</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($patient_info['physical_exam'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($patient_info['diagnosis'])): ?>
                <div class="info-item full-width">
                    <span class="info-label"><i class="fas fa-diagnoses"></i> Diagnosis</span>
                    <span class="info-value" style="color:var(--success);"><?= nl2br(htmlspecialchars($patient_info['diagnosis'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($patient_info['treatment'])): ?>
                <div class="info-item full-width">
                    <span class="info-label"><i class="fas fa-prescription"></i> Treatment Plan</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($patient_info['treatment'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB BILL -->
    <!-- ================================================================ -->
    <?php if ($lab_bill && count($lab_bill_items) > 0): ?>
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-file-invoice-dollar"></i> 
            Lab Test Bill
            <span style="font-size:0.75rem;font-weight:500;color:var(--text-secondary);margin-left:auto;font-family:monospace;">
                <?= htmlspecialchars($lab_bill['bill_number']) ?>
            </span>
        </h3>
        
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 16px;background:var(--primary-bg);border-radius:8px;margin-bottom:16px;border-left:4px solid var(--primary);">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:0.8rem;">
                <span><i class="fas fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($lab_bill['bill_created'])) ?></span>
                <span class="badge <?= $lab_bill['bill_status'] === 'paid' ? 'badge-success' : ($lab_bill['bill_status'] === 'partial' ? 'badge-warning' : 'badge-info') ?>">
                    <?php if ($lab_bill['bill_status'] === 'paid'): ?>
                        ✅ PAID
                    <?php elseif ($lab_bill['bill_status'] === 'partial'): ?>
                        🔄 PARTIAL
                    <?php else: ?>
                        ⏳ PENDING
                    <?php endif; ?>
                </span>
            </div>
            <div style="font-size:0.75rem;color:var(--text-secondary);">
                <i class="fas fa-flask"></i> Lab Tests Only
            </div>
        </div>
        
        <div style="overflow-x:auto;border-radius:8px;border:1px solid var(--border-color);">
            <table class="lab-bill-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th style="text-align:left;">Test Name</th>
                        <th style="text-align:center;width:60px;">Qty</th>
                        <th style="text-align:right;width:120px;">Unit Price</th>
                        <th style="text-align:right;width:120px;">Total</th>
                        <th style="text-align:center;width:100px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_num = 1; foreach ($lab_bill_items as $item): ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                            <td>
                                <div style="font-weight:600;color:var(--text-primary);">
                                    <i class="fas fa-flask" style="color:var(--primary);margin-right:6px;"></i>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </div>
                            </td>
                            <td style="text-align:center;font-weight:600;"><?= (int)($item['quantity'] ?? 1) ?></td>
                            <td style="text-align:right;font-family:monospace;font-weight:600;">
                                TSh <?= number_format((float)($item['unit_price'] ?? 0), 0) ?>
                            </td>
                            <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--primary);">
                                TSh <?= number_format((float)($item['total_price'] ?? 0), 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($item['item_status'] === 'paid'): ?>
                                    <span class="badge badge-success">✅ Paid</span>
                                <?php elseif ($item['item_status'] === 'partial'): ?>
                                    <span class="badge badge-warning">🔄 Partial</span>
                                <?php else: ?>
                                    <span class="badge badge-info">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--gray-50);border-top:2px solid var(--border-color);">
                        <td colspan="4" style="text-align:right;font-weight:600;color:var(--text-secondary);">Lab Test Subtotal:</td>
                        <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--text-primary);font-size:1rem;">
                            TSh <?= number_format($lab_bill_total, 0) ?>
                        </td>
                        <td></td>
                    </tr>
                    
                    <?php if (($lab_bill['bill_discount'] ?? 0) > 0): ?>
                    <tr style="background:var(--gray-50);">
                        <td colspan="4" style="text-align:right;font-weight:600;color:var(--warning);">
                            <i class="fas fa-tag"></i> Discount:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--warning);">
                            - TSh <?= number_format((float)$lab_bill['bill_discount'], 0) ?>
                        </td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (($lab_bill['bill_premium'] ?? 0) > 0): ?>
                    <tr style="background:var(--gray-50);">
                        <td colspan="4" style="text-align:right;font-weight:600;color:var(--premium);">
                            <i class="fas fa-crown"></i> Premium:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--premium);">
                            + TSh <?= number_format((float)$lab_bill['bill_premium'], 0) ?>
                        </td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr style="background:linear-gradient(135deg, #1E3A5F, #2563EB);color:white;">
                        <td colspan="4" style="text-align:right;font-weight:800;font-size:0.9rem;text-transform:uppercase;">
                            Lab Test Total:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-weight:800;font-size:1.15rem;color:#6EE7B7;">
                            TSh <?= number_format((float)($lab_bill['lab_total'] ?? $lab_bill_total), 0) ?>
                        </td>
                        <td></td>
                    </tr>
                    
                    <tr style="background:var(--success-bg);">
                        <td colspan="4" style="text-align:right;font-weight:700;color:var(--success);">
                            <i class="fas fa-check-circle"></i> Paid:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--success);font-size:1rem;">
                            TSh <?= number_format((float)($lab_bill['lab_paid'] ?? 0), 0) ?>
                        </td>
                        <td></td>
                    </tr>
                    
                    <?php $lab_balance = (float)($lab_bill['lab_balance'] ?? 0); ?>
                    <tr style="background:<?= $lab_balance > 0 ? 'var(--danger-bg)' : 'var(--success-bg)' ?>;">
                        <td colspan="4" style="text-align:right;font-weight:700;color:<?= $lab_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                            <i class="fas fa-<?= $lab_balance > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i> Balance:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-weight:800;color:<?= $lab_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-size:1rem;">
                            TSh <?= number_format($lab_balance, 0) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- NEW: TEST RESULTS HEADER -->
    <!-- ================================================================ -->
    <div class="test-results-header">
        <div class="trh-left">
            <div class="trh-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div>
                <div class="trh-title">
                    <i class="fas fa-flask"></i> Test Results
                    <span style="background:rgba(255,255,255,0.25);padding:2px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">
                        <?= count($tests) ?> <?= count($tests) == 1 ? 'Test' : 'Tests' ?>
                    </span>
                </div>
                <div class="trh-subtitle">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient_info['patient_name'] ?? 'N/A') ?>
                    · <i class="fas fa-calendar-check"></i> All tests completed
                </div>
            </div>
        </div>
        <div class="trh-right">
            <span class="trh-badge">
                <i class="fas fa-check-circle" style="color:#6EE7B7;"></i>
                <?= count($tests) ?> Completed
            </span>
            <?php 
            $total_with_equipment = 0;
            foreach ($tests as $t) {
                if (!empty($t['linked_equipment'])) $total_with_equipment++;
            }
            if ($total_with_equipment > 0): 
            ?>
            <span class="trh-badge" style="background:rgba(124,58,237,0.3);">
                <i class="fas fa-tools"></i> <?= $total_with_equipment ?> With Equipment
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULT CARDS - 2 COLUMNS -->
    <!-- ================================================================ -->
    <?php if (count($tests) > 0): ?>
        <div class="test-cards-grid">
            <?php foreach ($tests as $index => $test): 
                $test_number = $index + 1;
                $has_equipment = !empty($test['linked_equipment']);
                $has_linked = !empty($test['linked_tests']);
            ?>
                <div class="test-result-card">
                    <!-- TEST HEADER -->
                    <div class="test-result-header">
                        <div class="test-title">
                            <span class="test-number"><?= $test_number ?></span>
                            <i class="fas fa-flask"></i>
                            <span class="test-name-text" title="<?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>">
                                <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
                            </span>
                        </div>
                        <span class="test-status">
                            <i class="fas fa-check-circle"></i> COMPLETED
                        </span>
                    </div>
                    
                    <!-- TEST BODY -->
                    <div class="test-result-body">
                        <!-- Meta Chips -->
                        <div class="test-meta-row">
                            <?php if (!empty($test['test_type'])): ?>
                                <span class="meta-chip">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($test['test_type']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($test['sample_type'])): ?>
                                <span class="meta-chip purple">
                                    <i class="fas fa-vial"></i> <?= htmlspecialchars($test['sample_type']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($has_equipment): ?>
                                <span class="meta-chip warning">
                                    <i class="fas fa-tools"></i> <?= count($test['linked_equipment']) ?> Equipment
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- RESULT -->
                        <div class="result-box">
                            <div class="result-label">
                                <i class="fas fa-file-medical-alt"></i> Test Result
                            </div>
                            <div class="result-value">
                                <?php if (!empty($test['results'])): ?>
                                    <?= nl2br(htmlspecialchars($test['results'])) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);font-style:italic;">
                                        <i class="fas fa-info-circle"></i> No result recorded
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- REFERENCE RANGE -->
                        <?php if (!empty($test['reference_range'])): ?>
                            <div class="reference-box">
                                <i class="fas fa-chart-line"></i>
                                <div>
                                    <span class="reference-label">Normal Reference Range</span>
                                    <span class="reference-value"><?= htmlspecialchars($test['reference_range']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- NEW: EQUIPMENT USED -->
                        <?php if ($has_equipment): ?>
                            <div class="equipment-used-box">
                                <div class="eq-label">
                                    <i class="fas fa-tools"></i> Equipment Used
                                </div>
                                <div class="equipment-list">
                                    <?php foreach ($test['linked_equipment'] as $eq): ?>
                                        <div class="equipment-item">
                                            <span class="eq-name">
                                                <i class="fas fa-cog"></i>
                                                <?= htmlspecialchars($eq['equipment_name'] ?? 'Unknown Equipment') ?>
                                                <?php if (!empty($eq['category'])): ?>
                                                    <span style="font-size:0.65rem;color:var(--text-secondary);font-weight:400;">
                                                        (<?= htmlspecialchars($eq['category']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (!empty($eq['quantity_used'])): ?>
                                                <span class="eq-qty">
                                                    Qty: <?= (int)$eq['quantity_used'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- NEW: LINKED TESTS -->
                        <?php if ($has_linked): ?>
                            <div class="linked-test-box">
                                <div class="lt-label">
                                    <i class="fas fa-link"></i> Linked Tests
                                </div>
                                <?php foreach ($test['linked_tests'] as $lt): ?>
                                    <div class="linked-test-item">
                                        <i class="fas fa-flask" style="color:var(--primary);"></i>
                                        <?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?>
                                        <span class="badge badge-<?= ($lt['status'] ?? '') === 'completed' ? 'success' : 'info' ?>" style="margin-left:auto;font-size:0.6rem;">
                                            <?= strtoupper($lt['status'] ?? 'N/A') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- TEST FOOTER -->
                        <div class="test-footer">
                            <div class="footer-info">
                                <span class="footer-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <?= date('M d, Y', strtotime($test['completed_at'] ?? 'now')) ?>
                                </span>
                                <span class="footer-item">
                                    <i class="fas fa-clock"></i>
                                    <?= date('h:i A', strtotime($test['completed_at'] ?? 'now')) ?>
                                </span>
                            </div>
                            <div class="test-price">
                                <i class="fas fa-money-bill-wave"></i>
                                TSh <?= number_format((float)($test['test_price'] ?? 0), 0) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- ACTION BAR -->
        <div class="action-bar no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Results
            </button>
            <a href="completed_tests.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Completed Tests
            </a>
        </div>
        
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <h3>No Tests Found</h3>
                <p>No test results available to display.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <?= $is_group_view ? 'All Test Results' : 'Test Result' ?>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    }

    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    console.log('%c🔵 View Test(s) - With Equipment & 2-Column Layout', 'font-size:20px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Test cards ziko 2 kwa row (desktop)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Header ya Test Results imeongezwa', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Equipment Used inaonekana kama ipo', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Tests: <?= count($tests) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>