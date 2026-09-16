<?php
// ================================================================
// FILE: frontend/pages/admin/edit_prescription.php
// SUPER ADMIN - EDIT PRESCRIPTION
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Shows patient name, doctors & medications by branch
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

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
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
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

if ($prescription_id <= 0) {
    header('Location: prescriptions.php?branch=' . $branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// GET PRESCRIPTION DETAILS
// ================================================================
$sql = "
    SELECT 
        p.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth as patient_dob,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        b.name as branch_name,
        b.location as branch_location
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
";

$prescription = null;
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$prescription_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching prescription: " . $e->getMessage());
}

if (!$prescription) {
    header('Location: prescriptions.php?branch=' . $branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$prescription_items = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$prescription_id]);
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescription_items = [];
}

// ================================================================
// GET PATIENTS FOR DROPDOWN
// ================================================================
$patients = [];
try {
    $stmt = $db->query("
        SELECT id, patient_id, full_name, phone 
        FROM patients 
        WHERE status = 'active' OR status IS NULL 
        ORDER BY full_name
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// ================================================================
// GET DOCTORS FOR DROPDOWN - ONLY FROM SELECTED BRANCH
// ================================================================
$doctors = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$branch_id > 0 ? $branch_id : 1]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $doctors = [];
}

// ================================================================
// GET MEDICATIONS INVENTORY - ONLY FROM SELECTED BRANCH
// ================================================================
$medications = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, selling_price, quantity, unit, category 
        FROM medications_inventory 
        WHERE status = 'active' AND branch_id = ? AND quantity > 0
        ORDER BY medication_name
    ");
    $stmt->execute([$branch_id > 0 ? $branch_id : 1]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medications = [];
}

// ================================================================
// GET BRANCHES FOR DROPDOWN
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// UPDATE PRESCRIPTION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $branch_id_update = (int)($_POST['branch_id'] ?? $branch_id);
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $medication = trim($_POST['medication'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $instructions = trim($_POST['instructions'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $is_indoor = isset($_POST['is_indoor']) ? 1 : 0;
    
    $errors = [];
    if ($patient_id <= 0) $errors[] = 'Please select a patient';
    if (empty($medication)) $errors[] = 'Medication name is required';
    if (empty($dosage)) $errors[] = 'Dosage is required';
    if (empty($frequency)) $errors[] = 'Frequency is required';
    if ($duration <= 0) $errors[] = 'Duration is required';
    if (empty($route)) $errors[] = 'Route is required';
    if ($quantity <= 0) $errors[] = 'Quantity must be greater than 0';
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE prescriptions SET
                    patient_id = ?, doctor_id = ?, branch_id = ?, diagnosis = ?,
                    medication = ?, dosage = ?, frequency = ?, duration = ?,
                    route = ?, quantity = ?, instructions = ?, notes = ?,
                    status = ?, is_indoor = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $patient_id, $doctor_id, $branch_id_update, $diagnosis,
                $medication, $dosage, $frequency, $duration,
                $route, $quantity, $instructions, $notes,
                $status, $is_indoor, $prescription_id
            ]);
            
            // Update prescription items
            if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
                foreach ($_POST['item_id'] as $index => $item_id) {
                    $item_medication = trim($_POST['item_medication'][$index] ?? '');
                    $item_dosage = trim($_POST['item_dosage'][$index] ?? '');
                    $item_frequency = trim($_POST['item_frequency'][$index] ?? '');
                    $item_quantity = (int)($_POST['item_quantity'][$index] ?? 0);
                    $item_duration = trim($_POST['item_duration'][$index] ?? '');
                    $item_route = trim($_POST['item_route'][$index] ?? '');
                    $item_instructions = trim($_POST['item_instructions'][$index] ?? '');
                    $item_unit_price = (float)($_POST['item_unit_price'][$index] ?? 0);
                    
                    if ($item_id > 0 && !empty($item_medication)) {
                        $stmt = $db->prepare("
                            UPDATE prescription_items SET
                                medication_name = ?, dosage = ?, frequency = ?, quantity = ?,
                                duration = ?, route = ?, instructions = ?, unit_price = ?,
                                total_price = ? * ?
                            WHERE id = ? AND prescription_id = ?
                        ");
                        $stmt->execute([
                            $item_medication, $item_dosage, $item_frequency, $item_quantity,
                            $item_duration, $item_route, $item_instructions, $item_unit_price,
                            $item_quantity, $item_unit_price, $item_id, $prescription_id
                        ]);
                    }
                }
            }
            
            $db->commit();
            
            $message = '✅ Prescription updated successfully!';
            $message_type = 'success';
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY id ASC");
            $stmt->execute([$prescription_id]);
            $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = '❌ Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE DELETE
// ================================================================
if (isset($_GET['delete']) && (int)$_GET['delete'] === $prescription_id) {
    try {
        $stmt = $db->prepare("UPDATE prescriptions SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$prescription_id]);
        
        $message = '✅ Prescription cancelled successfully!';
        $message_type = 'success';
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$prescription_id]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo '<script>
            setTimeout(function() {
                window.location.href = "prescriptions.php?branch=' . $branch_id . '&deleted=1";
            }, 1500);
        </script>';
        
    } catch (Exception $e) {
        $message = '❌ Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// STATUS OPTIONS
// ================================================================
$status_options = ['pending', 'confirmed', 'dispensed', 'cancelled'];

function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

// ================================================================
// PAGE VARIABLES
// ================================================================
$patient_display_name = $prescription['patient_name'] ?? 'Unknown Patient';
$prescription_number = $prescription['prescription_number'] ?? 'N/A';
$patient_id_display = $prescription['patient_number'] ?? '';
$status_display = ucfirst($prescription['status'] ?? 'Pending');
$branch_name_display = $prescription['branch_name'] ?? 'N/A';
$doctor_name_display = !empty($prescription['doctor_name']) ? 'Dr. ' . $prescription['doctor_name'] : 'Not assigned';
$doctor_specialty = $prescription['doctor_specialty'] ?? '';
$patient_phone = $prescription['patient_phone'] ?? '';
$patient_gender = $prescription['patient_gender'] ?? '';
$patient_dob = $prescription['patient_dob'] ?? '';

$patient_age = '';
if (!empty($patient_dob)) {
    $dob = new DateTime($patient_dob);
    $now = new DateTime();
    $age = $now->diff($dob);
    $patient_age = $age->y . ' yrs';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-rx {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 20px 28px;
        margin-bottom: 24px;
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .page-header-rx::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 350px;
        height: 350px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-rx .page-title-rx {
        color: white;
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-rx .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(4px);
    }

    .page-header-rx .page-subtitle-rx {
        color: rgba(255,255,255,0.85);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 4px;
    }

    .page-header-rx .page-subtitle-rx strong {
        color: white;
        font-weight: 600;
    }

    .page-header-rx .header-badge-rx {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-rx .header-badge-rx.status {
        background: rgba(251, 191, 36, 0.2);
        border-color: rgba(251, 191, 36, 0.3);
        color: #FBBF24;
    }

    .page-header-rx .header-badge-rx.success {
        background: rgba(52, 211, 153, 0.2);
        border-color: rgba(52, 211, 153, 0.3);
        color: #34D399;
    }

    .page-header-rx .header-badge-rx.danger {
        background: rgba(248, 113, 113, 0.2);
        border-color: rgba(248, 113, 113, 0.3);
        color: #F87171;
    }

    .page-header-rx .btn-outline-light-rx {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        transition: all 0.3s ease;
    }

    .page-header-rx .btn-outline-light-rx:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-rx {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.08));
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }

    .form-card-rx:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.1));
    }

    .form-card-rx .form-title-rx {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        padding-bottom: 12px;
        margin-bottom: 18px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-card-rx .form-title-rx i {
        color: var(--page-primary, #0B5ED7);
    }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-label-rx {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
        display: block;
    }

    .form-label-rx .required {
        color: #DC2626;
        margin-left: 2px;
    }

    .form-control-rx {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.88rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .form-control-rx:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .form-control-rx::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    select.form-control-rx { appearance: auto; cursor: pointer; }
    textarea.form-control-rx { resize: vertical; min-height: 80px; }

    .form-row-rx { margin-bottom: 16px; }
    .form-row-rx:last-child { margin-bottom: 0; }

    .form-grid-rx {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-grid-rx .full-width { grid-column: 1 / -1; }

    .help-text-rx {
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
        display: block;
    }

    /* ================================================================
       MEDICATION DROPDOWN
       ================================================================ */
    .medication-select-wrapper-rx {
        position: relative;
    }

    .medication-select-wrapper-rx select {
        width: 100%;
        padding: 10px 40px 10px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.88rem;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        transition: all 0.3s ease;
        outline: none;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
    }

    .medication-select-wrapper-rx select:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    /* ================================================================
       ITEM ROWS
       ================================================================ */
    .item-header-rx {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 0.8fr 0.8fr 0.8fr 1fr 0.8fr 0.5fr;
        gap: 6px;
        padding: 4px 12px;
        font-size: 0.55rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .item-row-rx {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 0.8fr 0.8fr 0.8fr 1fr 0.8fr 0.5fr;
        gap: 6px;
        align-items: center;
        padding: 8px 12px;
        background: var(--page-hover, #F8FAFC);
        border-radius: 8px;
        border: 1px solid var(--page-border, #E2E8F0);
        margin-bottom: 6px;
    }

    [data-theme="dark"] .item-row-rx {
        background: #0F172A;
        border-color: #334155;
    }

    .item-row-rx .form-control-rx {
        padding: 6px 8px;
        font-size: 0.75rem;
        min-height: 32px;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-rx {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.88rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        font-family: inherit;
    }

    .btn-rx:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .btn-primary-rx {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }

    .btn-primary-rx:hover {
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-success-rx {
        background: #059669;
        color: white;
    }

    .btn-success-rx:hover {
        background: #047857;
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        color: white;
    }

    .btn-danger-rx {
        background: #DC2626;
        color: white;
    }

    .btn-danger-rx:hover {
        background: #B91C1C;
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        color: white;
    }

    .btn-outline-rx {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-rx:hover {
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    [data-theme="dark"] .btn-outline-rx {
        color: #94A3B8;
        border-color: #334155;
    }

    [data-theme="dark"] .btn-outline-rx:hover {
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .action-buttons-rx {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        padding-top: 16px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 16px;
    }

    .action-buttons-rx .btn-rx {
        min-width: 140px;
        justify-content: center;
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box-rx {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-weight: 500;
        animation: slideDownRx 0.4s ease;
    }

    @keyframes slideDownRx {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-rx.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }

    .message-box-rx.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }

    [data-theme="dark"] .message-box-rx.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .message-box-rx.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-rx {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success-rx { background: #059669; }
    .badge-danger-rx { background: #DC2626; }
    .badge-warning-rx { background: #D97706; color: #1E293B; }
    .badge-info-rx { background: #0B5ED7; }
    .badge-secondary-rx { background: #64748B; }

    [data-theme="dark"] .badge-warning-rx { color: #1E293B; }

    /* ================================================================
       PATIENT INFO GRID
       ================================================================ */
    .patient-info-grid-rx {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .patient-info-item-rx {
        padding: 10px 14px;
        background: var(--page-hover, #F8FAFC);
        border-radius: 8px;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .patient-info-item-rx {
        background: #0F172A;
        border-color: #334155;
    }

    .patient-info-item-rx .label-rx {
        font-size: 0.55rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .patient-info-item-rx .value-rx {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-top: 2px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-rx {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-rx .footer-brand-rx {
        color: var(--page-primary, #0B5ED7);
        font-weight: 600;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .form-grid-rx { grid-template-columns: 1fr; }
        .page-header-rx .page-title-rx { font-size: 1.3rem; }
        .page-header-rx { padding: 16px 18px; }
        .form-card-rx { padding: 16px 18px; }
        .action-buttons-rx { flex-direction: column; align-items: stretch; }
        .action-buttons-rx .btn-rx { min-width: unset; width: 100%; }
        .item-row-rx, .item-header-rx {
            grid-template-columns: 1fr 1fr;
            gap: 4px;
        }
        .item-header-rx { display: none; }
        .item-row-rx .form-control-rx { font-size: 0.7rem; padding: 4px 6px; }
    }

    @media (max-width: 480px) {
        .page-header-rx .page-title-rx { font-size: 1rem; }
        .form-card-rx { padding: 12px 14px; }
        .item-row-rx { grid-template-columns: 1fr; gap: 4px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-rx">
        <div>
            <h1 class="page-title-rx">
                <i class="fas fa-prescription"></i>
                Edit Prescription
                <span class="role-badge-display">ADMIN</span>
                <span style="background:rgba(255,255,255,0.15);padding:3px 14px;border-radius:20px;font-size:0.75rem;font-weight:500;">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($prescription_number) ?>
                </span>
                <span class="header-badge-rx <?= $status_display === 'Dispensed' ? 'success' : ($status_display === 'Cancelled' ? 'danger' : 'status') ?>">
                    <i class="fas fa-circle" style="font-size:8px;"></i>
                    <?= $status_display ?>
                </span>
            </h1>
            <p class="page-subtitle-rx">
                <strong style="font-size:1.1rem;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient_display_name) ?>
                </strong>
                <?php if (!empty($patient_id_display)): ?>
                    <span class="header-badge-rx">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient_id_display) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_phone)): ?>
                    <span class="header-badge-rx">
                        <i class="fas fa-phone"></i> <?= htmlspecialchars($patient_phone) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_age)): ?>
                    <span class="header-badge-rx">
                        <i class="fas fa-calendar"></i> <?= htmlspecialchars($patient_age) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_gender)): ?>
                    <span class="header-badge-rx">
                        <i class="fas fa-<?= strtolower($patient_gender) === 'male' ? 'mars' : 'venus' ?>"></i>
                        <?= htmlspecialchars($patient_gender) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge-rx">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?>
                </span>
                <?php if ($doctor_name_display !== 'Not assigned'): ?>
                    <span class="header-badge-rx success">
                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($doctor_name_display) ?>
                        <?php if (!empty($doctor_specialty)): ?>
                            (<?= htmlspecialchars($doctor_specialty) ?>)
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="header-badge-rx danger">
                        <i class="fas fa-user-md"></i> No doctor assigned
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="view_prescription.php?id=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light-rx">
                <i class="fas fa-arrow-left"></i> Back to View
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFO CARD -->
    <!-- ================================================================ -->
    <div class="form-card-rx">
        <div class="form-title-rx">
            <i class="fas fa-user-circle"></i>
            Patient Information
            <span style="margin-left:auto;font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);">
                Prescription #<?= htmlspecialchars($prescription_number) ?>
            </span>
        </div>
        <div class="patient-info-grid-rx">
            <div class="patient-info-item-rx">
                <div class="label-rx">Full Name</div>
                <div class="value-rx"><?= htmlspecialchars($patient_display_name) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Patient ID</div>
                <div class="value-rx"><?= htmlspecialchars($patient_id_display ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Phone</div>
                <div class="value-rx"><?= htmlspecialchars($patient_phone ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Gender</div>
                <div class="value-rx"><?= htmlspecialchars($patient_gender ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Age</div>
                <div class="value-rx"><?= htmlspecialchars($patient_age ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Branch</div>
                <div class="value-rx"><?= htmlspecialchars($branch_name_display) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Doctor</div>
                <div class="value-rx"><?= htmlspecialchars($doctor_name_display) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Status</div>
                <div class="value-rx">
                    <span class="badge-rx badge-<?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>-rx">
                        <?= $status_display ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box-rx <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.3rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="form-card-rx">
        <div class="form-title-rx">
            <i class="fas fa-edit"></i>
            Edit Prescription Details
            <span style="margin-left:auto;font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);">
                Created: <?= date('M d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?>
                <?php if (!empty($prescription['updated_at']) && $prescription['updated_at'] != $prescription['created_at']): ?>
                    · Updated: <?= date('M d, Y h:i A', strtotime($prescription['updated_at'])) ?>
                <?php endif; ?>
            </span>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <div class="form-grid-rx">
                <!-- Patient -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control-rx" required>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>" <?= $patient['id'] == $prescription['patient_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id']) ?>)
                                <?php if (!empty($patient['phone'])): ?>
                                    - <?= htmlspecialchars($patient['phone']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Doctor -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Doctor</label>
                    <select name="doctor_id" class="form-control-rx">
                        <option value="">-- No Doctor --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= $doctor['id'] ?>" <?= $doctor['id'] == $prescription['doctor_id'] ? 'selected' : '' ?>>
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                <?php if (!empty($doctor['specialty'])): ?>
                                    (<?= htmlspecialchars($doctor['specialty']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($doctors)): ?>
                            <option value="" disabled>No doctors available in this branch</option>
                        <?php endif; ?>
                    </select>
                    <span class="help-text-rx">
                        <i class="fas fa-info-circle"></i> Showing doctors from <?= htmlspecialchars($branch_name_display) ?> branch
                    </span>
                </div>
                
                <!-- Diagnosis -->
                <div class="form-row-rx full-width">
                    <label class="form-label-rx">Diagnosis</label>
                    <input type="text" name="diagnosis" class="form-control-rx" 
                           placeholder="e.g. Hypertension, Malaria, Diabetes, etc." 
                           value="<?= htmlspecialchars($prescription['diagnosis'] ?? '') ?>">
                </div>
                
                <!-- Medication Dropdown -->
                <div class="form-row-rx full-width">
                    <label class="form-label-rx">Medication <span class="required">*</span></label>
                    <div class="medication-select-wrapper-rx">
                        <select name="medication" class="form-control-rx" required id="medicationSelect">
                            <option value="">-- Select Medication --</option>
                            <?php if (!empty($medications)): ?>
                                <?php 
                                $current_category = '';
                                foreach ($medications as $med): 
                                    $category = $med['category'] ?? 'General';
                                    if ($category !== $current_category) {
                                        if ($current_category !== '') {
                                            echo '</optgroup>';
                                        }
                                        $current_category = $category;
                                        echo '<optgroup label="' . htmlspecialchars($category) . '">';
                                    }
                                ?>
                                    <option value="<?= htmlspecialchars($med['medication_name']) ?>" 
                                        <?= $med['medication_name'] == ($prescription['medication'] ?? '') ? 'selected' : '' ?>
                                        data-price="<?= $med['selling_price'] ?? 0 ?>"
                                        data-quantity="<?= $med['quantity'] ?? 0 ?>"
                                        data-unit="<?= htmlspecialchars($med['unit'] ?? '') ?>">
                                        <?= htmlspecialchars($med['medication_name']) ?>
                                        <?php if (!empty($med['unit'])): ?>
                                            (<?= htmlspecialchars($med['unit']) ?>)
                                        <?php endif; ?>
                                        - TSh <?= number_format($med['selling_price'] ?? 0, 0) ?>
                                        (Stock: <?= $med['quantity'] ?? 0 ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($current_category !== ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            <?php else: ?>
                                <option value="" disabled>No medications available in this branch</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <span class="help-text-rx" id="medicationHelp">
                        <i class="fas fa-info-circle"></i> Showing medications from <?= htmlspecialchars($branch_name_display) ?> branch
                        <?php if (!empty($medications)): ?>
                            · <?= count($medications) ?> medication(s) available
                        <?php endif; ?>
                    </span>
                </div>
                
                <!-- Dosage -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Dosage <span class="required">*</span></label>
                    <input type="text" name="dosage" class="form-control-rx" 
                           placeholder="e.g. 500mg" 
                           value="<?= htmlspecialchars($prescription['dosage'] ?? '') ?>" required>
                </div>
                
                <!-- Frequency -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Frequency <span class="required">*</span></label>
                    <input type="text" name="frequency" class="form-control-rx" 
                           placeholder="e.g. Twice Daily, Every 6 hours" 
                           value="<?= htmlspecialchars($prescription['frequency'] ?? '') ?>" required>
                </div>
                
                <!-- Duration -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Duration (days) <span class="required">*</span></label>
                    <input type="number" name="duration" class="form-control-rx" 
                           placeholder="e.g. 7" min="1" 
                           value="<?= htmlspecialchars($prescription['duration'] ?? 0) ?>" required>
                </div>
                
                <!-- Route -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Route <span class="required">*</span></label>
                    <input type="text" name="route" class="form-control-rx" 
                           placeholder="e.g. Oral, Topical, IV, IM" 
                           value="<?= htmlspecialchars($prescription['route'] ?? '') ?>" required>
                </div>
                
                <!-- Quantity -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control-rx" 
                           placeholder="e.g. 60" min="1" 
                           value="<?= htmlspecialchars($prescription['quantity'] ?? 0) ?>" required>
                </div>
                
                <!-- Status -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Status <span class="required">*</span></label>
                    <select name="status" class="form-control-rx" required>
                        <?php foreach ($status_options as $status): ?>
                            <option value="<?= $status ?>" <?= $status == $prescription['status'] ? 'selected' : '' ?>>
                                <?= ucfirst($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Is Indoor -->
                <div class="form-row-rx">
                    <label class="form-label-rx">Is Indoor</label>
                    <div style="display:flex;align-items:center;gap:10px;padding-top:8px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85rem;">
                            <input type="checkbox" name="is_indoor" value="1" 
                                   <?= ($prescription['is_indoor'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <span>Indoor Patient</span>
                        </label>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="form-row-rx full-width">
                    <label class="form-label-rx">Instructions</label>
                    <textarea name="instructions" class="form-control-rx" 
                              placeholder="e.g. Take after meals, Take with water, Avoid alcohol" 
                              rows="2"><?= htmlspecialchars($prescription['instructions'] ?? '') ?></textarea>
                </div>
                
                <!-- Notes -->
                <div class="form-row-rx full-width">
                    <label class="form-label-rx">Notes</label>
                    <textarea name="notes" class="form-control-rx" 
                              placeholder="Additional notes about the prescription" 
                              rows="2"><?= htmlspecialchars($prescription['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Prescription Items Section -->
            <?php if (count($prescription_items) > 0): ?>
            <div class="form-row-rx full-width" style="margin-top:20px;padding-top:20px;border-top:2px solid var(--page-border);">
                <div class="form-title-rx" style="border-bottom:none;padding-bottom:0;margin-bottom:12px;">
                    <i class="fas fa-list"></i>
                    Prescription Items
                    <span style="margin-left:auto;font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);">
                        <?= count($prescription_items) ?> item(s)
                    </span>
                </div>
                
                <div class="item-header-rx">
                    <span>Medication</span>
                    <span>Dosage</span>
                    <span>Frequency</span>
                    <span>Qty</span>
                    <span>Duration</span>
                    <span>Route</span>
                    <span>Instructions</span>
                    <span>Unit Price</span>
                    <span>Total</span>
                </div>
                
                <?php foreach ($prescription_items as $index => $item): ?>
                    <div class="item-row-rx">
                        <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                        <input type="text" name="item_medication[]" class="form-control-rx" 
                               value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" placeholder="Medication">
                        <input type="text" name="item_dosage[]" class="form-control-rx" 
                               value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" placeholder="Dosage">
                        <input type="text" name="item_frequency[]" class="form-control-rx" 
                               value="<?= htmlspecialchars($item['frequency'] ?? '') ?>" placeholder="Frequency">
                        <input type="number" name="item_quantity[]" class="form-control-rx" 
                               value="<?= $item['quantity'] ?? 0 ?>" placeholder="Qty" min="1">
                        <input type="text" name="item_duration[]" class="form-control-rx" 
                               value="<?= htmlspecialchars($item['duration'] ?? '') ?>" placeholder="Duration">
                        <input type="text" name="item_route[]" class="form-control-rx" 
                               value="<?= htmlspecialchars($item['route'] ?? '') ?>" placeholder="Route">
                        <input type="text" name="item_instructions[]" class="form-control-rx" 
                               value="<?= htmlspecialchars($item['instructions'] ?? '') ?>" placeholder="Instructions">
                        <input type="number" name="item_unit_price[]" class="form-control-rx" 
                               value="<?= $item['unit_price'] ?? 0 ?>" placeholder="Price" step="0.01" min="0">
                        <span style="font-weight:600;font-size:0.8rem;color:var(--page-primary);text-align:center;">
                            TSh <?= number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0), 0) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="action-buttons-rx">
                <button type="submit" class="btn-rx btn-primary-rx">
                    <i class="fas fa-save"></i> Update Prescription
                </button>
                <a href="view_prescription.php?id=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn-rx btn-outline-rx">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php if ($prescription['status'] === 'pending'): ?>
                    <a href="dispense_prescription.php?id=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn-rx btn-success-rx">
                        <i class="fas fa-check-circle"></i> Dispense
                    </a>
                <?php endif; ?>
                <?php if ($prescription['status'] !== 'cancelled'): ?>
                    <a href="?delete=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn-rx btn-danger-rx" 
                       onclick="return confirm('⚠️ Are you sure you want to cancel this prescription?\n\nPatient: <?= htmlspecialchars($patient_display_name) ?>\nPrescription: <?= htmlspecialchars($prescription_number) ?>\n\nThis action CANNOT be undone!')">
                        <i class="fas fa-trash"></i> Cancel Prescription
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION INFO CARD -->
    <!-- ================================================================ -->
    <div class="form-card-rx">
        <div class="form-title-rx">
            <i class="fas fa-info-circle"></i>
            Prescription Information
        </div>
        <div class="patient-info-grid-rx">
            <div class="patient-info-item-rx">
                <div class="label-rx">Prescription #</div>
                <div class="value-rx" style="font-family:monospace;color:var(--page-primary);"><?= htmlspecialchars($prescription_number) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Created</div>
                <div class="value-rx"><?= date('F d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Last Updated</div>
                <div class="value-rx"><?= date('F d, Y h:i A', strtotime($prescription['updated_at'] ?? 'now')) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Status</div>
                <div class="value-rx">
                    <span class="badge-rx badge-<?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>-rx">
                        <?= $status_display ?>
                    </span>
                </div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Doctor</div>
                <div class="value-rx"><?= htmlspecialchars($doctor_name_display) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Patient</div>
                <div class="value-rx"><?= htmlspecialchars($patient_display_name) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Branch</div>
                <div class="value-rx"><?= htmlspecialchars($branch_name_display) ?></div>
            </div>
            <div class="patient-info-item-rx">
                <div class="label-rx">Indoor</div>
                <div class="value-rx"><?= ($prescription['is_indoor'] ?? 0) == 1 ? '✅ Yes' : '❌ No' ?></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-rx">
        <p>
            <span class="footer-brand-rx">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Prescription - <?= htmlspecialchars($prescription_number) ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar, NO date-time) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ✅ FOOTER TIME ONLY (header ina date/time yake)
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // ✅ MEDICATION SELECT - Auto fill stock info
    // ================================================================
    var medicationSelect = document.getElementById('medicationSelect');
    if (medicationSelect) {
        medicationSelect.addEventListener('change', function() {
            var selected = this.options[this.selectedIndex];
            var price = selected.dataset.price || 0;
            var quantity = selected.dataset.quantity || 0;
            var unit = selected.dataset.unit || '';
            var helpEl = document.getElementById('medicationHelp');
            
            if (helpEl && quantity > 0) {
                helpEl.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> Stock: ' + quantity + ' ' + unit + ' available · Price: TSh ' + parseFloat(price).toLocaleString();
            }
        });
    }

    // ================================================================
    // ✅ FORM VALIDATION
    // ================================================================
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        var medication = document.getElementById('medicationSelect').value;
        var dosage = document.querySelector('input[name="dosage"]').value.trim();
        var frequency = document.querySelector('input[name="frequency"]').value.trim();
        var duration = document.querySelector('input[name="duration"]').value;
        var route = document.querySelector('input[name="route"]').value.trim();
        var quantity = document.querySelector('input[name="quantity"]').value;
        
        var errors = [];
        if (!medication) errors.push('Medication is required');
        if (!dosage) errors.push('Dosage is required');
        if (!frequency) errors.push('Frequency is required');
        if (!duration || parseInt(duration) <= 0) errors.push('Duration is required');
        if (!route) errors.push('Route is required');
        if (!quantity || parseInt(quantity) <= 0) errors.push('Quantity must be greater than 0');
        
        if (errors.length > 0) {
            e.preventDefault();
            showToast('⚠️ Validation Error', errors[0], 'warning');
            return false;
        }
    });

    // ================================================================
    // ✅ TOAST NOTIFICATION
    // ================================================================
    function showToast(title, message, type) {
        var existing = document.getElementById('pageToast');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.id = 'pageToast';
        var bgColor = type === 'success' ? '#059669' : 
                      type === 'error' ? '#DC2626' : 
                      type === 'warning' ? '#D97706' : '#0B5ED7';
        var icon = type === 'success' ? 'fa-check-circle' : 
                   type === 'error' ? 'fa-exclamation-circle' : 
                   type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        toast.style.cssText = `
            position: fixed; bottom: 24px; right: 24px; padding: 14px 20px;
            border-radius: 12px; z-index: 99999; max-width: 400px;
            display: flex; align-items: center; gap: 12px; color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            animation: slideInRx 0.4s ease; font-size: 0.85rem;
            font-weight: 500; background: ${bgColor};
        `;
        toast.innerHTML = `
            <i class="fas ${icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:2px 0 0 0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.style.animation = 'slideOutRx 0.4s ease';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3500);
    }

    if (!document.getElementById('toastAnimationsRx')) {
        var style = document.createElement('style');
        style.id = 'toastAnimationsRx';
        style.textContent = `
            @keyframes slideInRx { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutRx { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
        `;
        document.head.appendChild(style);
    }

    console.log('%c🏥 Braick - Edit Prescription', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription_number) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient_display_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Doctors filtered by branch: <?= count($doctors) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Medications filtered by branch: <?= count($medications) ?>', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>