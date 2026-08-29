<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_patient_prescriptions.php
// PHARMACY - VIEW PATIENT PRESCRIPTIONS
// FIXED: Update only discount, keep other bill items
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// PRE-DEFINED OPTIONS
// ================================================================
$instruction_options = [
    'Take with food',
    'Take on empty stomach',
    'Take after meals',
    'Take before meals',
    'Take with plenty of water',
    'Do not crush or chew',
    'Take at bedtime',
    'Take in the morning',
    'Take with milk',
    'Avoid alcohol',
    'Avoid driving',
    'Complete full course',
    'Store in a cool dry place',
    'Keep out of reach of children',
    'As directed by doctor',
    'Other - Please specify'
];

$frequency_options = [
    'Once Daily', 'Twice Daily', 'Three Times Daily', 'Four Times Daily',
    'Every 4 Hours', 'Every 6 Hours', 'Every 8 Hours', 'Every 12 Hours',
    'At Bedtime', 'In the Morning', 'With Meals', 'On Empty Stomach',
    'As Needed', 'Weekly', 'Monthly'
];

$route_options = [
    'Oral', 'Injection', 'Intravenous (IV)', 'Intramuscular (IM)',
    'Subcutaneous (SC)', 'Ophthalmic', 'Otic', 'Nasal',
    'Inhalation', 'Topical', 'Sublingual', 'Rectal', 'Vaginal'
];

$dosage_options = [
    '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
    '12', '15', '20', '25', '30', '40', '50', '60', '75', '80',
    '100', '120', '125', '150', '180', '200', '225', '250', '300',
    '350', '400', '450', '500', '600', '700', '750', '800', '900',
    '1000', '1200', '1500', '2000', '2500', '3000', '5000'
];

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // ================================================================
    // ✅ HANDLE SAVE - FIXED: Only update discount, don't replace bill
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_and_confirm') {
        $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $discount_amount = isset($_POST['discount_amount']) ? (float)str_replace(',', '', $_POST['discount_amount']) : 0;
        $total_amount = isset($_POST['total_amount']) ? (float)str_replace(',', '', $_POST['total_amount']) : 0;
        
        // Update each item with editable fields
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item_id => $item_data) {
                $dosage = trim($item_data['dosage'] ?? '');
                $frequency = trim($item_data['frequency'] ?? '');
                $route = trim($item_data['route'] ?? '');
                $duration = trim($item_data['duration'] ?? '');
                $instructions = trim($item_data['instructions'] ?? '');
                
                $stmt = $db->prepare("
                    UPDATE prescription_items 
                    SET dosage = ?,
                        frequency = ?,
                        route = ?,
                        duration = ?,
                        instructions = ?
                    WHERE id = ? AND patient_id = ? AND branch_id = ?
                ");
                $stmt->execute([
                    $dosage,
                    $frequency,
                    $route,
                    $duration,
                    $instructions,
                    $item_id,
                    $patient_id,
                    $user_branch_id
                ]);
            }
        }
        
        if ($patient_id > 0) {
            try {
                $db->beginTransaction();
                
                // Get all prescriptions for this patient
                $stmt = $db->prepare("
                    SELECT id, prescription_number, visit_id 
                    FROM prescriptions 
                    WHERE patient_id = ? AND branch_id = ? AND status = 'pending'
                ");
                $stmt->execute([$patient_id, $user_branch_id]);
                $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($prescriptions)) {
                    throw new Exception("No pending prescriptions found for this patient");
                }
                
                // Update all prescriptions to confirmed
                $prescription_ids = array_column($prescriptions, 'id');
                $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
                
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'confirmed', 
                        pharmacy_id = ?,
                        updated_at = NOW()
                    WHERE id IN ($placeholders) AND branch_id = ?
                ");
                $params = array_merge([$user_id], $prescription_ids, [$user_branch_id]);
                $stmt->execute($params);
                
                // Get the visit_id from the first prescription
                $visit_id = $prescriptions[0]['visit_id'] ?? null;
                
                // ================================================================
                // ✅ FIX: Check if bill exists and update ONLY discount
                // ================================================================
                $stmt = $db->prepare("
                    SELECT id, total_amount, discount_amount, subtotal
                    FROM bills 
                    WHERE patient_id = ? AND visit_id = ? AND status IN ('pending', 'partial')
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$patient_id, $visit_id]);
                $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $bill_id = null;
                
                if ($existing_bill) {
                    $bill_id = $existing_bill['id'];
                    $current_total = $existing_bill['total_amount'];
                    $current_discount = $existing_bill['discount_amount'] ?? 0;
                    
                    // ================================================================
                    // GET MEDICATION TOTAL FROM BILL_ITEMS (to calculate correct discount)
                    // ================================================================
                    $stmt_med = $db->prepare("
                        SELECT SUM(total_price) as med_total, COUNT(*) as med_count
                        FROM bill_items
                        WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                    ");
                    $stmt_med->execute([$bill_id]);
                    $med_data = $stmt_med->fetch(PDO::FETCH_ASSOC);
                    $med_total = $med_data['med_total'] ?? 0;
                    $med_count = $med_data['med_count'] ?? 0;
                    
                    // ================================================================
                    // GET OTHER ITEMS TOTAL (unchanged)
                    // ================================================================
                    $stmt_other = $db->prepare("
                        SELECT SUM(total_price) as other_total
                        FROM bill_items
                        WHERE bill_id = ? AND item_type != 'medication' AND status != 'cancelled'
                    ");
                    $stmt_other->execute([$bill_id]);
                    $other_data = $stmt_other->fetch(PDO::FETCH_ASSOC);
                    $other_total = $other_data['other_total'] ?? 0;
                    
                    // ================================================================
                    // UPDATE MEDICATION ITEMS WITH DISCOUNT (pro-rata)
                    // ================================================================
                    $discount_per_item = ($discount_amount > 0 && $med_count > 0) 
                        ? $discount_amount / $med_count 
                        : 0;
                    
                    $stmt_update_items = $db->prepare("
                        UPDATE bill_items 
                        SET discount_amount = ?,
                            total_price = total_price - ?,
                            final_price = total_price - ?,
                            updated_at = NOW()
                        WHERE bill_id = ? 
                        AND item_type = 'medication'
                        AND status != 'cancelled'
                    ");
                    $stmt_update_items->execute([
                        $discount_per_item,
                        $discount_per_item,
                        $discount_per_item,
                        $bill_id
                    ]);
                    
                    // ================================================================
                    // GET NEW MEDICATION TOTAL FROM BILL_ITEMS (with discount)
                    // ================================================================
                    $stmt_new_med = $db->prepare("
                        SELECT SUM(total_price) as med_total, SUM(discount_amount) as med_discount
                        FROM bill_items
                        WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
                    ");
                    $stmt_new_med->execute([$bill_id]);
                    $new_med_data = $stmt_new_med->fetch(PDO::FETCH_ASSOC);
                    $new_med_total = $new_med_data['med_total'] ?? 0;
                    $new_med_discount = $new_med_data['med_discount'] ?? 0;
                    
                    // ================================================================
                    // ✅ FIX: CALCULATE NEW BILL TOTAL
                    // NEW TOTAL = (NEW MEDICATION TOTAL) + OTHER ITEMS
                    // ================================================================
                    $new_total = $new_med_total + $other_total;
                    
                    // ================================================================
                    // ✅ FIX: UPDATE BILL - ONLY CHANGE DISCOUNT AND TOTAL
                    // ================================================================
                    $stmt_update_bill = $db->prepare("
                        UPDATE bills 
                        SET discount_amount = ?,
                            total_amount = ?,
                            balance = ?,
                            updated_at = NOW(),
                            notes = CONCAT(COALESCE(notes, ''), ' | Pharmacy discount: ', ?, ' applied ', NOW())
                        WHERE id = ? AND patient_id = ? AND visit_id = ?
                    ");
                    $stmt_update_bill->execute([
                        $new_med_discount,
                        $new_total,
                        $new_total, // balance = total (not paid yet)
                        $discount_amount,
                        $bill_id,
                        $patient_id,
                        $visit_id
                    ]);
                    
                    $message = "✅ Prescription(s) confirmed! Bill updated.<br>";
                    $message .= "Medication discount: " . $currency . " " . number_format($discount_amount, 0) . "<br>";
                    $message .= "New bill total: " . $currency . " " . number_format($new_total, 0) . " (was " . $currency . " " . number_format($current_total, 0) . ")";
                    $message_type = 'success';
                    
                } else {
                    // ================================================================
                    // CREATE NEW BILL (NO EXISTING BILL)
                    // ================================================================
                    $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    // Get medication items total
                    $stmt_items = $db->prepare("
                        SELECT SUM(total_price) as med_total
                        FROM prescription_items pi
                        JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE pi.patient_id = ? AND p.branch_id = ?
                    ");
                    $stmt_items->execute([$patient_id, $user_branch_id]);
                    $item_total = $stmt_items->fetch(PDO::FETCH_ASSOC);
                    $med_total = $item_total['med_total'] ?? 0;
                    
                    $final_total = $med_total - $discount_amount;
                    if ($final_total < 0) $final_total = 0;
                    
                    $stmt = $db->prepare("
                        INSERT INTO bills (
                            bill_number, patient_id, visit_id, branch_id, created_by,
                            subtotal, discount_amount, discount_percent, total_amount,
                            paid_amount, balance, status, payment_method, notes,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'cash', ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $bill_number, $patient_id, $visit_id, $user_branch_id, $user_id,
                        $med_total, $discount_amount, 0, $final_total,
                        0, $final_total,
                        "Prescription confirmed - Discount: " . number_format($discount_amount, 2)
                    ]);
                    $bill_id = $db->lastInsertId();
                    
                    // Create bill items for each prescription item
                    $stmt_items = $db->prepare("
                        SELECT pi.*, p.prescription_number 
                        FROM prescription_items pi
                        JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE pi.patient_id = ? AND p.branch_id = ?
                    ");
                    $stmt_items->execute([$patient_id, $user_branch_id]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($items as $item) {
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, patient_id, branch_id, item_type, item_name,
                                quantity, unit_price, total_price, discount_amount,
                                tax_amount, final_price, reference_id, reference_type,
                                status, created_at, updated_at
                            ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, ?, ?, 'prescription', 'pending', NOW(), NOW())
                        ");
                        $stmt->execute([
                            $bill_id, $patient_id, $user_branch_id,
                            $item['medication_name'] . ' (' . $item['dosage'] . ')',
                            $item['quantity'], $item['unit_price'], $item['total_price'],
                            0, 0, $item['total_price'], $item['id']
                        ]);
                    }
                    
                    $message = "✅ Prescription(s) confirmed! New bill created.<br>";
                    $message .= "Total: " . $currency . " " . number_format($final_total, 0);
                    $message_type = 'success';
                }
                
                $db->commit();
                
                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_type'] = $message_type;
                
                header('Location: pending_prescriptions.php');
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ================================================================
    // GET PATIENT INFORMATION
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM patients WHERE id = ? AND branch_id = ?
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $message = "❌ Patient not found";
        $message_type = 'error';
    }
    
    // ================================================================
    // GET ALL PRESCRIPTIONS FOR THIS PATIENT
    // ================================================================
    $prescriptions = [];
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name, v.visit_number
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE p.patient_id = ? AND p.branch_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET ALL PRESCRIPTION ITEMS FOR THIS PATIENT
    // ================================================================
    $items = [];
    $total_quantity = 0;
    $total_amount = 0;
    $total_items = 0;
    
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            p.prescription_number,
            p.visit_id,
            p.status as prescription_status,
            p.created_at as prescription_date
        FROM prescription_items pi
        JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE pi.patient_id = ? AND p.branch_id = ?
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        $total_quantity += $item['quantity'];
        $total_amount += $item['total_price'];
        $total_items++;
    }
    
    // ================================================================
    // GET BILL INFORMATION
    // ================================================================
    $bill = null;
    $visit_id = $prescriptions[0]['visit_id'] ?? null;
    if ($visit_id) {
        $stmt = $db->prepare("
            SELECT * FROM bills 
            WHERE patient_id = ? AND visit_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$patient_id, $visit_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get flash messages
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $items = [];
    $total_quantity = 0;
    $total_amount = 0;
    $total_items = 0;
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatMoney($amount) {
    return number_format($amount, 0, '.', ',');
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'confirmed' => '✅ Confirmed',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Prescriptions - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 16px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
            --field-height: 34px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1A1A2E;
            --gray-100: #1E293B;
            --gray-200: #2D3748;
            --gray-300: #4A5568;
            --gray-400: #718096;
            --gray-500: #A0AEC0;
            --gray-600: #CBD5E1;
            --gray-700: #E2E8F0;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3A2A1A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .page-title i { font-size: 1.4rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(52, 211, 153, 0.2);
            color: #34D399;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.5rem;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }
        
        .live-update-indicator {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1s infinite;
            margin-right: 4px;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        /* ================================================================
           PATIENT CARD
           ================================================================ */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow);
        }
        
        .patient-card:hover {
            border-color: var(--primary-light);
        }
        
        .patient-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .patient-info h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .patient-info .patient-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .patient-info .patient-details span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           ITEMS GRID
           ================================================================ */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .item-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 18px 20px;
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .item-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }
        
        .item-card .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .item-card .item-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .item-card .item-prescription {
            font-size: 0.6rem;
            color: var(--text-secondary);
            background: var(--gray-100);
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .item-card .item-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 12px;
            margin: 8px 0;
        }
        
        .item-card .item-detail {
            display: flex;
            flex-direction: column;
        }
        
        .item-card .item-detail .label {
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        
        .item-card .item-detail .value-display {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            padding: 4px 8px;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            min-height: var(--field-height);
            display: flex;
            align-items: center;
        }
        
        .item-card .item-detail .value-display.highlight {
            color: var(--primary);
            font-weight: 700;
            border-color: var(--primary-light);
        }
        
        .item-card .item-detail select,
        .item-card .item-detail input,
        .item-card .item-detail .field-wrapper {
            width: 100%;
            min-height: var(--field-height);
            padding: 4px 8px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
            font-family: inherit;
            height: var(--field-height);
            box-sizing: border-box;
        }
        
        .item-card .item-detail select:focus,
        .item-card .item-detail input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }
        
        .item-card .item-detail .field-wrapper {
            display: flex;
            gap: 4px;
            padding: 2px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            height: var(--field-height);
        }
        
        .item-card .item-detail .field-wrapper select {
            flex: 2;
            min-width: 60px;
            border: none;
            background: transparent;
            height: 100%;
            padding: 2px 4px;
            font-size: 0.75rem;
            color: var(--text-primary);
            outline: none;
        }
        
        .item-card .item-detail .field-wrapper select:focus {
            box-shadow: none;
        }
        
        .item-card .item-detail .field-wrapper input {
            flex: 1;
            min-width: 50px;
            border: none;
            border-left: 1px solid var(--border-color);
            background: transparent;
            height: 100%;
            padding: 2px 6px;
            font-size: 0.75rem;
            color: var(--text-primary);
            outline: none;
        }
        
        .item-card .item-instructions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid var(--border-color);
        }
        
        .item-card .item-instructions label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 4px;
        }
        
        .item-card .item-instructions .instr-row {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            height: var(--field-height);
        }
        
        .item-card .item-instructions .instr-row select {
            flex: 2;
            min-width: 100px;
            height: var(--field-height);
            padding: 4px 8px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .item-card .item-instructions .instr-row select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }
        
        .item-card .item-instructions .instr-row input {
            flex: 3;
            min-width: 120px;
            height: var(--field-height);
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .item-card .item-instructions .instr-row input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }
        
        .item-card .item-price {
            margin-top: 8px;
            text-align: right;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--success);
        }
        
        .item-card .item-price .label {
            font-weight: 400;
            color: var(--text-secondary);
            font-size: 0.7rem;
        }
        
        .item-card .item-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        /* ================================================================
           SUMMARY CARDS
           ================================================================ */
        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-card .summary-number {
            font-size: 1.8rem;
            font-weight: 800;
            display: block;
        }
        
        .summary-card .summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .summary-card.total .summary-number { color: var(--primary); }
        .summary-card.items .summary-number { color: #7C3AED; }
        .summary-card.qty .summary-number { color: var(--warning); }
        .summary-card.amount .summary-number { color: var(--success); }
        
        /* ================================================================
           DISCOUNT SECTION
           ================================================================ */
        .discount-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .discount-section .discount-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .discount-section .discount-title i {
            color: var(--warning);
        }
        
        .discount-section .discount-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            align-items: end;
        }
        
        .discount-section .discount-grid .field {
            display: flex;
            flex-direction: column;
        }
        
        .discount-section .discount-grid .field label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }
        
        .discount-section .discount-grid .field .value-display {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            padding: 6px 0;
            min-height: var(--field-height);
            display: flex;
            align-items: center;
        }
        
        .discount-section .discount-grid .field input {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
            width: 100%;
            height: var(--field-height);
        }
        
        .discount-section .discount-grid .field input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
            outline: none;
        }
        
        .discount-section .discount-grid .field input.discount-input {
            border-color: var(--warning);
        }
        
        .discount-section .discount-grid .field input.discount-input:focus {
            border-color: var(--warning);
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.1);
        }
        
        .discount-section .discount-grid .field .final-amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--success);
            padding: 4px 0;
            min-height: var(--field-height);
            display: flex;
            align-items: center;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
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
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
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
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        [data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            font-size: 0.8rem;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .items-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
            .discount-section .discount-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .items-grid { grid-template-columns: 1fr; }
            .discount-section .discount-grid { grid-template-columns: 1fr; }
            .summary-section { grid-template-columns: 1fr 1fr; }
            .patient-card { flex-direction: column; text-align: center; }
            .patient-info .patient-details { justify-content: center; }
            .item-card .item-details { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .summary-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Patient Prescriptions
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;font-weight:600;text-transform:uppercase;">PHARMACY</span>
                <span class="live-badge">
                    <span class="live-update-indicator"></span>
                    Live Update
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                View and manage all prescriptions for this patient
                <?php if ($patient): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.12);color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;">
                        <?= htmlspecialchars($patient['full_name']) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(52,211,153,0.12);color:#34D399;padding:2px 10px;border-radius:20px;font-size:0.5rem;">
                    <i class="fas fa-sync-alt fa-spin"></i> Auto-update
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.12);color:#D97706;padding:2px 10px;border-radius:20px;font-size:0.5rem;">
                    <i class="fas fa-edit"></i> Select + Manual
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="generatePDF()" class="btn-outline-light pdf-btn">
                <i class="fas fa-file-pdf"></i> View PDF
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1200px;margin:0 auto 12px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($patient && count($items) > 0): ?>
    
    <!-- PATIENT CARD -->
    <div class="patient-card">
        <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="patient-info">
            <h2><?= htmlspecialchars($patient['full_name']) ?></h2>
            <div class="patient-details">
                <span><i class="fas fa-id-card"></i> ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                <span><i class="fas fa-venus-mars"></i> <?= ucfirst($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-calendar-alt"></i> <?= calculateAge($patient['date_of_birth'] ?? '') ?> yrs</span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
        </div>
        <div style="margin-left:auto;">
            <span class="badge-status <?= getStatusBadgeClass($prescriptions[0]['status'] ?? 'pending') ?>" style="font-size:0.7rem;padding:4px 16px;">
                <?= getStatusLabel($prescriptions[0]['status'] ?? 'pending') ?>
            </span>
        </div>
    </div>

    <!-- SUMMARY SECTION -->
    <div class="summary-section" id="summarySection">
        <div class="summary-card total">
            <span class="summary-number"><?= count($prescriptions) ?></span>
            <span class="summary-label">📋 Prescriptions</span>
        </div>
        <div class="summary-card items">
            <span class="summary-number" id="totalItems"><?= $total_items ?></span>
            <span class="summary-label">📦 Items</span>
        </div>
        <div class="summary-card qty">
            <span class="summary-number" id="totalQty"><?= $total_quantity ?></span>
            <span class="summary-label">📊 Total Quantity</span>
        </div>
        <div class="summary-card amount">
            <span class="summary-number" id="totalAmountDisplay"><?= $currency ?> <?= formatMoney($total_amount) ?></span>
            <span class="summary-label">💰 Total Amount</span>
        </div>
    </div>

    <!-- ITEMS GRID -->
    <form method="POST" action="" id="prescriptionForm">
        <input type="hidden" name="action" value="save_and_confirm">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="total_amount" id="totalAmountHidden" value="<?= $total_amount ?>">
        
        <div class="items-grid" id="itemsGrid">
            <?php foreach ($items as $index => $item): ?>
                <div class="item-card" data-item-id="<?= $item['id'] ?>">
                    <div class="item-badge">#<?= $index + 1 ?></div>
                    
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                        <span class="item-prescription"><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></span>
                    </div>
                    
                    <div class="item-details">
                        <!-- DOSAGE -->
                        <div class="item-detail">
                            <span class="label">💊 Dosage <span class="live-update-badge"><i class="fas fa-sync-alt fa-spin"></i></span></span>
                            <div class="field-wrapper">
                                <select name="items[<?= $item['id'] ?>][dosage]" class="dosage-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()">
                                    <option value="">--</option>
                                    <?php foreach ($dosage_options as $dos): ?>
                                        <option value="<?= htmlspecialchars($dos) ?>" <?= $item['dosage'] == $dos ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dos) ?> mg
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="dosage-manual" data-item-id="<?= $item['id'] ?>" 
                                       value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" 
                                       placeholder="Manual" oninput="updateLiveData()">
                            </div>
                        </div>
                        
                        <!-- FREQUENCY -->
                        <div class="item-detail">
                            <span class="label">🕐 Frequency <span class="live-update-badge"><i class="fas fa-sync-alt fa-spin"></i></span></span>
                            <div class="field-wrapper">
                                <select name="items[<?= $item['id'] ?>][frequency]" class="frequency-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()">
                                    <option value="">--</option>
                                    <?php foreach ($frequency_options as $freq): ?>
                                        <option value="<?= htmlspecialchars($freq) ?>" <?= $item['frequency'] == $freq ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($freq) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="frequency-manual" data-item-id="<?= $item['id'] ?>" 
                                       value="<?= htmlspecialchars($item['frequency'] ?? '') ?>" 
                                       placeholder="Manual" oninput="updateLiveData()">
                            </div>
                        </div>
                        
                        <!-- QUANTITY -->
                        <div class="item-detail">
                            <span class="label">📦 Quantity</span>
                            <div class="value-display highlight qty-display" data-item-id="<?= $item['id'] ?>">
                                <?= $item['quantity'] ?? 0 ?>
                            </div>
                        </div>
                        
                        <!-- DURATION -->
                        <div class="item-detail">
                            <span class="label">📅 Duration <span class="live-update-badge"><i class="fas fa-sync-alt fa-spin"></i></span></span>
                            <div class="field-wrapper">
                                <select class="duration-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()">
                                    <option value="">--</option>
                                    <option value="1 day">1 day</option>
                                    <option value="2 days">2 days</option>
                                    <option value="3 days">3 days</option>
                                    <option value="5 days">5 days</option>
                                    <option value="7 days">7 days</option>
                                    <option value="10 days">10 days</option>
                                    <option value="14 days">14 days</option>
                                    <option value="21 days">21 days</option>
                                    <option value="1 month">1 month</option>
                                    <option value="2 months">2 months</option>
                                    <option value="3 months">3 months</option>
                                    <option value="6 months">6 months</option>
                                    <option value="1 year">1 year</option>
                                </select>
                                <input type="text" name="items[<?= $item['id'] ?>][duration]" class="duration-manual" data-item-id="<?= $item['id'] ?>" 
                                       value="<?= htmlspecialchars($item['duration'] ?? '') ?>" 
                                       placeholder="Manual" oninput="updateLiveData()">
                            </div>
                        </div>
                        
                        <!-- ROUTE -->
                        <div class="item-detail" style="grid-column: span 2;">
                            <span class="label">📏 Route <span class="live-update-badge"><i class="fas fa-sync-alt fa-spin"></i></span></span>
                            <div class="field-wrapper">
                                <select name="items[<?= $item['id'] ?>][route]" class="route-select" data-item-id="<?= $item['id'] ?>" onchange="updateLiveData()">
                                    <option value="">--</option>
                                    <?php foreach ($route_options as $route): ?>
                                        <option value="<?= htmlspecialchars($route) ?>" <?= $item['route'] == $route ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($route) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="route-manual" data-item-id="<?= $item['id'] ?>" 
                                       value="<?= htmlspecialchars($item['route'] ?? '') ?>" 
                                       placeholder="Manual" oninput="updateLiveData()">
                            </div>
                        </div>
                    </div>
                    
                    <!-- INSTRUCTIONS -->
                    <div class="item-instructions">
                        <label><i class="fas fa-edit"></i> Instructions (Pharmacy) <span class="live-update-badge"><i class="fas fa-sync-alt fa-spin"></i></span></label>
                        <div class="instr-row">
                            <select id="instr_select_<?= $item['id'] ?>" class="instr-select" data-item-id="<?= $item['id'] ?>" onchange="updateInstructionInput(<?= $item['id'] ?>); updateLiveData();">
                                <option value="">-- Select --</option>
                                <?php foreach ($instruction_options as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__">✏️ Custom...</option>
                            </select>
                            <input type="text" name="items[<?= $item['id'] ?>][instructions]" 
                                   id="instr_input_<?= $item['id'] ?>" 
                                   class="instr-input" 
                                   data-item-id="<?= $item['id'] ?>"
                                   value="<?= htmlspecialchars($item['instructions'] ?? '') ?>" 
                                   placeholder="Custom instructions..."
                                   oninput="updateLiveData()">
                        </div>
                    </div>
                    
                    <div class="item-price" id="itemPrice_<?= $item['id'] ?>">
                        <span class="label">Total Price: </span>
                        <?= $currency ?> <?= formatMoney($item['total_price'] ?? 0) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- DISCOUNT SECTION -->
        <div class="discount-section">
            <div class="discount-title">
                <i class="fas fa-tag"></i>
                Discount & Final Amount <span class="live-update-badge" style="font-size:0.6rem;"><i class="fas fa-sync-alt fa-spin"></i> Live</span>
            </div>
            <div class="discount-grid">
                <div class="field">
                    <label>💰 Subtotal Amount</label>
                    <div class="value-display" id="subtotalDisplay"><?= $currency ?> <?= formatMoney($total_amount) ?></div>
                </div>
                <div class="field">
                    <label><i class="fas fa-percentage" style="color:var(--warning);"></i> Discount Amount (<?= $currency ?>)</label>
                    <input type="text" class="discount-input" id="discountAmount" name="discount_amount" 
                           placeholder="Enter discount amount e.g. 5,000" 
                           value="0" 
                           oninput="calculateFinal()">
                </div>
                <div class="field">
                    <label>✅ Final Amount</label>
                    <div class="final-amount" id="finalAmount"><?= $currency ?> <?= formatMoney($total_amount) ?></div>
                </div>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <a href="pending_prescriptions.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
            <button type="submit" class="btn btn-success" onclick="return confirm('Confirm this prescription?\n\n✅ Status will change to: Confirmed\n💳 Discount will be applied to existing bill.\n\n👤 Patient: <?= addslashes($patient['full_name'] ?? 'Unknown') ?>\n📦 Total Items: <?= $total_items ?>\n📊 Total Quantity: <?= $total_quantity ?>\n💰 Subtotal: <?= $currency ?> <?= formatMoney($total_amount) ?>\n\n⚠️ After payment, status will auto-change to: Dispensed');">
                <i class="fas fa-check-circle"></i> Save & Confirm
            </button>
        </div>
    </form>

    <?php else: ?>
        <!-- EMPTY STATE -->
        <div class="text-center py-8" style="color:var(--text-secondary);">
            <i class="fas fa-prescription text-4xl block mb-3" style="color:var(--border-color);"></i>
            <p style="font-size:1.1rem;">No prescriptions found for this patient</p>
            <a href="pending_prescriptions.php" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Back to Prescriptions
            </a>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Prescriptions
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                Patient Prescriptions PDF - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:0.9rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    } else if (savedDarkMode === 'false') {
        htmlElement.removeAttribute('data-theme');
    } else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') {
            htmlElement.setAttribute('data-theme', 'dark');
        }
    }
    
    window.addEventListener('storage', function(e) {
        if (e.key === 'darkMode') {
            if (e.newValue === 'true') {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // MONEY FORMAT
    // ================================================================
    function formatMoney(amount) {
        return Number(amount).toLocaleString('en-US');
    }

    function unformatMoney(str) {
        return parseFloat(str.replace(/,/g, '')) || 0;
    }

    // ================================================================
    // SYNC SELECT AND MANUAL INPUTS
    // ================================================================
    function syncField(selectSelector, inputSelector, dataAttr) {
        var selects = document.querySelectorAll(selectSelector);
        var inputs = document.querySelectorAll(inputSelector);
        
        inputs.forEach(function(input) {
            var itemId = input.getAttribute(dataAttr);
            var select = document.querySelector(selectSelector + '[data-item-id="' + itemId + '"]');
            
            if (select) {
                input.addEventListener('input', function() {
                    var val = this.value.trim();
                    var found = false;
                    for (var i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === val) {
                            select.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found && val !== '') {
                        for (var i = 0; i < select.options.length; i++) {
                            if (select.options[i].text === val) {
                                select.selectedIndex = i;
                                found = true;
                                break;
                            }
                        }
                    }
                    if (!found && val === '') {
                        select.selectedIndex = 0;
                    }
                    updateLiveData();
                });
                
                select.addEventListener('change', function() {
                    var val = this.value;
                    if (val !== '') {
                        input.value = val;
                    } else {
                        input.value = '';
                    }
                    updateLiveData();
                });
            }
        });
    }

    // ================================================================
    // LIVE UPDATE
    // ================================================================
    function updateLiveData() {
        var totalQty = 0;
        var totalAmount = 0;
        var totalItems = 0;
        
        var itemCards = document.querySelectorAll('.item-card');
        itemCards.forEach(function(card) {
            var qtyEl = card.querySelector('.qty-display');
            if (qtyEl) {
                var qty = parseInt(qtyEl.textContent) || 0;
                totalQty += qty;
                totalItems++;
            }
            var priceEl = card.querySelector('.item-price');
            if (priceEl) {
                var priceText = priceEl.textContent.replace(/[^0-9]/g, '');
                var amount = parseInt(priceText) || 0;
                totalAmount += amount;
            }
        });
        
        var totalQtyEl = document.getElementById('totalQty');
        var totalItemsEl = document.getElementById('totalItems');
        var totalAmountDisplay = document.getElementById('totalAmountDisplay');
        var subtotalDisplay = document.getElementById('subtotalDisplay');
        var totalAmountHidden = document.getElementById('totalAmountHidden');
        
        if (totalQtyEl) totalQtyEl.textContent = totalQty;
        if (totalItemsEl) totalItemsEl.textContent = totalItems;
        
        var formattedAmount = '<?= $currency ?> ' + formatMoney(totalAmount);
        if (totalAmountDisplay) totalAmountDisplay.textContent = formattedAmount;
        if (subtotalDisplay) subtotalDisplay.textContent = formattedAmount;
        if (totalAmountHidden) totalAmountHidden.value = totalAmount;
        
        calculateFinal();
    }

    // ================================================================
    // CALCULATE FINAL AMOUNT WITH DISCOUNT
    // ================================================================
    function calculateFinal() {
        var totalAmount = parseFloat(document.getElementById('totalAmountHidden').value) || 0;
        var discountInput = document.getElementById('discountAmount');
        var finalDisplay = document.getElementById('finalAmount');
        
        var discountValue = unformatMoney(discountInput.value);
        
        if (discountValue < 0) {
            discountValue = 0;
            discountInput.value = '0';
        }
        
        var finalAmount = totalAmount - discountValue;
        if (finalAmount < 0) finalAmount = 0;
        
        finalDisplay.textContent = '<?= $currency ?> ' + formatMoney(finalAmount);
    }

    // ================================================================
    // DISCOUNT INPUT - Auto format with commas
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        syncField('.dosage-select', '.dosage-manual', 'data-item-id');
        syncField('.frequency-select', '.frequency-manual', 'data-item-id');
        syncField('.duration-select', '.duration-manual', 'data-item-id');
        syncField('.route-select', '.route-manual', 'data-item-id');
        
        var discountInput = document.getElementById('discountAmount');
        if (discountInput) {
            discountInput.addEventListener('input', function(e) {
                var start = this.selectionStart;
                var end = this.selectionEnd;
                
                var raw = this.value.replace(/,/g, '');
                var num = parseFloat(raw);
                
                if (!isNaN(num) && raw.length > 0) {
                    var formatted = formatMoney(num);
                    this.value = formatted;
                    var diff = formatted.length - raw.length;
                    this.setSelectionRange(start + diff, end + diff);
                } else if (raw.length === 0) {
                    this.value = '0';
                }
                
                calculateFinal();
            });
            
            discountInput.addEventListener('focus', function() {
                this.select();
            });
        }
        
        <?php foreach ($items as $item): ?>
            var instrInput<?= $item['id'] ?> = document.getElementById('instr_input_<?= $item['id'] ?>');
            var instrSelect<?= $item['id'] ?> = document.getElementById('instr_select_<?= $item['id'] ?>');
            if (instrInput<?= $item['id'] ?> && instrSelect<?= $item['id'] ?>) {
                var currentVal = instrInput<?= $item['id'] ?>.value.trim();
                if (currentVal) {
                    var found = false;
                    for (var i = 0; i < instrSelect<?= $item['id'] ?>.options.length; i++) {
                        if (instrSelect<?= $item['id'] ?>.options[i].value === currentVal) {
                            instrSelect<?= $item['id'] ?>.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        instrSelect<?= $item['id'] ?>.value = '__custom__';
                    }
                }
            }
        <?php endforeach; ?>
        
        document.querySelectorAll('.dosage-select, .dosage-manual, .frequency-select, .frequency-manual, .duration-select, .duration-manual, .route-select, .route-manual, .instr-select, .instr-input').forEach(function(el) {
            el.addEventListener('change', updateLiveData);
            el.addEventListener('input', updateLiveData);
        });
    });

    // ================================================================
    // UPDATE INSTRUCTION INPUT FROM SELECT
    // ================================================================
    function updateInstructionInput(itemId) {
        var select = document.getElementById('instr_select_' + itemId);
        var input = document.getElementById('instr_input_' + itemId);
        
        if (select.value === '__custom__') {
            input.value = '';
            input.focus();
            input.style.borderColor = 'var(--warning)';
        } else {
            input.value = select.value;
            input.style.borderColor = 'var(--border-color)';
        }
        updateLiveData();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // PDF GENERATION
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var itemsHtml = '';
        var totalQty = 0;
        var totalAmount = 0;
        var totalItems = 0;
        
        var itemCards = document.querySelectorAll('.item-card');
        itemCards.forEach(function(card, index) {
            var medName = card.querySelector('.item-name')?.textContent || 'N/A';
            var prescription = card.querySelector('.item-prescription')?.textContent || 'N/A';
            var dosage = card.querySelector('.dosage-select')?.value || card.querySelector('.dosage-manual')?.value || 'N/A';
            var frequency = card.querySelector('.frequency-select')?.value || card.querySelector('.frequency-manual')?.value || 'N/A';
            var quantity = parseInt(card.querySelector('.qty-display')?.textContent) || 0;
            var duration = card.querySelector('.duration-select')?.value || card.querySelector('.duration-manual')?.value || 'N/A';
            var route = card.querySelector('.route-select')?.value || card.querySelector('.route-manual')?.value || 'N/A';
            var instructions = card.querySelector('.instr-input')?.value || 'No instructions';
            var price = parseInt(card.querySelector('.item-price')?.textContent?.replace(/[^0-9]/g, '')) || 0;
            
            totalQty += quantity;
            totalAmount += price;
            totalItems++;
            
            itemsHtml += `
                <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;margin-bottom:10px;page-break-inside:avoid;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <strong style="color:#0B5ED7;font-size:16px;">${medName}</strong>
                        <span style="font-size:12px;color:#64748B;">${prescription}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;font-size:14px;">
                        <div><span style="font-weight:600;color:#64748B;">Dosage:</span> ${dosage}</div>
                        <div><span style="font-weight:600;color:#64748B;">Frequency:</span> ${frequency}</div>
                        <div><span style="font-weight:600;color:#64748B;">Quantity:</span> ${quantity}</div>
                        <div><span style="font-weight:600;color:#64748B;">Duration:</span> ${duration}</div>
                        <div style="grid-column:span 2;"><span style="font-weight:600;color:#64748B;">Route:</span> ${route}</div>
                        <div style="grid-column:span 2;margin-top:4px;padding-top:4px;border-top:1px solid #E2E8F0;">
                            <span style="font-weight:600;color:#64748B;">Instructions:</span> 
                            <span style="color:#1E293B;">${instructions}</span>
                        </div>
                        <div style="grid-column:span 2;text-align:right;font-weight:700;color:#059669;font-size:15px;">
                            Total: <?= $currency ?> ${formatMoney(price)}
                        </div>
                    </div>
                </div>
            `;
        });
        
        var discountValue = document.getElementById('discountAmount')?.value || '0';
        var discountNum = unformatMoney(discountValue);
        var finalAmount = totalAmount - discountNum;
        if (finalAmount < 0) finalAmount = 0;
        
        var html = `
            <div style="font-family:'Inter',sans-serif;padding:20px;">
                <!-- HEADER with Logo -->
                <div style="text-align:center;padding-bottom:16px;border-bottom:3px solid #0B5ED7;margin-bottom:20px;">
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" 
                             alt="Braick Logo" 
                             style="height:55px;width:auto;object-fit:contain;margin-bottom:6px;"
                             onerror="this.style.display='none'">
                        <div style="font-size:1.6rem;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                        <div style="font-size:0.8rem;color:#64748B;">Tunajali Afya Yako</div>
                    </div>
                    <div style="font-size:0.9rem;font-weight:600;color:#0B5ED7;margin-top:6px;">
                        Patient Prescriptions Report
                    </div>
                    <div style="font-size:0.7rem;color:#64748B;margin-top:2px;">
                        Generated: ${new Date().toLocaleString()}
                    </div>
                </div>
                
                <!-- PATIENT INFO -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;margin-bottom:16px;padding:12px 16px;background:#F8FAFC;border-radius:8px;">
                    <div><span style="font-weight:600;color:#64748B;">Patient:</span> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                    <div><span style="font-weight:600;color:#64748B;">ID:</span> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                    <div><span style="font-weight:600;color:#64748B;">Gender:</span> <?= ucfirst($patient['gender'] ?? 'N/A') ?></div>
                    <div><span style="font-weight:600;color:#64748B;">Age:</span> <?= calculateAge($patient['date_of_birth'] ?? '') ?> yrs</div>
                    <div><span style="font-weight:600;color:#64748B;">Phone:</span> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                    <div><span style="font-weight:600;color:#64748B;">Branch:</span> <?= htmlspecialchars($user_branch_name) ?></div>
                </div>
                
                <!-- ITEMS -->
                <div style="margin-bottom:16px;">
                    <div style="font-size:1rem;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:10px;">
                        📋 Prescription Items (${totalItems})
                    </div>
                    ${itemsHtml}
                </div>
                
                <!-- SUMMARY with Discount -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin:16px 0;padding:12px 16px;background:#E8F0FE;border-radius:8px;">
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Total Items</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;">${totalItems}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Total Quantity</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#D97706;">${totalQty}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Subtotal</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;"><?= $currency ?> ${formatMoney(totalAmount)}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">💰 Final Amount</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#059669;"><?= $currency ?> ${formatMoney(finalAmount)}</div>
                        ${discountNum > 0 ? `<div style="font-size:0.6rem;color:#D97706;">Discount: <?= $currency ?> ${formatMoney(discountNum)}</div>` : ''}
                    </div>
                </div>
                
                <!-- OFFICIAL STAMP -->
                <div style="margin-top:20px;padding-top:12px;border-top:2px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Prepared by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:16px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div style="text-align:center;padding:8px 20px;border:3px solid #0B5ED7;border-radius:10px;background:#E8F0FE;min-width:160px;">
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:1px;font-weight:700;">Official Stamp</div>
                        <div style="font-size:14px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                        <div style="font-size:12px;color:#64748B;margin-top:2px;">Approved By: _________________</div>
                        <div style="font-size:10px;color:#94A3B8;margin-top:2px;">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div style="text-align:center;margin-top:8px;font-size:12px;color:#94A3B8;">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Patient_Prescriptions_<?= $patient_id ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c💊 Braick - Patient Prescriptions View (FIXED)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Fixed: Discount updates existing bill, does NOT replace it', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Bill total = (Medication total after discount) + Other items', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Other items (consultation, lab tests, procedures) are preserved', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>