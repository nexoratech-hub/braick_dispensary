<?php
// ================================================================
// FILE: frontend/pages/admin/add_lab_results.php
// ADMIN - ADD LAB TEST RESULT
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Ultrasound + Regular test forms
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
// ROLE CHECK - ADMIN OR LABORATORY
// ================================================================
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'laboratory') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_specialty = $_SESSION['specialty'] ?? 'Administrator';
$user_username = $_SESSION['username'] ?? 'admin';
$user_email = $_SESSION['email'] ?? 'admin@braick.com';
$user_phone = $_SESSION['phone'] ?? '+255 700 000 000';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// GET PARAMETERS
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($lab_test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// DATABASE
// ================================================================
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

// ================================================================
// GET LAB TEST DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            lt.id, lt.visit_id, lt.doctor_id, lt.lab_technician_id,
            lt.test_name, lt.test_price, lt.test_type, lt.sample_type,
            lt.test_date, lt.results, lt.reference_range,
            lt.status as test_status, lt.bill_created, lt.notes,
            lt.technician_id, lt.branch_id, lt.created_at, lt.completed_at,
            lt.updated_at, lt.formatted_result, lt.printed_at, lt.printed_by,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone, p.gender, p.date_of_birth, p.blood_group, p.address, p.allergies,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
            v.status as visit_status, v.is_completed
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$lab_test_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab_test) {
        header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }

    $test_name = $lab_test['test_name'] ?? '';

} catch (Exception $e) {
    error_log("Add result error: " . $e->getMessage());
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// CHECK IF ULTRASOUND
// ================================================================
$is_ultrasound = false;
$ultrasound_keywords = [
    'ultrasound', 'sonography', 'US-', 'sono',
    'Obstetric', 'Abdominal', 'Pelvic', 'Pelvis',
    'Twin', 'Single', 'Early Pregnancy',
    'Abdomen', 'Obst', 'GYN', 'Fetal',
    'Scrotal', '3D', '4D'
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
            WHERE is_active = 1 AND category = 'ultrasound'
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
// HANDLE FORM SUBMISSION
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
            $message = "❌ Please enter a result";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                $visit_id = $lab_test['visit_id'];

                $stmt = $db->prepare("
                    UPDATE lab_tests 
                    SET results = ?, reference_range = ?, interpretation = ?,
                        notes = ?, status = ?, completed_at = NOW(),
                        updated_at = NOW(), performed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $result, $reference_range, $interpretation,
                    $notes, $status, $user_id, $lab_test_id
                ]);

                if ($visit_id) {
                    $stmt = $db->prepare("
                        UPDATE visits SET status = 'lab_test', updated_at = NOW() WHERE id = ?
                    ");
                    $stmt->execute([$visit_id]);
                }

                $db->commit();
                $message = "✅ Result added successfully! Visit is now in 'Lab Test' status. Doctor can continue consultation.";
                $message_type = 'success';
                $save_success = true;

            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
                error_log("Save result error: " . $e->getMessage());
            }
        }
    }
}

// Redirect on success
if ($save_success && $message_type === 'success') {
    $_SESSION['lab_result_message'] = $message;
    $_SESSION['lab_result_message_type'] = $message_type;
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&success=1');
    exit;
}

// Session messages
if (isset($_SESSION['lab_result_message'])) {
    $message = $_SESSION['lab_result_message'];
    $message_type = $_SESSION['lab_result_message_type'] ?? 'success';
    unset($_SESSION['lab_result_message'], $_SESSION['lab_result_message_type']);
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getLogoHTML() {
    $logo_paths = [
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
        '/dispensary_system/frontend/assets/img/braick_logo.png',
        '/dispensary_system/frontend/assets/img/logo.png',
    ];

    foreach ($logo_paths as $path) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            return '<img src="' . $path . '" alt="Braick Dispensary" style="height:60px;width:auto;max-height:60px;border-radius:8px;">';
        }
    }

    return '<div style="display:inline-block;background:#0B5ED7;color:white;padding:8px 20px;border-radius:8px;font-size:20px;font-weight:bold;font-family:Arial,sans-serif;">BRAICK</div>';
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
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

        $html .= '<div id="' . $form_id . '" class="ultrasound-form ' . $active_class . '" data-template-id="' . $template['id'] . '">' . $template_html . '</div>';
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

        $html .= '<div class="form-option ' . $active_class . '" onclick="selectForm(\'form_' . $template['id'] . '\', this)" data-form="form_' . $template['id'] . '">';
        $html .= '<span class="option-icon">' . $icon . '</span>';
        $html .= htmlspecialchars($template['template_name']);
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

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

// ================================================================
// NOTIFICATIONS
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
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-light: #E8F0FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-gray-50: #F8FAFC;
        --page-gray-100: #F1F5F9;
        --page-gray-200: #E2E8F0;
        --page-gray-300: #CBD5E1;
        --page-gray-400: #94A3B8;
        --page-gray-500: #64748B;
        --page-gray-700: #334155;
        --page-gray-800: #1E293B;
        --page-gray-900: #0F172A;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-radius: 10px;
        --page-radius-lg: 14px;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
    }

    /* ================================================================
       BODY & MAIN CONTENT - DARK MODE
       ================================================================ */
    body {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    .main-content {
        background: var(--page-bg-body, #F1F5F9);
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
    }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.3), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(11, 94, 215, 0.4), 0 6px 16px rgba(11, 94, 215, 0.25);
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        position: relative;
        z-index: 2;
        letter-spacing: -0.02em;
    }

    .page-header-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-subtitle strong {
        color: white;
        font-weight: 700;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
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
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       CARDS
       ================================================================ */
    .card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 20px 24px;
        border: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    html[data-theme="dark"] .card {
        background: #1E293B;
        border-color: #334155;
    }

    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .card-title {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .card-title .title-blue { color: #0B5ED7; }
    .card-title .title-green { color: #059669; }

    /* ================================================================
       FORM ELEMENTS
       ================================================================ */
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--page-text-secondary, #64748B);
        margin-bottom: 4px;
    }

    html[data-theme="dark"] .form-label {
        color: #94A3B8;
    }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    html[data-theme="dark"] .form-control {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control::placeholder {
        color: #64748B;
    }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    html[data-theme="dark"] .form-control:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }

    select.form-control {
        appearance: auto;
        cursor: pointer;
    }

    html[data-theme="dark"] select.form-control option {
        background: #1E293B;
        color: #F1F5F9;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-sm {
        padding: 4px 14px;
        font-size: 0.7rem;
    }

    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
    }

    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .btn-outline {
        color: #94A3B8;
        border-color: #334155;
    }

    .btn-outline:hover {
        background: var(--page-gray-50, #F8FAFC);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    html[data-theme="dark"] .btn-outline:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .btn-primary {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
    }

    .btn-primary:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
    }

    .btn-print {
        background: #6B7280;
        color: white;
        box-shadow: 0 2px 8px rgba(107, 114, 128, 0.2);
    }

    .btn-print:hover {
        background: #4B5563;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(107, 114, 128, 0.3);
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .badge-success { background: #D1FAE5; color: #059669; }
    .badge-warning { background: #FEF3C7; color: #D97706; }
    .badge-info { background: #E8F0FE; color: #0B5ED7; }
    .badge-danger { background: #FEE2E2; color: #DC2626; }
    .badge-purple { background: #EDE9FE; color: #7C3AED; }

    html[data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .badge-warning { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .badge-purple { background: #2A1A3A; color: #9B4DCA; }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert {
        padding: 14px 20px;
        border-radius: 10px;
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

    .alert-success { background: #D1FAE5; color: #059669; border-color: #059669; }
    .alert-error { background: #FEE2E2; color: #DC2626; border-color: #DC2626; }
    .alert-warning { background: #FEF3C7; color: #D97706; border-color: #D97706; }
    .alert-info { background: #E8F0FE; color: #0B5ED7; border-color: #0B5ED7; }

    html[data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    html[data-theme="dark"] .alert-warning { background: #3A2A1A; color: #FBBF24; border-color: #FBBF24; }
    html[data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }

    /* ================================================================
       DETAIL ROWS
       ================================================================ */
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .detail-row {
        border-bottom-color: #334155;
    }

    .detail-row:last-child { border-bottom: none; }

    .detail-label {
        font-weight: 600;
        color: var(--page-text-secondary, #64748B);
        width: 120px;
        flex-shrink: 0;
        font-size: 0.8rem;
    }

    html[data-theme="dark"] .detail-label {
        color: #94A3B8;
    }

    .detail-value {
        flex: 1;
        color: var(--page-text-primary, #1E293B);
        font-size: 0.85rem;
    }

    html[data-theme="dark"] .detail-value {
        color: #F1F5F9;
    }

    /* ================================================================
       ULTRASOUND FORM STYLES
       ================================================================ */
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

    .ultrasound-form.active {
        display: block !important;
    }

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

    .ultrasound-form .patient-info p {
        margin: 0;
        font-size: 14px;
    }

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

    .ultrasound-form .findings p strong {
        min-width: 150px;
    }

    .ultrasound-form .biometry {
        margin-bottom: 15px;
        overflow-x: auto;
    }

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

    .ultrasound-form .biometry table th {
        background: #E8F0FE;
    }

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

    /* Dark mode for ultrasound form */
    html[data-theme="dark"] .ultrasound-form {
        background: #1E293B !important;
        border-color: #3B82F6 !important;
    }

    html[data-theme="dark"] .ultrasound-form .report-header h2 {
        color: #60A5FA !important;
    }

    html[data-theme="dark"] .ultrasound-form .report-header h3 {
        color: #F1F5F9 !important;
    }

    html[data-theme="dark"] .ultrasound-form .patient-info p {
        color: #94A3B8 !important;
    }

    html[data-theme="dark"] .ultrasound-form h4 {
        color: #60A5FA !important;
    }

    html[data-theme="dark"] .ultrasound-form input.form-control,
    html[data-theme="dark"] .ultrasound-form textarea.form-control {
        background: #0F172A;
        border-color: #475569;
        color: #F1F5F9;
    }

    html[data-theme="dark"] .ultrasound-form .biometry table th {
        background: #1E3A5F !important;
        color: #F1F5F9 !important;
    }

    /* ================================================================
       FORM SELECTOR
       ================================================================ */
    .form-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }

    .form-selector .form-option {
        padding: 12px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-weight: 500;
        font-size: 0.8rem;
    }

    html[data-theme="dark"] .form-selector .form-option {
        background: #1E293B;
        color: #F1F5F9;
        border-color: #334155;
    }

    .form-selector .form-option:hover {
        border-color: #0B5ED7;
        background: #E8F0FE;
        transform: translateY(-2px);
    }

    html[data-theme="dark"] .form-selector .form-option:hover {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }

    .form-selector .form-option.active {
        border-color: #0B5ED7;
        background: #0B5ED7;
        color: white;
    }

    .form-selector .form-option .option-icon {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 4px;
    }

    /* ================================================================
       SAMPLE RESULT ITEMS
       ================================================================ */
    .sample-result-item {
        padding: 8px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--page-bg-card, #FFFFFF);
        margin-bottom: 6px;
    }

    html[data-theme="dark"] .sample-result-item {
        background: #1E293B;
        border-color: #334155;
    }

    .sample-result-item:hover {
        border-color: #0B5ED7;
        background: #E8F0FE;
        transform: translateX(4px);
    }

    html[data-theme="dark"] .sample-result-item:hover {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }

    .sample-result-item .sample-label {
        font-weight: 600;
        font-size: 0.75rem;
        color: #0B5ED7;
    }

    html[data-theme="dark"] .sample-result-item .sample-label {
        color: #6EA8FE;
    }

    .sample-result-item .sample-value {
        font-size: 0.85rem;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .sample-result-item .sample-value {
        color: #F1F5F9;
    }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 14px 22px;
        border-radius: 10px;
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
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .card { padding: 14px 16px; }
        .detail-row { flex-direction: column; }
        .detail-label { width: 100%; }
        .ultrasound-form .patient-info { grid-template-columns: 1fr; }
        .ultrasound-form .findings p { flex-direction: column; align-items: flex-start; }
        .ultrasound-form .findings p strong { min-width: auto; }
        .form-selector { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 480px) {
        .ultrasound-form { padding: 10px !important; }
        .form-selector { grid-template-columns: 1fr 1fr; }
        .form-selector .form-option { font-size: 0.7rem; padding: 8px 10px; }
        .form-selector .form-option .option-icon { font-size: 1.2rem; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .page-header-actions, .btn, .form-selector, .form-option, .alert {
            display: none !important;
        }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .ultrasound-form {
            border: 1px solid #0B5ED7 !important;
            box-shadow: none !important;
            display: block !important;
            padding: 15px !important;
            max-width: 100% !important;
        }
        .ultrasound-form.active { display: block !important; }
        .ultrasound-form input, .ultrasound-form textarea {
            border: 1px solid #ccc !important;
            background: white !important;
            color: #000 !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-edit"></i>
                Add Test Result
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-flask"></i>
                Test: <strong><?= htmlspecialchars($test_name ?? 'N/A') ?></strong>
                <span>|</span>
                Patient: <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></strong>
                <?php if ($show_ultrasound): ?>
                    <span class="page-header-badge">🩺 Ultrasound</span>
                    <span class="page-header-badge"><?= count($templates) ?> Templates</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($show_ultrasound): ?>
                <button onclick="printUltrasoundForm()" class="btn-outline-light">
                    <i class="fas fa-print"></i> Print Form
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message && !$save_success): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
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
                <span class="detail-value" style="font-weight:600;"><?= htmlspecialchars($test_name ?? 'N/A') ?></span>
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
                <div class="detail-row">
                    <span class="detail-label">Diagnosis</span>
                    <span class="detail-value"><?= htmlspecialchars($lab_test['diagnosis']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Result Form -->
        <div class="card lg:col-span-2">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green"></i>
                <?= $show_ultrasound ? 'Select Ultrasound Template & Fill Report' : 'Enter Result' ?>
                <?php if ($show_ultrasound): ?>
                    <button onclick="printUltrasoundForm()" class="btn btn-print btn-sm" style="margin-left:auto;">
                        <i class="fas fa-print"></i> Print
                    </button>
                <?php endif; ?>
            </h3>

            <?php if ($show_ultrasound): ?>
                <!-- Ultrasound Forms -->
                <?= generateFormSelector($templates) ?>

                <form method="POST" action="" id="ultrasoundForm">
                    <input type="hidden" name="action" value="save_result">
                    <input type="hidden" name="reference_range" value="">
                    <input type="hidden" name="interpretation" value="">

                    <?= generateFormsFromTemplates($templates, $lab_test, $user_full_name, $user_specialty) ?>

                    <input type="hidden" name="result" id="ultrasoundResult" value="">

                    <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:10px;">
                        <button type="button" class="btn btn-success" onclick="saveUltrasoundResult()">
                            <i class="fas fa-save"></i> Save Report
                        </button>
                        <button type="button" class="btn btn-print" onclick="printUltrasoundForm()">
                            <i class="fas fa-print"></i> Print Form
                        </button>
                        <button type="button" class="btn btn-outline" onclick="clearUltrasoundForm()">
                            <i class="fas fa-undo"></i> Clear Form
                        </button>
                        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
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
                        <div style="margin-bottom:20px;">
                            <label class="form-label" style="margin-bottom:8px;">
                                <i class="fas fa-list"></i> Sample Results (Click to fill)
                            </label>
                            <?php foreach ($samples as $label => $value): ?>
                                <div class="sample-result-item" onclick="fillResult('<?= addslashes($value) ?>', this)">
                                    <span class="sample-label"><?= htmlspecialchars($label) ?>:</span>
                                    <span class="sample-value"><?= htmlspecialchars($value) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Result Textarea -->
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Result <span style="color:#DC2626;">*</span></label>
                        <textarea name="result" class="form-control" id="resultText" rows="4" placeholder="Enter test result..."></textarea>
                    </div>

                    <!-- Reference Range -->
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Reference Range</label>
                        <input type="text" name="reference_range" class="form-control" placeholder="e.g. 0.5 - 1.2 mg/dL">
                    </div>

                    <!-- Interpretation -->
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Interpretation</label>
                        <textarea name="interpretation" class="form-control" rows="2" placeholder="Clinical interpretation of the results..."></textarea>
                    </div>

                    <!-- Status -->
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="completed">✅ Completed</option>
                            <option value="in_progress">🔄 In Progress</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Notes (Optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Additional notes...">
                    </div>

                    <!-- Buttons -->
                    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px;">
                        <button type="submit" class="btn btn-success" id="saveBtn">
                            <i class="fas fa-save"></i> Save Result
                        </button>
                        <button type="reset" class="btn btn-outline" onclick="document.getElementById('resultText').value = '';">
                            <i class="fas fa-undo"></i> Clear
                        </button>
                        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;margin:0;">Notification</p>
        <p id="toastMessage" style="font-size:0.8rem;margin:0;"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();

    document.addEventListener('darkModeChanged', function() {
        setTimeout(enforceDarkModeBackground, 50);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

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
        if (element) {
            element.classList.add('active');
        }
    }

    // ================================================================
    // FILL RESULT FROM SAMPLE
    // ================================================================
    function fillResult(value, element) {
        document.getElementById('resultText').value = value;
        document.querySelectorAll('.sample-result-item').forEach(function(el) {
            el.style.borderColor = '';
            el.style.background = '';
        });
        if (element) {
            element.style.borderColor = '#0B5ED7';
            element.style.background = '#E8F0FE';
        }
    }

    // ================================================================
    // REGULAR FORM VALIDATION
    // ================================================================
    document.getElementById('regularForm')?.addEventListener('submit', function(e) {
        var result = document.getElementById('resultText').value.trim();
        if (!result) {
            e.preventDefault();
            showToast('Error', 'Please enter the test result.', 'error');
            return false;
        }
        var btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        return true;
    });

    // ================================================================
    // SAVE ULTRASOUND RESULT
    // ================================================================
    function saveUltrasoundResult() {
        var activeForm = document.querySelector('.ultrasound-form.active');
        if (!activeForm) {
            showToast('Error', 'No form selected', 'error');
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
            showToast('Error', 'Please fill in the form before saving', 'error');
            return;
        }

        document.getElementById('ultrasoundResult').value = resultText;
        document.getElementById('ultrasoundForm').submit();
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
        showToast('Info', 'Form cleared', 'info');
    }

    // ================================================================
    // PRINT ULTRASOUND FORM
    // ================================================================
    function printUltrasoundForm() {
        var activeForm = document.querySelector('.ultrasound-form.active');
        if (!activeForm) {
            showToast('Error', 'No form selected to print', 'error');
            return;
        }

        var printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
            showToast('Error', 'Please allow popups for printing', 'error');
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
        printWindow.document.write('.ultrasound-report { max-width: 900px; margin: 0 auto; padding: 20px; border: 2px solid #0B5ED7; border-radius: 10px; }');
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
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c🧪 Admin - Add Lab Result', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c📋 Test: <?= htmlspecialchars($test_name ?? "N/A") ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test["patient_name"] ?? "N/A") ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>