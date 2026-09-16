<?php
// ================================================================
// FILE: frontend/pages/admin/add_sick_sheet.php
// SUPER ADMIN - CREATE SICK SHEET
// ✅ BLUE THEME ONLY
// ✅ Registered + External patients
// ✅ PDF Generation with Official Stamp
// ✅ 7 VITAL SIGNS with modern CSS cards (like edit_sick_sheet.php)
// ✅ FIXED: Oxygen saturation inakubali thamani yoyote (0 - 100+)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $current_branch_name_display = $branch_data['name'];
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PATIENTS
// ================================================================
$patients = [];
$patient_sql = "
    SELECT p.id, p.full_name, p.patient_id, p.phone, p.gender, p.date_of_birth, 
           p.address, p.blood_group, p.allergies, p.branch_id
    FROM patients p
    WHERE 1=1
";
$patient_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $patient_sql .= " AND p.branch_id = ?";
    $patient_params[] = (int)$selected_branch_id;
}
$patient_sql .= " ORDER BY p.full_name ASC LIMIT 500";

$stmt = $db->prepare($patient_sql);
$stmt->execute($patient_params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DOCTORS
// ================================================================
$doctors = [];
$doctor_sql = "
    SELECT u.id, u.full_name, u.specialty, u.branch_id
    FROM users u
    WHERE u.role = 'doctor' AND u.status = 'active'
";
$doctor_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $doctor_sql .= " AND u.branch_id = ?";
    $doctor_params[] = (int)$selected_branch_id;
}
$doctor_sql .= " ORDER BY u.full_name ASC";

$stmt = $db->prepare($doctor_sql);
$stmt->execute($doctor_params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_type = $_POST['patient_type'] ?? 'registered';
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $branch_id_post = (int)($_POST['branch_id'] ?? $user_branch_id);
    
    // External patient fields
    $external_name = trim($_POST['external_name'] ?? '');
    $external_id = trim($_POST['external_id'] ?? '');
    $external_phone = trim($_POST['external_phone'] ?? '');
    $external_gender = $_POST['external_gender'] ?? '';
    $external_dob = $_POST['external_dob'] ?? '';
    $external_address = trim($_POST['external_address'] ?? '');
    $external_blood = $_POST['external_blood'] ?? '';
    $external_allergies = trim($_POST['external_allergies'] ?? '');
    
    // If external, check if there's a doctor_id_external
    if ($patient_type === 'external') {
        $doctor_id = (int)($_POST['doctor_id_external'] ?? 0);
    }
    
    // Clinical details
    $symptoms = trim($_POST['symptoms'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    
    // ✅ 7 VITAL SIGNS (oxygen inakubali thamani yoyote)
    $temperature = $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null;
    $bp_systolic = $_POST['bp_systolic'] !== '' ? (int)$_POST['bp_systolic'] : null;
    $bp_diastolic = $_POST['bp_diastolic'] !== '' ? (int)$_POST['bp_diastolic'] : null;
    $pulse_rate = $_POST['pulse_rate'] !== '' ? (int)$_POST['pulse_rate'] : null;
    $oxygen_saturation = $_POST['oxygen_saturation'] !== '' ? (int)$_POST['oxygen_saturation'] : null; // ✅ Inakubali > 100
    $weight = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
    $height = $_POST['height'] !== '' ? (float)$_POST['height'] : null;
    
    // Auto-calculate BMI for PDF
    $bmi_display = '--';
    if ($weight && $height && $height > 0) {
        $height_m = $height / 100;
        $bmi_val = round($weight / ($height_m * $height_m), 1);
        $bmi_display = $bmi_val . ' kg/m²';
    }
    
    // Sick sheet details
    $sick_days = (int)($_POST['sick_days'] ?? 3);
    $sick_from = $_POST['sick_from'] ?? date('Y-m-d');
    $sick_to = $_POST['sick_to'] ?? date('Y-m-d', strtotime('+3 days'));
    $sick_reason = trim($_POST['sick_reason'] ?? 'Medical condition requiring rest');
    $sick_restrictions = trim($_POST['sick_restrictions'] ?? 'No heavy lifting, complete rest');
    
    $errors = [];
    
    // Validate
    if ($patient_type === 'registered' && $patient_id <= 0) {
        $errors[] = "Please select a registered patient";
    }
    if ($patient_type === 'external' && empty($external_name)) {
        $errors[] = "Please enter patient full name";
    }
    if ($sick_days <= 0) {
        $errors[] = "Sick days must be greater than 0";
    }
    if (empty($sick_from) || empty($sick_to)) {
        $errors[] = "Sick sheet dates are required";
    }
    if (empty($diagnosis)) {
        $errors[] = "Diagnosis is required";
    }
    
    if (empty($errors)) {
        try {
            // ================================================================
            // GET PATIENT DATA
            // ================================================================
            if ($patient_type === 'registered' && $patient_id > 0) {
                $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
                $stmt->execute([$patient_id]);
                $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $full_name = $patient_data['full_name'] ?? '';
                $patient_number = $patient_data['patient_id'] ?? '';
                $phone = $patient_data['phone'] ?? '';
                $gender = $patient_data['gender'] ?? '';
                $date_of_birth = $patient_data['date_of_birth'] ?? '';
                $address = $patient_data['address'] ?? '';
                $blood_group = $patient_data['blood_group'] ?? '';
                $allergies = $patient_data['allergies'] ?? '';
                $is_external = false;
            } else {
                $full_name = $external_name;
                $patient_number = !empty($external_id) ? $external_id : 'EXT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $phone = $external_phone;
                $gender = $external_gender;
                $date_of_birth = $external_dob;
                $address = $external_address;
                $blood_group = $external_blood;
                $allergies = $external_allergies;
                $is_external = true;
            }
            
            $document_number = 'SS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // ================================================================
            // BUILD PDF HTML CONTENT
            // ================================================================
            $branch_name_display = 'Braick Dispensary';
            $branch_location = '';
            $branch_phone = '';
            
            try {
                $stmt_b = $db->prepare("SELECT name, location, phone FROM branches WHERE id = ?");
                $stmt_b->execute([$branch_id_post]);
                $b_data = $stmt_b->fetch(PDO::FETCH_ASSOC);
                if ($b_data) {
                    $branch_name_display = $b_data['name'] ?? 'Braick Dispensary';
                    $branch_location = $b_data['location'] ?? '';
                    $branch_phone = $b_data['phone'] ?? '';
                }
            } catch (Exception $e) {}
            
            // Doctor info
            $doctor_name_display = '';
            $doctor_specialty_display = '';
            if ($doctor_id > 0) {
                foreach ($doctors as $d) {
                    if ($d['id'] == $doctor_id) {
                        $doctor_name_display = $d['full_name'];
                        $doctor_specialty_display = $d['specialty'];
                        break;
                    }
                }
            }
            
            // Build HTML for PDF
            $html_content = '
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <title>Sick Sheet - ' . htmlspecialchars($full_name) . '</title>
            <style>
                @page { margin: 15mm; size: A4; }
                body { font-family: Arial, sans-serif; font-size: 11px; color: #1E293B; line-height: 1.5; }
                .header { text-align: center; border-bottom: 3px solid #0B5ED7; padding-bottom: 12px; margin-bottom: 16px; }
                .header .logo-title { font-size: 22px; font-weight: 700; color: #0B5ED7; letter-spacing: 1px; }
                .header .logo-sub { font-size: 11px; color: #64748B; margin-top: 2px; }
                .header .branch-info { font-size: 9px; color: #64748B; margin-top: 2px; }
                .header .document-number { font-size: 10px; color: #64748B; font-weight: 600; }
                .page-title { font-size: 18px; font-weight: 700; color: #0B5ED7; text-align: center; margin: 6px 0 2px 0; }
                .page-subtitle { font-size: 10px; color: #64748B; text-align: center; margin-bottom: 10px; }
                .section-title { font-size: 12px; font-weight: 700; color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 4px; margin: 14px 0 10px 0; }
                .section-title.green { color: #059669; border-color: #059669; }
                .section-title.purple { color: #7C3AED; border-color: #7C3AED; }
                .section-title.orange { color: #D97706; border-color: #D97706; }
                .section-title.sky { color: #0284C7; border-color: #0284C7; }
                .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
                .row-7col { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
                .info-card { background: #F8FAFC; border-radius: 6px; padding: 10px 14px; border: 1px solid #E2E8F0; margin-bottom: 6px; }
                .info-card .label { font-size: 8px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; }
                .info-card .value { font-size: 11px; font-weight: 600; color: #1E293B; display: block; margin-top: 2px; }
                .info-card.blue { border-left: 4px solid #0B5ED7; }
                .info-card.green { border-left: 4px solid #059669; }
                .vital-item { background: #F8FAFC; border-radius: 6px; padding: 6px 4px; text-align: center; border: 1px solid #E2E8F0; }
                .vital-item .vital-label { font-size: 6px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; }
                .vital-item .vital-value { font-size: 11px; font-weight: 700; display: block; margin-top: 2px; }
                .vital-item.spo2 { background: #E0F2FE; border-color: #0EA5E9; }
                .vital-item.spo2 .vital-value { color: #0284C7; }
                .sick-box { background: #E8F0FE; border-radius: 6px; padding: 12px 16px; border: 2px solid #0B5ED7; margin: 8px 0; }
                .sick-box .sick-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
                .sick-box .sick-item .slabel { font-size: 8px; font-weight: 600; color: #64748B; text-transform: uppercase; display: block; }
                .sick-box .sick-item .svalue { font-size: 13px; font-weight: 700; color: #0B5ED7; display: block; margin-top: 2px; }
                .detail-row { display: flex; padding: 6px 0; border-bottom: 1px solid #E2E8F0; }
                .detail-label { font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 10px; }
                .detail-value { flex: 1; color: #1E293B; font-size: 10px; }
                .restriction-box { background: #FEF3C7; border-radius: 6px; padding: 8px 14px; border: 1px solid #F59E0B; margin: 6px 0; }
                .footer-section { margin-top: 20px; padding-top: 12px; border-top: 2px solid #E2E8F0; display: flex; justify-content: space-between; }
                .stamp { border: 2px solid #0B5ED7; border-radius: 6px; padding: 8px 18px; text-align: center; background: #F8FAFC; width: 170px; }
                .stamp .stamp-title { font-size: 8px; color: #64748B; text-transform: uppercase; letter-spacing: 1px; }
                .stamp .stamp-name { font-size: 13px; font-weight: 700; color: #0B5ED7; margin-top: 2px; }
                .stamp .stamp-line { border-top: 1px dashed #CBD5E1; margin: 4px 0; }
                .stamp .stamp-doctor { font-size: 9px; color: #0B5ED7; font-weight: 600; }
                .stamp .stamp-signature { font-size: 9px; color: #1E293B; }
                .stamp .stamp-date { font-size: 8px; color: #94A3B8; margin-top: 2px; }
                .footer-note { text-align: center; font-size: 7px; color: #94A3B8; margin-top: 12px; padding-top: 8px; border-top: 1px solid #E2E8F0; }
                .footer-note .brand { color: #0B5ED7; font-weight: 600; }
                .footer-note .slogan { font-size: 9px; color: #0B5ED7; font-weight: 600; margin-top: 3px; }
                .external-tag { font-size: 7px; background: #D97706; color: white; padding: 1px 8px; border-radius: 10px; margin-left: 6px; }
            </style>
            </head>
            <body>
            
            <div class="header">
                <div class="logo-title">🏥 BRAICK DISPENSARY</div>
                <div class="logo-sub">Quality Healthcare Services</div>
                <div class="branch-info">
                    ' . htmlspecialchars($branch_name_display) . ' | ' . htmlspecialchars($branch_location) . ' | Tel: ' . htmlspecialchars($branch_phone) . '
                </div>
                <div class="document-number">Document #: ' . htmlspecialchars($document_number) . '</div>
            </div>
            
            <div class="page-title">📋 MEDICAL SICK SHEET</div>
            <div class="page-subtitle">Certificate of Sickness</div>
            
            <!-- Patient Information -->
            <div class="section-title">👤 Patient Information</div>
            <div class="row-2col">
                <div>
                    <div class="info-card blue">
                        <span class="label">Full Name</span>
                        <span class="value">' . htmlspecialchars($full_name) . ($is_external ? '<span class="external-tag">⚠️ EXTERNAL</span>' : '') . '</span>
                    </div>
                    <div class="info-card">
                        <span class="label">Patient ID</span>
                        <span class="value" style="font-family:monospace;">' . htmlspecialchars($patient_number) . '</span>
                    </div>
                    <div class="info-card">
                        <span class="label">Gender</span>
                        <span class="value">' . ucfirst(htmlspecialchars($gender ?: 'N/A')) . '</span>
                    </div>
                </div>
                <div>
                    <div class="info-card green">
                        <span class="label">Phone</span>
                        <span class="value">' . htmlspecialchars($phone ?: 'N/A') . '</span>
                    </div>
                    <div class="info-card">
                        <span class="label">Blood Group</span>
                        <span class="value">' . htmlspecialchars($blood_group ?: 'N/A') . '</span>
                    </div>
                    <div class="info-card">
                        <span class="label">Date of Birth</span>
                        <span class="value">' . (!empty($date_of_birth) ? date('d M Y', strtotime($date_of_birth)) : 'N/A') . '</span>
                    </div>
                </div>
            </div>
            
            <!-- Doctor Information -->
            ' . ($doctor_name_display ? '
            <div class="section-title green">👨‍⚕️ Doctor Information</div>
            <div class="row-2col">
                <div class="info-card green">
                    <span class="label">Doctor Name</span>
                    <span class="value">Dr. ' . htmlspecialchars($doctor_name_display) . '</span>
                </div>
                <div class="info-card green">
                    <span class="label">Specialty</span>
                    <span class="value">' . htmlspecialchars($doctor_specialty_display ?: 'Medical Doctor') . '</span>
                </div>
            </div>' : '') . '
            
            <!-- Vital Signs -->
            ' . (($temperature || $bp_systolic || $pulse_rate || $oxygen_saturation || $weight || $height) ? '
            <div class="section-title sky">❤️ Vital Signs (7 Signs)</div>
            <div class="row-7col">
                <div class="vital-item"><span class="vital-label">🌡️ Temp</span><span class="vital-value">' . htmlspecialchars($temperature ?: '--') . ' °C</span></div>
                <div class="vital-item"><span class="vital-label">💓 BP</span><span class="vital-value">' . htmlspecialchars(($bp_systolic ?: '--') . '/' . ($bp_diastolic ?: '--')) . '</span></div>
                <div class="vital-item"><span class="vital-label">💓 Pulse</span><span class="vital-value">' . htmlspecialchars($pulse_rate ?: '--') . ' bpm</span></div>
                <div class="vital-item spo2"><span class="vital-label">🫁 SpO2</span><span class="vital-value">' . htmlspecialchars($oxygen_saturation ?: '--') . ' %</span></div>
                <div class="vital-item"><span class="vital-label">⚖️ Weight</span><span class="vital-value">' . htmlspecialchars($weight ?: '--') . ' kg</span></div>
                <div class="vital-item"><span class="vital-label">📏 Height</span><span class="vital-value">' . htmlspecialchars($height ?: '--') . ' cm</span></div>
                <div class="vital-item"><span class="vital-label">📊 BMI</span><span class="vital-value">' . htmlspecialchars($bmi_display) . '</span></div>
            </div>' : '') . '
            
            <!-- Clinical Details -->
            <div class="section-title purple">🩺 Clinical Details</div>
            ' . (!empty($symptoms) ? '<div class="detail-row"><span class="detail-label">Symptoms</span><span class="detail-value">' . nl2br(htmlspecialchars($symptoms)) . '</span></div>' : '') . '
            <div class="detail-row"><span class="detail-label">Diagnosis</span><span class="detail-value"><strong>' . nl2br(htmlspecialchars($diagnosis)) . '</strong></span></div>
            ' . (!empty($treatment) ? '<div class="detail-row"><span class="detail-label">Treatment</span><span class="detail-value">' . nl2br(htmlspecialchars($treatment)) . '</span></div>' : '') . '
            ' . (!empty($instructions) ? '<div class="detail-row"><span class="detail-label">Instructions</span><span class="detail-value">' . nl2br(htmlspecialchars($instructions)) . '</span></div>' : '') . '
            
            <!-- Sick Leave Details -->
            <div class="section-title orange">📅 Sick Leave Details</div>
            <div class="sick-box">
                <div class="sick-grid">
                    <div class="sick-item">
                        <span class="slabel">Sick Days</span>
                        <span class="svalue">' . (int)$sick_days . ' days</span>
                    </div>
                    <div class="sick-item">
                        <span class="slabel">From</span>
                        <span class="svalue">' . date('d M Y', strtotime($sick_from)) . '</span>
                    </div>
                    <div class="sick-item">
                        <span class="slabel">To</span>
                        <span class="svalue">' . date('d M Y', strtotime($sick_to)) . '</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-row"><span class="detail-label">Reason</span><span class="detail-value">' . htmlspecialchars($sick_reason) . '</span></div>
            
            <div class="restriction-box">
                <span style="font-size:9px;font-weight:700;color:#92400E;">⚠ Restrictions:</span>
                <span style="font-size:10px;color:#92400E;">' . htmlspecialchars($sick_restrictions) . '</span>
            </div>
            
            <!-- Footer -->
            <div class="footer-section">
                <div>
                    <div style="font-size:11px;font-weight:600;">Dr. ' . htmlspecialchars($doctor_name_display ?: 'Medical Officer') . '</div>
                    <div style="font-size:9px;color:#64748B;margin-top:2px;">
                        ' . htmlspecialchars($doctor_specialty_display ?: 'Medical Doctor') . '
                    </div>
                    <div style="display:flex;gap:30px;margin-top:10px;">
                        <div style="text-align:center;">
                            <div style="width:100px;border-bottom:1px solid #1E293B;height:20px;"></div>
                            <span style="font-size:8px;color:#64748B;">Doctor\'s Signature</span>
                        </div>
                        <div style="text-align:center;">
                            <div style="width:100px;border-bottom:1px solid #1E293B;height:20px;"></div>
                            <span style="font-size:8px;color:#64748B;">Date</span>
                        </div>
                    </div>
                </div>
                <div class="stamp">
                    <div class="stamp-title">Official Stamp</div>
                    <div class="stamp-name">BRAICK DISPENSARY</div>
                    <div class="stamp-line"></div>
                    <div class="stamp-doctor">Dr. ' . htmlspecialchars($doctor_name_display ?: 'Medical Officer') . '</div>
                    <div class="stamp-signature">_________________________</div>
                    <div class="stamp-date">Date: ' . date('d M Y') . '</div>
                </div>
            </div>
            
            <div class="footer-note">
                <div>
                    <span class="brand">🏥 Braick Dispensary</span> | ' . htmlspecialchars($document_number) . ' | Generated: ' . date('d M Y, h:i A') . '
                </div>
                <div class="slogan">⭐ Braick Dispensary - Tunajali Afya Yako ⭐</div>
            </div>
            
            </body>
            </html>';
            
            // ================================================================
            // SAVE PDF / HTML FILE
            // ================================================================
            $file_name = 'sick_sheet_' . $document_number . '.html';
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/sick_sheets/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_path = '/dispensary_system/frontend/assets/uploads/sick_sheets/' . $file_name;
            file_put_contents($upload_dir . $file_name, $html_content);
            
            // ================================================================
            // SAVE TO APPROPRIATE TABLE
            // ================================================================
            if ($is_external) {
                $stmt = $db->prepare("
                    INSERT INTO external_sick_sheets (
                        document_number, full_name, patient_id, phone, gender, date_of_birth,
                        address, blood_group, allergies, symptoms, diagnosis, treatment,
                        instructions, temperature, bp_systolic, bp_diastolic, pulse_rate,
                        oxygen_saturation, weight, height, sick_days, sick_from, sick_to,
                        sick_reason, sick_restrictions, doctor_id, branch_id,
                        file_name, file_path, file_type, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, NOW()
                    )
                ");
                
                $stmt->execute([
                    $document_number, $full_name, $patient_number, $phone, $gender, 
                    !empty($date_of_birth) ? $date_of_birth : null,
                    $address, $blood_group, $allergies, $symptoms, $diagnosis, $treatment,
                    $instructions, $temperature, $bp_systolic, $bp_diastolic, $pulse_rate,
                    $oxygen_saturation, $weight, $height,
                    $sick_days, $sick_from, $sick_to,
                    $sick_reason, $sick_restrictions, 
                    $doctor_id > 0 ? $doctor_id : $user_id, 
                    $branch_id_post,
                    $file_name, $file_path, 'text/html'
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO patient_documents (
                        document_number, patient_id, doctor_id, branch_id, uploaded_by,
                        document_type, document_name, document_title, description,
                        file_name, file_path, file_size, file_type,
                        sick_sheet_days, sick_sheet_from_date, sick_sheet_to_date,
                        sick_sheet_diagnosis, sick_sheet_recommendations, sick_sheet_restrictions,
                        is_verified, status, upload_date
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        'sick_sheet', ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        1, 'active', NOW()
                    )
                ");
                
                $stmt->execute([
                    $document_number, $patient_id, 
                    $doctor_id > 0 ? $doctor_id : $user_id, 
                    $branch_id_post, $user_id,
                    'Sick Sheet - ' . $full_name, 
                    'Sick Sheet',
                    'Sick Sheet for ' . $full_name . ' - ' . $sick_days . ' days',
                    $file_name, $file_path, strlen($html_content), 'text/html',
                    $sick_days, $sick_from, $sick_to,
                    $diagnosis, $instructions, $sick_restrictions
                ]);
            }
            
            $document_id = $db->lastInsertId();
            
            // Log activity
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'sick_sheet_created', ?, NOW())");
                $log_stmt->execute([
                    $user_id, $branch_id_post, 
                    "Created sick sheet #$document_number for $full_name ($sick_days days)"
                ]);
            } catch (Exception $e) {}
            
            $message = "✅ Sick sheet created successfully!<br>";
            $message .= "🔢 Document #: <strong>" . htmlspecialchars($document_number) . "</strong><br>";
            $message .= "👤 Patient: <strong>" . htmlspecialchars($full_name) . "</strong><br>";
            $message .= "📅 Sick Days: <strong>" . $sick_days . " days</strong><br>";
            $message .= "📄 <a href='" . htmlspecialchars($file_path) . "' target='_blank' style='color:#0B5ED7;font-weight:600;'>📄 View/Download Sick Sheet</a>";
            $message_type = 'success';
            
            // Redirect after 3 seconds
            echo '<script>setTimeout(function(){ window.location.href = "sick_sheets.php?branch=' . $selected_branch_id . '"; }, 3500);</script>';
            
        } catch (Exception $e) {
            $message = "❌ Failed to create: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ " . implode('<br>❌ ', $errors);
        $message_type = 'error';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================
   BLUE THEME ONLY
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --teal: #0D9488;
    --sky: #0EA5E9;
    --pink: #EC4899;
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

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
    position: relative;
    z-index: 1;
}

.page-header-custom .page-title i {
    color: rgba(255,255,255,0.85);
}

.page-header-custom .badge-branch {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
}

.page-header-custom .btn-back {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.3);
    padding: 9px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
}

.page-header-custom .btn-back:hover {
    background: rgba(255,255,255,0.25);
    transform: translateX(-3px);
    color: white;
}

/* FORM CARD */
.form-card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 28px 32px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    max-width: 1050px;
    margin-left: auto;
    margin-right: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.form-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 24px rgba(11, 94, 215, 0.08);
}

.form-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 2px dashed var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 16px;
}

.section-title i {
    width: 32px;
    height: 32px;
    background: var(--primary-bg);
    color: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.section-title.purple { color: #7C3AED; }
.section-title.purple i { background: #EDE9FE; color: #7C3AED; }
.section-title.teal { color: #0D9488; }
.section-title.teal i { background: #CCFBF1; color: #0D9488; }
.section-title.orange { color: #D97706; }
.section-title.orange i { background: #FEF3C7; color: #D97706; }
.section-title.sky { color: #0284C7; }
.section-title.sky i { background: #E0F2FE; color: #0284C7; }

[data-theme="dark"] .section-title.purple i { background: #2D1B4E; }
[data-theme="dark"] .section-title.teal i { background: #0F3A35; }
[data-theme="dark"] .section-title.orange i { background: #3A2A0F; }
[data-theme="dark"] .section-title.sky i { background: #0C2A3A; }

/* FORM ROWS */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.form-grid-4 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 12px;
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.form-label .required {
    color: #DC2626;
    margin-left: 2px;
}

.form-label .optional {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    font-family: inherit;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
}

.form-control:hover {
    border-color: var(--primary-light);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

select.form-control {
    cursor: pointer;
    appearance: auto;
}

/* PATIENT TYPE TOGGLE */
.patient-type-toggle {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    background: #F1F5F9;
    padding: 4px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
}

[data-theme="dark"] .patient-type-toggle {
    background: #0F172A;
}

.patient-type-btn {
    flex: 1;
    padding: 12px 16px;
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
    gap: 6px;
}

.patient-type-btn.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

/* ================================================================
   ✅ VITAL SIGNS CARD GRID - 7 SIGNS (from edit_sick_sheet.php)
   ================================================================ */
.vital-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.vital-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 12px 10px;
    text-align: center;
    border: 2px solid var(--border-color);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.vital-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 12px 12px 0 0;
}

.vital-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

/* VITAL CARD COLORS */
.vital-card.temp-card::before { background: linear-gradient(90deg, #EF4444, #F87171); }
.vital-card.temp-card .vital-icon { color: #EF4444; }

.vital-card.bp-card::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
.vital-card.bp-card .vital-icon { color: #0B5ED7; }

.vital-card.pulse-card::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
.vital-card.pulse-card .vital-icon { color: #EC4899; }

/* ✅ OXYGEN CARD - Sky Blue */
.vital-card.oxygen-card::before { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
.vital-card.oxygen-card .vital-icon { color: #0284C7; }
.vital-card.oxygen-card {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
    border-color: #0EA5E9;
}
.vital-card.oxygen-card:hover {
    border-color: #0284C7;
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.25);
}

.vital-card.weight-card::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.vital-card.weight-card .vital-icon { color: #7C3AED; }

.vital-card.height-card::before { background: linear-gradient(90deg, #059669, #10B981); }
.vital-card.height-card .vital-icon { color: #059669; }

.vital-card.bmi-card::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
.vital-card.bmi-card .vital-icon { color: #D97706; }

.vital-card .vital-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
    display: block;
}

.vital-card .vital-label-small {
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    display: block;
}

.vital-card .vital-input {
    width: 100%;
    padding: 8px 10px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    text-align: center;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    font-family: inherit;
}

.vital-card .vital-input:focus {
    border-color: #0B5ED7;
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
}

.vital-card.oxygen-card .vital-input {
    color: #0284C7;
    border-color: #0EA5E9;
}

.vital-card.temp-card .vital-input { color: #EF4444; }
.vital-card.bp-card .vital-input { color: #0B5ED7; }
.vital-card.pulse-card .vital-input { color: #EC4899; }
.vital-card.weight-card .vital-input { color: #7C3AED; }
.vital-card.height-card .vital-input { color: #059669; }
.vital-card.bmi-card .vital-input { color: #D97706; }

.vital-card .vital-unit-small {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 4px;
    display: block;
}

.vital-card .bp-inputs {
    display: flex;
    gap: 4px;
    align-items: center;
}

.vital-card .bp-inputs span {
    font-weight: 700;
    color: var(--text-secondary);
}

/* Vital info footer */
.vital-info-footer {
    margin-top: 12px;
    padding: 8px 14px;
    background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    border: 1px dashed #0EA5E9;
}

[data-theme="dark"] .vital-info-footer {
    background: #0C2A3A;
    border-color: #0EA5E9;
}

/* FORM ACTIONS */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    min-height: 42px;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* MESSAGE BOX */
.message-box {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-weight: 500;
    max-width: 1050px;
    margin-left: auto;
    margin-right: auto;
}

.message-box.success {
    background: #D1FAE5;
    color: #065F46;
    border: 2px solid #6EE7B7;
}

.message-box.error {
    background: #FEE2E2;
    color: #991B1B;
    border: 2px solid #FCA5A5;
}

[data-theme="dark"] .message-box.success {
    background: #1A3A2A;
    color: #34D399;
    border-color: #34D399;
}

[data-theme="dark"] .message-box.error {
    background: #3A1A1A;
    color: #F87171;
    border-color: #F87171;
}

/* SECTIONS */
.patient-section {
    display: none;
}

.patient-section.active {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 992px) {
    .vital-grid { grid-template-columns: repeat(3, 1fr); }
    .form-grid-3 { grid-template-columns: 1fr 1fr; }
    .form-grid-4 { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .form-grid-2, .form-grid-3, .form-grid-4 { grid-template-columns: 1fr; }
    .vital-grid { grid-template-columns: repeat(2, 1fr); }
    .form-card { padding: 18px 20px; }
    .form-actions { flex-direction: column-reverse; }
    .form-actions .btn { width: 100%; justify-content: center; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .patient-type-toggle { flex-direction: column; }
}

@media (max-width: 480px) {
    .vital-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical"></i>
                Create Sick Sheet
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($current_branch_name_display) ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px; position:relative; z-index:1;">
                Create sick sheet for registered or external patient • 7 Vital Signs
            </p>
        </div>
        <a href="sick_sheets.php?branch=<?= $selected_branch_id ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Sick Sheets
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="form-card">
        <form method="POST" action="" id="sickSheetForm">
            
            <!-- PATIENT TYPE TOGGLE -->
            <div class="patient-type-toggle">
                <button type="button" class="patient-type-btn active" id="btnRegistered" onclick="setPatientType('registered')">
                    <i class="fas fa-user-check"></i> Registered Patient
                </button>
                <button type="button" class="patient-type-btn" id="btnExternal" onclick="setPatientType('external')">
                    <i class="fas fa-user-plus"></i> External Patient
                </button>
            </div>
            
            <input type="hidden" name="patient_type" id="patientTypeHidden" value="registered">
            
            <!-- SECTION 1: REGISTERED PATIENT -->
            <div class="patient-section active" id="registeredSection">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user-check"></i>
                        <span>Select Registered Patient</span>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Patient <span class="required">*</span></label>
                            <select name="patient_id" class="form-control" id="patientSelect">
                                <option value="">-- Select Registered Patient --</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['full_name']) ?> 
                                        (<?= htmlspecialchars($p['patient_id']) ?>)
                                        <?php if (!empty($p['phone'])): ?>
                                            - <?= htmlspecialchars($p['phone']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Doctor <span class="optional">(optional)</span></label>
                            <select name="doctor_id" class="form-control" id="doctorSelect">
                                <option value="">-- Select Doctor --</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        Dr. <?= htmlspecialchars($d['full_name']) ?> 
                                        <?= !empty($d['specialty']) ? '(' . htmlspecialchars($d['specialty']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: EXTERNAL PATIENT -->
            <div class="patient-section" id="externalSection">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user-plus"></i>
                        <span>External Patient Details</span>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" name="external_name" class="form-control" 
                                   placeholder="Enter patient full name" maxlength="150">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Patient ID <span class="optional">(auto-generate if empty)</span></label>
                            <input type="text" name="external_id" class="form-control" 
                                   placeholder="e.g., EXT-2026-0001" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="external_phone" class="form-control" 
                                   placeholder="Phone number" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="external_gender" class="form-control">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="external_dob" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Blood Group</label>
                            <select name="external_blood" class="form-control">
                                <option value="">-- Select --</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Allergies</label>
                            <input type="text" name="external_allergies" class="form-control" 
                                   placeholder="e.g., Penicillin" maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Doctor <span class="optional">(optional)</span></label>
                            <select name="doctor_id_external" class="form-control">
                                <option value="">-- Select Doctor --</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        Dr. <?= htmlspecialchars($d['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="form-label">Address</label>
                            <textarea name="external_address" class="form-control" rows="2" 
                                      placeholder="Address" maxlength="300"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: CLINICAL DETAILS -->
            <div class="form-section">
                <div class="section-title purple">
                    <i class="fas fa-stethoscope"></i>
                    <span>Clinical Details</span>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Symptoms</label>
                        <textarea name="symptoms" class="form-control" rows="2" 
                                  placeholder="e.g., Fever, headache, fatigue..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Diagnosis <span class="required">*</span></label>
                        <textarea name="diagnosis" class="form-control" rows="2" 
                                  placeholder="e.g., Malaria, Typhoid" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Treatment</label>
                        <textarea name="treatment" class="form-control" rows="2" 
                                  placeholder="Treatment given..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Instructions</label>
                        <textarea name="instructions" class="form-control" rows="2" 
                                  placeholder="Patient instructions..."></textarea>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- ✅ SECTION 4: VITAL SIGNS - 7 CARDS WITH MODERN CSS -->
            <!-- ============================================================ -->
            <div class="form-section">
                <div class="section-title sky">
                    <i class="fas fa-heartbeat"></i>
                    <span>Vital Signs (7 Signs)</span>
                    <span style="font-size:0.7rem;font-weight:400;color:#0284C7;margin-left:8px;">🫁 SpO2 Normal: 95-100%</span>
                </div>
                
                <div class="vital-grid">
                    
                    <!-- 1. TEMPERATURE -->
                    <div class="vital-card temp-card">
                        <span class="vital-icon">🌡️</span>
                        <span class="vital-label-small">Temperature</span>
                        <input type="number" step="0.1" name="temperature" 
                               class="vital-input" 
                               placeholder="36.5">
                        <span class="vital-unit-small">°C</span>
                    </div>
                    
                    <!-- 2. BLOOD PRESSURE -->
                    <div class="vital-card bp-card">
                        <span class="vital-icon">💓</span>
                        <span class="vital-label-small">Blood Pressure</span>
                        <div class="bp-inputs">
                            <input type="number" name="bp_systolic" class="vital-input" 
                                   placeholder="120">
                            <span>/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" 
                                   placeholder="80">
                        </div>
                        <span class="vital-unit-small">mmHg</span>
                    </div>
                    
                    <!-- 3. PULSE RATE -->
                    <div class="vital-card pulse-card">
                        <span class="vital-icon">💗</span>
                        <span class="vital-label-small">Pulse Rate</span>
                        <input type="number" name="pulse_rate" class="vital-input" 
                               placeholder="72">
                        <span class="vital-unit-small">bpm</span>
                    </div>
                    
                    <!-- 4. ✅ OXYGEN SATURATION (SpO2) - 7th VITAL SIGN -->
                    <!-- ✅ FIXED: Hakuna max limit - inakubali thamani yoyote -->
                    <div class="vital-card oxygen-card">
                        <span class="vital-icon">🫁</span>
                        <span class="vital-label-small">Oxygen (SpO2)</span>
                        <input type="number" name="oxygen_saturation" 
                               class="vital-input oxygen-input" 
                               min="0"
                               placeholder="98">
                        <span class="vital-unit-small">%</span>
                    </div>
                    
                    <!-- 5. WEIGHT -->
                    <div class="vital-card weight-card">
                        <span class="vital-icon">⚖️</span>
                        <span class="vital-label-small">Weight</span>
                        <input type="number" step="0.1" name="weight" 
                               class="vital-input" 
                               id="weightInput"
                               placeholder="65">
                        <span class="vital-unit-small">kg</span>
                    </div>
                    
                    <!-- 6. HEIGHT -->
                    <div class="vital-card height-card">
                        <span class="vital-icon">📏</span>
                        <span class="vital-label-small">Height</span>
                        <input type="number" step="0.1" name="height" 
                               class="vital-input" 
                               id="heightInput"
                               placeholder="170">
                        <span class="vital-unit-small">cm</span>
                    </div>
                    
                    <!-- 7. BMI (Auto-calculated) -->
                    <div class="vital-card bmi-card">
                        <span class="vital-icon">📊</span>
                        <span class="vital-label-small">BMI</span>
                        <input type="text" name="bmi" 
                               class="vital-input" 
                               id="bmiInput"
                               placeholder="Auto" readonly
                               style="cursor:not-allowed;opacity:0.9;">
                        <span class="vital-unit-small">kg/m²</span>
                    </div>
                    
                    <!-- Empty slot for alignment -->
                    <div style="visibility:hidden;"></div>
                    
                </div>
                
                <!-- Vital Info Footer -->
                <div class="vital-info-footer">
                    <i class="fas fa-lungs" style="color:#0EA5E9;"></i>
                    <span style="color:#0284C7;">SpO2 (Oxygen Saturation) Normal Range: <strong>95-100%</strong></span>
                    <span style="color:#64748B;"> • 7 Vital Signs Tracked</span>
                    <span style="color:#64748B;"> • BMI auto-calculated</span>
                </div>
            </div>

            <!-- SECTION 5: SICK LEAVE DETAILS -->
            <div class="form-section">
                <div class="section-title orange">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Sick Leave Details</span>
                </div>
                
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Sick Days <span class="required">*</span></label>
                        <input type="number" name="sick_days" class="form-control" 
                               id="sickDays" value="3" min="1" max="365" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">From Date <span class="required">*</span></label>
                        <input type="date" name="sick_from" class="form-control" 
                               id="sickFrom" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date <span class="required">*</span></label>
                        <input type="date" name="sick_to" class="form-control" 
                               id="sickTo" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                    </div>
                </div>
                
                <div class="form-grid-2" style="margin-top:16px;">
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input type="text" name="sick_reason" class="form-control" 
                               value="Medical condition requiring rest" maxlength="200">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Restrictions</label>
                        <input type="text" name="sick_restrictions" class="form-control" 
                               value="No heavy lifting, complete rest" maxlength="200">
                    </div>
                </div>
            </div>

            <!-- BRANCH -->
            <?php if ($selected_branch_id === 'all'): ?>
                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Branch <span class="required">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $b['id'] == $user_branch_id ? 'selected' : '' ?>>
                                    🏥 <?= htmlspecialchars($b['name']) ?>
                                    <?= !empty($b['location']) ? ' - ' . htmlspecialchars($b['location']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="branch_id" value="<?= (int)$selected_branch_id ?>">
            <?php endif; ?>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <a href="sick_sheets.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-file-medical"></i> Create Sick Sheet
                </button>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Create Sick Sheet (7 Vital Signs)
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// PATIENT TYPE TOGGLE
// ================================================================
function setPatientType(type) {
    document.getElementById('patientTypeHidden').value = type;
    
    var registeredSection = document.getElementById('registeredSection');
    var externalSection = document.getElementById('externalSection');
    var btnRegistered = document.getElementById('btnRegistered');
    var btnExternal = document.getElementById('btnExternal');
    var patientSelect = document.getElementById('patientSelect');
    
    if (type === 'registered') {
        registeredSection.classList.add('active');
        externalSection.classList.remove('active');
        btnRegistered.classList.add('active');
        btnExternal.classList.remove('active');
        if (patientSelect) patientSelect.required = true;
    } else {
        registeredSection.classList.remove('active');
        externalSection.classList.add('active');
        btnRegistered.classList.remove('active');
        btnExternal.classList.add('active');
        if (patientSelect) patientSelect.required = false;
    }
}

// ================================================================
// AUTO-CALCULATE BMI
// ================================================================
function calculateBMI() {
    var weight = parseFloat(document.getElementById('weightInput')?.value) || 0;
    var height = parseFloat(document.getElementById('heightInput')?.value) || 0;
    var bmiInput = document.getElementById('bmiInput');
    
    if (!bmiInput) return;
    
    if (weight > 0 && height > 0) {
        var heightM = height / 100;
        var bmi = weight / (heightM * heightM);
        bmiInput.value = bmi.toFixed(1);
    } else {
        bmiInput.value = '';
    }
}

document.getElementById('weightInput')?.addEventListener('input', calculateBMI);
document.getElementById('heightInput')?.addEventListener('input', calculateBMI);

// ================================================================
// AUTO-CALCULATE SICK DAYS
// ================================================================
function calculateDays() {
    var from = document.getElementById('sickFrom').value;
    var to = document.getElementById('sickTo').value;
    
    if (from && to) {
        var fromDate = new Date(from);
        var toDate = new Date(to);
        
        if (toDate >= fromDate) {
            var days = Math.ceil((toDate - fromDate) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('sickDays').value = days;
        }
    }
}

document.getElementById('sickFrom')?.addEventListener('change', calculateDays);
document.getElementById('sickTo')?.addEventListener('change', calculateDays);

// ================================================================
// ✅ OXYGEN SATURATION VALIDATION
// ✅ FIXED: Inakubali thamani YOYOTE (0 - 100 na ZAIDI)
// ✅ Haizuii kuandika, inaonyesha warning tu kwa rangi
// ================================================================
var spo2Input = document.querySelector('input[name="oxygen_saturation"]');
if (spo2Input) {
    spo2Input.addEventListener('input', function() {
        if (this.value === '' || this.value === '-') {
            this.style.color = '';
            this.style.borderColor = '';
            this.title = '';
            return;
        }
        
        var val = parseFloat(this.value);
        
        if (isNaN(val)) {
            this.style.color = '';
            this.style.borderColor = '';
            this.title = '';
            return;
        }
        
        // ✅ Onyesha RANGI tu - USIBADILISHE value
        if (val < 0) {
            this.style.color = '#DC2626';
            this.style.borderColor = '#DC2626';
            this.title = '❌ SpO2 haiwezi kuwa hasi! Thamani si sahihi.';
        } else if (val < 70) {
            this.style.color = '#DC2626';
            this.style.borderColor = '#DC2626';
            this.title = '⚠️ SpO2 chini sana - Hatari ya kifo!';
        } else if (val < 95) {
            this.style.color = '#D97706';
            this.style.borderColor = '#D97706';
            this.title = '⚠️ SpO2 chini ya kawaida (Normal: 95-100%)';
        } else if (val <= 100) {
            this.style.color = '#059669';
            this.style.borderColor = '#0EA5E9';
            this.title = '✅ SpO2 nzuri';
        } else {
            // ✅ ZAIDI YA 100 - INAKUBALIWA
            this.style.color = '#D97706';
            this.style.borderColor = '#D97706';
            this.title = '⚠️ SpO2 imezidi 100%! Kawaida ni 95-100%. Angalia tena.';
        }
    });
}

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('sickSheetForm')?.addEventListener('submit', function(e) {
    var patientType = document.getElementById('patientTypeHidden').value;
    var diagnosis = document.querySelector('textarea[name="diagnosis"]').value.trim();
    var sickDays = document.getElementById('sickDays').value;
    var sickFrom = document.getElementById('sickFrom').value;
    var sickTo = document.getElementById('sickTo').value;
    
    if (patientType === 'registered') {
        var patientId = document.getElementById('patientSelect').value;
        if (!patientId) {
            e.preventDefault();
            alert('⚠️ Please select a registered patient');
            return false;
        }
    } else {
        var externalName = document.querySelector('input[name="external_name"]').value.trim();
        if (!externalName) {
            e.preventDefault();
            alert('⚠️ Please enter external patient name');
            return false;
        }
    }
    
    if (!diagnosis) {
        e.preventDefault();
        alert('⚠️ Please enter diagnosis');
        return false;
    }
    
    if (!sickDays || sickDays < 1) {
        e.preventDefault();
        alert('⚠️ Sick days must be at least 1');
        return false;
    }
    
    if (!sickFrom || !sickTo) {
        e.preventDefault();
        alert('⚠️ Please set sick sheet dates');
        return false;
    }
    
    // ✅ Warning tu (sio kuzuia) kama oxygen > 100 au < 0
    var oxygenVal = parseFloat(document.querySelector('input[name="oxygen_saturation"]')?.value);
    if (!isNaN(oxygenVal)) {
        if (oxygenVal > 100) {
            var proceed = confirm('⚠️ SpO2 imezidi 100% (' + oxygenVal + '%)!\n\nKawaida ni 95-100%.\n\nUna uhakika unataka kuendelea?');
            if (!proceed) {
                e.preventDefault();
                return false;
            }
        } else if (oxygenVal < 0) {
            var proceed2 = confirm('❌ SpO2 haiwezi kuwa hasi (' + oxygenVal + '%)!\n\nUna uhakika unataka kuendelea?');
            if (!proceed2) {
                e.preventDefault();
                return false;
            }
        }
    }
    
    var btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    btn.disabled = true;
    return true;
});

// ================================================================
// FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c📄 Admin - Create Sick Sheet (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Registered + External patients supported', 'font-size:12px;color:#34D399;');
console.log('%c✅ 7 Vital Signs with modern CSS cards', 'font-size:12px;color:#34D399;');
console.log('%c🫁 Oxygen Saturation (SpO2) - Inakubali thamani YOYOTE (0 - 100+)', 'font-size:12px;color:#0EA5E9;');
console.log('%c✅ Auto-generate document number', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>