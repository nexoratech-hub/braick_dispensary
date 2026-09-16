<?php
// ================================================================
// FILE: frontend/pages/admin/export_sick_sheet_pdf.php
// EXPORT SICK SHEET TO PDF - WITH ALL INFORMATION
// BRAICK DISPENSARY - BLUE THEME
// ✅ External & Registered sick sheets
// ✅ Vital Signs (7 Signs)
// ✅ Clinical Information
// ✅ Sick Leave Details
// ✅ Issued By (Doctor Info)
// ✅ Official Stamp & Admin Contacts
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

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$ss_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ss_type = isset($_GET['type']) ? $_GET['type'] : 'external';
$branch_param = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($ss_id <= 0) {
    die('Invalid sick sheet ID');
}

// ================================================================
// GET ADMIN CONTACTS
// ================================================================
$admin_phones = [];
try {
    $stmt = $db->prepare("
        SELECT phone FROM users 
        WHERE role = 'admin' AND branch_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute([$user_branch_id]);
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $admin_phones = [];
}

$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

$admin_phones_display = !empty($admin_phones) 
    ? implode(' | ', array_filter($admin_phones)) 
    : ($branch_phone ?: '+255 700 000 001');

// ================================================================
// FETCH SICK SHEET DATA
// ================================================================
$sick_sheet = null;
$vital_signs = null;

try {
    if ($ss_type === 'external') {
        // EXTERNAL
        $stmt = $db->prepare("
            SELECT 
                ess.*,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                u.phone as doctor_phone,
                b.name as branch_name,
                b.location as branch_location
            FROM external_sick_sheets ess
            LEFT JOIN users u ON ess.doctor_id = u.id
            LEFT JOIN branches b ON ess.branch_id = b.id
            WHERE ess.id = ?
        ");
        $stmt->execute([$ss_id]);
        $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sick_sheet) {
            $sick_sheet['source'] = 'external';
            $sick_sheet['is_registered'] = false;
            
            // Fetch vital signs for external (if exists)
            try {
                $vs_stmt = $db->prepare("
                    SELECT vs.*, u.full_name as recorded_by_name
                    FROM vital_signs vs
                    LEFT JOIN users u ON vs.recorded_by = u.id
                    WHERE vs.external_sick_sheet_id = ?
                    ORDER BY vs.recorded_at DESC
                    LIMIT 1
                ");
                $vs_stmt->execute([$ss_id]);
                $vital_signs = $vs_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $vital_signs = null;
            }
        }
    } else {
        // REGISTERED
        $stmt = $db->prepare("
            SELECT 
                pd.*,
                p.full_name,
                p.patient_id,
                p.phone,
                p.gender,
                p.date_of_birth,
                p.address,
                p.email,
                p.blood_group,
                p.allergies,
                pd.sick_sheet_diagnosis as diagnosis,
                pd.sick_sheet_days as sick_days,
                pd.sick_sheet_from_date as sick_from,
                pd.sick_sheet_to_date as sick_to,
                pd.sick_sheet_recommendations as recommendations,
                pd.sick_sheet_restrictions as restrictions,
                pd.upload_date as created_at,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                u.phone as doctor_phone,
                b.name as branch_name,
                b.location as branch_location
            FROM patient_documents pd
            LEFT JOIN patients p ON pd.patient_id = p.id
            LEFT JOIN users u ON pd.doctor_id = u.id
            LEFT JOIN branches b ON pd.branch_id = b.id
            WHERE pd.id = ? AND pd.document_type = 'sick_sheet'
        ");
        $stmt->execute([$ss_id]);
        $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sick_sheet) {
            $sick_sheet['source'] = 'registered';
            $sick_sheet['is_registered'] = true;
            
            // Fetch latest vital signs for this patient
            try {
                $vs_stmt = $db->prepare("
                    SELECT vs.*, u.full_name as recorded_by_name
                    FROM vital_signs vs
                    LEFT JOIN users u ON vs.recorded_by = u.id
                    WHERE vs.patient_id = ?
                    ORDER BY vs.recorded_at DESC
                    LIMIT 1
                ");
                $vs_stmt->execute([$sick_sheet['patient_id']]);
                $vital_signs = $vs_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $vital_signs = null;
            }
        }
    }
} catch (Exception $e) {
    die("Error fetching sick sheet: " . $e->getMessage());
}

if (!$sick_sheet) {
    die('Sick sheet not found');
}

// ================================================================
// CALCULATE AGE
// ================================================================
$age = 'N/A';
if (!empty($sick_sheet['date_of_birth'])) {
    try {
        $birthDate = new DateTime($sick_sheet['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    } catch (Exception $e) {}
}

// ================================================================
// SpO2 STATUS
// ================================================================
$spo2_value = !empty($vital_signs['oxygen_saturation']) ? (int)$vital_signs['oxygen_saturation'] : null;
$spo2_status = 'N/A';
$spo2_class = 'normal';
if ($spo2_value !== null) {
    if ($spo2_value < 90) {
        $spo2_status = 'CRITICAL';
        $spo2_class = 'critical';
    } elseif ($spo2_value < 95) {
        $spo2_status = 'LOW';
        $spo2_class = 'low';
    } else {
        $spo2_status = 'NORMAL';
        $spo2_class = 'normal';
    }
}

// ================================================================
// LOGO
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><rect width="60" height="60" rx="12" fill="#0B5ED7"/><text x="30" y="38" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="Arial">B</text></svg>');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sick Sheet - <?= htmlspecialchars($sick_sheet['full_name'] ?? 'Patient') ?></title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           BASE
           ================================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', 'Inter', Arial, sans-serif;
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            padding: 20px;
            color: #1E293B;
            line-height: 1.5;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.15);
            overflow: hidden;
        }

        /* ================================================================
           PRINT CONTROLS
           ================================================================ */
        .print-controls {
            background: #F8FAFC;
            padding: 16px 24px;
            text-align: center;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .print-btn {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }

        .print-btn:hover {
            background: linear-gradient(135deg, #0A4CA8, #083D8A);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(11, 94, 215, 0.35);
            color: white;
        }

        .print-btn.secondary {
            background: #64748B;
            box-shadow: 0 2px 8px rgba(100, 116, 139, 0.25);
        }

        .print-btn.secondary:hover {
            background: #475569;
        }

        .pdf-note {
            text-align: center;
            font-size: 11px;
            color: #94A3B8;
            padding: 8px 20px;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
        }

        /* ================================================================
           REPORT HEADER
           ================================================================ */
        .report-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
            color: white;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .report-header .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .report-header .logo-container {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.25);
        }

        .report-header .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
        }

        .report-header .brand-text h1 {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            color: white;
            letter-spacing: 0.5px;
        }

        .report-header .brand-text p {
            font-size: 12px;
            opacity: 0.9;
            margin: 2px 0 0 0;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }

        .report-header .meta {
            text-align: right;
            font-size: 11px;
            position: relative;
            z-index: 1;
        }

        .report-header .meta .doc-title {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .report-header .meta .badge-print {
            background: rgba(255,255,255,0.2);
            padding: 4px 14px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            margin-top: 6px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Admin Contact Line */
        .admin-contact-line {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            font-size: 10px;
            color: rgba(255,255,255,0.8);
            padding: 10px 20px;
            background: rgba(0,0,0,0.15);
            position: relative;
            z-index: 1;
        }

        .admin-contact-line span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .admin-contact-line i {
            color: rgba(255,255,255,0.7);
        }

        /* ================================================================
           ALERT BAR - EXTERNAL PATIENT
           ================================================================ */
        .alert-bar {
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .alert-bar.external {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            color: #92400E;
            border-bottom: 2px solid #FCD34D;
        }

        .alert-bar.registered {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #065F46;
            border-bottom: 2px solid #6EE7B7;
        }

        .alert-bar i {
            font-size: 16px;
            flex-shrink: 0;
        }

        /* ================================================================
           CONTENT WRAPPER
           ================================================================ */
        .content {
            padding: 28px 32px;
        }

        /* ================================================================
           SECTION TITLES
           ================================================================ */
        .section-title {
            background: linear-gradient(90deg, #EFF6FF, #F8FAFC);
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
            border-left: 5px solid #0B5ED7;
            margin: 22px 0 14px 0;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1E293B;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .section-title i {
            color: #0B5ED7;
            font-size: 14px;
            width: 24px;
            height: 24px;
            background: #DBEAFE;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ================================================================
           INFO GRID
           ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
            padding: 4px 0;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            font-size: 12px;
            border-bottom: 1px dashed #E2E8F0;
            align-items: flex-start;
        }

        .info-row.full-width {
            grid-column: 1 / -1;
        }

        .info-row .label {
            font-weight: 700;
            color: #64748B;
            min-width: 130px;
            flex-shrink: 0;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.4px;
            padding-top: 2px;
        }

        .info-row .value {
            font-weight: 600;
            color: #1E293B;
            word-break: break-word;
            flex: 1;
        }

        .info-row .value.mono {
            font-family: 'Courier New', monospace;
            color: #0B5ED7;
            font-weight: 700;
        }

        /* ================================================================
           VITAL SIGNS GRID
           ================================================================ */
        .vital-signs-box {
            background: linear-gradient(135deg, #F0FDF4, #DCFCE7);
            border: 2px solid #86EFAC;
            border-radius: 12px;
            padding: 16px 20px;
            margin: 14px 0;
            position: relative;
        }

        .vital-signs-box .vital-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px dashed #86EFAC;
            flex-wrap: wrap;
            gap: 8px;
        }

        .vital-signs-box .vital-header .title {
            font-size: 12px;
            font-weight: 800;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vital-signs-box .vital-header .title i {
            font-size: 14px;
        }

        .vital-signs-box .vital-header .meta {
            font-size: 10px;
            color: #64748B;
            font-weight: 600;
        }

        .vital-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .vital-item {
            background: #FFFFFF;
            border: 2px solid #D1FAE5;
            border-radius: 10px;
            padding: 12px 10px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .vital-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #10B981;
        }

        .vital-item.spo2-normal::before { background: #10B981; }
        .vital-item.spo2-low::before { background: #F59E0B; }
        .vital-item.spo2-critical::before { background: #DC2626; }

        .vital-item .icon {
            font-size: 20px;
            margin-bottom: 4px;
            display: block;
        }

        .vital-item .label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.4px;
            display: block;
            margin-bottom: 4px;
        }

        .vital-item .value {
            font-size: 16px;
            font-weight: 800;
            color: #1E293B;
            display: block;
            line-height: 1.2;
        }

        .vital-item .value .unit {
            font-size: 10px;
            color: #64748B;
            font-weight: 500;
            margin-left: 2px;
        }

        .vital-item.spo2-normal .value { color: #059669; }
        .vital-item.spo2-low .value { color: #D97706; }
        .vital-item.spo2-critical .value { color: #DC2626; }

        .vital-item .status-badge {
            display: inline-block;
            font-size: 8px;
            font-weight: 800;
            padding: 1px 8px;
            border-radius: 10px;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .vital-item.spo2-normal .status-badge { background: #D1FAE5; color: #059669; }
        .vital-item.spo2-low .status-badge { background: #FEF3C7; color: #D97706; }
        .vital-item.spo2-critical .status-badge { background: #FEE2E2; color: #DC2626; }

        .vital-note {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed #86EFAC;
            font-size: 10px;
            color: #059669;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-weight: 600;
        }

        /* ================================================================
           CONTENT BOXES
           ================================================================ */
        .content-box {
            padding: 14px 18px;
            margin: 10px 0;
            border-radius: 8px;
            font-size: 13px;
            border-left: 5px solid;
        }

        .content-box .box-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .content-box .box-content {
            font-size: 14px;
            line-height: 1.6;
            font-weight: 600;
        }

        .content-box.diagnosis {
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            border-left-color: #0B5ED7;
        }
        .content-box.diagnosis .box-label { color: #0B5ED7; }
        .content-box.diagnosis .box-content { color: #0A4CA8; font-size: 16px; }

        .content-box.symptoms {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border-left-color: #D97706;
        }
        .content-box.symptoms .box-label { color: #D97706; }
        .content-box.symptoms .box-content { color: #92400E; }

        .content-box.treatment {
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
            border-left-color: #059669;
        }
        .content-box.treatment .box-label { color: #059669; }
        .content-box.treatment .box-content { color: #065F46; }

        .content-box.instructions {
            background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
            border-left-color: #0891B2;
        }
        .content-box.instructions .box-label { color: #0891B2; }
        .content-box.instructions .box-content { color: #0E7490; }

        .content-box.recommendations {
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
            border-left-color: #059669;
        }
        .content-box.recommendations .box-label { color: #059669; }

        .content-box.restrictions {
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            border-left-color: #DC2626;
        }
        .content-box.restrictions .box-label { color: #DC2626; }
        .content-box.restrictions .box-content { color: #991B1B; }

        /* ================================================================
           SICK LEAVE BOX - HIGHLIGHTED
           ================================================================ */
        .sick-leave-box {
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            border: 3px solid #0B5ED7;
            border-radius: 14px;
            padding: 20px 24px;
            margin: 14px 0;
            text-align: center;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
        }

        .sick-leave-box .sick-days-label {
            font-size: 10px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .sick-leave-box .sick-days-value {
            font-size: 48px;
            font-weight: 900;
            color: #0B5ED7;
            line-height: 1;
            margin-bottom: 2px;
        }

        .sick-leave-box .sick-days-unit {
            font-size: 15px;
            color: #0B5ED7;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .sick-leave-box .date-range {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px dashed #93C5FD;
            flex-wrap: wrap;
        }

        .sick-leave-box .date-item {
            text-align: center;
        }

        .sick-leave-box .date-item .date-label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .sick-leave-box .date-item .date-value {
            font-size: 15px;
            font-weight: 800;
            color: #1E293B;
        }

        .sick-leave-box .date-arrow {
            color: #0B5ED7;
            font-size: 20px;
            display: flex;
            align-items: center;
            padding-top: 10px;
        }

        /* ================================================================
           ISSUED BY BOX
           ================================================================ */
        .issued-by-box {
            background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            padding: 18px 22px;
            margin: 14px 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 20px;
        }

        .issued-by-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .issued-by-item .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        .issued-by-item .info .label {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .issued-by-item .info .value {
            font-size: 13px;
            font-weight: 700;
            color: #1E293B;
            margin-top: 2px;
        }

        /* ================================================================
           SIGNATURE SECTION
           ================================================================ */
        .signature-section {
            margin-top: 30px;
            padding-top: 24px;
            border-top: 3px dashed #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 24px;
        }

        .signature-box {
            text-align: center;
            min-width: 220px;
            flex: 1;
        }

        .signature-box .sig-line {
            width: 100%;
            border-bottom: 2px solid #1E293B;
            margin-bottom: 8px;
            height: 50px;
        }

        .signature-box .sig-label {
            font-size: 10px;
            color: #64748B;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .signature-box .sig-name {
            font-size: 13px;
            font-weight: 800;
            color: #0B5ED7;
            margin-top: 6px;
        }

        /* ================================================================
           OFFICIAL STAMP
           ================================================================ */
        .official-stamp {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 3px solid #0B5ED7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .official-stamp .stamp-left {
            font-size: 11px;
            color: #64748B;
            line-height: 1.8;
        }

        .official-stamp .stamp-left strong {
            color: #1E293B;
        }

        .official-stamp .stamp-box {
            text-align: center;
            padding: 14px 28px;
            border: 3px solid #0B5ED7;
            border-radius: 12px;
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            min-width: 200px;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
        }

        .official-stamp .stamp-box .stamp-title {
            font-size: 9px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 800;
        }

        .official-stamp .stamp-box .stamp-name {
            font-size: 15px;
            font-weight: 900;
            color: #0B5ED7;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .official-stamp .stamp-box .stamp-line {
            font-size: 10px;
            color: #64748B;
            margin-top: 6px;
        }

        .official-stamp .stamp-box .stamp-date {
            font-size: 9px;
            color: #94A3B8;
            margin-top: 2px;
        }

        /* ================================================================
           FOOTER
           ================================================================ */
        .report-footer {
            text-align: center;
            font-size: 10px;
            color: #94A3B8;
            padding: 20px 32px;
            background: #F8FAFC;
            border-top: 1px solid #E2E8F0;
            line-height: 1.8;
        }

        .report-footer strong {
            color: #0B5ED7;
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .content { padding: 20px 16px; }
            .report-header { padding: 20px; flex-direction: column; text-align: center; }
            .report-header .brand { flex-direction: column; }
            .report-header .meta { text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
            .info-row .label { min-width: 110px; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
            .sick-leave-box .sick-days-value { font-size: 36px; }
            .sick-leave-box .date-range { gap: 16px; }
            .issued-by-box { grid-template-columns: 1fr; }
            .signature-section { flex-direction: column; align-items: stretch; }
            .signature-box { min-width: 100%; }
            .official-stamp { flex-direction: column; text-align: center; }
            .admin-contact-line { flex-direction: column; gap: 4px; }
        }

        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .container {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .print-controls, .pdf-note, .no-print {
                display: none !important;
            }
            .report-header,
            .alert-bar,
            .vital-signs-box,
            .sick-leave-box,
            .content-box,
            .section-title,
            .official-stamp .stamp-box,
            .issued-by-box {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .content { padding: 20px !important; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- ================================================================ -->
    <!-- PRINT CONTROLS -->
    <!-- ================================================================ -->
    <div class="print-controls no-print">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-file-pdf"></i> Save as PDF / Print
        </button>
        <a href="sick_sheets.php?branch=<?= htmlspecialchars($branch_param) ?>" class="print-btn secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <button onclick="window.close()" class="print-btn secondary">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="pdf-note no-print">
        <i class="fas fa-info-circle"></i>
        Click <strong>"Save as PDF / Print"</strong> then select <strong>"Save as PDF"</strong> as destination.
    </div>

    <!-- ================================================================ -->
    <!-- REPORT HEADER -->
    <!-- ================================================================ -->
    <div class="report-header">
        <div class="brand">
            <div class="logo-container">
                <img src="<?= $logo_url ?>" 
                     alt="Braick Dispensary Logo" 
                     onerror="this.onerror=null; this.src='<?= $logo_fallback ?>'">
            </div>
            <div class="brand-text">
                <h1>BRAICK DISPENSARY</h1>
                <p>🏥 Tunajali Afya Yako</p>
            </div>
        </div>
        <div class="meta">
            <div class="doc-title">📋 MEDICAL SICK SHEET</div>
            <div>Generated: <?= date('M d, Y h:i A') ?></div>
            <span class="badge-print">
                <i class="fas fa-<?= $ss_type === 'external' ? 'user-plus' : 'user-check' ?>"></i>
                <?= $ss_type === 'external' ? 'External Patient' : 'Registered Patient' ?>
            </span>
        </div>
    </div>

    <!-- ADMIN CONTACT LINE -->
    <div class="admin-contact-line">
        <span><i class="fas fa-phone-alt"></i> Admin: <?= htmlspecialchars($admin_phones_display) ?></span>
        <span><i class="fas fa-store"></i> <?= htmlspecialchars($sick_sheet['branch_name'] ?? $user_branch_name) ?> Branch</span>
        <span><i class="fas fa-user"></i> Issued by: <?= htmlspecialchars($user_full_name) ?></span>
    </div>

    <!-- ALERT BAR -->
    <div class="alert-bar <?= $ss_type === 'external' ? 'external' : 'registered' ?>">
        <i class="fas fa-<?= $ss_type === 'external' ? 'info-circle' : 'check-circle' ?>"></i>
        <span>
            <?php if ($ss_type === 'external'): ?>
                <strong>External Patient Sick Sheet</strong> - This sick sheet is for an external patient (not registered in the system).
            <?php else: ?>
                <strong>Registered Patient Sick Sheet</strong> - This patient is registered in the system.
            <?php endif; ?>
        </span>
    </div>

    <!-- ================================================================ -->
    <!-- CONTENT -->
    <!-- ================================================================ -->
    <div class="content">

        <!-- PATIENT INFORMATION -->
        <div class="section-title">
            <i class="fas fa-user"></i> Patient Information
        </div>
        <div class="info-grid">
            <div class="info-row">
                <span class="label">Full Name</span>
                <span class="value"><?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Patient ID</span>
                <span class="value mono"><?= htmlspecialchars($sick_sheet['patient_id'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Gender</span>
                <span class="value"><?= htmlspecialchars($sick_sheet['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Date of Birth</span>
                <span class="value">
                    <?= !empty($sick_sheet['date_of_birth']) ? date('M d, Y', strtotime($sick_sheet['date_of_birth'])) : 'N/A' ?>
                    <?php if ($age !== 'N/A'): ?> (<?= $age ?> yrs)<?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($sick_sheet['phone'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($sick_sheet['blood_group'])): ?>
            <div class="info-row">
                <span class="label">Blood Group</span>
                <span class="value" style="color:#DC2626;font-weight:800;"><?= htmlspecialchars($sick_sheet['blood_group']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($sick_sheet['allergies'])): ?>
            <div class="info-row full-width">
                <span class="label">Allergies</span>
                <span class="value" style="color:#DC2626;">⚠️ <?= htmlspecialchars($sick_sheet['allergies']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($sick_sheet['address'])): ?>
            <div class="info-row full-width">
                <span class="label">Address</span>
                <span class="value"><?= htmlspecialchars($sick_sheet['address']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- VITAL SIGNS (7 SIGNS) -->
        <!-- ============================================================ -->
        <?php if ($vital_signs && (
            !empty($vital_signs['temperature']) || 
            !empty($vital_signs['blood_pressure_systolic']) || 
            !empty($vital_signs['pulse_rate']) || 
            !empty($vital_signs['weight']) || 
            !empty($vital_signs['height']) || 
            !empty($vital_signs['bmi']) ||
            $spo2_value !== null
        )): ?>
        <div class="section-title">
            <i class="fas fa-heartbeat"></i> Vital Signs (7 Signs)
        </div>

        <div class="vital-signs-box">
            <div class="vital-header">
                <div class="title">
                    <i class="fas fa-heartbeat"></i>
                    7 Vital Signs Tracked
                </div>
                <div class="meta">
                    Recorded: <?= !empty($vital_signs['recorded_at']) ? date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) : 'N/A' ?>
                </div>
            </div>

            <div class="vital-grid">
                <!-- 1. Temperature -->
                <?php if (!empty($vital_signs['temperature'])): ?>
                <div class="vital-item">
                    <span class="icon">🌡️</span>
                    <span class="label">Temperature</span>
                    <span class="value"><?= htmlspecialchars($vital_signs['temperature']) ?><span class="unit">°C</span></span>
                </div>
                <?php endif; ?>

                <!-- 2. Blood Pressure -->
                <?php if (!empty($vital_signs['blood_pressure_systolic']) && !empty($vital_signs['blood_pressure_diastolic'])): ?>
                <div class="vital-item">
                    <span class="icon">💓</span>
                    <span class="label">Blood Pressure</span>
                    <span class="value"><?= htmlspecialchars($vital_signs['blood_pressure_systolic']) ?>/<?= htmlspecialchars($vital_signs['blood_pressure_diastolic']) ?><span class="unit">mmHg</span></span>
                </div>
                <?php endif; ?>

                <!-- 3. Pulse Rate -->
                <?php if (!empty($vital_signs['pulse_rate'])): ?>
                <div class="vital-item">
                    <span class="icon">💗</span>
                    <span class="label">Pulse Rate</span>
                    <span class="value"><?= htmlspecialchars($vital_signs['pulse_rate']) ?><span class="unit">bpm</span></span>
                </div>
                <?php endif; ?>

                <!-- 4. Oxygen Saturation (SpO2) -->
                <?php if ($spo2_value !== null): ?>
                <div class="vital-item spo2-<?= $spo2_class ?>">
                    <span class="icon">🫁</span>
                    <span class="label">Oxygen (SpO2)</span>
                    <span class="value"><?= $spo2_value ?><span class="unit">%</span></span>
                    <span class="status-badge"><?= $spo2_status ?></span>
                </div>
                <?php endif; ?>

                <!-- 5. Weight -->
                <?php if (!empty($vital_signs['weight'])): ?>
                <div class="vital-item">
                    <span class="icon">⚖️</span>
                    <span class="label">Weight</span>
                    <span class="value"><?= htmlspecialchars($vital_signs['weight']) ?><span class="unit">kg</span></span>
                </div>
                <?php endif; ?>

                <!-- 6. Height -->
                <?php if (!empty($vital_signs['height'])): ?>
                <div class="vital-item">
                    <span class="icon">📏</span>
                    <span class="label">Height</span>
                    <span class="value"><?= htmlspecialchars($vital_signs['height']) ?><span class="unit">cm</span></span>
                </div>
                <?php endif; ?>

                <!-- 7. BMI -->
                <?php if (!empty($vital_signs['bmi'])): ?>
                <div class="vital-item">
                    <span class="icon">📊</span>
                    <span class="label">BMI</span>
                    <span class="value"><?= htmlspecialchars($vital_signs['bmi']) ?><span class="unit">kg/m²</span></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="vital-note">
                <span>🫁 SpO2 (Oxygen Saturation) Normal Range: 95-100%</span>
                <?php if (!empty($vital_signs['recorded_by_name'])): ?>
                <span>Recorded by: Dr. <?= htmlspecialchars($vital_signs['recorded_by_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- CLINICAL INFORMATION -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-stethoscope"></i> Clinical Information
        </div>

        <?php if (!empty($sick_sheet['symptoms'])): ?>
        <div class="content-box symptoms">
            <div class="box-label"><i class="fas fa-thermometer-half"></i> Symptoms</div>
            <div class="box-content"><?= htmlspecialchars($sick_sheet['symptoms']) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($sick_sheet['diagnosis'])): ?>
        <div class="content-box diagnosis">
            <div class="box-label"><i class="fas fa-stethoscope"></i> Diagnosis</div>
            <div class="box-content"><?= htmlspecialchars($sick_sheet['diagnosis']) ?></div>
        </div>
        <?php endif; ?>

        <div class="content-box treatment">
            <div class="box-label"><i class="fas fa-prescription-bottle-medical"></i> Treatment</div>
            <div class="box-content">
                <?= !empty($sick_sheet['treatment']) ? htmlspecialchars($sick_sheet['treatment']) : 'Not specified' ?>
            </div>
        </div>

        <div class="content-box instructions">
            <div class="box-label"><i class="fas fa-clipboard-list"></i> Instructions</div>
            <div class="box-content">
                <?= !empty($sick_sheet['instructions']) ? htmlspecialchars($sick_sheet['instructions']) : 'Not specified' ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SICK LEAVE DETAILS -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-calendar-alt"></i> Sick Leave Details
        </div>

        <div class="sick-leave-box">
            <div class="sick-days-label">Sick Days</div>
            <div class="sick-days-value"><?= (int)($sick_sheet['sick_days'] ?? 0) ?></div>
            <div class="sick-days-unit">Day<?= (int)($sick_sheet['sick_days'] ?? 0) !== 1 ? 's' : '' ?></div>
            
            <div class="date-range">
                <div class="date-item">
                    <div class="date-label">From</div>
                    <div class="date-value">
                        <?= !empty($sick_sheet['sick_from']) ? date('M d, Y', strtotime($sick_sheet['sick_from'])) : 'N/A' ?>
                    </div>
                </div>
                <div class="date-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="date-item">
                    <div class="date-label">To</div>
                    <div class="date-value">
                        <?= !empty($sick_sheet['sick_to']) ? date('M d, Y', strtotime($sick_sheet['sick_to'])) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-box treatment">
            <div class="box-label"><i class="fas fa-info-circle"></i> Reason</div>
            <div class="box-content">Medical condition requiring rest</div>
        </div>

        <?php if (!empty($sick_sheet['restrictions'])): ?>
        <div class="content-box restrictions">
            <div class="box-label"><i class="fas fa-exclamation-triangle"></i> Restrictions</div>
            <div class="box-content"><?= htmlspecialchars($sick_sheet['restrictions']) ?></div>
        </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- ISSUED BY -->
        <!-- ============================================================ -->
        <div class="section-title">
            <i class="fas fa-user-md"></i> Issued By
        </div>

        <div class="issued-by-box">
            <div class="issued-by-item">
                <div class="icon-circle">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="info">
                    <div class="label">Doctor</div>
                    <div class="value">Dr. <?= htmlspecialchars($sick_sheet['doctor_name'] ?? 'N/A') ?></div>
                </div>
            </div>
            
            <div class="issued-by-item">
                <div class="icon-circle">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="info">
                    <div class="label">Specialty</div>
                    <div class="value"><?= htmlspecialchars($sick_sheet['doctor_specialty'] ?? 'General Medicine') ?></div>
                </div>
            </div>
            
            <?php if (!empty($sick_sheet['doctor_phone'])): ?>
            <div class="issued-by-item">
                <div class="icon-circle">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="info">
                    <div class="label">Doctor Phone</div>
                    <div class="value"><?= htmlspecialchars($sick_sheet['doctor_phone']) ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="issued-by-item">
                <div class="icon-circle">
                    <i class="fas fa-store-alt"></i>
                </div>
                <div class="info">
                    <div class="label">Branch</div>
                    <div class="value"><?= htmlspecialchars($sick_sheet['branch_name'] ?? $user_branch_name) ?></div>
                </div>
            </div>
            
            <div class="issued-by-item">
                <div class="icon-circle">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="info">
                    <div class="label">Issued On</div>
                    <div class="value"><?= !empty($sick_sheet['created_at']) ? date('M d, Y h:i A', strtotime($sick_sheet['created_at'])) : 'N/A' ?></div>
                </div>
            </div>

            <?php if (!empty($sick_sheet['updated_at']) && $sick_sheet['updated_at'] != $sick_sheet['created_at']): ?>
            <div class="issued-by-item">
                <div class="icon-circle">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="info">
                    <div class="label">Last Updated</div>
                    <div class="value"><?= date('M d, Y h:i A', strtotime($sick_sheet['updated_at'])) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- SIGNATURES -->
        <!-- ============================================================ -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">Patient Signature</div>
                <div class="sig-name"><?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?></div>
            </div>
            
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">Attending Doctor</div>
                <div class="sig-name">Dr. <?= htmlspecialchars($sick_sheet['doctor_name'] ?? 'N/A') ?></div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- OFFICIAL STAMP -->
        <!-- ============================================================ -->
        <div class="official-stamp">
            <div class="stamp-left">
                <div>Generated by: <strong><?= htmlspecialchars($user_full_name) ?></strong></div>
                <div>Date: <strong><?= date('F d, Y') ?></strong></div>
                <div style="font-size:10px;color:#94A3B8;margin-top:4px;">
                    <i class="fas fa-print"></i> Printed: <?= date('h:i A') ?>
                </div>
            </div>
            <div class="stamp-box">
                <div class="stamp-title">Official Stamp</div>
                <div class="stamp-name">BRAICK DISPENSARY</div>
                <div class="stamp-line">Approved By: _________________</div>
                <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>

    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <strong>Braick Dispensary</strong> Management System
        <span style="margin:0 8px;">|</span>
        Sick Sheet Details - <?= htmlspecialchars($sick_sheet['document_number'] ?? 'SS-' . date('Ymd')) ?>
        <span style="margin:0 8px;">|</span>
        <?= date('M d, Y h:i A') ?>
        <span style="margin:0 8px;">|</span>
        &copy; <?= date('Y') ?> All rights reserved
        <br>
        <span style="font-size:9px;color:#CBD5E1;margin-top:6px;display:inline-block;">
            This document is computer-generated and valid without signature. For verification, contact Braick Dispensary.
        </span>
    </div>

</div>

<script>
    // ================================================================
    // AUTO PRINT
    // ================================================================
    if (window.location.search.includes('print=1')) {
        setTimeout(function() {
            window.print();
        }, 500);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        if (e.key === 'Escape') {
            window.close();
        }
    });

    console.log('%c📄 Braick - Sick Sheet PDF Export', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars(addslashes($sick_sheet['full_name'] ?? 'N/A')) ?>', 'font-size:13px;color:#059669;');
    console.log('%c📋 Document: <?= htmlspecialchars(addslashes($sick_sheet['document_number'] ?? 'N/A')) ?>', 'font-size:13px;color:#0B5ED7;');
    console.log('%c✅ Ctrl+P to print, Esc to close', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>