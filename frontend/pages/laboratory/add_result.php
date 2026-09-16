<?php
// ================================================================
// FILE: frontend/pages/laboratory/add_result.php
// LABORATORY - ADD TEST RESULT (ENGLISH)
// ✅ INABAKI KWENYE PAGE HIYO HIYO baada ya ku-save
// ✅ SUCCESS BANNER inaonekana juu ya page (ENGLISH)
// ✅ BACK TO IN PROGRESS button
// ✅ Ultrasound Forms OR Regular Textarea
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
$user_specialty = $_SESSION['specialty'] ?? 'Laboratory';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$user_email = $_SESSION['email'] ?? 'lab@braick.com';
$user_phone = $_SESSION['phone'] ?? '+255 700 000 000';

if ($_SESSION['role'] === 'admin') {
    $user_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;
}

$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_test_id <= 0) {
    header('Location: in_progress_tests.php?error=invalid_id');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

$message = '';
$message_type = '';
$lab_test = null;
$templates = [];
$test_name = '';
$save_success = false;
$is_ultrasound = false;

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}

function getLogoHTML() {
    $logo_paths = [
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
    ];
    
    foreach ($logo_paths as $path) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (file_exists($full_path)) {
            return '<img src="' . $path . '" alt="Braick Dispensary" style="height:60px;width:auto;max-height:60px;border-radius:8px;">';
        }
    }
    
    return '<div style="display:inline-block;background:#0B5ED7;color:white;padding:8px 20px;border-radius:8px;font-size:20px;font-weight:bold;font-family:Arial,sans-serif;">BRAICK</div>';
}

function generateFormsFromTemplates($templates, $lab_test, $user_full_name, $user_specialty) {
    $html = '';
    $counter = 0;
    $logo_html = getLogoHTML();
    
    foreach ($templates as $template) {
        $counter++;
        $form_id = 'form_' . $template['id'];
        $template_html = $template['template_html'];
        
        $template_html = str_replace('{patient_name}', htmlspecialchars($lab_test['patient_name'] ?? 'Unknown'), $template_html);
        $template_html = str_replace('{patient_id}', htmlspecialchars($lab_test['patient_code'] ?? 'N/A'), $template_html);
        $template_html = str_replace('{age}', calculateAge($lab_test['date_of_birth'] ?? ''), $template_html);
        $template_html = str_replace('{gender}', htmlspecialchars($lab_test['gender'] ?? 'N/A'), $template_html);
        $template_html = str_replace('{exam_date}', date('d/m/Y'), $template_html);
        $template_html = str_replace('{report_date}', date('d/m/Y H:i'), $template_html);
        $template_html = str_replace('{technician_name}', htmlspecialchars($user_full_name), $template_html);
        $template_html = str_replace('{technician_specialty}', htmlspecialchars($user_specialty), $template_html);
        $template_html = str_replace('{logo}', $logo_html, $template_html);
        $template_html = str_replace('{logo_url}', '', $template_html);
        
        $active_class = ($counter === 1) ? 'active' : '';
        
        $html .= '
        <div id="' . $form_id . '" class="ultrasound-form ' . $active_class . '" data-template-id="' . $template['id'] . '">
            ' . $template_html . '
        </div>';
    }
    
    return $html;
}

function generateFormSelector($templates) {
    if (empty($templates)) {
        return '<div class="text-center text-gray-500 py-4">No ultrasound templates available.</div>';
    }
    
    $html = '<div class="form-selector">';
    $counter = 0;
    foreach ($templates as $template) {
        $counter++;
        $active_class = ($counter === 1) ? 'active' : '';
        $icon = '📋';
        
        if (stripos($template['template_name'], 'Twin') !== false) $icon = '🤰';
        elseif (stripos($template['template_name'], 'Single') !== false) $icon = '👶';
        elseif (stripos($template['template_name'], 'Early') !== false) $icon = '🌱';
        elseif (stripos($template['template_name'], 'Abdominal') !== false) $icon = '🩺';
        elseif (stripos($template['template_name'], 'Scrotal') !== false) $icon = '🫂';
        
        $html .= '
        <div class="form-option ' . $active_class . '" onclick="selectForm(\'form_' . $template['id'] . '\', this)" data-form="form_' . $template['id'] . '">
            <span class="option-icon">' . $icon . '</span>
            ' . htmlspecialchars($template['template_name']) . '
        </div>';
    }
    $html .= '</div>';
    
    return $html;
}

// ================================================================
// GET LAB TEST DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            lt.id,
            lt.visit_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.test_name,
            lt.test_price,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.reference_range,
            lt.interpretation,
            lt.status as test_status,
            lt.bill_created,
            lt.notes,
            lt.technician_id,
            lt.branch_id,
            lt.created_at,
            lt.completed_at,
            lt.updated_at,
            lt.formatted_result,
            lt.printed_at,
            lt.printed_by,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            p.address,
            p.allergies,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            v.visit_type,
            v.diagnosis,
            v.symptoms,
            v.status as visit_status,
            v.is_completed
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.id = ? AND lt.branch_id = ?
    ");
    $stmt->execute([$lab_test_id, $user_branch_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lab_test) {
        header('Location: in_progress_tests.php?error=test_not_found');
        exit;
    }
    
    $test_name = $lab_test['test_name'] ?? '';
    
} catch (Exception $e) {
    error_log("Add result error: " . $e->getMessage());
    header('Location: in_progress_tests.php?error=database_error');
    exit;
}

// ================================================================
// CHECK IF TEST IS ULTRASOUND
// ================================================================
$ultrasound_keywords = [
    'ultrasound', 'sonography', 'US-', 'sono',
    'Obstetric', 'Abdominal', 'Pelvic', 'Pelvis',
    'Twin', 'Single', 'Early Pregnancy',
    'Abdomen', 'Obst', 'GYN', 'Fetal',
    'Scrotal', '3D', '4D', 'Obstetric'
];

foreach ($ultrasound_keywords as $keyword) {
    if (stripos($test_name, $keyword) !== false) {
        $is_ultrasound = true;
        break;
    }
}

// ================================================================
// GET TEMPLATES
// ================================================================
if ($is_ultrasound) {
    try {
        $stmt = $db->prepare("
            SELECT id, template_name, test_type, category, template_html 
            FROM lab_result_templates 
            WHERE is_active = 1 
            AND category = 'ultrasound'
            ORDER BY template_name
        ");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Templates error: " . $e->getMessage());
        $templates = [];
    }
}

// ================================================================
// ✅ HANDLE FORM SUBMISSION (ENGLISH MESSAGES)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_result') {
        $result = trim($_POST['result'] ?? '');
        $reference_range = trim($_POST['reference_range'] ?? '');
        $interpretation = trim($_POST['interpretation'] ?? '');
        $status = $_POST['status'] ?? 'completed';
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($result)) {
            $message = "❌ Please enter a result before saving";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                $visit_id = $lab_test['visit_id'];
                
                // ✅ Update lab_tests
                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET results = ?,
                        reference_range = ?,
                        interpretation = ?,
                        notes = ?,
                        status = 'completed',
                        completed_at = NOW(),
                        updated_at = NOW(),
                        performed_by = ?,
                        lab_technician_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $result,
                    $reference_range,
                    $interpretation,
                    $notes,
                    $user_id,
                    $user_id,
                    $lab_test_id
                ]);
                
                // ✅ Update visit status
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET status = 'lab_test', 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$visit_id]);
                
                $db->commit();
                
                $message = "✅ Result saved successfully! Test is now completed.";
                $message_type = 'success';
                $save_success = true;
                
                // ✅ REDIRECT KWA PAGE HIYO HIYO (ili kuonyesha message)
                $_SESSION['lab_result_message'] = $message;
                $_SESSION['lab_result_message_type'] = 'success';
                header('Location: add_result.php?id=' . $lab_test_id . '&saved=1');
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Save result error: " . $e->getMessage());
            }
        }
    }
}

// ================================================================
// ✅ CHECK SESSION MESSAGES
// ================================================================
if (isset($_SESSION['lab_result_message'])) {
    $message = $_SESSION['lab_result_message'];
    $message_type = $_SESSION['lab_result_message_type'] ?? 'success';
    unset($_SESSION['lab_result_message']);
    unset($_SESSION['lab_result_message_type']);
}

// ================================================================
// CHECK IF TEST IS ALREADY COMPLETED
// ================================================================
$already_completed = ($lab_test['test_status'] === 'completed' && !empty($lab_test['results']));

// ================================================================
// SAMPLE RESULTS
// ================================================================
$sample_results = [
    'Blood Glucose (Fasting)' => ['Normal' => '70-100 mg/dL', 'Prediabetes' => '100-125 mg/dL', 'Diabetes' => '>126 mg/dL'],
    'Blood Glucose (Random)' => ['Normal' => '<140 mg/dL', 'Prediabetes' => '140-199 mg/dL', 'Diabetes' => '>200 mg/dL'],
    'Complete Blood Count (CBC)' => ['Normal' => 'RBC: 4.5-5.5M, WBC: 4.5-11K, HGB: 13-17g/dL, PLT: 150-400K'],
    'Malaria Rapid Test' => ['Negative' => 'Negative', 'Positive (Pf)' => 'Positive - Plasmodium falciparum'],
    'HIV Rapid Test' => ['Negative' => 'Non-reactive', 'Positive' => 'Reactive - Confirm with ELISA'],
    'Pregnancy Test (Urine)' => ['Negative' => 'Negative', 'Positive' => 'Positive - HCG detected'],
    'Urinalysis' => ['Normal' => 'pH: 4.5-8.0, Protein: Negative, Glucose: Negative'],
    'COVID-19 Rapid Antigen Test' => ['Negative' => 'Negative', 'Positive' => 'Positive - SARS-CoV-2 antigen detected'],
    'Hepatitis B Surface Antigen (HBsAg)' => ['Negative' => 'Non-reactive', 'Positive' => 'Reactive'],
    'Renal Function Test (RFT)' => ['Normal' => 'Creatinine: 0.6-1.2, BUN: 7-20, Uric Acid: 3.5-7.2'],
    'Pregnancy Test (Blood - Beta HCG)' => ['Negative' => 'Negative - No HCG detected', 'Positive' => 'Positive - HCG detected'],
];

$samples = [];
if (!$is_ultrasound || empty($templates)) {
    foreach ($sample_results as $key => $sample) {
        if (stripos($key, $test_name) !== false || stripos($test_name, $key) !== false) {
            $samples = $sample;
            break;
        }
    }
    if (empty($samples)) {
        $first_word = explode(' ', $test_name)[0] ?? $test_name;
        foreach ($sample_results as $key => $sample) {
            if (stripos($key, $first_word) !== false) {
                $samples = $sample;
                break;
            }
        }
    }
}

$show_ultrasound = ($is_ultrasound && !empty($templates));

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

$profile_pic = $_SESSION['profile_pic'] ?? '';
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
    <title>Add Result - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
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
        
        /* ================================================================ */
        /* SUCCESS BANNER - ENGLISH */
        /* ================================================================ */
        .success-banner {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            border-left: 6px solid #059669;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.2);
            animation: successSlide 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .success-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            pointer-events: none;
        }
        
        @keyframes successSlide {
            0% { opacity: 0; transform: translateY(-30px) scale(0.95); }
            60% { transform: translateY(5px) scale(1.01); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .success-banner .success-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #059669, #047857);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
            animation: iconPulse 2s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }
        
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 6px 20px rgba(5,150,105,0.4); }
            50% { transform: scale(1.08); box-shadow: 0 8px 24px rgba(5,150,105,0.6); }
        }
        
        .success-banner .success-content {
            flex: 1;
            min-width: 250px;
            position: relative;
            z-index: 1;
        }
        
        .success-banner .success-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #065F46;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .success-banner .success-title .status-badge {
            background: #059669;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        
        .success-banner .success-details {
            font-size: 0.9rem;
            color: #047857;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        
        .success-banner .success-details strong {
            color: #065F46;
            font-weight: 700;
        }
        
        .success-banner .success-hint {
            font-size: 0.8rem;
            color: #047857;
            background: rgba(255,255,255,0.7);
            padding: 8px 14px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }
        
        .success-banner .success-hint i {
            color: #D97706;
            font-size: 1rem;
        }
        
        .success-banner .success-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .success-banner .btn-back-progress {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 12px 26px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 18px rgba(11, 94, 215, 0.35);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .success-banner .btn-back-progress:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(11, 94, 215, 0.5);
            color: white;
        }
        
        /* ================================================================ */
        /* ALREADY COMPLETED NOTICE */
        /* ================================================================ */
        .completed-notice {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            border-left: 5px solid #3B82F6;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            color: #1E40AF;
            font-weight: 500;
        }
        
        .completed-notice i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        [data-theme="dark"] .completed-notice {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            color: #BFDBFE;
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
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
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
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
            min-height: 100px;
            line-height: 1.6;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.4);
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
        
        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(11, 94, 215, 0.4);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #6B7280, #4B5563);
            color: white;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(107, 114, 128, 0.4);
        }
        
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: #EDE9FE; color: #7C3AED; }
        
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 130px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 500;
        }
        
        /* Ultrasound Form Styles */
        .ultrasound-form {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #0B5ED7;
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            display: none;
        }
        
        .ultrasound-form.active { display: block !important; }
        
        .ultrasound-form .report-header {
            text-align: center;
            border-bottom: 3px double #0B5ED7;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .ultrasound-form .report-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .ultrasound-form .report-header .logo-container img {
            height: 60px;
            width: auto;
            max-height: 60px;
            border-radius: 8px;
        }
        
        .ultrasound-form .report-header h2 {
            color: #0B5ED7;
            font-size: 22px;
            margin: 0;
        }
        
        .ultrasound-form .report-header h3 {
            font-size: 16px;
            color: #333;
            margin: 5px 0 0 0;
        }
        
        .ultrasound-form .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .ultrasound-form .patient-info p { margin: 0; font-size: 14px; }
        .ultrasound-form .patient-info strong { color: #333; }
        
        .ultrasound-form h4 {
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 5px;
            margin: 0 0 10px 0;
        }
        
        .ultrasound-form .findings p {
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ultrasound-form .findings p strong { min-width: 150px; }
        
        .ultrasound-form .biometry { margin-bottom: 15px; overflow-x: auto; }
        
        .ultrasound-form .biometry table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .ultrasound-form .biometry table th,
        .ultrasound-form .biometry table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        
        .ultrasound-form .biometry table th { background: #E8F0FE; }
        
        .ultrasound-form .conclusion textarea {
            width: 100%;
            min-height: 60px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            font-size: 14px;
        }
        
        .ultrasound-form .report-footer {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .ultrasound-form input.form-control,
        .ultrasound-form textarea.form-control {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 13px;
            width: 100%;
            transition: border-color 0.3s;
        }
        
        .ultrasound-form input.form-control:focus,
        .ultrasound-form textarea.form-control:focus {
            border-color: #0B5ED7;
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 94, 215, 0.15);
        }
        
        [data-theme="dark"] .ultrasound-form {
            background: #1E293B !important;
            border-color: #3B82F6 !important;
        }
        
        [data-theme="dark"] .ultrasound-form .report-header h2 { color: #60A5FA !important; }
        [data-theme="dark"] .ultrasound-form .report-header h3 { color: #F1F5F9 !important; }
        [data-theme="dark"] .ultrasound-form .patient-info strong { color: #F1F5F9 !important; }
        [data-theme="dark"] .ultrasound-form .patient-info p { color: #94A3B8 !important; }
        [data-theme="dark"] .ultrasound-form h4 { color: #60A5FA !important; }
        
        [data-theme="dark"] .ultrasound-form input.form-control,
        [data-theme="dark"] .ultrasound-form textarea.form-control {
            background: #1E293B;
            border-color: #475569;
            color: #F1F5F9;
        }
        
        [data-theme="dark"] .ultrasound-form .biometry table th {
            background: #1E3A5F !important;
            color: #F1F5F9 !important;
        }
        
        .form-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .form-selector .form-option {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            background: var(--bg-card);
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .form-selector .form-option:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-2px);
        }
        
        .form-selector .form-option.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .form-selector .form-option .option-icon {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        
        .sample-result-item {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-card);
            margin-bottom: 8px;
        }
        
        .sample-result-item:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateX(4px);
        }
        
        .sample-result-item .sample-label {
            font-weight: 700;
            font-size: 0.75rem;
            color: var(--primary);
        }
        
        .sample-result-item .sample-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-left: 6px;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            border-left: 5px solid;
        }
        
        .alert-success { background: var(--success-bg); color: #065F46; border-left-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: #991B1B; border-left-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: #92400E; border-left-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: #0A4CA8; border-left-color: var(--primary); }
        
        @media print {
            .top-nav, .sidebar, .btn, .btn-outline, .btn-success, .btn-print, .btn-primary,
            .form-selector, .form-option, .page-header .btn-outline-light, .alert, .footer,
            .success-banner, .completed-notice { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; background: white !important; }
            .ultrasound-form { border: 1px solid #0B5ED7 !important; display: block !important; padding: 15px !important; max-width: 100% !important; }
            .ultrasound-form.active { display: block !important; }
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .ultrasound-form .patient-info { grid-template-columns: 1fr; }
            .form-selector { grid-template-columns: repeat(2, 1fr); }
            .success-banner { flex-direction: column; text-align: center; }
            .success-banner .success-actions { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ================================================================ -->
    <!-- ✅ SUCCESS BANNER (ENGLISH) -->
    <!-- ================================================================ -->
    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1' && $message_type === 'success'): ?>
        <div class="success-banner">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="success-content">
                <div class="success-title">
                    <i class="fas fa-check-circle" style="color:#059669;"></i>
                    Result Saved Successfully!
                    <span class="status-badge">
                        <i class="fas fa-check"></i> COMPLETED
                    </span>
                </div>
                <div class="success-details">
                    <div><i class="fas fa-flask"></i> <strong>Test:</strong> <?= htmlspecialchars($test_name ?? 'N/A') ?></div>
                    <div><i class="fas fa-user"></i> <strong>Patient:</strong> <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?> (<?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?>)</div>
                    <div><i class="fas fa-clock"></i> <strong>Saved at:</strong> <?= date('d M Y, h:i A') ?></div>
                </div>
                <div class="success-hint">
                    <i class="fas fa-lightbulb"></i>
                    <span>Go back to <strong>In Progress Tests</strong> to continue filling in results for other tests.</span>
                </div>
            </div>
            <div class="success-actions">
                <a href="in_progress_tests.php" class="btn-back-progress">
                    <i class="fas fa-arrow-left"></i>
                    Back to In Progress
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ✅ ALREADY COMPLETED NOTICE -->
    <!-- ================================================================ -->
    <?php if ($already_completed && !isset($_GET['saved'])): ?>
        <div class="completed-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>This test is already completed.</strong>
                You can view, print, or go back to In Progress Tests.
            </div>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-<?= $already_completed ? 'check-circle' : 'edit' ?>"></i>
                <?= $already_completed ? 'View Result' : 'Add Test Result' ?>
                <span class="role-badge-display">LABORATORY</span>
                <?php if ($already_completed): ?>
                    <span class="badge" style="background:rgba(52,211,153,0.3);color:#A7F3D0;border:1px solid rgba(52,211,153,0.5);padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:700;">
                        <i class="fas fa-check-circle"></i> COMPLETED
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                Test: <strong><?= htmlspecialchars($test_name ?? 'N/A') ?></strong>
                <span>|</span>
                Patient: <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></strong>
                <?php if ($show_ultrasound): ?>
                    <span class="badge badge-purple" style="margin-left:8px;">🩺 Ultrasound</span>
                    <span class="badge badge-info"><?= count($templates) ?> Templates</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="in_progress_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to In Progress
            </a>
            <?php if ($show_ultrasound): ?>
                <button onclick="printUltrasoundForm()" class="btn-outline-light" style="background:rgba(255,255,255,0.25);">
                    <i class="fas fa-print"></i> Print Form
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message (kama hakuna success banner) -->
    <?php if ($message && !isset($_GET['saved'])): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- LEFT: Test Details -->
        <div class="card lg:col-span-1">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue"></i>
                Test Details
            </h3>
            
            <div class="detail-row">
                <span class="detail-label">Test Name</span>
                <span class="detail-value" style="font-weight:700;"><?= htmlspecialchars($test_name ?? 'N/A') ?></span>
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
                <span class="detail-label">Specialty</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['doctor_specialty'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="badge <?= ($lab_test['test_status'] ?? '') === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                        <?= ucfirst($lab_test['test_status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value">
                    <?php if ($show_ultrasound): ?>
                        <span class="badge badge-purple">🩺 Ultrasound</span>
                    <?php else: ?>
                        <span class="badge badge-info">📊 Regular Test</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($lab_test['diagnosis'])): ?>
                <div class="detail-row" style="flex-direction:column;">
                    <span class="detail-label" style="width:100%;margin-bottom:4px;">Diagnosis</span>
                    <span class="detail-value" style="background:var(--primary-bg);padding:8px 12px;border-radius:8px;border-left:3px solid var(--primary);">
                        <?= htmlspecialchars($lab_test['diagnosis']) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- RIGHT: Result Form -->
        <div class="card lg:col-span-2">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green"></i>
                <?php if ($already_completed): ?>
                    Test Result (Completed)
                <?php else: ?>
                    <?= $show_ultrasound ? 'Select Ultrasound Template & Fill Report' : 'Enter Result' ?>
                <?php endif; ?>
                <?php if ($show_ultrasound): ?>
                    <button onclick="printUltrasoundForm()" class="btn btn-print" style="margin-left:auto;padding:6px 14px;font-size:0.75rem;">
                        <i class="fas fa-print"></i> Print
                    </button>
                <?php endif; ?>
            </h3>
            
            <?php if ($already_completed): ?>
                <!-- ✅ TEST COMPLETED - SHOW RESULTS ONLY -->
                <div style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);padding:18px 22px;border-radius:12px;border-left:5px solid #059669;margin-bottom:16px;">
                    <div style="font-weight:700;color:#065F46;margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-check-circle"></i> Result for This Test:
                    </div>
                    <div style="background:white;padding:14px 18px;border-radius:10px;border:1px solid #059669;">
                        <div style="font-family:'Courier New',monospace;font-size:0.9rem;color:#1E293B;white-space:pre-wrap;line-height:1.7;"><?= htmlspecialchars($lab_test['results'] ?? 'No result recorded') ?></div>
                    </div>
                    <?php if (!empty($lab_test['reference_range'])): ?>
                        <div style="margin-top:10px;padding:8px 12px;background:#FEF3C7;border-radius:8px;border-left:3px solid #D97706;">
                            <strong style="font-size:0.7rem;color:#92400E;text-transform:uppercase;">Reference Range:</strong>
                            <div style="font-family:monospace;font-size:0.85rem;color:#78350F;"><?= htmlspecialchars($lab_test['reference_range']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($lab_test['completed_at'])): ?>
                        <div style="margin-top:10px;font-size:0.75rem;color:#047857;">
                            <i class="fas fa-clock"></i> Completed: <?= date('d M Y, h:i A', strtotime($lab_test['completed_at'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex flex-wrap gap-3" style="margin-top:16px;">
                    <a href="in_progress_tests.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to In Progress
                    </a>
                    <a href="completed_tests.php" class="btn btn-outline">
                        <i class="fas fa-list"></i> Completed Tests
                    </a>
                    <a href="view_test.php?id=<?= $lab_test_id ?>" class="btn btn-outline">
                        <i class="fas fa-eye"></i> View Full Report
                    </a>
                </div>
                
            <?php elseif ($show_ultrasound): ?>
                <!-- Ultrasound Forms -->
                <?= generateFormSelector($templates) ?>
                
                <form method="POST" action="" id="ultrasoundForm">
                    <input type="hidden" name="action" value="save_result">
                    <input type="hidden" name="reference_range" value="">
                    <input type="hidden" name="interpretation" value="">
                    
                    <?= generateFormsFromTemplates($templates, $lab_test, $user_full_name, $user_specialty) ?>
                    
                    <input type="hidden" name="result" id="ultrasoundResult" value="">
                    
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="btn btn-success" onclick="saveUltrasoundResult()">
                            <i class="fas fa-save"></i> Save Report
                        </button>
                        <button type="button" class="btn btn-print" onclick="printUltrasoundForm()">
                            <i class="fas fa-print"></i> Print Form
                        </button>
                        <button type="button" class="btn btn-outline" onclick="clearUltrasoundForm()">
                            <i class="fas fa-undo"></i> Clear Form
                        </button>
                        <a href="in_progress_tests.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
                
            <?php else: ?>
                <!-- Regular Test Result Form -->
                <form method="POST" action="" id="regularForm">
                    <input type="hidden" name="action" value="save_result">
                    
                    <!-- Sample Results -->
                    <?php if (!empty($samples)): ?>
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-list"></i> Sample Results (Click to fill)
                            </label>
                            <div class="space-y-2">
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
                    <div class="mb-3">
                        <label class="form-label">Result <span style="color:var(--danger);">*</span></label>
                        <textarea name="result" class="form-control" id="resultText" rows="5" placeholder="Enter test result..." required></textarea>
                    </div>
                    
                    <!-- Reference Range -->
                    <div class="mb-3">
                        <label class="form-label">Reference Range</label>
                        <input type="text" name="reference_range" class="form-control" placeholder="e.g. 0.5 - 1.2 mg/dL" value="<?= htmlspecialchars($lab_test['reference_range'] ?? '') ?>">
                    </div>
                    
                    <!-- Interpretation -->
                    <div class="mb-3">
                        <label class="form-label">Interpretation</label>
                        <textarea name="interpretation" class="form-control" rows="2" placeholder="Clinical interpretation of the results..."></textarea>
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Additional notes...">
                    </div>
                    
                    <!-- Buttons -->
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="submit" class="btn btn-success" id="saveBtn">
                            <i class="fas fa-save"></i> Save Result
                        </button>
                        <button type="reset" class="btn btn-outline" onclick="document.getElementById('resultText').value = '';">
                            <i class="fas fa-undo"></i> Clear
                        </button>
                        <a href="in_progress_tests.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <?= $show_ultrasound ? 'Ultrasound Report' : 'Add Result' ?>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

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
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        if (sidebar) sidebar.classList.toggle('open');
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
        if (el) el.textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SELECT ULTRASOUND FORM
    // ================================================================
    function selectForm(formId, element) {
        document.querySelectorAll('.ultrasound-form').forEach(function(form) {
            form.classList.remove('active');
        });
        var selectedForm = document.getElementById(formId);
        if (selectedForm) {
            selectedForm.classList.add('active');
            selectedForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.querySelectorAll('.form-option').forEach(function(opt) {
            opt.classList.remove('active');
        });
        if (element) element.classList.add('active');
    }

    // ================================================================
    // FILL RESULT FROM SAMPLE
    // ================================================================
    function fillResult(value, element) {
        var resultText = document.getElementById('resultText');
        if (resultText) {
            resultText.value = value;
            resultText.focus();
        }
        document.querySelectorAll('.sample-result-item').forEach(function(el) {
            el.style.borderColor = 'var(--border-color)';
            el.style.background = 'var(--bg-card)';
        });
        if (element) {
            element.style.borderColor = 'var(--primary)';
            element.style.background = 'var(--primary-bg)';
        }
    }

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('regularForm')?.addEventListener('submit', function(e) {
        var result = document.getElementById('resultText').value.trim();
        if (!result) {
            e.preventDefault();
            showToast('⚠️ Error', 'Please enter a result before saving.', 'error');
            return false;
        }
        var btn = document.getElementById('saveBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }
        return true;
    });

    // ================================================================
    // SAVE ULTRASOUND RESULT
    // ================================================================
    function saveUltrasoundResult() {
        var activeForm = document.querySelector('.ultrasound-form.active');
        if (!activeForm) {
            showToast('⚠️ Error', 'No form selected', 'error');
            return;
        }
        
        var formData = [];
        var inputs = activeForm.querySelectorAll('input, textarea');
        inputs.forEach(function(input) {
            if (input.type === 'hidden') return;
            var label = input.dataset.placeholder || input.placeholder || 'field';
            label = label.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            if (input.value) {
                formData.push(label + ': ' + input.value);
            }
        });
        
        var resultText = formData.join('\n');
        
        if (!resultText.trim()) {
            showToast('⚠️ Error', 'Please fill in the form before saving', 'error');
            return;
        }
        
        var hiddenResult = document.getElementById('ultrasoundResult');
        if (hiddenResult) hiddenResult.value = resultText;
        
        var form = document.getElementById('ultrasoundForm');
        if (form) form.submit();
    }

    // ================================================================
    // CLEAR ULTRASOUND FORM
    // ================================================================
    function clearUltrasoundForm() {
        if (!confirm('Clear all fields on the ultrasound form?')) return;
        var activeForm = document.querySelector('.ultrasound-form.active');
        if (activeForm) {
            var inputs = activeForm.querySelectorAll('input, textarea');
            inputs.forEach(function(input) {
                if (input.type !== 'submit' && input.type !== 'button' && input.type !== 'hidden') {
                    input.value = '';
                }
            });
        }
        showToast('ℹ️ Info', 'Form cleared', 'info');
    }

    // ================================================================
    // PRINT ULTRASOUND FORM
    // ================================================================
    function printUltrasoundForm() {
        var activeForm = document.querySelector('.ultrasound-form.active');
        if (!activeForm) {
            showToast('⚠️ Error', 'No form selected to print', 'error');
            return;
        }
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
            showToast('⚠️ Error', 'Please allow popups for printing', 'error');
            return;
        }
        
        var formHTML = activeForm.outerHTML;
        
        var inputs = activeForm.querySelectorAll('input, textarea');
        inputs.forEach(function(input) {
            var value = input.value || '___________________';
            var inputHTML = input.outerHTML;
            var replacement = '<span style="display:inline-block;min-width:100px;border-bottom:1px solid #333;padding:2px 4px;font-weight:normal;font-family:Arial,sans-serif;">' + (value || '___________________') + '</span>';
            formHTML = formHTML.replace(inputHTML, replacement);
        });
        
        printWindow.document.write('<!DOCTYPE html><html><head><title>Ultrasound Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 40px; background: white; }');
        printWindow.document.write('.ultrasound-form { max-width: 900px; margin: 0 auto; padding: 20px; border: 2px solid #0B5ED7; border-radius: 10px; }');
        printWindow.document.write('.report-header { text-align: center; border-bottom: 3px double #0B5ED7; padding-bottom: 10px; margin-bottom: 15px; }');
        printWindow.document.write('.logo-container { display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 10px; flex-wrap: wrap; }');
        printWindow.document.write('.logo-container img { height: 60px; width: auto; max-height: 60px; border-radius: 8px; }');
        printWindow.document.write('.report-header h2 { color: #0B5ED7; font-size: 22px; margin: 0; }');
        printWindow.document.write('.report-header h3 { font-size: 16px; color: #333; margin: 5px 0 0 0; }');
        printWindow.document.write('.patient-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }');
        printWindow.document.write('.patient-info p { margin: 0; font-size: 14px; }');
        printWindow.document.write('.patient-info strong { color: #333; }');
        printWindow.document.write('.findings p { margin: 5px 0; display: flex; gap: 10px; }');
        printWindow.document.write('.findings p strong { min-width: 150px; }');
        printWindow.document.write('h4 { color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 5px; margin: 0 0 10px 0; }');
        printWindow.document.write('.biometry { margin-bottom: 15px; overflow-x: auto; }');
        printWindow.document.write('.biometry table { width: 100%; border-collapse: collapse; font-size: 14px; }');
        printWindow.document.write('.biometry table th, .biometry table td { border: 1px solid #ddd; padding: 6px; text-align: left; }');
        printWindow.document.write('.biometry table th { background: #E8F0FE; }');
        printWindow.document.write('.conclusion textarea { width: 100%; min-height: 60px; border: 1px solid #ddd; border-radius: 4px; padding: 8px; font-size: 14px; }');
        printWindow.document.write('.report-footer { display: flex; justify-content: space-between; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }');
        printWindow.document.write('span { display: inline-block; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(formHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        setTimeout(function() {
            printWindow.print();
        }, 500);
    }

    // ================================================================
    // TOAST (ENGLISH)
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
    // AUTO-LOG
    // ================================================================
    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
    console.log('%c✅ Result saved successfully - page stays the same', 'font-size:14px;color:#059669;font-weight:bold;');
    <?php endif; ?>

    console.log('%c🧪 Braick - Add Result (STAY ON PAGE)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Stays on this page after saving', 'font-size:13px;color:#34D399;font-weight:bold;');
    console.log('%c✅ Success banner appears at the top', 'font-size:13px;color:#34D399;');
    console.log('%c✅ Back to In Progress button available', 'font-size:13px;color:#34D399;');
</script>

</body>
</html>