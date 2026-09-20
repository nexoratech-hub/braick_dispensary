<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_consultation.php
// ADMIN - EDIT CONSULTATION
// ✅ Ina-update: visit_type, doctor, diagnosis, symptoms, treatment
// ✅ Ina-update: consultation_fee, status, payment_status
// ✅ Ina-recalculate bill kama consultation_fee imebadilika
// ✅ Ina-log activity
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error_message'] = "Only Admin can edit consultations.";
    header('Location: other_services.php?tab=consultations');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$visit_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($visit_id <= 0) {
    $_SESSION['error_message'] = "Invalid consultation ID.";
    header('Location: other_services.php?tab=consultations');
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// CURRENCY
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

function logActivity($db, $user_id, $branch_id, $patient_id, $action, $details) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, branch_id, patient_id, action, details, ip_address, user_agent, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $branch_id, $patient_id, $action, $details, $ip, $ua]);
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

// ================================================================
// FETCH VISIT
// ================================================================
$visit = null;
try {
    $stmt = $db->prepare("
        SELECT v.*,
               p.id as patient_db_id, p.full_name as patient_name,
               p.patient_id as patient_number, p.phone as patient_phone,
               p.gender as patient_gender, p.date_of_birth,
               d.full_name as doctor_name,
               r.full_name as receptionist_name,
               br.name as branch_name
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users d ON v.doctor_id = d.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches br ON v.branch_id = br.id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Visit fetch error: " . $e->getMessage());
}

if (!$visit) {
    $_SESSION['error_message'] = "Consultation not found.";
    header('Location: other_services.php?tab=consultations');
    exit;
}

// ================================================================
// FETCH DOCTORS LIST
// ================================================================
$doctors_list = [];
try {
    $stmt = $db->query("SELECT id, full_name, specialty FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY full_name");
    $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// FETCH DISEASES LIST
// ================================================================
$diseases_list = [];
try {
    $stmt = $db->query("SELECT id, disease_name, disease_code FROM diseases WHERE is_active = 1 ORDER BY disease_name");
    $diseases_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// HANDLE SUBMIT
// ================================================================
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_consultation') {
    $new_doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $new_visit_type = trim($_POST['visit_type'] ?? '');
    $new_consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $new_status = trim($_POST['status'] ?? 'waiting');
    $new_payment_status = trim($_POST['payment_status'] ?? 'pending');
    $new_symptoms = trim($_POST['symptoms'] ?? '');
    $new_hpi = trim($_POST['hpi'] ?? '');
    $new_physical_exam = trim($_POST['physical_exam'] ?? '');
    $new_diagnosis = trim($_POST['diagnosis'] ?? '');
    $new_disease_id = (int)($_POST['disease_id'] ?? 0);
    $new_treatment = trim($_POST['treatment'] ?? '');
    $new_notes = trim($_POST['notes'] ?? '');
    $new_follow_up_date = trim($_POST['follow_up_date'] ?? '');
    
    // Validation
    if ($new_consultation_fee < 0) $errors[] = "Consultation fee cannot be negative.";
    if (!empty($new_follow_up_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_follow_up_date)) {
        $errors[] = "Invalid follow-up date format.";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Fetch disease_code if selected
            $disease_code = null;
            if ($new_disease_id > 0) {
                $stmt = $db->prepare("SELECT disease_code FROM diseases WHERE id = ?");
                $stmt->execute([$new_disease_id]);
                $disease_code = $stmt->fetchColumn();
            }
            
            // ============================================================
            // 1. Update visit
            // ============================================================
            $stmt = $db->prepare("
                UPDATE visits 
                SET doctor_id = ?,
                    visit_type = ?,
                    consultation_fee = ?,
                    status = ?,
                    payment_status = ?,
                    symptoms = ?,
                    hpi = ?,
                    physical_exam = ?,
                    diagnosis = ?,
                    disease_id = ?,
                    disease_code = ?,
                    treatment = ?,
                    notes = ?,
                    follow_up_date = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $new_doctor_id ?: null,
                $new_visit_type,
                $new_consultation_fee,
                $new_status,
                $new_payment_status,
                $new_symptoms ?: null,
                $new_hpi ?: null,
                $new_physical_exam ?: null,
                $new_diagnosis ?: null,
                $new_disease_id ?: null,
                $disease_code,
                $new_treatment ?: null,
                $new_notes ?: null,
                $new_follow_up_date ?: null,
                $visit_id
            ]);
            
            // ============================================================
            // 2. Update bill (consultation item) kama fee imebadilika
            // ============================================================
            $stmt = $db->prepare("SELECT * FROM bills WHERE visit_id = ? LIMIT 1");
            $stmt->execute([$visit_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill) {
                $stmt = $db->prepare("
                    SELECT * FROM bill_items 
                    WHERE bill_id = ? AND item_type = 'consultation'
                    LIMIT 1
                ");
                $stmt->execute([$bill['id']]);
                $consultation_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($consultation_item) {
                    // Update consultation item price
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET item_name = ?,
                            unit_price = ?,
                            total_price = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $new_visit_type ?: 'New Patient',
                        $new_consultation_fee,
                        $new_consultation_fee,
                        $consultation_item['id']
                    ]);
                    
                    // Recalculate bill
                    $stmt = $db->prepare("
                        SELECT 
                            COALESCE(SUM(total_price), 0) as new_subtotal,
                            COALESCE(SUM(discount_amount), 0) as new_items_discount
                        FROM bill_items 
                        WHERE bill_id = ? AND status != 'cancelled'
                    ");
                    $stmt->execute([$bill['id']]);
                    $recalc = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $new_subtotal = (float)($recalc['new_subtotal'] ?? 0);
                    $new_items_discount = (float)($recalc['new_items_discount'] ?? 0);
                    
                    $bill_discount = (float)($bill['discount_amount'] ?? 0);
                    $bill_premium = (float)($bill['premium_amount'] ?? 0);
                    $bill_paid = (float)($bill['paid_amount'] ?? 0);
                    
                    $new_total_discount = $new_items_discount + $bill_discount;
                    $new_total_amount = $new_subtotal - $new_total_discount + $bill_premium;
                    if ($new_total_amount < 0) $new_total_amount = 0;
                    
                    $new_balance = $new_total_amount - $bill_paid;
                    if ($new_balance < 0) $new_balance = 0;
                    
                    $new_bill_status = 'pending';
                    if ($new_balance <= 0 && $new_total_amount > 0) $new_bill_status = 'paid';
                    elseif ($bill_paid > 0 && $new_balance > 0) $new_bill_status = 'partial';
                    
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET subtotal = ?,
                            total_discount = ?,
                            total_amount = ?,
                            balance = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $new_subtotal,
                        $new_total_discount,
                        $new_total_amount,
                        $new_balance,
                        $new_bill_status,
                        $bill['id']
                    ]);
                }
            }
            
            // ============================================================
            // 3. Log activity
            // ============================================================
            $changes = [];
            if ((float)$visit['consultation_fee'] != $new_consultation_fee) {
                $changes[] = "Fee: {$currency} " . number_format($visit['consultation_fee'], 0) . " → {$currency} " . number_format($new_consultation_fee, 0);
            }
            if ($visit['status'] != $new_status) {
                $changes[] = "Status: {$visit['status']} → {$new_status}";
            }
            if ($visit['payment_status'] != $new_payment_status) {
                $changes[] = "Payment: {$visit['payment_status']} → {$new_payment_status}";
            }
            if ($visit['diagnosis'] != $new_diagnosis) {
                $changes[] = "Diagnosis updated";
            }
            if ((int)$visit['doctor_id'] != $new_doctor_id) {
                $changes[] = "Doctor changed";
            }
            
            $change_str = !empty($changes) ? implode(' | ', $changes) : 'No significant changes';
            
            logActivity(
                $db, $user_id, $visit['branch_id'] ?? null, $visit['patient_db_id'] ?? null,
                'edit_consultation',
                "Edited consultation #{$visit['visit_number']}: $change_str"
            );
            
            $db->commit();
            $success = true;
            $_SESSION['success_message'] = "✅ Consultation '{$visit['visit_number']}' updated successfully!";
            
            header('Location: view_consultation.php?id=' . $visit_id . '&branch=' . $selected_branch_id . '&updated=1');
            exit;
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}

$patient_age = calculateAge($visit['date_of_birth']);
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Consultation - <?= htmlspecialchars($visit['visit_number']) ?></title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
}
[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); box-sizing: border-box; }
html, body { background: var(--bg-body); color: var(--text-primary); margin: 0; }
.mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; }

/* ALERTS */
.alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert.error i { font-size: 1.1rem; }

/* PAGE HEADER */
.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; margin: 0; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(255,255,255,0.1); }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; position: relative; z-index: 1; }
.btn-header:hover { background: rgba(255,255,255,0.28); color: white; }

/* FORM CARD */
.form-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; margin-bottom: 16px; box-shadow: var(--shadow-sm); }
.form-card .card-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.85rem; font-weight: 800; }
.form-card .card-header .title { display: flex; align-items: center; gap: 8px; }
.form-card .card-header i { color: #93C5FD; font-size: 1rem; }
.form-card .card-body { padding: 22px; }

/* PATIENT INFO BAR */
.patient-info-bar {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    border-left: 4px solid var(--primary);
}
[data-theme="dark"] .patient-info-bar { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.patient-info-bar .avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
}
.patient-info-bar .info { flex: 1; min-width: 200px; }
.patient-info-bar .name { font-size: 1rem; font-weight: 800; color: var(--text-primary); }
.patient-info-bar .meta {
    display: flex; gap: 12px; font-size: 0.7rem;
    color: var(--text-secondary); flex-wrap: wrap; margin-top: 2px;
}
.patient-info-bar .meta span { display: flex; align-items: center; gap: 4px; }

/* FORM GRID */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid-full { grid-template-columns: 1fr; }
.form-group { margin-bottom: 16px; }
.form-group:last-child { margin-bottom: 0; }
.form-group label {
    display: flex; align-items: center; gap: 5px;
    font-size: 0.7rem; font-weight: 700; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;
}
.form-group label .req { color: var(--danger); }
.form-group label i { color: var(--primary); font-size: 0.7rem; }
.form-control {
    width: 100%; padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem; font-weight: 500;
    outline: none; transition: all 0.2s ease;
    font-family: var(--font-primary);
}
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }
.form-control.mono { font-family: var(--font-mono); }
textarea.form-control { min-height: 80px; resize: vertical; line-height: 1.5; }
select.form-control { cursor: pointer; }
select.form-control option { padding: 8px; }

/* INFO ROW */
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-color); font-size: 0.82rem; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: var(--text-secondary); font-weight: 600; font-size: 0.72rem; display: flex; align-items: center; gap: 6px; }
.info-row .label i { color: var(--primary); font-size: 0.7rem; }
.info-row .value { color: var(--text-primary); font-weight: 700; text-align: right; }
.info-row .value.mono { font-family: var(--font-mono); }

/* SIDEBAR INFO */
.info-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; margin-bottom: 16px; box-shadow: var(--shadow-sm); }
.info-card .card-header { padding: 14px 18px; background: linear-gradient(135deg, #64748B, #475569); color: white; display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 800; }
.info-card .card-header i { color: #CBD5E1; }
.info-card .card-body { padding: 16px 18px; }

/* ACTION BAR */
.action-bar { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; padding: 20px 0; border-top: 2px solid var(--border-color); margin-top: 20px; }
.btn { padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; font-family: var(--font-primary); }
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); color: white; }
.btn-success { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.btn-success:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5); color: white; }
.btn-secondary { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

.footer { padding: 14px 0; border-top: 1px solid var(--border-color); margin-top: 24px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); }
.footer .footer-brand { color: var(--primary); font-weight: 600; }

@media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-card .card-body { padding: 16px; }
    .btn { padding: 9px 16px; font-size: 0.75rem; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Consultation
                <span class="branch-tag" style="background:linear-gradient(135deg,#FCD34D,#F59E0B);color:#78350F;font-weight:800;">
                    <i class="fas fa-crown"></i> ADMIN
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($visit['visit_date'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_consultation.php?id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="other_services.php?tab=consultations&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ERRORS -->
    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <?php foreach ($errors as $err): ?>
                    <div>• <?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="update_consultation">
        
        <div class="form-grid">
            
            <!-- LEFT: FORM -->
            <div>
                
                <!-- PATIENT INFO BAR -->
                <div class="patient-info-bar">
                    <div class="avatar"><?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?></div>
                    <div class="info">
                        <div class="name"><?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?></div>
                        <div class="meta">
                            <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></span>
                            <?php if (!empty($visit['patient_phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($visit['patient_phone']) ?></span>
                            <?php endif; ?>
                            <?php if ($patient_age !== 'N/A'): ?>
                                <span><i class="fas fa-birthday-cake"></i> <?= $patient_age ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- VISIT INFO -->
                <div class="form-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-calendar-check"></i> Visit Information</span>
                    </div>
                    <div class="card-body">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-user-md"></i> Doctor</label>
                                <select name="doctor_id" class="form-control">
                                    <option value="0">— Not Assigned —</option>
                                    <?php foreach ($doctors_list as $doc): ?>
                                        <option value="<?= $doc['id'] ?>" 
                                                <?= ($visit['doctor_id'] == $doc['id']) ? 'selected' : '' ?>>
                                            Dr. <?= htmlspecialchars($doc['full_name']) ?>
                                            <?= !empty($doc['specialty']) ? ' (' . htmlspecialchars($doc['specialty']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Visit Type</label>
                                <input type="text" name="visit_type" class="form-control"
                                       value="<?= htmlspecialchars($visit['visit_type'] ?? '') ?>"
                                       placeholder="e.g. New Patient, Follow-up">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-money-bill-wave"></i> Consultation Fee (<?= $currency ?>) <span class="req">*</span></label>
                                <input type="number" name="consultation_fee" class="form-control mono"
                                       value="<?= (float)($visit['consultation_fee'] ?? 0) ?>"
                                       min="0" step="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-info-circle"></i> Status</label>
                                <select name="status" class="form-control">
                                    <option value="pending" <?= ($visit['status'] ?? '') === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                    <option value="assigned" <?= ($visit['status'] ?? '') === 'assigned' ? 'selected' : '' ?>>👨‍⚕️ Assigned</option>
                                    <option value="with_doctor" <?= ($visit['status'] ?? '') === 'with_doctor' ? 'selected' : '' ?>>🩺 With Doctor</option>
                                    <option value="lab_test" <?= ($visit['status'] ?? '') === 'lab_test' ? 'selected' : '' ?>>🧪 Lab Test</option>
                                    <option value="lab_completed" <?= ($visit['status'] ?? '') === 'lab_completed' ? 'selected' : '' ?>>✅ Lab Completed</option>
                                    <option value="prescribed" <?= ($visit['status'] ?? '') === 'prescribed' ? 'selected' : '' ?>>💊 Prescribed</option>
                                    <option value="waiting" <?= ($visit['status'] ?? '') === 'waiting' ? 'selected' : '' ?>>⏳ Waiting</option>
                                    <option value="completed" <?= ($visit['status'] ?? '') === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                                    <option value="cancelled" <?= ($visit['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-credit-card"></i> Payment Status</label>
                                <select name="payment_status" class="form-control">
                                    <option value="pending" <?= ($visit['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                    <option value="partial" <?= ($visit['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>⚡ Partial</option>
                                    <option value="paid" <?= ($visit['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                                    <option value="cancelled" <?= ($visit['payment_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calendar-plus"></i> Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="form-control mono"
                                       value="<?= htmlspecialchars($visit['follow_up_date'] ?? '') ?>">
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- CLINICAL NOTES -->
                <div class="form-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-notes-medical"></i> Clinical Notes</span>
                    </div>
                    <div class="card-body">
                        
                        <div class="form-group">
                            <label><i class="fas fa-comment-medical"></i> Symptoms / Chief Complaint</label>
                            <textarea name="symptoms" class="form-control" 
                                      placeholder="Enter patient symptoms..."><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-history"></i> History of Present Illness (HPI)</label>
                            <textarea name="hpi" class="form-control" 
                                      placeholder="Enter HPI..."><?= htmlspecialchars($visit['hpi'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-stethoscope"></i> Physical Examination</label>
                            <textarea name="physical_exam" class="form-control" 
                                      placeholder="Enter physical examination findings..."><?= htmlspecialchars($visit['physical_exam'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-diagnoses"></i> Diagnosis</label>
                                <input type="text" name="diagnosis" class="form-control"
                                       value="<?= htmlspecialchars($visit['diagnosis'] ?? '') ?>"
                                       placeholder="Enter diagnosis...">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-virus"></i> Disease (Catalog)</label>
                                <select name="disease_id" class="form-control">
                                    <option value="0">— Select Disease —</option>
                                    <?php foreach ($diseases_list as $dis): ?>
                                        <option value="<?= $dis['id'] ?>" 
                                                <?= ($visit['disease_id'] == $dis['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dis['disease_name']) ?>
                                            <?= !empty($dis['disease_code']) ? ' (' . htmlspecialchars($dis['disease_code']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-prescription"></i> Treatment</label>
                            <textarea name="treatment" class="form-control" 
                                      placeholder="Enter treatment plan..."><?= htmlspecialchars($visit['treatment'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Notes</label>
                            <textarea name="notes" class="form-control" 
                                      placeholder="Additional notes..."><?= htmlspecialchars($visit['notes'] ?? '') ?></textarea>
                        </div>
                        
                    </div>
                </div>
                
            </div>
            
            <!-- RIGHT: INFO SUMMARY -->
            <div>
                
                <!-- VISIT SUMMARY -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Visit Summary
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="label"><i class="fas fa-hashtag"></i> Visit Number</span>
                            <span class="value mono" style="color:var(--primary);">
                                <?= htmlspecialchars($visit['visit_number']) ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-calendar"></i> Visit Date</span>
                            <span class="value mono">
                                <?= date('d M Y, H:i', strtotime($visit['visit_date'])) ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-user-md"></i> Doctor</span>
                            <span class="value">
                                Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-user-tie"></i> Receptionist</span>
                            <span class="value">
                                <?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                            <span class="value">
                                <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- CURRENT STATUS -->
                <div class="info-card">
                    <div class="card-header" style="background:linear-gradient(135deg,#0891B2,#0E7490);">
                        <i class="fas fa-chart-line"></i> Current Status
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="label"><i class="fas fa-info-circle"></i> Visit Status</span>
                            <span class="value">
                                <span style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:var(--warning);">
                                    <?= htmlspecialchars($visit['status'] ?? 'N/A') ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-credit-card"></i> Payment</span>
                            <span class="value">
                                <span style="font-size:0.7rem;font-weight:700;text-transform:uppercase;color:var(--warning);">
                                    <?= htmlspecialchars($visit['payment_status'] ?? 'N/A') ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><i class="fas fa-money-bill-wave"></i> Current Fee</span>
                            <span class="value mono" style="color:var(--primary);font-size:0.9rem;">
                                <?= $currency ?> <?= number_format((float)($visit['consultation_fee'] ?? 0), 0) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- WARNING -->
                <div style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);border:2px solid #F59E0B;border-radius:12px;padding:14px 18px;margin-bottom:16px;">
                    <div style="font-size:0.75rem;font-weight:800;color:#78350F;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-exclamation-triangle"></i> Important
                    </div>
                    <div style="font-size:0.72rem;color:#78350F;line-height:1.6;">
                        Kubadilisha <strong>Consultation Fee</strong> kuta-update bill automatically.
                        Kama bill ilikuwa paid, itakuwa recalculated kwa balance mpya.
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- ACTION BAR -->
        <div class="action-bar">
            <a href="view_consultation.php?id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" 
               class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-success" id="submitBtn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
        
    </form>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Edit Consultation <?= htmlspecialchars($visit['visit_number']) ?>
            <span style="margin:0 8px;">|</span>
            <span><?= date('d M Y, H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
document.getElementById('editForm').addEventListener('submit', function(e) {
    if (!confirm('Save changes to this consultation?\n\nBill itarekebishwa automatically kama fee imebadilika.')) {
        e.preventDefault();
        return false;
    }
    
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

console.log('%c✏️ Edit Consultation', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Visit: <?= htmlspecialchars($visit['visit_number']) ?>', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c✅ Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px;color:#34D399;');
console.log('%c💰 Current Fee: <?= $currency ?> <?= number_format((float)($visit['consultation_fee'] ?? 0), 0) ?>', 'font-size:13px;color:#0B5ED7;font-weight:bold;');
</script>

</body>
</html>