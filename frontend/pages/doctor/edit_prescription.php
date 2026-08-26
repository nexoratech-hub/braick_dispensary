<?php
// ================================================================
// FILE: frontend/pages/doctor/edit_prescription.php
// DOCTOR - EDIT PRESCRIPTION
// BRAICK DISPENSARY - USING YOUR DATABASE
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
// GET DOCTOR INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Doctor';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_role = $_SESSION['role'];
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: view_prescriptions.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PRESCRIPTION DETAILS - USING YOUR DATABASE STRUCTURE
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            v.visit_number,
            v.visit_date
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN visits v ON pr.visit_id = v.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$prescription_id]);
} else {
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            v.visit_number,
            v.visit_date
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN visits v ON pr.visit_id = v.id
        WHERE pr.id = ? AND pr.doctor_id = ?
    ");
    $stmt->execute([$prescription_id, $user_id]);
}
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: view_prescriptions.php?error=not_found');
    exit;
}

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM prescription_items 
    WHERE prescription_id = ?
    ORDER BY id ASC
");
$stmt->execute([$prescription_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET MEDICATIONS FROM YOUR INVENTORY (medications_inventory)
// ================================================================
$medications_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, selling_price, quantity,
               batch_number, expiry_date
        FROM medications_inventory 
        WHERE status = 'active' 
        AND branch_id = ?
        AND quantity > 0
        ORDER BY medication_name ASC
    ");
    $stmt->execute([$user_branch_id]);
    $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medications_list = [];
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prescription'])) {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    
    // Check if prescription can be edited
    if ($prescription['status'] === 'dispensed' && !$is_admin) {
        $message = '❌ Cannot edit a dispensed prescription!';
        $message_type = 'error';
    } else {
        try {
            // Update prescription
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET diagnosis = ?, notes = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND status != 'dispensed'
            ");
            $stmt->execute([$diagnosis, $notes, $status, $prescription_id]);
            
            // Update items if exist
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item_id => $item_data) {
                    $med_name = trim($item_data['medication_name'] ?? '');
                    $dosage = trim($item_data['dosage'] ?? '');
                    $frequency = trim($item_data['frequency'] ?? '');
                    $quantity = (int)($item_data['quantity'] ?? 0);
                    $duration = trim($item_data['duration'] ?? '');
                    $instructions = trim($item_data['instructions'] ?? '');
                    $unit_price = (float)($item_data['unit_price'] ?? 0);
                    $total_price = $unit_price * $quantity;
                    
                    // Check if item exists
                    $stmt_check = $db->prepare("SELECT id FROM prescription_items WHERE id = ? AND prescription_id = ?");
                    $stmt_check->execute([$item_id, $prescription_id]);
                    if ($stmt_check->fetch()) {
                        $stmt = $db->prepare("
                            UPDATE prescription_items 
                            SET medication_name = ?, dosage = ?, frequency = ?, 
                                quantity = ?, duration = ?, instructions = ?,
                                unit_price = ?, total_price = ?
                            WHERE id = ? AND prescription_id = ?
                        ");
                        $stmt->execute([
                            $med_name, $dosage, $frequency,
                            $quantity, $duration, $instructions,
                            $unit_price, $total_price,
                            $item_id, $prescription_id
                        ]);
                    }
                }
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'prescription_updated', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $user_branch_id,
                    "Prescription #" . $prescription['prescription_number'] . " updated" . 
                    ($is_admin ? " (Admin)" : "") . 
                    " | Patient: " . $prescription['patient_name']
                ]);
            } catch (Exception $e) {}
            
            $message = '✅ Prescription updated successfully!';
            $message_type = 'success';
            
            // Refresh data
            $stmt = $db->prepare("SELECT * FROM prescriptions WHERE id = ?");
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
            $stmt->execute([$prescription_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Redirect after success
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "view_prescription.php?id=' . $prescription_id . '&updated=1"; 
                }, 1500);
            </script>';
            
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
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
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           STYLES
           ================================================================ */
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
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            transition: background 0.3s ease, color 0.3s ease;
        }
        [data-theme="dark"] body {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            color: white;
            position: relative;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.6), rgba(255,255,255,0.3));
            border-radius: 0 0 4px 4px;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
            color: white;
        }
        .page-title i { color: rgba(255,255,255,0.8); }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9);
        }
        .page-subtitle strong { color: white; font-weight: 700; }
        .page-subtitle .tag {
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            color: white;
        }
        
        /* Edit Card */
        .edit-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 56rem;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .edit-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        .edit-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        /* Info Bar */
        .info-bar {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid rgba(11, 94, 215, 0.15);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        [data-theme="dark"] .info-bar {
            background: #1E3A5F;
            border-color: #1E3A5F;
        }
        .info-bar .info-item .label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            display: block;
        }
        .info-bar .info-item .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            display: block;
            margin-top: 2px;
        }
        [data-theme="dark"] .info-bar .info-item .value {
            color: var(--gray-100);
        }
        
        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        [data-theme="dark"] .form-label {
            color: var(--gray-300);
        }
        .form-label i { margin-right: 4px; }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        [data-theme="dark"] .form-control {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        [data-theme="dark"] .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.12);
        }
        .form-control:disabled,
        .form-control[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
            background: var(--gray-100);
        }
        [data-theme="dark"] .form-control:disabled,
        [data-theme="dark"] .form-control[readonly] {
            background: var(--gray-600);
        }
        textarea.form-control { resize: vertical; min-height: 80px; }
        select.form-control { appearance: auto; cursor: pointer; }
        
        /* Item Row */
        .item-row {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
        }
        [data-theme="dark"] .item-row {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        .item-row .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .item-row .item-header .item-number {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .item-row .item-header .item-total {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--success);
        }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 14px;
            border-radius: 20px;
            border: 1px solid transparent;
        }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border-color: #FDE68A; }
        .badge-success { background: var(--success-bg); color: var(--success); border-color: #A7F3D0; }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border-color: #FCA5A5; }
        .badge-info { background: var(--primary-bg); color: var(--primary); border-color: #BFDBFE; }
        
        [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; border-color: #78350F; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #065F46; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #7F1D1D; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #1A7FE8);
            color: white;
            box-shadow: 0 2px 12px rgba(11,94,215,0.25);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(11,94,215,0.35); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
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
        [data-theme="dark"] .btn-outline:hover {
            background: var(--gray-700);
        }
        .btn-sm { padding: 6px 16px; font-size: 0.75rem; border-radius: 8px; }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        .form-actions .btn { flex: 1; justify-content: center; }
        
        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            max-width: 56rem;
            margin-left: auto;
            margin-right: auto;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        
        /* Footer */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        [data-theme="dark"] .footer { border-color: var(--gray-700); }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .text-xs { font-size: 0.75rem; }
        .text-sm { font-size: 0.85rem; }
        .text-center { text-align: center; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .edit-card { padding: 16px; }
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr; }
            .grid-4 { grid-template-columns: 1fr 1fr; }
            .info-bar { grid-template-columns: 1fr 1fr; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { flex: none; width: 100%; }
            .page-title { font-size: 1.2rem; }
            .item-row { padding: 12px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .edit-card { padding: 10px; }
            .info-bar { grid-template-columns: 1fr; }
            .grid-4 { grid-template-columns: 1fr; }
            .page-header { padding: 16px; }
            .page-title { font-size: 1rem; }
            .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Prescription
                <span class="page-badge">#<?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
                <?php if ($is_admin): ?>
                    <span class="page-badge" style="background:rgba(220,38,38,0.3);border-color:rgba(220,38,38,0.3);">👑 Admin</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></strong>
                <span class="tag"><?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></span>
                <span class="tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="tag">
                    Status: <span class="status-badge <?= $prescription['status'] === 'dispensed' ? 'badge-success' : ($prescription['status'] === 'cancelled' ? 'badge-danger' : 'badge-warning') ?>">
                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_prescription.php?id=<?= $prescription_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="edit-card">
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="update_prescription" value="1">
            
            <!-- Info Bar -->
            <div class="info-bar">
                <div class="info-item">
                    <span class="label">Patient</span>
                    <span class="value"><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Visit Number</span>
                    <span class="value font-mono"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Doctor</span>
                    <span class="value">Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Date</span>
                    <span class="value"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></span>
                </div>
            </div>
            
            <!-- Diagnosis -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-stethoscope" style="color:#7C3AED;"></i> Diagnosis
                </label>
                <textarea name="diagnosis" class="form-control" rows="2" 
                          placeholder="Enter diagnosis..."
                          <?= ($prescription['status'] === 'dispensed' && !$is_admin) ? 'readonly' : '' ?>><?= htmlspecialchars($prescription['diagnosis'] ?? '') ?></textarea>
            </div>
            
            <!-- Notes -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-info-circle" style="color:#0B5ED7;"></i> Notes / Instructions
                </label>
                <textarea name="notes" class="form-control" rows="2" 
                          placeholder="Additional notes..."
                          <?= ($prescription['status'] === 'dispensed' && !$is_admin) ? 'readonly' : '' ?>><?= htmlspecialchars($prescription['notes'] ?? '') ?></textarea>
            </div>
            
            <!-- Status -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-circle" style="color:#D97706;"></i> Status
                </label>
                <select name="status" class="form-control" <?= ($prescription['status'] === 'dispensed' && !$is_admin) ? 'disabled' : '' ?>>
                    <option value="pending" <?= $prescription['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <?php if ($is_admin || $prescription['status'] !== 'dispensed'): ?>
                        <option value="dispensed" <?= $prescription['status'] === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                    <?php endif; ?>
                    <option value="cancelled" <?= $prescription['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <?php if ($prescription['status'] === 'dispensed' && !$is_admin): ?>
                    <p class="text-xs" style="color:#DC2626;margin-top:4px;">
                        <i class="fas fa-info-circle"></i> Cannot change status - already dispensed
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Medication Items -->
            <div class="form-group mt-4">
                <label class="form-label">
                    <i class="fas fa-pills" style="color:#059669;"></i> Medication Items
                    <span class="text-xs text-gray-400">(<?= count($items) ?> items)</span>
                </label>
                
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $index => $item): ?>
                        <div class="item-row">
                            <div class="item-header">
                                <span class="item-number">Item #<?= $index + 1 ?></span>
                                <?php if (isset($item['unit_price']) && isset($item['quantity'])): ?>
                                    <span class="item-total">
                                        TSh <?= number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0), 0) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="items[<?= $item['id'] ?>][id]" value="<?= $item['id'] ?>">
                            
                            <div class="grid-2">
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="text-xs text-gray-400">Medication</label>
                                    <?php if ($prescription['status'] === 'dispensed' && !$is_admin): ?>
                                        <input type="text" name="items[<?= $item['id'] ?>][medication_name]" 
                                               class="form-control" value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" readonly>
                                    <?php else: ?>
                                        <input type="text" name="items[<?= $item['id'] ?>][medication_name]" 
                                               class="form-control" value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>"
                                               placeholder="Medication name">
                                    <?php endif; ?>
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="text-xs text-gray-400">Dosage</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][dosage]" 
                                           class="form-control" value="<?= htmlspecialchars($item['dosage'] ?? '') ?>"
                                           placeholder="e.g. 500mg">
                                </div>
                            </div>
                            <div class="grid-3">
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="text-xs text-gray-400">Frequency</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][frequency]" 
                                           class="form-control" value="<?= htmlspecialchars($item['frequency'] ?? '') ?>"
                                           placeholder="e.g. Twice Daily">
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="text-xs text-gray-400">Quantity</label>
                                    <input type="number" name="items[<?= $item['id'] ?>][quantity]" 
                                           class="form-control" value="<?= $item['quantity'] ?? '' ?>" min="0">
                                </div>
                                <div class="form-group" style="margin-bottom:8px;">
                                    <label class="text-xs text-gray-400">Duration</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][duration]" 
                                           class="form-control" value="<?= htmlspecialchars($item['duration'] ?? '') ?>"
                                           placeholder="e.g. 7 days">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="text-xs text-gray-400">Instructions</label>
                                <input type="text" name="items[<?= $item['id'] ?>][instructions]" 
                                       class="form-control" value="<?= htmlspecialchars($item['instructions'] ?? '') ?>"
                                       placeholder="e.g. Take after meals">
                            </div>
                            <?php if (isset($item['unit_price']) && $item['unit_price'] > 0): ?>
                                <div class="mt-2 text-xs text-gray-400">
                                    Unit Price: TSh <?= number_format($item['unit_price'] ?? 0, 0) ?>
                                    <?php if (isset($item['total_price'])): ?>
                                        | Total: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-pills text-2xl block mb-2"></i>
                        <p>No medication items found</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= ($prescription['status'] === 'dispensed' && !$is_admin) ? 'disabled' : '' ?>>
                    <i class="fas fa-save"></i> Update Prescription
                </button>
                <a href="view_prescription.php?id=<?= $prescription_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <?php if ($is_admin): ?>
                <div class="mt-3 text-xs text-center text-gray-400">
                    <i class="fas fa-user-shield"></i> You are editing as ADMIN. You can modify any prescription.
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">🏥 Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Prescription
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

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

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : '❌ Error' ?>', 
                '<?= addslashes(strip_tags($message)) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c📝 Edit Prescription - <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= $user_name ?> | Role: <?= $user_role ?>', 'font-size:12px; color:#64748B;');
    console.log('%c💊 Items: <?= count($items) ?>', 'font-size:12px; color:#059669;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Can Edit Any Prescription', 'font-size:12px; color:#DC2626;');
    <?php endif; ?>
</script>

</body>
</html>