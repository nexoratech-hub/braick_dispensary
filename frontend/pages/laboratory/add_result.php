<?php
// ================================================================
// FILE: frontend/pages/laboratory/add_result.php
// LABORATORY - ADD TEST RESULT
// WITH SAMPLE RESULTS TO PICK OR TYPE MANUALLY
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Lab Technician
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET LAB TEST ID
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_test_id <= 0) {
    header('Location: in_progress.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();
$message = '';
$message_type = '';
$lab_test = null;

// ================================================================
// SAMPLE RESULTS FOR DIFFERENT TEST TYPES
// ================================================================
$sample_results = [
    'Blood Glucose (Fasting)' => [
        'Normal' => '70-100 mg/dL',
        'Prediabetes' => '100-125 mg/dL',
        'Diabetes' => '>126 mg/dL',
    ],
    'Blood Glucose (Random)' => [
        'Normal' => '<140 mg/dL',
        'Prediabetes' => '140-199 mg/dL',
        'Diabetes' => '>200 mg/dL',
    ],
    'Complete Blood Count (CBC)' => [
        'Normal' => 'RBC: 4.5-5.5M, WBC: 4.5-11K, HGB: 13-17g/dL, PLT: 150-400K',
        'Anemia' => 'HGB < 13g/dL (Male) or < 12g/dL (Female)',
        'Infection' => 'WBC > 11K',
    ],
    'Lipid Profile' => [
        'Normal' => 'Total: <200, LDL: <100, HDL: >40, TG: <150',
        'High Cholesterol' => 'Total: >240, LDL: >160',
        'Low HDL' => 'HDL: <40 (Male) or <50 (Female)',
    ],
    'Liver Function Test (LFT)' => [
        'Normal' => 'AST: 10-40, ALT: 7-56, ALP: 44-147, T.Bili: 0.1-1.2',
        'Hepatitis' => 'ALT > 100, AST > 80',
        'Biliary Obstruction' => 'ALP > 300, T.Bili > 5',
    ],
    'Renal Function Test (RFT)' => [
        'Normal' => 'Creatinine: 0.6-1.2, BUN: 7-20, Uric Acid: 3.5-7.2',
        'Kidney Disease' => 'Creatinine > 1.5, BUN > 30',
        'Dehydration' => 'BUN > 25, BUN/Cr > 20',
    ],
    'Malaria Rapid Test' => [
        'Negative' => 'Negative',
        'Positive (Pf)' => 'Positive - Plasmodium falciparum',
        'Positive (Pv)' => 'Positive - Plasmodium vivax',
        'Mixed' => 'Positive - Mixed infection',
    ],
    'Typhoid Test (Widal)' => [
        'Negative' => 'O: <1:80, H: <1:160',
        'Positive' => 'O: ≥1:160, H: ≥1:320',
    ],
    'HIV Rapid Test' => [
        'Negative' => 'Non-reactive',
        'Positive' => 'Reactive - Confirm with ELISA',
    ],
    'Pregnancy Test (Urine)' => [
        'Negative' => 'Negative',
        'Positive' => 'Positive - HCG detected',
    ],
    'Thyroid Function Test (TFT)' => [
        'Normal' => 'TSH: 0.4-4.0, Free T4: 0.8-1.8, Free T3: 2.3-4.2',
        'Hyperthyroidism' => 'TSH < 0.4, Free T4 > 1.8',
        'Hypothyroidism' => 'TSH > 4.0, Free T4 < 0.8',
    ],
    'Urinalysis' => [
        'Normal' => 'pH: 4.5-8.0, Protein: Negative, Glucose: Negative, RBC: 0-2/hpf, WBC: 0-5/hpf',
        'UTI' => 'WBC > 10/hpf, Bacteria present, Nitrite positive',
        'Proteinuria' => 'Protein > 30 mg/dL',
        'Glucosuria' => 'Glucose Positive',
    ],
    'COVID-19 Rapid Antigen Test' => [
        'Negative' => 'Negative',
        'Positive' => 'Positive - SARS-CoV-2 antigen detected',
    ],
    'COVID-19 PCR Test' => [
        'Negative' => 'Negative (Not detected)',
        'Positive' => 'Positive (Detected) - Ct value: < 35',
    ],
    'Hepatitis B Surface Antigen (HBsAg)' => [
        'Negative' => 'Non-reactive',
        'Positive' => 'Reactive - Hepatitis B infection',
    ],
    'Hepatitis C Antibody (Anti-HCV)' => [
        'Negative' => 'Non-reactive',
        'Positive' => 'Reactive - Hepatitis C exposure',
    ],
    'CD4 Count' => [
        'Normal' => '> 500 cells/mm³',
        'Mild Immunosuppression' => '200-499 cells/mm³',
        'Severe Immunosuppression' => '< 200 cells/mm³',
    ],
    'Viral Load HIV' => [
        'Undetectable' => '< 20 copies/mL (Undetectable)',
        'Low' => '20-1000 copies/mL',
        'High' => '> 1000 copies/mL',
    ],
    'Echocardiogram' => [
        'Normal' => 'Normal study - No significant abnormalities',
        'Mild Abnormality' => 'Mild left ventricular hypertrophy',
        'Moderate Abnormality' => 'Moderate mitral regurgitation',
        'Severe Abnormality' => 'Severe aortic stenosis',
    ],
    'ECG (Electrocardiogram)' => [
        'Normal' => 'Normal sinus rhythm, normal axis, normal intervals',
        'Abnormal' => 'ST-T changes, borderline ECG',
        'Ischemia' => 'ST depression, T wave inversion',
    ],
    'Chest X-Ray' => [
        'Normal' => 'Normal chest X-ray',
        'Abnormal' => 'Abnormal - Requires further evaluation',
        'Pneumonia' => 'Consolidation, infiltrates',
    ],
    'Abdominal Ultrasound' => [
        'Normal' => 'Normal study - No significant abnormalities',
        'Abnormal' => 'Abnormal - Requires further evaluation',
        'Gallstones' => 'Gallstones present',
    ],
];

// ================================================================
// GET LAB TEST DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.gender,
               p.date_of_birth,
               u.full_name as doctor_name,
               v.visit_number,
               v.visit_type,
               lr.request_number,
               lr.id as request_id
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN lab_requests lr ON lt.visit_id = lr.visit_id
        WHERE lt.id = ? AND lt.branch_id = ?
    ");
    $stmt->execute([$lab_test_id, $user_branch_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lab_test) {
        header('Location: in_progress.php?error=test_not_found');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: in_progress.php?error=database_error');
    exit;
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_result') {
        $result = trim($_POST['result'] ?? '');
        $status = $_POST['status'] ?? 'completed';
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($result)) {
            $message = "❌ Please enter a result";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                // Update lab_tests
                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET results = ?, status = ?, notes = ?, 
                        completed_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$result, $status, $notes, $lab_test_id, $user_branch_id]);
                
                // Update lab_request_items if exists
                $stmt = $db->prepare("
                    UPDATE lab_request_items 
                    SET result = ?, status = ?, completed_at = NOW()
                    WHERE request_id = ? AND test_name = ?
                ");
                $stmt->execute([$result, $status, $lab_test['request_id'], $lab_test['test_name']]);
                
                // Check if all tests for this visit are completed
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total,
                           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM lab_tests 
                    WHERE visit_id = ?
                ");
                $stmt->execute([$lab_test['visit_id']]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($counts['total'] > 0 && $counts['total'] == $counts['completed']) {
                    // All tests completed - update lab_requests
                    $stmt = $db->prepare("
                        UPDATE lab_requests 
                        SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                        WHERE visit_id = ?
                    ");
                    $stmt->execute([$lab_test['visit_id']]);
                }
                
                $db->commit();
                
                $message = "✅ Result saved successfully!";
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "in_progress.php?success=1"; 
                    }, 1500);
                </script>';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET SAMPLE RESULTS FOR THIS TEST
// ================================================================
$samples = [];
$test_name = $lab_test['test_name'] ?? '';
foreach ($sample_results as $key => $sample) {
    if (stripos($key, $test_name) !== false || stripos($test_name, $key) !== false) {
        $samples = $sample;
        break;
    }
}
// If no match, use first word of test name
if (empty($samples)) {
    $first_word = explode(' ', $test_name)[0] ?? $test_name;
    foreach ($sample_results as $key => $sample) {
        if (stripos($key, $first_word) !== false) {
            $samples = $sample;
            break;
        }
    }
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <title>Add Result - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .role-badge-display {
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
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
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
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
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
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: #EDE9FE; color: #7C3AED; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .sample-result-item {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-card);
            margin-bottom: 6px;
        }
        
        .sample-result-item:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateX(4px);
        }
        
        .sample-result-item .sample-label {
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--primary);
        }
        
        .sample-result-item .sample-value {
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .sample-result-item:hover {
            background: #1E3A5F;
        }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 120px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
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
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .sample-result-item { padding: 6px 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .card { padding: 10px 12px; }
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }
        .text-danger { color: var(--danger); }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
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
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-edit"></i>
                Add Test Result
                <span class="role-badge-display">LABORATORY</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                Enter result for <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Patient: <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></strong>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="in_progress.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- ================================================================ -->
        <!-- LEFT: Test Details -->
        <!-- ================================================================ -->
        <div class="card lg:col-span-1">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i>
                Test Details
            </h3>
            
            <div class="detail-row">
                <span class="detail-label">Test Name</span>
                <span class="detail-value font-semibold"><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Patient</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Patient ID</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Doctor</span>
                <span class="detail-value">Dr. <?= htmlspecialchars($lab_test['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="badge <?= ($lab_test['status'] ?? '') === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                        <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Requested</span>
                <span class="detail-value"><?= date('M d, Y h:i A', strtotime($lab_test['created_at'] ?? 'now')) ?></span>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- RIGHT: Add Result Form -->
        <!-- ================================================================ -->
        <div class="card lg:col-span-2">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green mr-2"></i>
                Enter Result
                <?php if (!empty($samples)): ?>
                    <span class="text-xs font-normal text-gray-400">(Click sample to auto-fill)</span>
                <?php endif; ?>
            </h3>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_result">
                
                <!-- Sample Results -->
                <?php if (!empty($samples)): ?>
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-list mr-1"></i> Sample Results (Click to fill)
                        </label>
                        <div class="space-y-1">
                            <?php foreach ($samples as $label => $value): ?>
                                <div class="sample-result-item" onclick="fillResult('<?= addslashes($value) ?>', this)">
                                    <span class="sample-label"><?= htmlspecialchars($label) ?>:</span>
                                    <span class="sample-value"><?= htmlspecialchars($value) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Result Textarea -->
                <div class="form-group mb-3">
                    <label class="form-label">Result <span class="text-danger">*</span></label>
                    <textarea name="result" class="form-control" id="resultText" rows="4" placeholder="Enter test result..."></textarea>
                </div>
                
                <!-- Status -->
                <div class="form-group mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="completed">✅ Completed</option>
                        <option value="in_progress">🔄 In Progress</option>
                    </select>
                </div>
                
                <!-- Notes -->
                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="Additional notes...">
                </div>
                
                <!-- Actions -->
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Result
                    </button>
                    <button type="reset" class="btn btn-outline" onclick="document.getElementById('resultText').value = '';">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                    <a href="in_progress.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add Result
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
    // FILL RESULT FROM SAMPLE
    // ================================================================
    function fillResult(value, element) {
        document.getElementById('resultText').value = value;
        
        // Highlight selected
        document.querySelectorAll('.sample-result-item').forEach(function(el) {
            el.style.borderColor = 'var(--border-color)';
            el.style.background = '';
        });
        if (element) {
            element.style.borderColor = 'var(--primary)';
            element.style.background = 'var(--primary-bg)';
        }
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

    console.log('%c🧪 Add Result - Laboratory', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Sample Results Available: <?= count($samples) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💡 Click sample to auto-fill', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>