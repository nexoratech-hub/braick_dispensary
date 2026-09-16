<?php
// ================================================================
// FILE: frontend/pages/admin/view_referral.php
// SUPER ADMIN - VIEW REFERRAL DETAILS
// ✅ BLUE THEME ONLY
// ✅ Dark mode inafanya kazi kwa page YOTE
// ✅ Print PDF inafungua referral_pdf.php
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$referral_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($referral_id <= 0) {
    header('Location: referrals.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// HANDLE DELETE
// ================================================================
if (isset($_GET['delete']) && $_GET['delete'] == 1) {
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM referrals WHERE id = ?");
        $stmt->execute([$referral_id]);
        
        try {
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'referral_deleted', ?, NOW())");
            $log_stmt->execute([$user_id, $user_branch_id, "Deleted referral #$referral_id"]);
        } catch (Exception $e) {}
        
        $db->commit();
        header('Location: referrals.php?branch=' . urlencode($selected_branch_id) . '&deleted=1');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error_message = "Failed to delete: " . $e->getMessage();
    }
}

// ================================================================
// FETCH REFERRAL DETAILS
// ================================================================
$referral = null;

try {
    $sql = "
        SELECT 
            r.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            p.gender as patient_gender,
            p.date_of_birth as patient_dob,
            p.address as patient_address,
            p.blood_group as patient_blood,
            p.allergies as patient_allergies,
            u_from.full_name as from_doctor_name,
            u_from.specialty as from_doctor_specialty,
            u_from.phone as from_doctor_phone,
            u_from.email as from_doctor_email,
            u_to.full_name as to_doctor_name,
            u_to.specialty as to_doctor_specialty,
            u_to.phone as to_doctor_phone,
            u_to.email as to_doctor_email,
            v.visit_number,
            v.visit_date,
            v.diagnosis as visit_diagnosis,
            v.status as visit_status,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN visits v ON r.visit_id = v.id
        LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
        LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$referral_id]);
    $referral = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$referral) {
        header('Location: referrals.php?branch=' . urlencode($selected_branch_id) . '&error=not_found');
        exit;
    }
} catch (Exception $e) {
    die("Error fetching referral: " . $e->getMessage());
}

// ================================================================
// STATUS BADGE HELPERS — ALL BLUE
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'badge-blue-2';
        case 'referred': return 'badge-blue-5';
        case 'accepted': return 'badge-blue-1';
        case 'completed': return 'badge-blue-3';
        case 'cancelled': return 'badge-blue-6';
        default: return 'badge-blue-2';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return 'fa-clock';
        case 'referred': return 'fa-share-square';
        case 'accepted': return 'fa-check-circle';
        case 'completed': return 'fa-check-double';
        case 'cancelled': return 'fa-times-circle';
        default: return 'fa-info-circle';
    }
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

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Referral - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           BLUE THEME ONLY — DARK MODE FULL PAGE
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --border-color: #334155;
        }
        
        /* ✅ FORCE DARK MODE */
        html[data-theme="dark"],
        html[data-theme="dark"] body {
            background: #0F172A !important;
            color: #F1F5F9 !important;
        }
        
        html[data-theme="dark"] .main-content {
            background: #0F172A !important;
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
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header-custom {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header-custom::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .page-header-custom .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin: 0;
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
            gap: 6px;
        }
        
        .page-header-custom .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header-custom .btn-header {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            cursor: pointer;
            backdrop-filter: blur(4px);
        }
        
        .page-header-custom .btn-header:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .page-header-custom .btn-header.danger {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.4);
        }
        
        .page-header-custom .btn-header.danger:hover {
            background: rgba(220,38,38,0.5);
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        html[data-theme="dark"] .card {
            background: #1E293B !important;
            border-color: #334155 !important;
            color: #F1F5F9 !important;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            padding-bottom: 12px;
            margin-bottom: 18px;
            border-bottom: 2px solid var(--primary-bg);
        }
        
        .section-title i {
            width: 34px;
            height: 34px;
            background: var(--primary);
            color: white;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        /* ================================================================
           INFO GRID
           ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        
        .info-item {
            padding: 12px 16px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }
        
        html[data-theme="dark"] .info-item {
            background: #1E3A5F !important;
        }
        
        .info-item:hover {
            transform: translateX(3px);
        }
        
        .info-item .info-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }
        
        .info-item .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            display: block;
            word-break: break-word;
        }
        
        .info-item .info-value.big {
            font-size: 1rem;
            color: var(--primary);
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        /* ================================================================
           BADGES - ALL BLUE
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-blue-1 { background: #0B5ED7; color: white; }
        .badge-blue-2 { background: #1A73E8; color: white; }
        .badge-blue-3 { background: #0A4CA8; color: white; }
        .badge-blue-4 { background: #6EA8FE; color: white; }
        .badge-blue-5 { background: #1E40AF; color: white; }
        .badge-blue-6 { background: #1E3A8A; color: white; }
        
        /* ================================================================
           PATIENT CARD
           ================================================================ */
        .patient-card {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 18px 22px;
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            border-radius: var(--radius-lg);
            border: 2px solid var(--primary-light);
            margin-bottom: 20px;
        }
        
        html[data-theme="dark"] .patient-card {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF) !important;
            border-color: #3B82F6 !important;
        }
        
        .patient-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
            border: 3px solid white;
        }
        
        .patient-info h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }
        
        .patient-info p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 2px 0;
        }
        
        /* ================================================================
           REFERRAL BOX (highlighted)
           ================================================================ */
        .referral-box {
            background: var(--primary-bg);
            border: 2px dashed var(--primary);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        
        html[data-theme="dark"] .referral-box {
            background: #1E3A5F !important;
            border-color: #3B82F6 !important;
        }
        
        .referral-box .referral-number {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
        }
        
        /* ================================================================
           ACTION BUTTONS
           ================================================================ */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
            border: 2px solid transparent;
            cursor: pointer;
            min-height: 100px;
            text-align: center;
        }
        
        .action-btn i {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        .action-btn .btn-label {
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .action-btn .btn-sublabel {
            font-size: 0.62rem;
            font-weight: 400;
            opacity: 0.85;
            margin-top: 2px;
        }
        
        .action-btn-blue-1 {
            background: #E8F0FE;
            color: #0B5ED7;
            border-color: #6EA8FE;
        }
        
        .action-btn-blue-1:hover {
            background: #0B5ED7;
            color: white;
            border-color: #0B5ED7;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.3);
        }
        
        .action-btn-blue-2 {
            background: #DBEAFE;
            color: #0A4CA8;
            border-color: #93C5FD;
        }
        
        .action-btn-blue-2:hover {
            background: #0A4CA8;
            color: white;
            border-color: #0A4CA8;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(10, 76, 168, 0.3);
        }
        
        .action-btn-blue-3 {
            background: #E0F2FE;
            color: #0B3D8A;
            border-color: #7DD3FC;
        }
        
        .action-btn-blue-3:hover {
            background: #0B3D8A;
            color: white;
            border-color: #0B3D8A;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(11, 61, 138, 0.3);
        }
        
        .action-btn-danger {
            background: #FEE2E2;
            color: #DC2626;
            border-color: #FCA5A5;
        }
        
        .action-btn-danger:hover {
            background: #DC2626;
            color: white;
            border-color: #DC2626;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
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
        @media (max-width: 768px) {
            .card { padding: 16px; }
            .page-header-custom { padding: 18px 20px; }
            .page-header-custom .page-title { font-size: 1.15rem; }
            .header-actions { width: 100%; }
            .header-actions .btn-header { flex: 1; justify-content: center; }
            .info-grid { grid-template-columns: 1fr; }
            .patient-card { flex-direction: column; text-align: center; }
            .action-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .action-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-share-square"></i> Referral Details
                <span class="badge-branch">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>
                </span>
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($referral['branch_name'] ?? 'N/A') ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:6px;">
                Complete referral information and history
            </p>
        </div>
        <div class="header-actions">
            <a href="referrals.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="edit_referral.php?id=<?= $referral['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-edit"></i> Edit
            </a>
            <!-- ✅ PRINT PDF — Inafungua referral_pdf.php -->
            <a href="referral_pdf.php?id=<?= $referral['id'] ?>&branch=<?= $selected_branch_id ?>" 
               target="_blank"
               class="btn-header">
                <i class="fas fa-file-pdf"></i> Print PDF
            </a>
            <a href="?id=<?= $referral['id'] ?>&branch=<?= $selected_branch_id ?>&delete=1" 
               class="btn-header danger"
               onclick="return confirm('⚠️ Delete this referral?\n\nReferral #: <?= htmlspecialchars(addslashes($referral['referral_number'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($referral['patient_name'] ?? 'N/A')) ?>\n\nThis action cannot be undone!');">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div style="background:#FEE2E2;color:#991B1B;padding:14px 20px;border-radius:12px;margin-bottom:16px;border:2px solid #FCA5A5;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- REFERRAL BOX (highlighted) -->
    <div class="referral-box">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <div class="referral-number">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>
                </div>
                <div style="font-size:0.8rem;color:var(--text-secondary);">
                    <i class="fas fa-calendar"></i> Created: <?= date('F d, Y • h:i A', strtotime($referral['created_at'] ?? 'now')) ?>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="badge <?= getStatusBadgeClass($referral['status'] ?? 'pending') ?>">
                    <i class="fas <?= getStatusIcon($referral['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($referral['status'] ?? 'Pending') ?>
                </span>
                <span class="badge <?= ($referral['referral_type'] ?? '') === 'internal' ? 'badge-blue-1' : 'badge-blue-3' ?>">
                    <i class="fas <?= ($referral['referral_type'] ?? '') === 'internal' ? 'fa-hospital' : 'fa-globe-africa' ?>"></i>
                    <?= ucfirst($referral['referral_type'] ?? 'N/A') ?>
                </span>
                <?php if (!empty($referral['urgency'])): ?>
                    <span class="badge badge-blue-<?= $referral['urgency'] === 'emergency' ? '6' : ($referral['urgency'] === 'urgent' ? '5' : '2') ?>">
                        <i class="fas fa-bolt"></i>
                        <?= ucfirst($referral['urgency']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PATIENT CARD -->
    <?php if (!empty($referral['patient_name'])): ?>
    <div class="patient-card">
        <div class="patient-avatar">
            <?= strtoupper(substr($referral['patient_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-info" style="flex:1;">
            <h2><?= htmlspecialchars($referral['patient_name']) ?></h2>
            <p>
                <i class="fas fa-id-card"></i> <?= htmlspecialchars($referral['patient_code'] ?? 'N/A') ?>
                <?php if (!empty($referral['patient_phone'])): ?>
                    <span style="margin-left:12px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($referral['patient_phone']) ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php if (!empty($referral['patient_gender'])): ?>
                    <i class="fas fa-venus-mars"></i> <?= htmlspecialchars($referral['patient_gender']) ?>
                <?php endif; ?>
                <?php if (!empty($referral['patient_dob'])): ?>
                    <span style="margin-left:12px;"><i class="fas fa-birthday-cake"></i> <?= calculateAge($referral['patient_dob']) ?> yrs</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- REFERRAL INFORMATION -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-info-circle"></i>
            <span>Referral Information</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Referral Number</span>
                <span class="info-value big" style="font-family:monospace;">
                    <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Referral Type</span>
                <span class="info-value">
                    <i class="fas <?= ($referral['referral_type'] ?? '') === 'internal' ? 'fa-hospital' : 'fa-globe-africa' ?>"></i>
                    <?= ucfirst($referral['referral_type'] ?? 'N/A') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="badge <?= getStatusBadgeClass($referral['status'] ?? 'pending') ?>">
                        <?= ucfirst($referral['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Urgency</span>
                <span class="info-value">
                    <i class="fas fa-bolt"></i>
                    <?= ucfirst($referral['urgency'] ?? 'Routine') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Referral Date</span>
                <span class="info-value">
                    <i class="fas fa-calendar"></i>
                    <?= !empty($referral['referral_date']) ? date('F d, Y', strtotime($referral['referral_date'])) : (!empty($referral['created_at']) ? date('F d, Y', strtotime($referral['created_at'])) : 'N/A') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Branch</span>
                <span class="info-value">
                    <i class="fas fa-store-alt"></i>
                    <?= htmlspecialchars($referral['branch_name'] ?? 'N/A') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- FROM / TO INFORMATION -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-exchange-alt"></i>
            <span>Referral Path</span>
        </div>
        
        <div class="info-grid">
            <!-- FROM DOCTOR -->
            <div class="info-item full-width" style="background:linear-gradient(135deg, #DBEAFE, #BFDBFE);">
                <span class="info-label">📤 Referred FROM</span>
                <span class="info-value big">
                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? 'N/A') ?>
                </span>
                <?php if (!empty($referral['from_doctor_specialty'])): ?>
                    <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:4px;">
                        <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($referral['from_doctor_specialty']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($referral['from_doctor_phone'])): ?>
                    <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:2px;">
                        <i class="fas fa-phone"></i> <?= htmlspecialchars($referral['from_doctor_phone']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- TO DOCTOR / HOSPITAL -->
            <div class="info-item full-width" style="background:linear-gradient(135deg, #E8F0FE, #DBEAFE);">
                <span class="info-label">📥 Referred TO</span>
                <?php if (($referral['referral_type'] ?? '') === 'internal'): ?>
                    <span class="info-value big">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'N/A') ?>
                    </span>
                    <?php if (!empty($referral['to_doctor_specialty'])): ?>
                        <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:4px;">
                            <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($referral['to_doctor_specialty']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($referral['to_doctor_phone'])): ?>
                        <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:2px;">
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($referral['to_doctor_phone']) ?>
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="info-value big">
                        <i class="fas fa-hospital"></i> <?= htmlspecialchars($referral['to_hospital_name'] ?? 'N/A') ?>
                    </span>
                    <?php if (!empty($referral['to_hospital_address'])): ?>
                        <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:4px;">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($referral['to_hospital_address']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($referral['to_hospital_phone'])): ?>
                        <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:2px;">
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($referral['to_hospital_phone']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($referral['expert_type'])): ?>
                        <span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:2px;">
                            <i class="fas fa-user-tie"></i> Expert: <?= htmlspecialchars($referral['expert_type']) ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- VISIT INFORMATION -->
    <?php if (!empty($referral['visit_number'])): ?>
    <div class="card">
        <div class="section-title">
            <i class="fas fa-notes-medical"></i>
            <span>Visit Information</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Visit Number</span>
                <span class="info-value" style="font-family:monospace;">
                    <?= htmlspecialchars($referral['visit_number']) ?>
                </span>
            </div>
            
            <?php if (!empty($referral['visit_date'])): ?>
            <div class="info-item">
                <span class="info-label">Visit Date</span>
                <span class="info-value">
                    <i class="fas fa-calendar"></i>
                    <?= date('F d, Y • h:i A', strtotime($referral['visit_date'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['visit_status'])): ?>
            <div class="info-item">
                <span class="info-label">Visit Status</span>
                <span class="info-value">
                    <span class="badge badge-blue-2">
                        <?= ucfirst(str_replace('_', ' ', $referral['visit_status'])) ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['visit_diagnosis'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Visit Diagnosis</span>
                <span class="info-value"><?= htmlspecialchars($referral['visit_diagnosis']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CLINICAL INFORMATION -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-stethoscope"></i>
            <span>Clinical Information</span>
        </div>
        
        <div class="info-grid">
            <?php if (!empty($referral['reason'])): ?>
            <div class="info-item full-width">
                <span class="info-label">📋 Reason for Referral</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($referral['reason'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['diagnosis'])): ?>
            <div class="info-item full-width" style="background:linear-gradient(135deg, #DBEAFE, #BFDBFE);">
                <span class="info-label">🩺 Diagnosis</span>
                <span class="info-value big">
                    <?= htmlspecialchars($referral['diagnosis']) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['treatment_given'])): ?>
            <div class="info-item full-width">
                <span class="info-label">💊 Treatment Given</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($referral['treatment_given'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['clinical_notes'])): ?>
            <div class="info-item full-width">
                <span class="info-label">📝 Clinical Notes</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($referral['clinical_notes'])) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['notes'])): ?>
            <div class="info-item full-width">
                <span class="info-label">📌 Notes</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($referral['notes'])) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PATIENT DETAILS -->
    <?php if (!empty($referral['patient_name'])): ?>
    <div class="card">
        <div class="section-title">
            <i class="fas fa-user"></i>
            <span>Patient Details</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value big"><?= htmlspecialchars($referral['patient_name']) ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Patient ID</span>
                <span class="info-value" style="font-family:monospace;"><?= htmlspecialchars($referral['patient_code'] ?? 'N/A') ?></span>
            </div>
            
            <?php if (!empty($referral['patient_phone'])): ?>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <span class="info-value"><i class="fas fa-phone"></i> <?= htmlspecialchars($referral['patient_phone']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['patient_email'])): ?>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><i class="fas fa-envelope"></i> <?= htmlspecialchars($referral['patient_email']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['patient_gender'])): ?>
            <div class="info-item">
                <span class="info-label">Gender</span>
                <span class="info-value"><?= htmlspecialchars($referral['patient_gender']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['patient_dob'])): ?>
            <div class="info-item">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?= date('F d, Y', strtotime($referral['patient_dob'])) ?> (<?= calculateAge($referral['patient_dob']) ?> yrs)</span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['patient_blood'])): ?>
            <div class="info-item">
                <span class="info-label">Blood Group</span>
                <span class="info-value" style="color:#DC2626;font-weight:700;"><?= htmlspecialchars($referral['patient_blood']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['patient_allergies'])): ?>
            <div class="info-item full-width">
                <span class="info-label">⚠️ Allergies</span>
                <span class="info-value" style="color:#DC2626;"><?= htmlspecialchars($referral['patient_allergies']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['patient_address'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Address</span>
                <span class="info-value" style="font-weight:400;"><?= htmlspecialchars($referral['patient_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- TIMELINE -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-history"></i>
            <span>Activity Timeline</span>
        </div>
        
        <div style="position:relative;padding-left:30px;">
            <div style="position:absolute;left:8px;top:5px;bottom:5px;width:3px;background:var(--primary-bg);border-radius:3px;"></div>
            
            <!-- Created -->
            <div style="position:relative;padding:10px 0;">
                <div style="position:absolute;left:-30px;top:16px;width:19px;height:19px;background:var(--primary);border:4px solid var(--bg-card);border-radius:50%;box-shadow:0 0 0 3px var(--primary-bg);"></div>
                <p style="font-size:0.85rem;font-weight:700;color:var(--text-primary);margin:0 0 2px 0;">
                    <i class="fas fa-plus-circle" style="color:#0B5ED7;"></i>
                    Referral Created
                </p>
                <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                    <?= !empty($referral['created_at']) ? date('l, F d, Y \a\t h:i A', strtotime($referral['created_at'])) : 'N/A' ?>
                </p>
            </div>
            
            <?php if (!empty($referral['updated_at']) && $referral['updated_at'] !== $referral['created_at']): ?>
            <div style="position:relative;padding:10px 0;">
                <div style="position:absolute;left:-30px;top:16px;width:19px;height:19px;background:var(--primary);border:4px solid var(--bg-card);border-radius:50%;box-shadow:0 0 0 3px var(--primary-bg);"></div>
                <p style="font-size:0.85rem;font-weight:700;color:var(--text-primary);margin:0 0 2px 0;">
                    <i class="fas fa-edit" style="color:#1A73E8;"></i>
                    Last Updated
                </p>
                <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($referral['updated_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['accepted_at'])): ?>
            <div style="position:relative;padding:10px 0;">
                <div style="position:absolute;left:-30px;top:16px;width:19px;height:19px;background:var(--primary);border:4px solid var(--bg-card);border-radius:50%;box-shadow:0 0 0 3px var(--primary-bg);"></div>
                <p style="font-size:0.85rem;font-weight:700;color:var(--text-primary);margin:0 0 2px 0;">
                    <i class="fas fa-check-circle" style="color:#0A4CA8;"></i>
                    Referral Accepted
                </p>
                <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($referral['accepted_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['completed_at'])): ?>
            <div style="position:relative;padding:10px 0;">
                <div style="position:absolute;left:-30px;top:16px;width:19px;height:19px;background:var(--primary);border:4px solid var(--bg-card);border-radius:50%;box-shadow:0 0 0 3px var(--primary-bg);"></div>
                <p style="font-size:0.85rem;font-weight:700;color:var(--text-primary);margin:0 0 2px 0;">
                    <i class="fas fa-check-double" style="color:#0B3D8A;"></i>
                    Referral Completed
                </p>
                <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($referral['completed_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($referral['cancelled_at'])): ?>
            <div style="position:relative;padding:10px 0;">
                <div style="position:absolute;left:-30px;top:16px;width:19px;height:19px;background:#94A3B8;border:4px solid var(--bg-card);border-radius:50%;box-shadow:0 0 0 3px var(--primary-bg);"></div>
                <p style="font-size:0.85rem;font-weight:700;color:var(--text-primary);margin:0 0 2px 0;">
                    <i class="fas fa-times-circle" style="color:#64748B;"></i>
                    Referral Cancelled
                </p>
                <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($referral['cancelled_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-bolt"></i>
            <span>Quick Actions</span>
        </div>
        
        <div class="action-grid">
            <a href="edit_referral.php?id=<?= $referral['id'] ?>&branch=<?= $selected_branch_id ?>" class="action-btn action-btn-blue-1">
                <i class="fas fa-edit"></i>
                <span class="btn-label">Edit Referral</span>
                <span class="btn-sublabel">Modify details</span>
            </a>
            
            <?php if (!empty($referral['patient_id'])): ?>
            <a href="patient_details.php?id=<?= $referral['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="action-btn action-btn-blue-2">
                <i class="fas fa-user-circle"></i>
                <span class="btn-label">View Patient</span>
                <span class="btn-sublabel">Full profile</span>
            </a>
            <?php endif; ?>
            
            <!-- ✅ PRINT PDF — Inafungua referral_pdf.php -->
            <a href="referral_pdf.php?id=<?= $referral['id'] ?>&branch=<?= $selected_branch_id ?>" 
               target="_blank"
               class="action-btn action-btn-blue-3">
                <i class="fas fa-file-pdf"></i>
                <span class="btn-label">Print PDF</span>
                <span class="btn-sublabel">Referral letter</span>
            </a>
            
            <a href="?id=<?= $referral['id'] ?>&branch=<?= $selected_branch_id ?>&delete=1" 
               class="action-btn action-btn-danger"
               onclick="return confirm('⚠️ Delete this referral?\n\nThis action cannot be undone!');">
                <i class="fas fa-trash"></i>
                <span class="btn-label">Delete</span>
                <span class="btn-sublabel">Remove permanently</span>
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Referral #<?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// UPDATE FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// ================================================================
// ESC KEY TO GO BACK
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'referrals.php?branch=<?= $selected_branch_id ?>';
    }
});

console.log('%c📋 Admin - View Referral (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Dark mode inafanya kazi kwa page YOTE', 'font-size:12px;color:#34D399;');
console.log('%c✅ Blue theme everywhere', 'font-size:12px;color:#34D399;');
console.log('%c✅ Print PDF inafungua referral_pdf.php', 'font-size:12px;color:#34D399;');
console.log('%c📄 Referral: <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>', 'font-size:12px;color:#64748B;');
console.log('%c⌨️ Press ESC to go back', 'font-size:12px;color:#64748B;');
</script>

</body>
</html>