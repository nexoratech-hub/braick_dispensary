<?php
// ================================================================
// FILE: frontend/pages/admin/edit_prescription.php
// SUPER ADMIN - EDIT PRESCRIPTION
// BRAICK DISPENSARY - FIXED: Shows patient name, doctors & medications by branch
// ================================================================

// ================================================================
// START SESSION
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
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
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

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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
// GET PATIENTS FOR DROPDOWN - Ordered by name
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
// ✅ GET DOCTORS FOR DROPDOWN - ONLY FROM SELECTED BRANCH
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
// ✅ GET MEDICATIONS INVENTORY - ONLY FROM SELECTED BRANCH
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
    
    // Validation
    $errors = [];
    if ($patient_id <= 0) {
        $errors[] = 'Please select a patient';
    }
    if (empty($medication)) {
        $errors[] = 'Medication name is required';
    }
    if (empty($dosage)) {
        $errors[] = 'Dosage is required';
    }
    if (empty($frequency)) {
        $errors[] = 'Frequency is required';
    }
    if ($duration <= 0) {
        $errors[] = 'Duration is required';
    }
    if (empty($route)) {
        $errors[] = 'Route is required';
    }
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE prescriptions SET
                    patient_id = ?,
                    doctor_id = ?,
                    branch_id = ?,
                    diagnosis = ?,
                    medication = ?,
                    dosage = ?,
                    frequency = ?,
                    duration = ?,
                    route = ?,
                    quantity = ?,
                    instructions = ?,
                    notes = ?,
                    status = ?,
                    is_indoor = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $patient_id,
                $doctor_id,
                $branch_id_update,
                $diagnosis,
                $medication,
                $dosage,
                $frequency,
                $duration,
                $route,
                $quantity,
                $instructions,
                $notes,
                $status,
                $is_indoor,
                $prescription_id
            ]);
            
            // Update prescription items if any
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
                                medication_name = ?,
                                dosage = ?,
                                frequency = ?,
                                quantity = ?,
                                duration = ?,
                                route = ?,
                                instructions = ?,
                                unit_price = ?,
                                total_price = ? * ?
                            WHERE id = ? AND prescription_id = ?
                        ");
                        $stmt->execute([
                            $item_medication,
                            $item_dosage,
                            $item_frequency,
                            $item_quantity,
                            $item_duration,
                            $item_route,
                            $item_instructions,
                            $item_unit_price,
                            $item_quantity,
                            $item_unit_price,
                            $item_id,
                            $prescription_id
                        ]);
                    }
                }
            }
            
            $db->commit();
            
            $message = '✅ Prescription updated successfully!';
            $message_type = 'success';
            
            // Refresh data
            $stmt = $db->prepare($sql);
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh items
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
        
        // Refresh data
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
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// STATUS OPTIONS
// ================================================================
$status_options = ['pending', 'confirmed', 'dispensed', 'cancelled'];

function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 0);
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

// Calculate age if DOB exists
$patient_age = '';
if (!empty($patient_dob)) {
    $dob = new DateTime($patient_dob);
    $now = new DateTime();
    $age = $now->diff($dob);
    $patient_age = $age->y . ' yrs';
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription - <?= htmlspecialchars($patient_display_name) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #083C8A);
            --success: #059669;
            --danger: #DC2626;
            --warning: #D97706;
        }
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================
           TOP NAV
           ================================================================ */
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
            transition: background 0.3s ease, border-color 0.3s ease;
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
            background: var(--primary-gradient);
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
        .top-nav .branch-selector {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            min-width: 160px;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .top-nav .branch-selector:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            background: #059669;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: #94A3B8; animation: none; }
        @keyframes pulse-dot { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
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
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           SIDEBAR
           ================================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 270px;
            background: #0B4EA8;
            color: white;
            z-index: 50;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s ease-in-out;
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sidebar-brand {
            padding: 18px 16px 14px;
            border-bottom: 2px solid #0B3D8A;
            background: #0B4EA8;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .sidebar-brand .logo {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            object-fit: cover;
            background: white;
            padding: 4px;
            border: 2px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .sidebar-brand .brand-sub { color: #9EC5FE; font-size: 0.65rem; font-weight: 500; }
        .sidebar-nav { padding: 10px 8px 20px; }
        .sidebar-nav .nav-label {
            font-size: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6EA8FE;
            padding: 0 10px;
            margin: 12px 0 4px;
            font-weight: 700;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #D2E3FC;
            text-decoration: none;
            transition: all 0.25s ease;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 1px 0;
            background: transparent;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            position: relative;
        }
        .sidebar-link:hover {
            background: #0AA84F;
            color: white;
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.35);
            transform: translateX(4px);
        }
        .sidebar-link.active {
            background: #0AA84F;
            color: white;
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.35);
        }
        .sidebar-link.logout-link {
            border-top: 2px solid rgba(255,255,255,0.08);
            padding-top: 10px;
            margin-top: 6px;
            color: #FCA5A5;
        }
        .sidebar-link.logout-link:hover {
            background: #DC2626;
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease, color 0.3s ease;
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header-box {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
        }
        .page-header-box::before {
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
        .page-header-box .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header-box .page-title .role-badge-display {
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
        .page-header-box .page-subtitle {
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
        .page-header-box .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        .page-header-box .header-badge {
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
        .page-header-box .header-badge i { opacity: 0.8; }
        .page-header-box .header-badge.status {
            background: rgba(251, 191, 36, 0.2);
            border-color: rgba(251, 191, 36, 0.3);
            color: #FBBF24;
        }
        .page-header-box .header-badge.success {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.3);
            color: #34D399;
        }
        .page-header-box .header-badge.info {
            background: rgba(96, 165, 250, 0.2);
            border-color: rgba(96, 165, 250, 0.3);
            color: #60A5FA;
        }
        .page-header-box .header-badge.danger {
            background: rgba(248, 113, 113, 0.2);
            border-color: rgba(248, 113, 113, 0.3);
            color: #F87171;
        }
        
        /* ================================================================
           FORM STYLES
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .form-card .form-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-bottom: 12px;
            margin-bottom: 18px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-card .form-title i {
            color: var(--primary);
        }
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.88rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-row { margin-bottom: 16px; }
        .form-row:last-child { margin-bottom: 0; }
        select.form-control { appearance: auto; cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-grid .full-width { grid-column: 1 / -1; }
        
        /* ================================================================
           ✅ MEDICATION DROPDOWN - BEAUTIFUL CSS
           ================================================================ */
        .medication-select-wrapper {
            position: relative;
        }
        .medication-select-wrapper select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.88rem;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.3s ease;
            outline: none;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }
        .medication-select-wrapper select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        .medication-select-wrapper select option {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        .medication-select-wrapper select option:checked {
            background: var(--primary);
            color: white;
        }
        .medication-select-wrapper select optgroup {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .medication-select-wrapper .select-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
        }
        
        /* ================================================================
           ITEM ROWS
           ================================================================ */
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 0.8fr 0.8fr 0.8fr 1fr 0.8fr 0.5fr;
            gap: 6px;
            align-items: center;
            padding: 8px 12px;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 6px;
        }
        .item-row .form-control {
            padding: 6px 8px;
            font-size: 0.75rem;
            min-height: 32px;
        }
        .item-row .item-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .item-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 0.8fr 0.8fr 0.8fr 1fr 0.8fr 0.5fr;
            gap: 6px;
            padding: 4px 12px;
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
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
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-gradient-strong);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        }
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: #047857;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #B91C1C;
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-sm { padding: 6px 14px; font-size: 0.75rem; }
        .btn-xs { padding: 4px 10px; font-size: 0.65rem; border-radius: 6px; }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
        }
        .action-buttons .btn { min-width: 140px; justify-content: center; }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        .message-box i { font-size: 1.3rem; }
        
        /* ================================================================
           BADGE
           ================================================================ */
        .badge {
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
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        
        /* ================================================================
           PATIENT INFO
           ================================================================ */
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .patient-info-item {
            padding: 10px 14px;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .patient-info-item .label {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .patient-info-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        [data-theme="dark"] .patient-info-item {
            background: #0F172A;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .page-header-box .page-title { font-size: 1.3rem; }
            .page-header-box { padding: 16px 18px; }
            .form-card { padding: 16px 18px; }
            .action-buttons { flex-direction: column; align-items: stretch; }
            .action-buttons .btn { min-width: unset; width: 100%; }
            .item-row, .item-header {
                grid-template-columns: 1fr 1fr;
                gap: 4px;
            }
            .item-header { display: none; }
            .item-row .form-control { font-size: 0.7rem; padding: 4px 6px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
            .form-card { padding: 12px 14px; }
            .item-row { grid-template-columns: 1fr; gap: 4px; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 45;
            display: none;
            backdrop-filter: blur(2px);
        }
        @media (max-width: 1024px) {
            #sidebarOverlay.show { display: block; }
        }
    </style>
</head>
<body>

<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo" 
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Super Admin</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="/dispensary_system/frontend/pages/admin/employees.php" class="sidebar-link"><i class="fas fa-users"></i> Employees</a>
        <a href="/dispensary_system/frontend/pages/admin/patients.php" class="sidebar-link"><i class="fas fa-user-injured"></i> Patients</a>
        
        <div class="nav-label">Modules</div>
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php" class="sidebar-link"><i class="fas fa-user-md"></i> Doctors</a>
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php" class="sidebar-link"><i class="fas fa-prescription"></i> Pharmacy</a>
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php" class="sidebar-link"><i class="fas fa-headset"></i> Reception</a>
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php" class="sidebar-link"><i class="fas fa-flask"></i> Laboratory</a>
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php" class="sidebar-link"><i class="fas fa-cash-register"></i> Cashier</a>
        
        <div class="nav-label">Management</div>
        <a href="/dispensary_system/frontend/pages/admin/branches.php" class="sidebar-link"><i class="fas fa-store-alt"></i> Branches</a>
        <a href="/dispensary_system/frontend/pages/admin/departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <a href="/dispensary_system/frontend/pages/admin/reports.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        
        <div class="nav-label">Account</div>
        <a href="/dispensary_system/frontend/pages/admin/profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="icon-btn lg:hidden">
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
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all">🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER - Shows patient name -->
    <!-- ================================================================ -->
    <div class="page-header-box animate-fade-in-up" style="animation-delay:0.05s;">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Edit Prescription
                <span class="role-badge-display">ADMIN</span>
                <span style="background:rgba(255,255,255,0.15);padding:3px 14px;border-radius:20px;font-size:0.75rem;font-weight:500;">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($prescription_number) ?>
                </span>
                <span class="header-badge <?= $status_display === 'Dispensed' ? 'success' : ($status_display === 'Cancelled' ? 'danger' : 'status') ?>">
                    <i class="fas fa-circle"></i>
                    <?= $status_display ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <strong style="font-size:1.1rem;color:#ffffff;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient_display_name) ?>
                </strong>
                <?php if (!empty($patient_id_display)): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient_id_display) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_phone)): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.12);">
                        <i class="fas fa-phone"></i> <?= htmlspecialchars($patient_phone) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_age)): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.12);">
                        <i class="fas fa-calendar"></i> <?= htmlspecialchars($patient_age) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($patient_gender)): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.12);">
                        <i class="fas fa-<?= strtolower($patient_gender) === 'male' ? 'mars' : 'venus' ?>"></i>
                        <?= htmlspecialchars($patient_gender) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?>
                </span>
                <?php if ($doctor_name_display !== 'Not assigned'): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.2);color:#34D399;">
                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($doctor_name_display) ?>
                        <?php if (!empty($doctor_specialty)): ?>
                            (<?= htmlspecialchars($doctor_specialty) ?>)
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="header-badge" style="background:rgba(248,113,113,0.15);border-color:rgba(248,113,113,0.2);color:#F87171;">
                        <i class="fas fa-user-md"></i> No doctor assigned
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="view_prescription.php?id=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">
                <i class="fas fa-arrow-left"></i> Back to View
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFO CARD -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up" style="animation-delay:0.08s;">
        <div class="form-title">
            <i class="fas fa-user-circle"></i>
            Patient Information
            <span style="margin-left:auto;font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                Prescription #<?= htmlspecialchars($prescription_number) ?>
            </span>
        </div>
        <div class="patient-info-grid">
            <div class="patient-info-item">
                <div class="label">Full Name</div>
                <div class="value"><?= htmlspecialchars($patient_display_name) ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Patient ID</div>
                <div class="value"><?= htmlspecialchars($patient_id_display ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Phone</div>
                <div class="value"><?= htmlspecialchars($patient_phone ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Gender</div>
                <div class="value"><?= htmlspecialchars($patient_gender ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Age</div>
                <div class="value"><?= htmlspecialchars($patient_age ?: 'N/A') ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Branch</div>
                <div class="value"><?= htmlspecialchars($branch_name_display) ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Doctor</div>
                <div class="value"><?= htmlspecialchars($doctor_name_display) ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Status</div>
                <div class="value">
                    <span class="badge badge-<?= getStatusBadge($prescription['status'] ?? 'pending') ?>" style="font-size:0.7rem;">
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
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?> animate-fade-in-up">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="form-title">
            <i class="fas fa-edit"></i>
            Edit Prescription Details
            <span style="margin-left:auto;font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                Created: <?= date('M d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?>
                <?php if (!empty($prescription['updated_at']) && $prescription['updated_at'] != $prescription['created_at']): ?>
                    · Updated: <?= date('M d, Y h:i A', strtotime($prescription['updated_at'])) ?>
                <?php endif; ?>
            </span>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <div class="form-grid">
                <!-- ✅ Patient - No "-- Select Patient --" -->
                <div class="form-row">
                    <label class="form-label">Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
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
                
                <!-- ✅ Doctor - Only from selected branch -->
                <div class="form-row">
                    <label class="form-label">Doctor</label>
                    <select name="doctor_id" class="form-control">
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
                    <small style="font-size:0.65rem;color:var(--text-secondary);">
                        <i class="fas fa-info-circle"></i> Showing doctors from <?= htmlspecialchars($branch_name_display) ?> branch
                    </small>
                </div>
                
                <!-- Diagnosis -->
                <div class="form-row full-width">
                    <label class="form-label">Diagnosis</label>
                    <input type="text" name="diagnosis" class="form-control" 
                           placeholder="e.g. Hypertension, Malaria, Diabetes, etc." 
                           value="<?= htmlspecialchars($prescription['diagnosis'] ?? '') ?>">
                </div>
                
                <!-- ✅ Medication - Beautiful Dropdown -->
                <div class="form-row full-width">
                    <label class="form-label">Medication <span class="required">*</span></label>
                    <div class="medication-select-wrapper">
                        <select name="medication" class="form-control" required id="medicationSelect" style="padding-right:40px;">
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
                                        <span style="font-weight:400;color:var(--text-secondary);font-size:0.7rem;">
                                            (Stock: <?= $med['quantity'] ?? 0 ?>)
                                        </span>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($current_category !== ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            <?php else: ?>
                                <option value="" disabled>No medications available in this branch</option>
                            <?php endif; ?>
                        </select>
                        <span class="select-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <small style="font-size:0.65rem;color:var(--text-secondary);">
                        <i class="fas fa-info-circle"></i> Showing medications from <?= htmlspecialchars($branch_name_display) ?> branch
                        <?php if (!empty($medications)): ?>
                            · <?= count($medications) ?> medication(s) available
                        <?php endif; ?>
                    </small>
                </div>
                
                <!-- Dosage -->
                <div class="form-row">
                    <label class="form-label">Dosage <span class="required">*</span></label>
                    <input type="text" name="dosage" class="form-control" 
                           placeholder="e.g. 500mg" 
                           value="<?= htmlspecialchars($prescription['dosage'] ?? '') ?>" required>
                </div>
                
                <!-- Frequency -->
                <div class="form-row">
                    <label class="form-label">Frequency <span class="required">*</span></label>
                    <input type="text" name="frequency" class="form-control" 
                           placeholder="e.g. Twice Daily, Every 6 hours" 
                           value="<?= htmlspecialchars($prescription['frequency'] ?? '') ?>" required>
                </div>
                
                <!-- Duration -->
                <div class="form-row">
                    <label class="form-label">Duration (days) <span class="required">*</span></label>
                    <input type="number" name="duration" class="form-control" 
                           placeholder="e.g. 7" min="1" 
                           value="<?= htmlspecialchars($prescription['duration'] ?? 0) ?>" required>
                </div>
                
                <!-- Route -->
                <div class="form-row">
                    <label class="form-label">Route <span class="required">*</span></label>
                    <input type="text" name="route" class="form-control" 
                           placeholder="e.g. Oral, Topical, IV, IM" 
                           value="<?= htmlspecialchars($prescription['route'] ?? '') ?>" required>
                </div>
                
                <!-- Quantity -->
                <div class="form-row">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" 
                           placeholder="e.g. 60" min="1" 
                           value="<?= htmlspecialchars($prescription['quantity'] ?? 0) ?>" required>
                </div>
                
                <!-- Status -->
                <div class="form-row">
                    <label class="form-label">Status <span class="required">*</span></label>
                    <select name="status" class="form-control" required>
                        <?php foreach ($status_options as $status): ?>
                            <option value="<?= $status ?>" <?= $status == $prescription['status'] ? 'selected' : '' ?>>
                                <?= ucfirst($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Is Indoor -->
                <div class="form-row">
                    <label class="form-label">Is Indoor</label>
                    <div style="display:flex;align-items:center;gap:10px;padding-top:8px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85rem;">
                            <input type="checkbox" name="is_indoor" value="1" 
                                   <?= ($prescription['is_indoor'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <span>Indoor Patient</span>
                        </label>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="form-row full-width">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions" class="form-control" 
                              placeholder="e.g. Take after meals, Take with water, Avoid alcohol" 
                              rows="2"><?= htmlspecialchars($prescription['instructions'] ?? '') ?></textarea>
                </div>
                
                <!-- Notes -->
                <div class="form-row full-width">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" 
                              placeholder="Additional notes about the prescription" 
                              rows="2"><?= htmlspecialchars($prescription['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Prescription Items Section -->
            <?php if (count($prescription_items) > 0): ?>
            <div class="form-row full-width" style="margin-top:20px;padding-top:20px;border-top:2px solid var(--border-color);">
                <div class="form-title" style="border-bottom:none;padding-bottom:0;margin-bottom:12px;">
                    <i class="fas fa-list"></i>
                    Prescription Items
                    <span style="margin-left:auto;font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        <?= count($prescription_items) ?> item(s)
                    </span>
                </div>
                
                <div class="item-header">
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
                    <div class="item-row">
                        <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                        <input type="text" name="item_medication[]" class="form-control" 
                               value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" placeholder="Medication">
                        <input type="text" name="item_dosage[]" class="form-control" 
                               value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" placeholder="Dosage">
                        <input type="text" name="item_frequency[]" class="form-control" 
                               value="<?= htmlspecialchars($item['frequency'] ?? '') ?>" placeholder="Frequency">
                        <input type="number" name="item_quantity[]" class="form-control" 
                               value="<?= $item['quantity'] ?? 0 ?>" placeholder="Qty" min="1">
                        <input type="text" name="item_duration[]" class="form-control" 
                               value="<?= htmlspecialchars($item['duration'] ?? '') ?>" placeholder="Duration">
                        <input type="text" name="item_route[]" class="form-control" 
                               value="<?= htmlspecialchars($item['route'] ?? '') ?>" placeholder="Route">
                        <input type="text" name="item_instructions[]" class="form-control" 
                               value="<?= htmlspecialchars($item['instructions'] ?? '') ?>" placeholder="Instructions">
                        <input type="number" name="item_unit_price[]" class="form-control" 
                               value="<?= $item['unit_price'] ?? 0 ?>" placeholder="Price" step="0.01" min="0">
                        <span style="font-weight:600;font-size:0.8rem;color:var(--primary);text-align:center;">
                            TSh <?= number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0), 0) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Prescription
                </button>
                <a href="view_prescription.php?id=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php if ($prescription['status'] === 'pending'): ?>
                    <a href="dispense_prescription.php?id=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Dispense
                    </a>
                <?php endif; ?>
                <?php if ($prescription['status'] !== 'cancelled'): ?>
                    <a href="?delete=<?= $prescription_id ?>&branch=<?= $branch_id ?>" class="btn btn-danger" 
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
    <div class="form-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="form-title">
            <i class="fas fa-info-circle"></i>
            Prescription Information
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-400 font-medium">Prescription #</p>
                <p class="font-mono text-sm font-bold text-primary"><?= htmlspecialchars($prescription_number) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Created</p>
                <p class="text-sm"><?= date('F d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Last Updated</p>
                <p class="text-sm"><?= date('F d, Y h:i A', strtotime($prescription['updated_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Status</p>
                <p><span class="badge badge-<?= getStatusBadge($prescription['status'] ?? 'pending') ?>">
                    <?= $status_display ?>
                </span></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Doctor</p>
                <p class="text-sm"><?= htmlspecialchars($doctor_name_display) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Patient</p>
                <p class="text-sm font-semibold"><?= htmlspecialchars($patient_display_name) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Branch</p>
                <p class="text-sm"><?= htmlspecialchars($branch_name_display) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium">Indoor</p>
                <p class="text-sm"><?= ($prescription['is_indoor'] ?? 0) == 1 ? '✅ Yes' : '❌ No' ?></p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Prescription - <?= htmlspecialchars($prescription_number) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    }
    
    sidebarToggle?.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });
    
    overlay?.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('error');
        window.location.href = url.toString();
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // ✅ MEDICATION SELECT - Auto fill dosage & quantity
    // ================================================================
    var medicationSelect = document.getElementById('medicationSelect');
    if (medicationSelect) {
        medicationSelect.addEventListener('change', function() {
            var selected = this.options[this.selectedIndex];
            var price = selected.dataset.price || 0;
            var quantity = selected.dataset.quantity || 0;
            var unit = selected.dataset.unit || '';
            
            // Auto-fill quantity with 1
            var qtyInput = document.querySelector('input[name="quantity"]');
            if (qtyInput && qtyInput.value == 0) {
                qtyInput.value = 1;
            }
            
            // Show stock info
            var stockInfo = document.querySelector('.medication-select-wrapper small');
            if (stockInfo && quantity > 0) {
                stockInfo.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> Stock: ' + quantity + ' ' + unit + ' available · Price: TSh ' + parseFloat(price).toLocaleString();
            }
        });
    }

    console.log('%c🏥 Braick Dispensary - Edit Prescription (FULL FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription_number) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient_display_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($doctor_name_display) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Status: <?= $status_display ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Patient name now visible in header', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Doctors filtered by branch: <?= count($doctors) ?> doctors', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Medications filtered by branch: <?= count($medications) ?> medications', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Beautiful medication dropdown with categories', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No "-- Select Patient --" option', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>